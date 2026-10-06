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
 * List of cohort access rules and rule actions.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_cepv_cohortaccess\manager;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_cepv_cohortaccess');
$baseurl = new moodle_url('/local/cepv_cohortaccess/index.php');
$syscontext = context_system::instance();

/**
 * Human-readable description of a rule's parts.
 *
 * @param stdClass $rule Rule.
 * @return stdClass cohort, role and target names (plain text), plus targeturl (or null).
 */
function local_cepv_cohortaccess_describe(stdClass $rule): stdClass {
    global $DB;
    $syscontext = context_system::instance();
    $cohort = $DB->get_record('cohort', ['id' => $rule->cohortid]);
    $role = $DB->get_record('role', ['id' => $rule->roleid]);
    $desc = (object) [
        'cohort' => $cohort ? format_string($cohort->name, true, ['context' => $cohort->contextid]) : '?',
        'role' => $role ? role_get_name($role, $syscontext) : '?',
        'target' => get_string('targetmissing', 'local_cepv_cohortaccess'),
        'targeturl' => null,
    ];
    $context = manager::get_target_context($rule);
    if ($context) {
        $desc->target = $context->get_context_name(false);
        $desc->targeturl = $context->get_url();
    }
    return $desc;
}

if ($action) {
    $rule = manager::get_rule($id);
    if (!$rule) {
        throw new moodle_exception('invalidrecord', 'error', $baseurl);
    }
    if ($action === 'delete') {
        if (!optional_param('confirm', 0, PARAM_BOOL)) {
            echo $OUTPUT->header();
            $confirmurl = new moodle_url($baseurl, ['action' => 'delete', 'id' => $id, 'confirm' => 1]);
            echo $OUTPUT->confirm(
                get_string('confirmdelete', 'local_cepv_cohortaccess', local_cepv_cohortaccess_describe($rule)),
                new single_button($confirmurl, get_string('delete'), 'post', single_button::BUTTON_DANGER),
                $baseurl
            );
            echo $OUTPUT->footer();
            die();
        }
        require_sesskey();
        manager::delete_rule($id);
        redirect($baseurl, get_string('ruledeleted', 'local_cepv_cohortaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    require_sesskey();
    if ($action === 'enable' || $action === 'disable') {
        $result = manager::set_enabled($id, $action === 'enable');
    } else if ($action === 'sync') {
        $result = manager::sync_rule($rule);
    } else {
        throw new moodle_exception('invalidparameter', 'debug', $baseurl);
    }
    redirect($baseurl, get_string('syncresult', 'local_cepv_cohortaccess', (object) $result), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_cepv_cohortaccess'));
echo $OUTPUT->single_button(new moodle_url('/local/cepv_cohortaccess/edit.php'),
    get_string('addrule', 'local_cepv_cohortaccess'), 'get', ['type' => single_button::BUTTON_PRIMARY]);

$rules = manager::get_rules();
if (!$rules) {
    echo $OUTPUT->notification(get_string('norules', 'local_cepv_cohortaccess'), 'info', false);
    echo $OUTPUT->footer();
    die();
}

$table = new html_table();
$table->head = [
    get_string('cohort', 'local_cepv_cohortaccess'),
    get_string('target', 'local_cepv_cohortaccess'),
    get_string('role', 'local_cepv_cohortaccess'),
    get_string('members', 'local_cepv_cohortaccess'),
    get_string('status', 'local_cepv_cohortaccess'),
    get_string('actions', 'local_cepv_cohortaccess'),
];
$table->attributes['class'] = 'generaltable';
foreach ($rules as $rule) {
    $desc = local_cepv_cohortaccess_describe($rule);
    $target = $desc->targeturl ? html_writer::link($desc->targeturl, s($desc->target)) : s($desc->target);

    $members = manager::count_members($rule->cohortid) . html_writer::div(
        get_string('assignments', 'local_cepv_cohortaccess', manager::count_assignments($rule->id)), 'small text-muted');

    $status = $rule->enabled
        ? html_writer::span(get_string('enabled', 'local_cepv_cohortaccess'), 'badge bg-success text-white')
        : html_writer::span(get_string('disabled', 'local_cepv_cohortaccess'), 'badge bg-secondary text-white');
    $warnings = manager::get_role_warnings($rule);
    if (!$desc->targeturl) {
        $warnings[] = get_string('targetmissing', 'local_cepv_cohortaccess');
    }
    if ($warnings) {
        $status .= ' ' . $OUTPUT->pix_icon('i/warning', implode(' ', $warnings));
    }

    $actionurl = fn(string $action) => new moodle_url($baseurl, ['action' => $action, 'id' => $rule->id,
        'sesskey' => sesskey()]);
    $actions = [
        html_writer::link(new moodle_url('/local/cepv_cohortaccess/edit.php', ['id' => $rule->id]),
            get_string('edit')),
        $rule->enabled
            ? html_writer::link($actionurl('disable'), get_string('disable', 'local_cepv_cohortaccess'))
            : html_writer::link($actionurl('enable'), get_string('enable', 'local_cepv_cohortaccess')),
        html_writer::link($actionurl('sync'), get_string('sync', 'local_cepv_cohortaccess')),
        html_writer::link(new moodle_url($baseurl, ['action' => 'delete', 'id' => $rule->id]), get_string('delete')),
    ];

    $table->data[] = [s($desc->cohort), $target, s($desc->role), $members, $status, implode(' · ', $actions)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
