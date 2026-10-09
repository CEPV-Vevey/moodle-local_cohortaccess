<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_cohortaccess;

use context;
use context_course;
use context_coursecat;
use context_system;
use core_php_time_limit;
use moodle_url;
use stdClass;

/**
 * Business logic: rule storage and role assignment synchronisation.
 *
 * Each rule gives the members of a cohort, or a single user, a role in a course
 * or course category context. Role assignments are tagged with component = COMPONENT and
 * itemid = rule id, so the plugin never touches manual assignments or those
 * created by other plugins. No enrolment is ever created.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Component used to tag role assignments. */
    public const COMPONENT = 'local_cohortaccess';

    /** @var string Rule table. */
    public const TABLE = 'local_cohortaccess';

    /** @var string Target type: course. */
    public const TARGET_COURSE = 'course';

    /** @var string Target type: course category. */
    public const TARGET_CATEGORY = 'category';

    /** @var string Beneficiary type: the members of a cohort. */
    public const BENEFICIARY_COHORT = 'cohort';

    /** @var string Beneficiary type: a single user. */
    public const BENEFICIARY_USER = 'user';

    /**
     * Get a rule.
     *
     * @param int $ruleid Rule id.
     * @return stdClass|false
     */
    public static function get_rule(int $ruleid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $ruleid]);
    }

    /**
     * Get all rules.
     *
     * @return stdClass[]
     */
    public static function get_rules(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'id');
    }

    /**
     * Create a rule and synchronise its role assignments.
     *
     * @param stdClass $data cohortid or userid, targettype, targetid, roleid, enabled.
     * @return stdClass The created rule.
     */
    public static function create_rule(stdClass $data): stdClass {
        global $DB;
        $rule = self::normalise($data);
        $rule->timecreated = $rule->timemodified = time();
        $rule->id = $DB->insert_record(self::TABLE, $rule);
        self::sync_rule($rule);
        return $rule;
    }

    /**
     * Update a rule and synchronise its role assignments.
     *
     * Assignments matching the previous configuration are removed by the sync.
     *
     * @param stdClass $data Rule data including id.
     * @return stdClass The updated rule.
     */
    public static function update_rule(stdClass $data): stdClass {
        global $DB;
        $rule = self::normalise($data);
        $rule->id = (int) $data->id;
        $rule->timemodified = time();
        $DB->update_record(self::TABLE, $rule);
        self::sync_rule($rule);
        return $rule;
    }

    /**
     * Enable or disable a rule and synchronise its role assignments.
     *
     * @param int $ruleid Rule id.
     * @param bool $enabled New state.
     * @return array{added: int, removed: int}
     */
    public static function set_enabled(int $ruleid, bool $enabled): array {
        global $DB;
        $DB->update_record(self::TABLE, (object) [
            'id' => $ruleid,
            'enabled' => (int) $enabled,
            'timemodified' => time(),
        ]);
        return self::sync_rule(self::get_rule($ruleid));
    }

    /**
     * Delete a rule and all role assignments it created.
     *
     * @param int $ruleid Rule id.
     */
    public static function delete_rule(int $ruleid): void {
        global $DB;
        // Row first, so that a concurrent sync of this rule finds nothing to assign.
        $DB->delete_records(self::TABLE, ['id' => $ruleid]);
        self::remove_rule_assignments($ruleid);
    }

    /**
     * Whether another rule with the same beneficiary, target and role exists.
     *
     * @param stdClass $data Rule data.
     * @param int $excludeid Rule id to ignore (the rule being edited).
     * @return bool
     */
    public static function rule_exists(stdClass $data, int $excludeid = 0): bool {
        global $DB;
        $rule = self::normalise($data);
        $field = $rule->userid ? 'userid' : 'cohortid';
        return $DB->record_exists_select(
            self::TABLE,
            "$field = :beneficiaryid AND targettype = :targettype AND targetid = :targetid
             AND roleid = :roleid AND id <> :excludeid",
            [
                'beneficiaryid' => $rule->$field,
                'targettype' => $rule->targettype,
                'targetid' => $rule->targetid,
                'roleid' => $rule->roleid,
                'excludeid' => $excludeid,
            ]
        );
    }

    /**
     * Get the context targeted by a rule.
     *
     * @param stdClass $rule Rule.
     * @return context|null Null when the course or category no longer exists.
     */
    public static function get_target_context(stdClass $rule): ?context {
        if ($rule->targettype === self::TARGET_COURSE) {
            $context = context_course::instance($rule->targetid, IGNORE_MISSING);
        } else if ($rule->targettype === self::TARGET_CATEGORY) {
            $context = context_coursecat::instance($rule->targetid, IGNORE_MISSING);
        } else {
            $context = false;
        }
        return $context ?: null;
    }

    /**
     * Bring the role assignments of a rule in line with its configuration.
     *
     * The rule is re-read from the database, so a stale copy cannot re-grant
     * access after the rule was deleted or changed. Expected assignments are the
     * beneficiaries (see expected_users()) when the rule exists, is enabled and its
     * target exists. Assignments of this rule with another role, another context or a
     * user not expected are removed; missing ones are added.
     *
     * @param stdClass $rule Rule.
     * @return array{added: int, removed: int}
     */
    public static function sync_rule(stdClass $rule): array {
        global $DB;
        core_php_time_limit::raise();

        $current = self::get_rule($rule->id);
        $context = $current ? self::get_target_context($current) : null;
        $expected = [];
        if ($current && $current->enabled && $context) {
            $expected = self::expected_users($current);
        }

        $result = ['added' => 0, 'removed' => 0];
        $existing = $DB->get_recordset(
            'role_assignments',
            ['component' => self::COMPONENT, 'itemid' => $rule->id],
            '',
            'id, roleid, userid, contextid'
        );
        $present = [];
        $toremove = [];
        foreach ($existing as $ra) {
            $valid = $context && $ra->roleid == $current->roleid && $ra->contextid == $context->id
                && isset($expected[$ra->userid]);
            if ($valid) {
                $present[$ra->userid] = true;
            } else {
                $toremove[] = $ra;
            }
        }
        $existing->close();

        foreach ($toremove as $ra) {
            role_unassign($ra->roleid, $ra->userid, $ra->contextid, self::COMPONENT, $rule->id);
            $result['removed']++;
        }
        foreach (array_keys(array_diff_key($expected, $present)) as $userid) {
            role_assign($current->roleid, $userid, $context->id, self::COMPONENT, $rule->id);
            $result['added']++;
        }
        return $result;
    }

    /**
     * Users who should get the role of a rule: the non-deleted members of its cohort,
     * or its user when not deleted.
     *
     * @param stdClass $rule Rule.
     * @return array<int, true> Keyed by user id.
     */
    private static function expected_users(stdClass $rule): array {
        global $DB;
        if (!empty($rule->userid)) {
            $userids = $DB->get_fieldset_select('user', 'id', 'id = :userid AND deleted = 0', ['userid' => $rule->userid]);
        } else {
            $userids = $DB->get_fieldset_sql(
                'SELECT cm.userid
                   FROM {cohort_members} cm
                   JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
                  WHERE cm.cohortid = :cohortid',
                ['cohortid' => $rule->cohortid]
            );
        }
        return array_fill_keys($userids, true);
    }

    /**
     * Synchronise all rules and remove assignments left by rules that no longer exist.
     *
     * @return array{added: int, removed: int} Totals.
     */
    public static function sync_all(): array {
        global $DB;
        $totals = ['added' => 0, 'removed' => 0];
        foreach (self::get_rules() as $rule) {
            $result = self::sync_rule($rule);
            $totals['added'] += $result['added'];
            $totals['removed'] += $result['removed'];
        }

        $orphans = $DB->get_records_sql(
            'SELECT ra.itemid, COUNT(1) AS total
               FROM {role_assignments} ra
          LEFT JOIN {' . self::TABLE . '} r ON r.id = ra.itemid
              WHERE ra.component = :component AND r.id IS NULL
           GROUP BY ra.itemid',
            ['component' => self::COMPONENT]
        );
        foreach ($orphans as $orphan) {
            self::remove_rule_assignments($orphan->itemid);
            $totals['removed'] += $orphan->total;
        }
        return $totals;
    }

    /**
     * Remove all role assignments created by a rule.
     *
     * @param int $ruleid Rule id.
     */
    public static function remove_rule_assignments(int $ruleid): void {
        role_unassign_all(['component' => self::COMPONENT, 'itemid' => $ruleid]);
    }

    /**
     * Display names of the parts of a rule, formatted for HTML output (already escaped).
     *
     * @param stdClass $rule Rule.
     * @return stdClass beneficiarytype (BENEFICIARY_COHORT or BENEFICIARY_USER), beneficiary,
     *     role and target (HTML-safe strings), beneficiarymissing (bool), beneficiaryurl
     *     (user profile moodle_url, else null) and targeturl (moodle_url, or null if the target is missing).
     */
    public static function describe_rule(stdClass $rule): stdClass {
        global $DB;
        $role = $DB->get_record('role', ['id' => $rule->roleid]);
        $desc = (object) [
            'beneficiarytype' => empty($rule->userid) ? self::BENEFICIARY_COHORT : self::BENEFICIARY_USER,
            'beneficiary' => '?',
            'beneficiaryurl' => null,
            'beneficiarymissing' => true,
            'role' => $role ? role_get_name($role, context_system::instance()) : '?',
            'target' => get_string('targetmissing', 'local_cohortaccess'),
            'targeturl' => null,
        ];
        if ($desc->beneficiarytype === self::BENEFICIARY_USER) {
            $user = $DB->get_record('user', ['id' => $rule->userid, 'deleted' => 0]);
            if ($user) {
                $desc->beneficiary = s(fullname($user));
                $desc->beneficiaryurl = new moodle_url('/user/profile.php', ['id' => $user->id]);
                $desc->beneficiarymissing = false;
            }
        } else if ($cohort = $DB->get_record('cohort', ['id' => $rule->cohortid])) {
            $desc->beneficiary = format_string($cohort->name, true, ['context' => $cohort->contextid]);
            $desc->beneficiarymissing = false;
        }
        if ($context = self::get_target_context($rule)) {
            $desc->target = $context->get_context_name(false);
            $desc->targeturl = $context->get_url();
        }
        return $desc;
    }

    /**
     * Non-blocking warnings about the role of a rule.
     *
     * The role definition is never modified.
     *
     * @param stdClass $rule Rule (roleid, targettype, targetid).
     * @return string[] Warning messages.
     */
    public static function get_role_warnings(stdClass $rule): array {
        global $DB;
        $warnings = [];
        $syscontextid = context_system::instance()->id;
        $allowed = function (string $capability) use ($DB, $rule, $syscontextid): bool {
            return $DB->record_exists('role_capabilities', [
                'roleid' => $rule->roleid,
                'contextid' => $syscontextid,
                'capability' => $capability,
                'permission' => CAP_ALLOW,
            ]);
        };

        if (!$allowed('moodle/course:view')) {
            $warnings[] = get_string('warningnocourseview', 'local_cohortaccess');
        }
        if ($rule->targettype === self::TARGET_CATEGORY) {
            // Only relevant when the category tree contains a hidden course.
            $context = context_coursecat::instance($rule->targetid, IGNORE_MISSING);
            $checkhidden = $context && $DB->record_exists_sql(
                'SELECT 1
                   FROM {course} c
                   JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :courselevel
                  WHERE c.visible = 0 AND ' . $DB->sql_like('ctx.path', ':path'),
                ['courselevel' => CONTEXT_COURSE, 'path' => $context->path . '/%']
            );
        } else {
            $visible = $DB->get_field('course', 'visible', ['id' => $rule->targetid]);
            $checkhidden = $visible !== false && !$visible;
        }
        if ($checkhidden && !$allowed('moodle/course:viewhiddencourses')) {
            $warnings[] = get_string('warningnoviewhidden', 'local_cohortaccess');
        }
        return $warnings;
    }

    /**
     * Count the users a rule applies to: non-deleted cohort members, or 1 for an existing user.
     *
     * @param stdClass $rule Rule.
     * @return int
     */
    public static function count_beneficiaries(stdClass $rule): int {
        global $DB;
        if (!empty($rule->userid)) {
            return count(self::expected_users($rule));
        }
        return $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {cohort_members} cm
               JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
              WHERE cm.cohortid = :cohortid',
            ['cohortid' => $rule->cohortid]
        );
    }

    /**
     * Count the role assignments created by a rule.
     *
     * @param int $ruleid Rule id.
     * @return int
     */
    public static function count_assignments(int $ruleid): int {
        global $DB;
        return $DB->count_records('role_assignments', ['component' => self::COMPONENT, 'itemid' => $ruleid]);
    }

    /**
     * Give a new cohort member the roles of the enabled rules of the cohort.
     *
     * @param int $cohortid Cohort id.
     * @param int $userid User id.
     */
    public static function member_added(int $cohortid, int $userid): void {
        global $DB;
        foreach ($DB->get_records(self::TABLE, ['cohortid' => $cohortid, 'enabled' => 1]) as $rule) {
            if ($context = self::get_target_context($rule)) {
                role_assign($rule->roleid, $userid, $context->id, self::COMPONENT, $rule->id);
            }
        }
    }

    /**
     * Remove from a former cohort member the roles given by the rules of the cohort.
     *
     * @param int $cohortid Cohort id.
     * @param int $userid User id.
     */
    public static function member_removed(int $cohortid, int $userid): void {
        global $DB;
        foreach ($DB->get_records(self::TABLE, ['cohortid' => $cohortid], '', 'id') as $rule) {
            role_unassign_all(['userid' => $userid, 'component' => self::COMPONENT, 'itemid' => $rule->id]);
        }
    }

    /**
     * Delete the rules of a deleted cohort and their role assignments.
     *
     * @param int $cohortid Cohort id.
     */
    public static function cohort_deleted(int $cohortid): void {
        self::delete_rules_where(['cohortid' => $cohortid]);
    }

    /**
     * Delete the rules of a deleted user and their role assignments.
     *
     * @param int $userid User id.
     */
    public static function user_deleted(int $userid): void {
        self::delete_rules_where(['userid' => $userid]);
    }

    /**
     * Delete the rules targeting a deleted course or category.
     *
     * @param string $targettype TARGET_COURSE or TARGET_CATEGORY.
     * @param int $targetid Course or category id.
     */
    public static function target_deleted(string $targettype, int $targetid): void {
        self::delete_rules_where(['targettype' => $targettype, 'targetid' => $targetid]);
    }

    /**
     * Delete the rules using a deleted role.
     *
     * @param int $roleid Role id.
     */
    public static function role_deleted(int $roleid): void {
        self::delete_rules_where(['roleid' => $roleid]);
    }

    /**
     * Delete the rules matching conditions, with their role assignments.
     *
     * @param array $conditions Rule table conditions.
     */
    private static function delete_rules_where(array $conditions): void {
        global $DB;
        foreach ($DB->get_records(self::TABLE, $conditions, '', 'id') as $rule) {
            self::delete_rule($rule->id);
        }
    }

    /**
     * Keep only the rule fields, with their database types.
     *
     * A non-empty userid makes a user rule (cohortid is then null); otherwise the
     * rule is a cohort rule (userid is null).
     *
     * @param stdClass $data Raw data.
     * @return stdClass
     */
    private static function normalise(stdClass $data): stdClass {
        $userid = (int) ($data->userid ?? 0);
        return (object) [
            'cohortid' => $userid ? null : (int) ($data->cohortid ?? 0),
            'userid' => $userid ?: null,
            'targettype' => $data->targettype === self::TARGET_CATEGORY ? self::TARGET_CATEGORY : self::TARGET_COURSE,
            'targetid' => (int) $data->targetid,
            'roleid' => (int) $data->roleid,
            'enabled' => empty($data->enabled) ? 0 : 1,
        ];
    }
}
