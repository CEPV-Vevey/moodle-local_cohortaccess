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

namespace local_cepv_cohortaccess\form;

use local_cepv_cohortaccess\manager;
use stdClass;

/**
 * Tests for the rule form.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(rule_form::class)]
final class rule_form_test extends \advanced_testcase {
    /** @var stdClass Cohort. */
    private stdClass $cohort;

    /** @var stdClass Course. */
    private stdClass $course;

    /** @var int Role id. */
    private int $roleid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->cohort = $this->getDataGenerator()->create_cohort();
        $this->course = $this->getDataGenerator()->create_course();
        $this->roleid = create_role('cepvview', 'cepvview', '');
        set_role_contextlevels($this->roleid, [CONTEXT_COURSE, CONTEXT_COURSECAT]);
    }

    /**
     * Submit the form with data and return the validated rule data.
     *
     * @param array $data Submitted values.
     * @param stdClass|null $rule Rule being edited.
     * @return array{0: ?stdClass, 1: array} Rule data or null, and validation errors.
     */
    private function submit(array $data, ?stdClass $rule = null): array {
        rule_form::mock_submit($data + [
            'id' => $rule->id ?? 0,
            'cohortid' => $this->cohort->id,
            'targettype' => manager::TARGET_COURSE,
            'roleid' => $this->roleid,
            'enabled' => 1,
        ]);
        $form = new rule_form(null, ['rule' => $rule]);
        $errors = $form->validation($form->get_submitted_data() ? (array) $form->get_submitted_data() : [], []);
        return [$form->get_rule_data(), $errors];
    }

    public function test_valid_course_rule(): void {
        [$rule, $errors] = $this->submit(['courseid' => $this->course->id]);
        $this->assertEmpty($errors);
        $this->assertEquals(manager::TARGET_COURSE, $rule->targettype);
        $this->assertEquals($this->course->id, $rule->targetid);
        $this->assertEquals($this->cohort->id, $rule->cohortid);
        $this->assertEquals($this->roleid, $rule->roleid);
        $this->assertEquals(1, $rule->enabled);
    }

    public function test_valid_category_rule(): void {
        $category = $this->getDataGenerator()->create_category();
        [$rule, $errors] = $this->submit(['targettype' => manager::TARGET_CATEGORY,
            'categoryid' => $category->id, 'courseid' => $this->course->id]);
        $this->assertEmpty($errors);
        $this->assertEquals(manager::TARGET_CATEGORY, $rule->targettype);
        $this->assertEquals($category->id, $rule->targetid);
    }

    public function test_target_required_and_must_exist(): void {
        [$rule, $errors] = $this->submit([]);
        $this->assertNull($rule);
        $this->assertArrayHasKey('courseid', $errors);

        [, $errors] = $this->submit(['courseid' => SITEID]);
        $this->assertArrayHasKey('courseid', $errors);

        [, $errors] = $this->submit(['targettype' => manager::TARGET_CATEGORY, 'categoryid' => 999999]);
        $this->assertArrayHasKey('categoryid', $errors);
    }

    public function test_duplicate_rule_rejected_but_editing_itself_allowed(): void {
        $existing = manager::create_rule((object) ['cohortid' => $this->cohort->id,
            'targettype' => manager::TARGET_COURSE, 'targetid' => $this->course->id,
            'roleid' => $this->roleid, 'enabled' => 1]);

        [, $errors] = $this->submit(['courseid' => $this->course->id]);
        $this->assertEquals(get_string('duplicaterule', 'local_cepv_cohortaccess'), $errors['cohortid']);

        [$rule, $errors] = $this->submit(['courseid' => $this->course->id, 'enabled' => 0], $existing);
        $this->assertEmpty($errors);
        $this->assertEquals($existing->id, $rule->id);
        $this->assertEquals(0, $rule->enabled);
    }

    public function test_role_must_be_assignable_in_target_context(): void {
        $systemonly = create_role('sysonly', 'sysonly', '');
        set_role_contextlevels($systemonly, [CONTEXT_SYSTEM]);

        [$rule, $errors] = $this->submit(['courseid' => $this->course->id, 'roleid' => $systemonly]);

        $this->assertNull($rule);
        $this->assertArrayHasKey('roleid', $errors);
    }

    public function test_delegated_manager_limited_to_roles_they_may_assign(): void {
        global $DB;
        $syscontext = \context_system::instance();
        $coordinator = create_role('coordinator', 'coordinator', '');
        set_role_contextlevels($coordinator, [CONTEXT_SYSTEM]);
        assign_capability('local/cepv_cohortaccess:manage', CAP_ALLOW, $coordinator, $syscontext->id);
        assign_capability('moodle/role:assign', CAP_ALLOW, $coordinator, $syscontext->id);
        core_role_set_assign_allowed($coordinator, $this->roleid);
        $user = $this->getDataGenerator()->create_user();
        role_assign($coordinator, $user->id, $syscontext->id);
        $this->setUser($user);
        $editingteacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);

        [$rule, $errors] = $this->submit(['courseid' => $this->course->id, 'roleid' => $editingteacher]);
        $this->assertNull($rule);
        $this->assertArrayHasKey('roleid', $errors);

        [$rule, $errors] = $this->submit(['courseid' => $this->course->id]);
        $this->assertEmpty($errors);
        $this->assertEquals($this->roleid, $rule->roleid);
    }

    public function test_editing_keeps_current_role_even_if_no_longer_assignable(): void {
        $existing = manager::create_rule((object) ['cohortid' => $this->cohort->id,
            'targettype' => manager::TARGET_COURSE, 'targetid' => $this->course->id,
            'roleid' => $this->roleid, 'enabled' => 1]);
        set_role_contextlevels($this->roleid, [CONTEXT_SYSTEM]);

        [$rule, $errors] = $this->submit(['courseid' => $this->course->id, 'enabled' => 0], $existing);

        $this->assertEmpty($errors);
        $this->assertEquals(0, $rule->enabled);
    }

    public function test_edit_form_is_prefilled_from_rule(): void {
        $category = $this->getDataGenerator()->create_category();
        $existing = manager::create_rule((object) ['cohortid' => $this->cohort->id,
            'targettype' => manager::TARGET_CATEGORY, 'targetid' => $category->id,
            'roleid' => $this->roleid, 'enabled' => 0]);
        $form = new rule_form(null, ['rule' => $existing]);
        $form->set_data_from_rule($existing);
        $html = $form->render();
        $this->assertStringContainsString('value="' . $existing->id . '"', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $category->id . '"\s+selected/', $html);
    }
}
