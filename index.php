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
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_cohortaccess\manager;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_cohortaccess');
$baseurl = new moodle_url('/local/cohortaccess/index.php');

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
                get_string('confirmdelete', 'local_cohortaccess', manager::describe_rule($rule)),
                new single_button($confirmurl, get_string('delete'), 'post', single_button::BUTTON_DANGER),
                $baseurl
            );
            echo $OUTPUT->footer();
            die();
        }
        require_sesskey();
        manager::delete_rule($id);
        redirect($baseurl, get_string('ruledeleted', 'local_cohortaccess'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    require_sesskey();
    if ($action === 'enable' || $action === 'disable') {
        $result = manager::set_enabled($id, $action === 'enable');
    } else if ($action === 'sync') {
        $result = manager::sync_rule($rule);
    } else {
        throw new moodle_exception('invalidparameter', 'debug', $baseurl);
    }
    redirect(
        $baseurl,
        get_string('syncresult', 'local_cohortaccess', (object) $result),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_cohortaccess'));
echo $OUTPUT->single_button(
    new moodle_url('/local/cohortaccess/edit.php'),
    get_string('addrule', 'local_cohortaccess'),
    'get',
    ['type' => single_button::BUTTON_PRIMARY]
);

$rules = manager::get_rules();
if (!$rules) {
    echo $OUTPUT->notification(get_string('norules', 'local_cohortaccess'), 'info', false);
    echo $OUTPUT->footer();
    die();
}

$table = \local_cohortaccess\output\rules_table::build($rules, $baseurl);
echo html_writer::table($table);
echo $OUTPUT->footer();
