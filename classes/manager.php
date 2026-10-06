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

namespace local_cepv_cohortaccess;

use context;
use context_course;
use context_coursecat;
use context_system;
use core_php_time_limit;
use stdClass;

/**
 * Business logic: rule storage and role assignment synchronisation.
 *
 * Each rule gives the members of a cohort a role in a course or course category
 * context. Role assignments are tagged with component = COMPONENT and
 * itemid = rule id, so the plugin never touches manual assignments or those
 * created by other plugins. No enrolment is ever created.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var string Component used to tag role assignments. */
    public const COMPONENT = 'local_cepv_cohortaccess';

    /** @var string Rule table. */
    public const TABLE = 'local_cepv_cohortaccess';

    /** @var string Target type: course. */
    public const TARGET_COURSE = 'course';

    /** @var string Target type: course category. */
    public const TARGET_CATEGORY = 'category';

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
     * @param stdClass $data cohortid, targettype, targetid, roleid, enabled.
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
        self::remove_rule_assignments($ruleid);
        $DB->delete_records(self::TABLE, ['id' => $ruleid]);
    }

    /**
     * Whether another rule with the same cohort, target and role exists.
     *
     * @param stdClass $data Rule data.
     * @param int $excludeid Rule id to ignore (the rule being edited).
     * @return bool
     */
    public static function rule_exists(stdClass $data, int $excludeid = 0): bool {
        global $DB;
        return $DB->record_exists_select(
            self::TABLE,
            'cohortid = :cohortid AND targettype = :targettype AND targetid = :targetid
             AND roleid = :roleid AND id <> :excludeid',
            [
                'cohortid' => $data->cohortid,
                'targettype' => $data->targettype,
                'targetid' => $data->targetid,
                'roleid' => $data->roleid,
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
     * Expected assignments are the non-deleted cohort members when the rule is
     * enabled and its target exists. Assignments of this rule with another role,
     * another context or a user not expected are removed; missing ones are added.
     *
     * @param stdClass $rule Rule.
     * @return array{added: int, removed: int}
     */
    public static function sync_rule(stdClass $rule): array {
        global $DB;
        core_php_time_limit::raise();

        $context = self::get_target_context($rule);
        $expected = [];
        if ($rule->enabled && $context) {
            $expected = $DB->get_fieldset_sql(
                'SELECT cm.userid
                   FROM {cohort_members} cm
                   JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
                  WHERE cm.cohortid = :cohortid',
                ['cohortid' => $rule->cohortid]
            );
            $expected = array_fill_keys($expected, true);
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
            $valid = $context && $ra->roleid == $rule->roleid && $ra->contextid == $context->id
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
            role_assign($rule->roleid, $userid, $context->id, self::COMPONENT, $rule->id);
            $result['added']++;
        }
        return $result;
    }

    /**
     * Synchronise all rules.
     *
     * @return array{added: int, removed: int} Totals.
     */
    public static function sync_all(): array {
        $totals = ['added' => 0, 'removed' => 0];
        foreach (self::get_rules() as $rule) {
            $result = self::sync_rule($rule);
            $totals['added'] += $result['added'];
            $totals['removed'] += $result['removed'];
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
            $warnings[] = get_string('warningnocourseview', 'local_cepv_cohortaccess');
        }
        if ($rule->targettype === self::TARGET_CATEGORY) {
            $checkhidden = true;
        } else {
            $visible = $DB->get_field('course', 'visible', ['id' => $rule->targetid]);
            $checkhidden = $visible !== false && !$visible;
        }
        if ($checkhidden && !$allowed('moodle/course:viewhiddencourses')) {
            $warnings[] = get_string('warningnoviewhidden', 'local_cepv_cohortaccess');
        }
        return $warnings;
    }

    /**
     * Count the non-deleted members of a cohort.
     *
     * @param int $cohortid Cohort id.
     * @return int
     */
    public static function count_members(int $cohortid): int {
        global $DB;
        return $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {cohort_members} cm
               JOIN {user} u ON u.id = cm.userid AND u.deleted = 0
              WHERE cm.cohortid = :cohortid',
            ['cohortid' => $cohortid]
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
     * @param stdClass $data Raw data.
     * @return stdClass
     */
    private static function normalise(stdClass $data): stdClass {
        return (object) [
            'cohortid' => (int) $data->cohortid,
            'targettype' => $data->targettype === self::TARGET_CATEGORY ? self::TARGET_CATEGORY : self::TARGET_COURSE,
            'targetid' => (int) $data->targetid,
            'roleid' => (int) $data->roleid,
            'enabled' => empty($data->enabled) ? 0 : 1,
        ];
    }
}
