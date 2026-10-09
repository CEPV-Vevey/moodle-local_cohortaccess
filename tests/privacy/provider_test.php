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

namespace local_cohortaccess\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_cohortaccess\manager;
use stdClass;

/**
 * Tests for the privacy provider.
 *
 * @package    local_cohortaccess
 * @copyright  2026 CEPV, Yann Rapenne
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var int Role id. */
    private int $roleid;

    /** @var stdClass Course. */
    private stdClass $course;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->roleid = create_role('courseviewer', 'courseviewer', '');
        $this->course = $this->getDataGenerator()->create_course(['fullname' => 'Physics']);
    }

    /**
     * Create a rule for a user, or for a new cohort when no user is given.
     *
     * @param stdClass|null $user Beneficiary.
     * @return stdClass Rule.
     */
    private function create_rule(?stdClass $user = null): stdClass {
        return manager::create_rule((object) [
            'userid' => $user->id ?? 0,
            'cohortid' => $user ? 0 : $this->getDataGenerator()->create_cohort()->id,
            'targettype' => manager::TARGET_COURSE,
            'targetid' => $this->course->id,
            'roleid' => $this->roleid,
            'enabled' => 1,
        ]);
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('local_cohortaccess'));
        $items = $collection->get_collection();
        $this->assertCount(2, $items);
        $this->assertEquals('local_cohortaccess', $items[0]->get_name());
        $this->assertArrayHasKey('userid', $items[0]->get_privacy_fields());
        $this->assertEquals('core_role', $items[1]->get_name());
    }

    public function test_contexts_and_users(): void {
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_rule($user);
        $this->create_rule();

        $this->assertEquals([context_system::instance()->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($other->id)->get_contextids());

        $userlist = new userlist(context_system::instance(), 'local_cohortaccess');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user->id], $userlist->get_userids());

        $userlist = new userlist(\context_course::instance($this->course->id), 'local_cohortaccess');
        provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->create_rule($user);
        $context = context_system::instance();

        $this->export_context_data_for_user($user->id, $context, 'local_cohortaccess');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('pluginname', 'local_cohortaccess')]);
        $this->assertCount(1, $data->rules);
        $this->assertEquals('Physics', $data->rules[0]->target);
        $this->assertEquals('courseviewer', $data->rules[0]->role);
    }

    public function test_delete_data_for_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $rule = $this->create_rule($user);
        $otherrule = $this->create_rule($other);

        $contextlist = new approved_contextlist($user, 'local_cohortaccess', [context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse(manager::get_rule($rule->id));
        $this->assertEquals(0, manager::count_assignments($rule->id));
        $this->assertNotEmpty(manager::get_rule($otherrule->id));
    }

    public function test_delete_data_for_users(): void {
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $rule = $this->create_rule($user);
        $otherrule = $this->create_rule($other);

        $userlist = new approved_userlist(context_system::instance(), 'local_cohortaccess', [$user->id]);
        provider::delete_data_for_users($userlist);
        $this->assertFalse(manager::get_rule($rule->id));
        $this->assertNotEmpty(manager::get_rule($otherrule->id));

        $coursecontext = \context_course::instance($this->course->id);
        $userlist = new approved_userlist($coursecontext, 'local_cohortaccess', [$other->id]);
        provider::delete_data_for_users($userlist);
        $this->assertNotEmpty(manager::get_rule($otherrule->id));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        $user = $this->getDataGenerator()->create_user();
        $rule = $this->create_rule($user);
        $cohortrule = $this->create_rule();

        provider::delete_data_for_all_users_in_context(\context_course::instance($this->course->id));
        $this->assertNotEmpty(manager::get_rule($rule->id));

        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertFalse(manager::get_rule($rule->id));
        $this->assertNotEmpty(manager::get_rule($cohortrule->id));
    }
}
