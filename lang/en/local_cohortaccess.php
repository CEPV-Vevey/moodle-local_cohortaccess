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
 * English strings for local_cohortaccess.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['activeassignments'] = 'Active role assignments: {$a}';
$string['addrule'] = 'Add a rule';
$string['beneficiary'] = 'Beneficiary';
$string['beneficiarytype'] = 'Access granted to';
$string['cohort'] = 'Cohort';
$string['cohortaccess:manage'] = 'Manage cohort course access rules';
$string['confirmdelete'] = 'Delete the rule giving cohort "{$a->beneficiary}" the role "{$a->role}" in "{$a->target}"? All role assignments created by this rule will be removed.';
$string['confirmdeleteuser'] = 'Delete the rule giving user "{$a->beneficiary}" the role "{$a->role}" in "{$a->target}"? The role assignment created by this rule will be removed.';
$string['disable'] = 'Disable';
$string['duplicaterule'] = 'A rule with the same beneficiary, target and role already exists.';
$string['editrule'] = 'Edit rule';
$string['enable'] = 'Enable';
$string['enabled'] = 'Enabled';
$string['members'] = 'Members';
$string['norules'] = 'No rules defined yet.';
$string['pluginname'] = 'Cohort course access';
$string['privacy:metadata:core_role'] = 'Rules give roles to users in courses or categories. These role assignments are stored by the core role subsystem.';
$string['privacy:metadata:local_cohortaccess'] = 'Rules giving a role to a single user in a course or course category.';
$string['privacy:metadata:local_cohortaccess:enabled'] = 'Whether the rule is enabled.';
$string['privacy:metadata:local_cohortaccess:roleid'] = 'The role given to the user.';
$string['privacy:metadata:local_cohortaccess:targetid'] = 'The course or course category where the role is given.';
$string['privacy:metadata:local_cohortaccess:targettype'] = 'Whether the target is a course or a course category.';
$string['privacy:metadata:local_cohortaccess:timecreated'] = 'When the rule was created.';
$string['privacy:metadata:local_cohortaccess:timemodified'] = 'When the rule was last modified.';
$string['privacy:metadata:local_cohortaccess:userid'] = 'The user who receives the role.';
$string['role'] = 'Role';
$string['rolenotassignable'] = 'You are not allowed to assign this role in this course or category.';
$string['ruledeleted'] = 'Rule deleted.';
$string['rulesaved'] = 'Rule saved.';
$string['sync'] = 'Synchronise';
$string['syncresult'] = 'Synchronisation done: {$a->added} role assignments added, {$a->removed} removed.';
$string['target'] = 'Course / category';
$string['targetcategory'] = 'Course category';
$string['targetcourse'] = 'Course';
$string['targetmissing'] = 'The target course or category no longer exists.';
$string['targettype'] = 'Target type';
$string['tasksync'] = 'Synchronise cohort course access';
$string['user'] = 'User';
$string['usermissing'] = 'This user no longer exists.';
$string['warningnocourseview'] = 'This role does not have moodle/course:view. Users might not be able to access the course without enrolment.';
$string['warningnoviewhidden'] = 'This role does not have moodle/course:viewhiddencourses. Users will not be able to access hidden courses.';
