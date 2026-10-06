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

use html_table;
use html_writer;
use local_cohortaccess\manager;
use moodle_url;
use pix_icon;
use stdClass;

/**
 * Table listing the rules, laid out like Moodle core management tables.
 *
 * Enabled rules show an open eye (click to disable), disabled rules are dimmed and
 * show a slashed eye (click to enable). Actions are core icons.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules_table {
    /**
     * Build the table.
     *
     * @param stdClass[] $rules Rules to list.
     * @param moodle_url $baseurl URL of the rules list page, which handles the actions.
     * @return html_table
     */
    public static function build(array $rules, moodle_url $baseurl): html_table {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable admintable';
        $table->head = [
            get_string('cohort', 'local_cohortaccess'),
            get_string('target', 'local_cohortaccess'),
            get_string('role', 'local_cohortaccess'),
            get_string('members', 'local_cohortaccess'),
            get_string('enable'),
            get_string('actions', 'local_cohortaccess'),
        ];
        $table->colclasses = ['', '', '', '', 'text-center', 'text-nowrap'];
        foreach ($rules as $rule) {
            $table->data[] = self::row($rule, $baseurl);
            $table->rowclasses[] = $rule->enabled ? '' : 'dimmed_text';
        }
        return $table;
    }

    /**
     * Cells of one rule.
     *
     * @param stdClass $rule Rule.
     * @param moodle_url $baseurl URL of the rules list page.
     * @return string[]
     */
    private static function row(stdClass $rule, moodle_url $baseurl): array {
        global $OUTPUT;
        $desc = manager::describe_rule($rule);

        if ($desc->targeturl) {
            $target = html_writer::link($desc->targeturl, $desc->target);
        } else {
            $target = $desc->target . ' ' . self::warning($desc->target);
        }

        $role = $desc->role;
        if ($warnings = manager::get_role_warnings($rule)) {
            $role .= ' ' . self::warning(implode(' ', $warnings));
        }

        $members = manager::count_members($rule->cohortid) . html_writer::div(
            get_string('activeassignments', 'local_cohortaccess', manager::count_assignments($rule->id)),
            'small text-muted'
        );

        $actionurl = fn(string $action): moodle_url => new moodle_url(
            $baseurl,
            ['action' => $action, 'id' => $rule->id, 'sesskey' => sesskey()]
        );
        if ($rule->enabled) {
            $toggle = $OUTPUT->action_icon($actionurl('disable'), new pix_icon('t/hide', get_string('disable')));
        } else {
            $toggle = $OUTPUT->action_icon($actionurl('enable'), new pix_icon('t/show', get_string('enable')));
        }

        $actions = $OUTPUT->action_icon(
            $actionurl('sync'),
            new pix_icon('i/reload', get_string('sync', 'local_cohortaccess'))
        ) . $OUTPUT->action_icon(
            new moodle_url('/local/cohortaccess/edit.php', ['id' => $rule->id]),
            new pix_icon('t/edit', get_string('edit'))
        ) . $OUTPUT->action_icon(
            new moodle_url($baseurl, ['action' => 'delete', 'id' => $rule->id]),
            new pix_icon('t/delete', get_string('delete'))
        );

        return [$desc->cohort, $target, $role, $members, $toggle, $actions];
    }

    /**
     * Warning icon whose tooltip explains the problem.
     *
     * @param string $message Warning text.
     * @return string HTML.
     */
    private static function warning(string $message): string {
        global $OUTPUT;
        return $OUTPUT->pix_icon('i/warning', $message, 'moodle', ['class' => 'text-warning']);
    }
}
