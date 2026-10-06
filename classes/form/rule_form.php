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

use context_system;
use core_course_category;
use local_cepv_cohortaccess\manager;
use stdClass;

/**
 * Add / edit form for a cohort access rule.
 *
 * Custom data: 'rule' => stdClass|null, the rule being edited.
 *
 * @package    local_cepv_cohortaccess
 * @copyright  2026 CEPV
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('cohort', 'cohortid', get_string('cohort', 'local_cepv_cohortaccess'), [
            'contextid' => context_system::instance()->id,
            'includes' => 'all',
        ]);
        $mform->addRule('cohortid', null, 'required', null, 'client');

        $mform->addElement('select', 'targettype', get_string('targettype', 'local_cepv_cohortaccess'), [
            manager::TARGET_COURSE => get_string('targetcourse', 'local_cepv_cohortaccess'),
            manager::TARGET_CATEGORY => get_string('targetcategory', 'local_cepv_cohortaccess'),
        ]);
        $mform->setDefault('targettype', manager::TARGET_COURSE);

        $mform->addElement('course', 'courseid', get_string('targetcourse', 'local_cepv_cohortaccess'));
        $mform->hideIf('courseid', 'targettype', 'neq', manager::TARGET_COURSE);

        $mform->addElement(
            'autocomplete',
            'categoryid',
            get_string('targetcategory', 'local_cepv_cohortaccess'),
            ['' => ''] + core_course_category::make_categories_list()
        );
        $mform->hideIf('categoryid', 'targettype', 'neq', manager::TARGET_CATEGORY);

        $roles = role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL, true);
        $mform->addElement('autocomplete', 'roleid', get_string('role', 'local_cepv_cohortaccess'), ['' => ''] + $roles);
        $mform->addRule('roleid', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_cepv_cohortaccess'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons();
    }

    /**
     * Fill the form from an existing rule.
     *
     * @param stdClass $rule Rule.
     */
    public function set_data_from_rule(stdClass $rule): void {
        $data = clone $rule;
        if ($rule->targettype === manager::TARGET_CATEGORY) {
            $data->categoryid = $rule->targetid;
        } else {
            $data->courseid = $rule->targetid;
        }
        $this->set_data($data);
    }

    /**
     * Validated submitted data as rule fields.
     *
     * @return stdClass|null Rule fields (id is 0 for a new rule), or null when not submitted or invalid.
     */
    public function get_rule_data(): ?stdClass {
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        return self::to_rule($data);
    }

    /**
     * Validation: target required and existing, role and cohort existing, no duplicate rule.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $rule = self::to_rule((object) $data);

        if (!$rule->cohortid || !$DB->record_exists('cohort', ['id' => $rule->cohortid])) {
            $errors['cohortid'] = get_string('required');
        }
        if (!$rule->roleid || !$DB->record_exists('role', ['id' => $rule->roleid])) {
            $errors['roleid'] = get_string('required');
        }
        if ($rule->targettype === manager::TARGET_CATEGORY) {
            if (!$rule->targetid || !$DB->record_exists('course_categories', ['id' => $rule->targetid])) {
                $errors['categoryid'] = get_string('required');
            }
        } else if (
            !$rule->targetid || $rule->targetid == SITEID
                || !$DB->record_exists('course', ['id' => $rule->targetid])
        ) {
            $errors['courseid'] = get_string('required');
        }

        if (!$errors && manager::rule_exists($rule, $rule->id)) {
            $errors['cohortid'] = get_string('duplicaterule', 'local_cepv_cohortaccess');
        }
        return $errors;
    }

    /**
     * Map form values to rule fields.
     *
     * @param stdClass $data Form values.
     * @return stdClass
     */
    private static function to_rule(stdClass $data): stdClass {
        $targettype = ($data->targettype ?? '') === manager::TARGET_CATEGORY
            ? manager::TARGET_CATEGORY : manager::TARGET_COURSE;
        $targetfield = $targettype === manager::TARGET_CATEGORY ? 'categoryid' : 'courseid';
        return (object) [
            'id' => (int) ($data->id ?? 0),
            'cohortid' => (int) ($data->cohortid ?? 0),
            'targettype' => $targettype,
            'targetid' => (int) ($data->{$targetfield} ?? 0),
            'roleid' => (int) ($data->roleid ?? 0),
            'enabled' => empty($data->enabled) ? 0 : 1,
        ];
    }
}
