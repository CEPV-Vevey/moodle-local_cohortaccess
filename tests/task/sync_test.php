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

namespace local_cepv_cohortaccess\task;

use context_course;
use context_system;
use local_cepv_cohortaccess\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests for the synchronisation scheduled task.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sync::class)]
final class sync_test extends \advanced_testcase {
    public function test_task_is_registered(): void {
        $task = \core\task\manager::get_scheduled_task(sync::class);
        $this->assertInstanceOf(sync::class, $task);
        $this->assertNotEmpty($task->get_name());
    }

    public function test_execute_repairs_drift_and_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = create_role('cepvview', 'cepvview', '');
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, context_system::instance()->id);
        $cohort = $this->getDataGenerator()->create_cohort();
        $user = $this->getDataGenerator()->create_user();
        cohort_add_member($cohort->id, $user->id);
        $course = $this->getDataGenerator()->create_course();
        $rule = manager::create_rule((object) ['cohortid' => $cohort->id, 'targettype' => manager::TARGET_COURSE,
            'targetid' => $course->id, 'roleid' => $roleid, 'enabled' => 1]);
        // Membership changed behind the plugin's back (no event).
        $DB->delete_records('role_assignments', ['component' => manager::COMPONENT, 'itemid' => $rule->id]);
        $outsider = $this->getDataGenerator()->create_user();
        role_assign($roleid, $outsider->id, context_course::instance($course->id)->id, manager::COMPONENT, $rule->id);

        $task = new sync();
        ob_start();
        $task->execute();
        $first = ob_get_clean();
        ob_start();
        $task->execute();
        $second = ob_get_clean();

        $this->assertStringContainsString('1 role assignments added, 1 removed', $first);
        $this->assertStringContainsString('0 role assignments added, 0 removed', $second);
        $this->assertTrue(can_access_course($course, $user));
        $this->assertFalse(can_access_course($course, $outsider));
    }
}
