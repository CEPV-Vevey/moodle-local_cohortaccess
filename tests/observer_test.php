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

use context_course;
use context_system;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests for the event observers.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class observer_test extends \advanced_testcase {
    /** @var int Role with moodle/course:view. */
    private int $roleid;

    /** @var stdClass Cohort used by the tests. */
    private stdClass $cohort;

    /** @var \core_course_category Course category used by the tests. */
    private \core_course_category $category;

    /** @var stdClass Course used by the tests. */
    private stdClass $course;

    /** @var stdClass User used by the tests. */
    private stdClass $user;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->roleid = create_role('courseviewer', 'courseviewer', '');
        assign_capability('moodle/course:view', CAP_ALLOW, $this->roleid, context_system::instance()->id);
        $this->cohort = $this->getDataGenerator()->create_cohort();
        $this->category = $this->getDataGenerator()->create_category();
        $this->course = $this->getDataGenerator()->create_course(['category' => $this->category->id]);
        $this->user = $this->getDataGenerator()->create_user();
    }

    /**
     * Create a rule for the test cohort.
     *
     * @param array $overrides Rule field overrides.
     * @return stdClass The created rule.
     */
    private function create_rule(array $overrides = []): stdClass {
        return manager::create_rule((object) array_merge([
            'cohortid' => $this->cohort->id,
            'targettype' => manager::TARGET_COURSE,
            'targetid' => $this->course->id,
            'roleid' => $this->roleid,
            'enabled' => 1,
        ], $overrides));
    }

    public function test_member_added_and_removed_updates_access(): void {
        $rule = $this->create_rule();
        $disabled = $this->create_rule(['targettype' => manager::TARGET_CATEGORY,
            'targetid' => $this->category->id, 'enabled' => 0]);
        $this->assertFalse(can_access_course($this->course, $this->user));

        cohort_add_member($this->cohort->id, $this->user->id);
        $this->assertEquals(1, manager::count_assignments($rule->id));
        $this->assertEquals(0, manager::count_assignments($disabled->id));
        $this->assertTrue(can_access_course($this->course, $this->user));

        cohort_remove_member($this->cohort->id, $this->user->id);
        $this->assertEquals(0, manager::count_assignments($rule->id));
        $this->assertFalse(can_access_course($this->course, $this->user));
    }

    public function test_member_removed_keeps_manual_assignment(): void {
        $context = context_course::instance($this->course->id);
        role_assign($this->roleid, $this->user->id, $context->id);
        $this->create_rule();
        cohort_add_member($this->cohort->id, $this->user->id);

        cohort_remove_member($this->cohort->id, $this->user->id);

        $this->assertTrue(user_has_role_assignment($this->user->id, $this->roleid, $context->id));
    }

    public function test_member_added_ignores_rule_with_missing_target(): void {
        $rule = $this->create_rule(['targetid' => $this->course->id + 1000]);
        cohort_add_member($this->cohort->id, $this->user->id);
        $this->assertEquals(0, manager::count_assignments($rule->id));
    }

    public function test_cohort_deleted_removes_assignments_and_rules(): void {
        cohort_add_member($this->cohort->id, $this->user->id);
        $rule = $this->create_rule();
        $this->assertEquals(1, manager::count_assignments($rule->id));

        cohort_delete_cohort($this->cohort);

        $this->assertEquals(0, manager::count_assignments($rule->id));
        $this->assertFalse(manager::get_rule($rule->id));
        $this->assertFalse(can_access_course($this->course, $this->user));
    }

    public function test_course_deleted_removes_rule(): void {
        $rule = $this->create_rule();
        $other = $this->create_rule(['targettype' => manager::TARGET_CATEGORY,
            'targetid' => $this->category->id]);

        delete_course($this->course, false);

        $this->assertFalse(manager::get_rule($rule->id));
        $this->assertNotEmpty(manager::get_rule($other->id));
    }

    public function test_category_deleted_removes_rule(): void {
        $rule = $this->create_rule(['targettype' => manager::TARGET_CATEGORY,
            'targetid' => $this->category->id]);

        \core_course_category::get($this->category->id)->delete_full(false);

        $this->assertFalse(manager::get_rule($rule->id));
    }

    public function test_role_deleted_removes_rule(): void {
        cohort_add_member($this->cohort->id, $this->user->id);
        $rule = $this->create_rule();

        delete_role($this->roleid);

        $this->assertFalse(manager::get_rule($rule->id));
        $this->assertEquals(0, manager::count_assignments($rule->id));
    }
}
