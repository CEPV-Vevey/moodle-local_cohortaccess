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

namespace local_cohortaccess\task;

use local_cohortaccess\manager;

/**
 * Hourly full synchronisation of the rules' role assignments.
 *
 * Catches up with changes made without events (e.g. direct database edits).
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync extends \core\task\scheduled_task {
    /**
     * Task name shown to administrators.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksync', 'local_cohortaccess');
    }

    /**
     * Synchronise all rules.
     */
    public function execute(): void {
        mtrace(get_string('syncresult', 'local_cohortaccess', (object) manager::sync_all()));
    }
}
