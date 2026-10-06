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
use context_coursecat;
use context_system;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests for the rule manager.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(manager::class)]
final class manager_test extends \advanced_testcase {
    /** @var int Role with moodle/course:view. */
    private int $roleid;

    /** @var stdClass Cohort used by the tests. */
    private stdClass $cohort;

    /** @var stdClass[] Cohort members. */
    private array $members = [];

    /** @var stdClass Course used by the tests. */
    private stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->roleid = $this->create_view_role('courseviewer');
        $this->cohort = $this->getDataGenerator()->create_cohort();
        for ($i = 0; $i < 3; $i++) {
            $user = $this->getDataGenerator()->create_user();
            cohort_add_member($this->cohort->id, $user->id);
            $this->members[] = $user;
        }
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Create a custom role allowed to view courses without enrolment.
     *
     * @param string $shortname Role short name.
     * @param bool $viewhidden Also allow moodle/course:viewhiddencourses.
     * @return int Role id.
     */
    private function create_view_role(string $shortname, bool $viewhidden = false): int {
        $roleid = create_role($shortname, $shortname, '');
        set_role_contextlevels($roleid, [CONTEXT_COURSE, CONTEXT_COURSECAT]);
        $syscontextid = context_system::instance()->id;
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $syscontextid);
        if ($viewhidden) {
            assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, $syscontextid);
        }
        return $roleid;
    }

    /**
     * Create a rule targeting the test course (or another target).
     *
     * @param array $overrides Rule field overrides.
     * @return stdClass The created rule.
     */
    private function create_course_rule(array $overrides = []): stdClass {
        return manager::create_rule((object) array_merge([
            'cohortid' => $this->cohort->id,
            'targettype' => manager::TARGET_COURSE,
            'targetid' => $this->course->id,
            'roleid' => $this->roleid,
            'enabled' => 1,
        ], $overrides));
    }

    /**
     * Count plugin role assignments for a rule.
     *
     * @param int $ruleid Rule id.
     * @param array $extra Extra conditions.
     * @return int
     */
    private function count_ras(int $ruleid, array $extra = []): int {
        global $DB;
        return $DB->count_records(
            'role_assignments',
            ['component' => manager::COMPONENT, 'itemid' => $ruleid] + $extra
        );
    }

    public function test_course_rule_assigns_role_without_enrolment(): void {
        global $DB;
        $rule = $this->create_course_rule();
        $context = context_course::instance($this->course->id);

        $this->assertEquals(3, $this->count_ras(
            $rule->id,
            ['contextid' => $context->id, 'roleid' => $this->roleid]
        ));
        $this->assertEquals(0, $DB->count_records('user_enrolments'));
        foreach ($this->members as $user) {
            $this->assertEmpty(enrol_get_all_users_courses($user->id));
            $this->assertTrue(can_access_course($this->course, $user));
            $this->assertFalse(is_enrolled($context, $user));
        }
        $outsider = $this->getDataGenerator()->create_user();
        $this->assertFalse(can_access_course($this->course, $outsider));
    }

    public function test_category_rule_grants_view_on_courses_in_category(): void {
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $rule = $this->create_course_rule([
            'targettype' => manager::TARGET_CATEGORY,
            'targetid' => $category->id,
        ]);

        $catcontext = context_coursecat::instance($category->id);
        $this->assertEquals(3, $this->count_ras($rule->id, ['contextid' => $catcontext->id]));
        $coursecontext = context_course::instance($course->id);
        foreach ($this->members as $user) {
            $this->assertTrue(has_capability('moodle/course:view', $coursecontext, $user));
            $this->assertTrue(can_access_course($course, $user));
        }
    }

    public function test_manual_assignment_survives_rule_deletion_and_disabling(): void {
        $context = context_course::instance($this->course->id);
        $user = $this->members[0];
        role_assign($this->roleid, $user->id, $context->id);

        $rule = $this->create_course_rule();
        manager::set_enabled($rule->id, false);
        $this->assertTrue(user_has_role_assignment($user->id, $this->roleid, $context->id));

        manager::set_enabled($rule->id, true);
        manager::delete_rule($rule->id);
        $this->assertEquals(0, $this->count_ras($rule->id));
        $this->assertTrue(user_has_role_assignment($user->id, $this->roleid, $context->id));
        $this->assertTrue(can_access_course($this->course, $user));
    }

    public function test_access_kept_through_other_rule_when_one_is_deleted(): void {
        $user = $this->members[0];
        $cohort2 = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort2->id, $user->id);
        $rule1 = $this->create_course_rule();
        $rule2 = $this->create_course_rule(['cohortid' => $cohort2->id]);

        manager::delete_rule($rule1->id);

        $this->assertFalse(manager::get_rule($rule1->id));
        $this->assertEquals(1, $this->count_ras($rule2->id, ['userid' => $user->id]));
        $this->assertTrue(can_access_course($this->course, $user));
        $this->assertFalse(can_access_course($this->course, $this->members[1]));
    }

    public function test_update_rule_replaces_old_assignments(): void {
        $rule = $this->create_course_rule();
        $oldcontext = context_course::instance($this->course->id);
        $newcourse = $this->getDataGenerator()->create_course();
        $newcontext = context_course::instance($newcourse->id);
        $newroleid = $this->create_view_role('courseviewer2');

        $rule->targetid = $newcourse->id;
        $rule->roleid = $newroleid;
        manager::update_rule($rule);

        $this->assertEquals(0, $this->count_ras($rule->id, ['contextid' => $oldcontext->id]));
        $this->assertEquals(0, $this->count_ras($rule->id, ['roleid' => $this->roleid]));
        $this->assertEquals(3, $this->count_ras(
            $rule->id,
            ['contextid' => $newcontext->id, 'roleid' => $newroleid]
        ));
        $this->assertFalse(can_access_course($this->course, $this->members[0]));
        $this->assertTrue(can_access_course($newcourse, $this->members[0]));
    }

    public function test_disable_removes_and_enable_restores_assignments(): void {
        $rule = $this->create_course_rule();

        manager::set_enabled($rule->id, false);
        $this->assertEquals(0, $this->count_ras($rule->id));
        $this->assertEquals(0, manager::get_rule($rule->id)->enabled);

        manager::set_enabled($rule->id, true);
        $this->assertEquals(3, $this->count_ras($rule->id));
    }

    public function test_disabled_rule_creates_no_assignment(): void {
        $rule = $this->create_course_rule(['enabled' => 0]);
        $this->assertEquals(0, $this->count_ras($rule->id));
    }

    public function test_sync_is_idempotent_and_repairs_drift(): void {
        global $DB;
        $rule = $this->create_course_rule();
        $context = context_course::instance($this->course->id);

        $this->assertEquals(['added' => 0, 'removed' => 0], manager::sync_rule($rule));
        $this->assertEquals(['added' => 0, 'removed' => 0], manager::sync_all());

        // Assignment removed by hand is restored.
        $DB->delete_records('role_assignments', ['component' => manager::COMPONENT,
            'itemid' => $rule->id, 'userid' => $this->members[0]->id]);
        // Assignment for a user outside the cohort is removed.
        $outsider = $this->getDataGenerator()->create_user();
        role_assign($this->roleid, $outsider->id, $context->id, manager::COMPONENT, $rule->id);
        // Deleted users get no assignment.
        delete_user($this->members[1]);

        $this->assertEquals(['added' => 1, 'removed' => 1], manager::sync_all());
        $this->assertEquals(2, $this->count_ras($rule->id));
        $this->assertEquals(0, $this->count_ras($rule->id, ['userid' => $outsider->id]));
        $this->assertEquals(1, $this->count_ras($rule->id, ['userid' => $this->members[0]->id]));
    }

    public function test_rule_with_missing_target_creates_no_assignment(): void {
        $rule = $this->create_course_rule(['targetid' => $this->course->id + 1000]);
        $this->assertNull(manager::get_target_context($rule));
        $this->assertEquals(0, $this->count_ras($rule->id));
    }

    public function test_rule_exists_detects_duplicates(): void {
        $rule = $this->create_course_rule();
        $data = clone $rule;
        unset($data->id);

        $this->assertTrue(manager::rule_exists($data));
        $this->assertFalse(manager::rule_exists($rule, $rule->id));
        $data->roleid = $this->create_view_role('other');
        $this->assertFalse(manager::rule_exists($data));
    }

    public function test_counts(): void {
        $rule = $this->create_course_rule();
        $this->assertEquals(3, manager::count_members($this->cohort->id));
        $this->assertEquals(3, manager::count_assignments($rule->id));
    }

    public function test_sync_all_removes_assignments_of_missing_rules(): void {
        $context = context_course::instance($this->course->id);
        $rule = $this->create_course_rule();
        $orphanid = $rule->id + 1000;
        role_assign($this->roleid, $this->members[0]->id, $context->id, manager::COMPONENT, $orphanid);

        $this->assertEquals(['added' => 0, 'removed' => 1], manager::sync_all());
        $this->assertEquals(0, $this->count_ras($orphanid));
        $this->assertEquals(3, $this->count_ras($rule->id));
    }

    public function test_sync_of_stale_rule_does_not_recreate_deleted_rule_assignments(): void {
        $rule = $this->create_course_rule();
        $stale = clone $rule;
        manager::delete_rule($rule->id);

        $this->assertEquals(['added' => 0, 'removed' => 0], manager::sync_rule($stale));
        $this->assertEquals(0, $this->count_ras($rule->id));
    }

    public function test_sync_of_stale_rule_uses_current_state(): void {
        $rule = $this->create_course_rule();
        $stale = clone $rule;
        manager::set_enabled($rule->id, false);

        manager::sync_rule($stale);
        $this->assertEquals(0, $this->count_ras($rule->id));
    }

    public function test_describe_rule_returns_names_escaped_once(): void {
        global $DB;
        $DB->set_field('cohort', 'name', "Cours d'été & co", ['id' => $this->cohort->id]);
        $DB->set_field('course', 'fullname', 'R&D <b>', ['id' => $this->course->id]);
        $rule = $this->create_course_rule();

        $desc = manager::describe_rule($rule);

        $this->assertEquals(format_string("Cours d'été & co"), $desc->cohort);
        $this->assertStringNotContainsString('&amp;amp;', $desc->target);
        $this->assertStringNotContainsString('<b>', $desc->target);
        $this->assertEquals('courseviewer', $desc->role);
        $this->assertEquals(new \moodle_url('/course/view.php', ['id' => $this->course->id]), $desc->targeturl);

        $rule->targetid += 1000;
        $desc = manager::describe_rule($rule);
        $this->assertNull($desc->targeturl);
        $this->assertEquals(get_string('targetmissing', 'local_cohortaccess'), $desc->target);
    }

    public function test_uninstall_removes_only_plugin_assignments(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/cohortaccess/db/uninstall.php');
        $context = context_course::instance($this->course->id);
        role_assign($this->roleid, $this->members[0]->id, $context->id);
        $this->create_course_rule();

        xmldb_local_cohortaccess_uninstall();

        $this->assertEquals(0, $DB->count_records('role_assignments', ['component' => manager::COMPONENT]));
        $this->assertTrue(user_has_role_assignment($this->members[0]->id, $this->roleid, $context->id));
    }

    public function test_role_warnings(): void {
        $this->assertEmpty(manager::get_role_warnings($this->create_course_rule()));

        $noviewrole = create_role('noview', 'noview', '');
        $rule = $this->create_course_rule(['roleid' => $noviewrole]);
        $this->assertContains(
            get_string('warningnocourseview', 'local_cohortaccess'),
            manager::get_role_warnings($rule)
        );

        $hidden = $this->getDataGenerator()->create_course(['visible' => 0]);
        $rule = $this->create_course_rule(['targetid' => $hidden->id]);
        $this->assertEquals(
            [get_string('warningnoviewhidden', 'local_cohortaccess')],
            manager::get_role_warnings($rule)
        );

        $rule = $this->create_course_rule(['targetid' => $hidden->id,
            'roleid' => $this->create_view_role('viewhidden', true)]);
        $this->assertEmpty(manager::get_role_warnings($rule));
    }
}
