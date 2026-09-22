<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_usertitles\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_usertitles\manager;

/**
 * Tests for the User titles privacy provider.
 *
 * @package   local_usertitles
 * @copyright 2026 Richard Rangel
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_usertitles\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Creates an assigned title for a user.
     *
     * @param \stdClass $user User record.
     * @return \stdClass Created title.
     */
    private function create_assignment(\stdClass $user): \stdClass {
        $title = manager::create_title((object) [
            'name' => 'Privacy Professor',
            'abbreviation' => 'Privacy Prof.',
            'enabled' => 1,
            'sortorder' => 10,
        ]);
        manager::set_user_title((int) $user->id, (int) $title->id);
        return $title;
    }

    /**
     * Tests user-context discovery.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $user->id)->get_contextids());

        $this->create_assignment($user);
        $context = \context_user::instance((int) $user->id);
        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertSame([(int) $context->id], array_map('intval', $contextlist->get_contextids()));
    }

    /**
     * Tests exporting an assignment through the Privacy API.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        writer::reset();

        $user = $this->getDataGenerator()->create_user();
        $this->create_assignment($user);
        $context = \context_user::instance((int) $user->id);
        $approvedlist = new approved_contextlist($user, 'local_usertitles', [$context->id]);

        provider::export_user_data($approvedlist);
        $data = writer::with_context($context)->get_data([]);

        $this->assertSame('Privacy Professor', $data['title']);
        $this->assertSame('Privacy Prof.', $data['abbreviation']);
        $this->assertSame(transform::yesno(true), $data['active']);
        $this->assertNull($data['synchronized_value']);
    }

    /**
     * Tests deleting data for all users in one user context.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $title = $this->create_assignment($user1);
        manager::set_user_title((int) $user2->id, (int) $title->id);

        provider::delete_data_for_all_users_in_context(\context_user::instance((int) $user1->id));

        $this->assertFalse($DB->record_exists('local_usertitles_assignment', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('local_usertitles_assignment', ['userid' => $user2->id]));
    }

    /**
     * Tests deleting data for an approved user context list.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $this->create_assignment($user);
        $context = \context_user::instance((int) $user->id);
        $approvedlist = new approved_contextlist($user, 'local_usertitles', [$context->id]);

        provider::delete_data_for_user($approvedlist);

        $this->assertFalse($DB->record_exists('local_usertitles_assignment', ['userid' => $user->id]));
    }

    /**
     * Tests user-list discovery and approved multi-user deletion.
     *
     * @return void
     */
    public function test_get_users_in_context_and_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $this->create_assignment($user);
        $context = \context_user::instance((int) $user->id);

        $userlist = new userlist($context, 'local_usertitles');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $user->id], array_map('intval', $userlist->get_userids()));

        $approvedlist = new approved_userlist($context, 'local_usertitles', $userlist->get_userids());
        provider::delete_data_for_users($approvedlist);

        $this->assertFalse($DB->record_exists('local_usertitles_assignment', ['userid' => $user->id]));
    }

    /**
     * Tests that unrelated contexts do not delete user assignments.
     *
     * @return void
     */
    public function test_system_context_does_not_delete_assignment(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $this->create_assignment($user);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertTrue($DB->record_exists('local_usertitles_assignment', ['userid' => $user->id]));
    }
}
