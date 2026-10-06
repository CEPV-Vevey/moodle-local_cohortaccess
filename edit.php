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
 * Add or edit a cohort access rule.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_cepv_cohortaccess\form\rule_form;
use local_cepv_cohortaccess\manager;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);

$url = new moodle_url('/local/cepv_cohortaccess/edit.php', $id ? ['id' => $id] : []);
admin_externalpage_setup('local_cepv_cohortaccess', '', null, $url);
$returnurl = new moodle_url('/local/cepv_cohortaccess/index.php');

$rule = null;
if ($id) {
    $rule = manager::get_rule($id);
    if (!$rule) {
        throw new moodle_exception('invalidrecord', 'error', $returnurl);
    }
}
$title = get_string($rule ? 'editrule' : 'addrule', 'local_cepv_cohortaccess');
$PAGE->navbar->add($title, $url);
$PAGE->set_title($title);

$form = new rule_form($url, ['rule' => $rule]);
if ($rule) {
    $form->set_data_from_rule($rule);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_rule_data()) {
    $saved = $data->id ? manager::update_rule($data) : manager::create_rule($data);
    foreach (manager::get_role_warnings($saved) as $warning) {
        \core\notification::warning($warning);
    }
    redirect(
        $returnurl,
        get_string('rulesaved', 'local_cepv_cohortaccess'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();
