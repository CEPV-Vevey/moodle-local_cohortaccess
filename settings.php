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

/**
 * Admin tree entry for local_cohortaccess, next to the cohorts pages.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Right after the core cohort pages, whatever page follows them in this Moodle version
// (admin tools and other core pages are added to the same category before local plugins).
$beforesibling = null;
if ($accounts = $ADMIN->locate('accounts')) {
    $names = array_values(array_map(fn($child) => $child->name, $accounts->get_children()));
    $position = array_search('cohort_customfield', $names, true);
    if ($position === false) {
        $position = array_search('cohorts', $names, true);
    }
    if ($position !== false && isset($names[$position + 1])) {
        $beforesibling = $names[$position + 1];
    }
}
$ADMIN->add('accounts', new admin_externalpage(
    'local_cohortaccess',
    new lang_string('pluginname', 'local_cohortaccess'),
    new moodle_url('/local/cohortaccess/index.php'),
    'local/cohortaccess:manage'
), $beforesibling);
