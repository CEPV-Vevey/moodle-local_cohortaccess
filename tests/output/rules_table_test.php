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

namespace local_cohortaccess\output;

use context_system;
use local_cohortaccess\manager;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Tests for the rules list table.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(rules_table::class)]
final class rules_table_test extends \advanced_testcase {
    /** @var moodle_url Rules list URL. */
    private moodle_url $baseurl;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->baseurl = new moodle_url('/local/cohortaccess/index.php');
    }

    /**
     * Create a rule on a new course with a role that can view courses.
     *
     * @param array $overrides Rule field overrides.
     * @return stdClass
     */
    private function create_rule(array $overrides = []): stdClass {
        $roleid = create_role('viewer' . random_string(4), 'viewer' . random_string(4), '');
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, context_system::instance()->id);
        return manager::create_rule((object) array_merge([
            'cohortid' => $this->getDataGenerator()->create_cohort()->id,
            'targettype' => manager::TARGET_COURSE,
            'targetid' => $this->getDataGenerator()->create_course()->id,
            'roleid' => $roleid,
            'enabled' => 1,
        ], $overrides));
    }

    /**
     * Render the table of the given rules.
     *
     * @param stdClass[] $rules Rules.
     * @return array{0: \html_table, 1: string} Table and its HTML.
     */
    private function render(array $rules): array {
        $table = rules_table::build($rules, $this->baseurl);
        return [$table, \html_writer::table($table)];
    }

    public function test_enabled_rule_shows_open_eye_to_disable(): void {
        $rule = $this->create_rule();
        [$table, $html] = $this->render([$rule]);

        $disableurl = new moodle_url($this->baseurl, ['action' => 'disable', 'id' => $rule->id, 'sesskey' => sesskey()]);
        $this->assertStringContainsString($disableurl->out(false), $html);
        $this->assertStringContainsString('title="' . get_string('disable') . '"', $html);
        $this->assertStringNotContainsString('dimmed_text', $table->rowclasses[0] ?? '');
    }

    public function test_disabled_rule_is_dimmed_with_slashed_eye_to_enable(): void {
        $rule = $this->create_rule(['enabled' => 0]);
        [$table, $html] = $this->render([$rule]);

        $enableurl = new moodle_url($this->baseurl, ['action' => 'enable', 'id' => $rule->id, 'sesskey' => sesskey()]);
        $this->assertStringContainsString($enableurl->out(false), $html);
        $this->assertStringContainsString('title="' . get_string('enable') . '"', $html);
        $this->assertStringContainsString('dimmed_text', $table->rowclasses[0]);
    }

    public function test_action_icons(): void {
        $rule = $this->create_rule();
        [, $html] = $this->render([$rule]);

        $syncurl = new moodle_url($this->baseurl, ['action' => 'sync', 'id' => $rule->id, 'sesskey' => sesskey()]);
        $editurl = new moodle_url('/local/cohortaccess/edit.php', ['id' => $rule->id]);
        $deleteurl = new moodle_url($this->baseurl, ['action' => 'delete', 'id' => $rule->id]);
        foreach ([$syncurl, $editurl, $deleteurl] as $url) {
            $this->assertStringContainsString($url->out(false), $html);
        }
        foreach ([get_string('sync', 'local_cohortaccess'), get_string('edit'), get_string('delete')] as $title) {
            $this->assertStringContainsString('title="' . $title . '"', $html);
        }
    }

    public function test_warnings_are_shown_next_to_role_and_target(): void {
        $noview = create_role('noview', 'noview', '');
        $rule = $this->create_rule(['roleid' => $noview]);
        $missing = $this->create_rule();
        $missing->targetid += 1000;
        [$table] = $this->render([$rule, $missing]);

        $rolecell = $table->data[0][2];
        $targetcell = $table->data[1][1];
        $this->assertStringContainsString(get_string('warningnocourseview', 'local_cohortaccess'), $rolecell);
        $this->assertStringContainsString(get_string('targetmissing', 'local_cohortaccess'), $targetcell);
    }

    public function test_members_cell_shows_active_assignments(): void {
        $rule = $this->create_rule();
        cohort_add_member($rule->cohortid, $this->getDataGenerator()->create_user()->id);
        [$table] = $this->render([$rule]);

        $this->assertStringContainsString(get_string('activeassignments', 'local_cohortaccess', 1), $table->data[0][3]);
    }
}
