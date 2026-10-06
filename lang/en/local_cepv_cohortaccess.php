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
 * English strings for local_cepv_cohortaccess.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['addrule'] = 'Add a rule';
$string['assignments'] = '{$a} active role assignments';
$string['cepv_cohortaccess:manage'] = 'Manage cohort course access rules';
$string['cohort'] = 'Cohort';
$string['confirmdelete'] = 'Delete the rule giving cohort "{$a->cohort}" the role "{$a->role}" in "{$a->target}"? All role assignments created by this rule will be removed.';
$string['disable'] = 'Disable';
$string['disabled'] = 'Disabled';
$string['duplicaterule'] = 'A rule with the same cohort, target and role already exists.';
$string['editrule'] = 'Edit rule';
$string['enable'] = 'Enable';
$string['enabled'] = 'Enabled';
$string['members'] = 'Members';
$string['norules'] = 'No rules defined yet.';
$string['pluginname'] = 'Cohort course access';
$string['privacy:metadata'] = 'The Cohort course access plugin does not store any personal data. Role assignments it creates are stored by the core role subsystem.';
$string['role'] = 'Role';
$string['ruledeleted'] = 'Rule deleted.';
$string['rulesaved'] = 'Rule saved.';
$string['status'] = 'Status';
$string['sync'] = 'Synchronise';
$string['syncresult'] = 'Synchronisation done: {$a->added} role assignments added, {$a->removed} removed.';
$string['target'] = 'Course / category';
$string['targetcategory'] = 'Course category';
$string['targetcourse'] = 'Course';
$string['targetmissing'] = 'The target course or category no longer exists.';
$string['targettype'] = 'Target type';
$string['tasksync'] = 'Synchronise cohort course access';
$string['warningnocourseview'] = 'This role does not have moodle/course:view. Users might not be able to access the course without enrolment.';
$string['warningnoviewhidden'] = 'This role does not have moodle/course:viewhiddencourses. Users will not be able to access hidden courses.';
