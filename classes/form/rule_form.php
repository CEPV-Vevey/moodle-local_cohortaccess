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

namespace local_cohortaccess\form;

use context_system;
use core_course_category;
use local_cohortaccess\manager;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add / edit form for an access rule (cohort or user).
 *
 * Custom data: 'rule' => stdClass|null, the rule being edited.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends \moodleform {
    /** @var bool Whether the current user may search users (needed to pick a single user). */
    private bool $canpickuser = false;

    /**
     * Form definition.
     */
    protected function definition(): void {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // The core user selector searches with core_user_search_identity, which needs viewalldetails.
        $this->canpickuser = has_capability('moodle/user:viewalldetails', context_system::instance());
        if ($this->canpickuser) {
            $mform->addElement('select', 'beneficiarytype', get_string('beneficiarytype', 'local_cohortaccess'), [
                manager::BENEFICIARY_COHORT => get_string('cohort', 'local_cohortaccess'),
                manager::BENEFICIARY_USER => get_string('user', 'local_cohortaccess'),
            ]);
        } else {
            $mform->addElement('hidden', 'beneficiarytype');
            $mform->setType('beneficiarytype', PARAM_ALPHA);
        }
        $mform->setDefault('beneficiarytype', manager::BENEFICIARY_COHORT);

        $cohortelement = $mform->addElement('cohort', 'cohortid', get_string('cohort', 'local_cohortaccess'), [
            'contextid' => context_system::instance()->id,
            'includes' => 'all',
        ]);
        // The core element only pre-fills cohorts of the system context (or its parents):
        // add the edited rule's cohort explicitly so that category cohorts are shown too.
        if (!empty($this->_customdata['rule']->cohortid)) {
            $cohort = $DB->get_record('cohort', ['id' => $this->_customdata['rule']->cohortid]);
            if ($cohort) {
                $cohortelement->addOption(format_string($cohort->name, true, ['context' => $cohort->contextid]), $cohort->id);
            }
        }
        $mform->hideIf('cohortid', 'beneficiarytype', 'neq', manager::BENEFICIARY_COHORT);

        if ($this->canpickuser) {
            $mform->addElement('autocomplete', 'userid', get_string('user', 'local_cohortaccess'), [], [
                'ajax' => 'core_user/form_user_selector',
                'valuehtmlcallback' => function ($userid) {
                    global $DB;
                    $user = $DB->get_record('user', ['id' => (int) $userid, 'deleted' => 0]);
                    return $user ? s(fullname($user)) : false;
                },
            ]);
            $mform->setType('userid', PARAM_INT);
            $mform->hideIf('userid', 'beneficiarytype', 'neq', manager::BENEFICIARY_USER);
        } else if (!empty($this->_customdata['rule']->userid)) {
            // Without viewalldetails the user cannot be changed: show it read-only and keep it.
            $user = $DB->get_record('user', ['id' => $this->_customdata['rule']->userid, 'deleted' => 0]);
            $mform->addElement(
                'static',
                'userdisplay',
                get_string('user', 'local_cohortaccess'),
                $user ? s(fullname($user)) : get_string('usermissing', 'local_cohortaccess')
            );
            $mform->addElement('hidden', 'userid', $this->_customdata['rule']->userid);
            $mform->setType('userid', PARAM_INT);
        }

        $mform->addElement('select', 'targettype', get_string('targettype', 'local_cohortaccess'), [
            manager::TARGET_COURSE => get_string('targetcourse', 'local_cohortaccess'),
            manager::TARGET_CATEGORY => get_string('targetcategory', 'local_cohortaccess'),
        ]);
        $mform->setDefault('targettype', manager::TARGET_COURSE);

        $mform->addElement('course', 'courseid', get_string('targetcourse', 'local_cohortaccess'));
        $mform->hideIf('courseid', 'targettype', 'neq', manager::TARGET_COURSE);

        $mform->addElement(
            'autocomplete',
            'categoryid',
            get_string('targetcategory', 'local_cohortaccess'),
            ['' => ''] + core_course_category::make_categories_list()
        );
        $mform->hideIf('categoryid', 'targettype', 'neq', manager::TARGET_CATEGORY);

        // Roles usable in a course or category context; assignability by the current
        // user in the actual target context is checked in validation().
        $roleids = array_merge(get_roles_for_contextlevels(CONTEXT_COURSE), get_roles_for_contextlevels(CONTEXT_COURSECAT));
        if (!empty($this->_customdata['rule'])) {
            $roleids[] = $this->_customdata['rule']->roleid;
        }
        $roles = array_intersect_key(
            role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL, true),
            array_flip($roleids)
        );
        $mform->addElement('autocomplete', 'roleid', get_string('role', 'local_cohortaccess'), ['' => ''] + $roles);
        $mform->addRule('roleid', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_cohortaccess'));
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
        $data->beneficiarytype = empty($rule->userid) ? manager::BENEFICIARY_COHORT : manager::BENEFICIARY_USER;
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
     * Validation: beneficiary (cohort, or a non-deleted non-guest user) existing, target
     * required and existing, role existing and assignable by the current user in the
     * target context, no duplicate rule.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $rule = self::to_rule((object) $data);
        $isuser = ($data['beneficiarytype'] ?? '') === manager::BENEFICIARY_USER;
        $beneficiaryfield = $isuser ? 'userid' : 'cohortid';

        if ($isuser) {
            $user = $rule->userid ? $DB->get_record('user', ['id' => $rule->userid, 'deleted' => 0]) : false;
            $existing = $this->_customdata['rule'] ?? null;
            if (!$this->canpickuser && (!$existing || empty($existing->userid) || $existing->userid != $rule->userid)) {
                $errors['beneficiarytype'] = get_string('required');
            } else if (!$user || isguestuser($user)) {
                $errors['userid'] = get_string('required');
            }
        } else if (!$rule->cohortid || !$DB->record_exists('cohort', ['id' => $rule->cohortid])) {
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

        if (!$errors && !$this->is_role_allowed($rule)) {
            $errors['roleid'] = get_string('rolenotassignable', 'local_cohortaccess');
        }
        if (!$errors && manager::rule_exists($rule, $rule->id)) {
            $errors[$beneficiaryfield] = get_string('duplicaterule', 'local_cohortaccess');
        }
        return $errors;
    }

    /**
     * Whether the current user may give this role in the rule's target context.
     *
     * The existing role of an edited rule is kept even if it is no longer assignable,
     * as long as the target does not change.
     *
     * @param stdClass $rule Submitted rule fields.
     * @return bool
     */
    private function is_role_allowed(stdClass $rule): bool {
        $existing = $this->_customdata['rule'] ?? null;
        if (
            $existing && $existing->roleid == $rule->roleid && $existing->targettype === $rule->targettype
                && $existing->targetid == $rule->targetid
        ) {
            return true;
        }
        $context = manager::get_target_context($rule);
        return $context && array_key_exists($rule->roleid, get_assignable_roles($context, ROLENAME_ORIGINAL));
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
        $isuser = ($data->beneficiarytype ?? '') === manager::BENEFICIARY_USER;
        return (object) [
            'id' => (int) ($data->id ?? 0),
            'cohortid' => $isuser ? 0 : (int) ($data->cohortid ?? 0),
            'userid' => $isuser ? (int) ($data->userid ?? 0) : 0,
            'targettype' => $targettype,
            'targetid' => (int) ($data->{$targetfield} ?? 0),
            'roleid' => (int) ($data->roleid ?? 0),
            'enabled' => empty($data->enabled) ? 0 : 1,
        ];
    }
}
