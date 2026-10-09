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

namespace local_cohortaccess\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_cohortaccess\manager;

/**
 * Privacy provider: rules giving a role to a single user store that user's id.
 *
 * Cohort rules hold no personal data. Role assignments created by the plugin are
 * stored and exported by core_role.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored personal data.
     *
     * @param collection $collection Collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(manager::TABLE, [
            'userid' => 'privacy:metadata:local_cohortaccess:userid',
            'targettype' => 'privacy:metadata:local_cohortaccess:targettype',
            'targetid' => 'privacy:metadata:local_cohortaccess:targetid',
            'roleid' => 'privacy:metadata:local_cohortaccess:roleid',
            'enabled' => 'privacy:metadata:local_cohortaccess:enabled',
            'timecreated' => 'privacy:metadata:local_cohortaccess:timecreated',
            'timemodified' => 'privacy:metadata:local_cohortaccess:timemodified',
        ], 'privacy:metadata:local_cohortaccess');
        $collection->link_subsystem('core_role', 'privacy:metadata:core_role');
        return $collection;
    }

    /**
     * Contexts holding data of a user: the system context when a rule targets them.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists(manager::TABLE, ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with data in a context: users targeted by a rule, in the system context.
     *
     * @param userlist $userlist List to fill.
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {' . manager::TABLE . '} WHERE userid IS NOT NULL', []);
    }

    /**
     * Export the rules targeting a user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $context = context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids())) {
            return;
        }
        $rules = $DB->get_records(manager::TABLE, ['userid' => $contextlist->get_user()->id], 'id');
        if (!$rules) {
            return;
        }
        $export = [];
        foreach ($rules as $rule) {
            $desc = manager::describe_rule($rule);
            $export[] = (object) [
                'target' => $desc->target,
                'role' => $desc->role,
                'enabled' => transform::yesno($rule->enabled),
                'timecreated' => transform::datetime($rule->timecreated),
                'timemodified' => transform::datetime($rule->timemodified),
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_cohortaccess')],
            (object) ['rules' => $export]
        );
    }

    /**
     * Delete every user rule (system context only), with their role assignments.
     *
     * @param context $context Context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        foreach ($DB->get_records_select(manager::TABLE, 'userid IS NOT NULL', null, '', 'id') as $rule) {
            manager::delete_rule($rule->id);
        }
    }

    /**
     * Delete the rules targeting a user, with their role assignments.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (in_array(context_system::instance()->id, $contextlist->get_contextids())) {
            manager::user_deleted($contextlist->get_user()->id);
        }
    }

    /**
     * Delete the rules targeting the listed users, with their role assignments.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            manager::user_deleted($userid);
        }
    }
}
