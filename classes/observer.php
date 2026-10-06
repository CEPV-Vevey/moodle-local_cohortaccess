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

/**
 * Event observers: keep role assignments in line with cohorts and targets.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A user joined a cohort.
     *
     * @param \core\event\cohort_member_added $event Event.
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event): void {
        manager::member_added($event->objectid, $event->relateduserid);
    }

    /**
     * A user left a cohort.
     *
     * @param \core\event\cohort_member_removed $event Event.
     */
    public static function cohort_member_removed(\core\event\cohort_member_removed $event): void {
        manager::member_removed($event->objectid, $event->relateduserid);
    }

    /**
     * A cohort was deleted (its members are removed without member events).
     *
     * @param \core\event\cohort_deleted $event Event.
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        manager::cohort_deleted($event->objectid);
    }

    /**
     * A course was deleted.
     *
     * @param \core\event\course_deleted $event Event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        manager::target_deleted(manager::TARGET_COURSE, $event->objectid);
    }

    /**
     * A course category was deleted.
     *
     * @param \core\event\course_category_deleted $event Event.
     */
    public static function course_category_deleted(\core\event\course_category_deleted $event): void {
        manager::target_deleted(manager::TARGET_CATEGORY, $event->objectid);
    }

    /**
     * A role was deleted.
     *
     * @param \core\event\role_deleted $event Event.
     */
    public static function role_deleted(\core\event\role_deleted $event): void {
        manager::role_deleted($event->objectid);
    }
}
