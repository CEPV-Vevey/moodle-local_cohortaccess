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
 * Tests for the admin tree entry.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class settings_test extends \advanced_testcase {
    public function test_rules_page_is_next_to_cohorts(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = admin_get_root(true, false)->locate('local_cohortaccess', true);

        $this->assertInstanceOf(\admin_externalpage::class, $page);
        $this->assertEquals('accounts', $page->path[1]);
        $this->assertEquals(['local/cohortaccess:manage'], $page->req_capability);

        $accounts = admin_get_root(true, false)->locate('accounts');
        $siblings = array_values(array_map(fn($child) => $child->name, $accounts->get_children()));
        $position = array_search('local_cohortaccess', $siblings);
        $this->assertEquals('cohort_customfield', $siblings[$position - 1]);
    }
}
