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

namespace local_usertitles\external;

use local_usertitles\manager;

/**
 * Tests for the visual title external service.
 *
 * @package   local_usertitles
 * @copyright 2026 Richard Rangel
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_usertitles\external\get_user_titles
 */
final class get_user_titles_test extends \advanced_testcase {
    /**
     * Tests that the service returns only assigned titles.
     *
     * @return void
     */
    public function test_execute_returns_assigned_titles(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $titleduser = $this->getDataGenerator()->create_user([
            'firstname' => 'Richard',
            'lastname' => 'Rangel',
        ]);
        $plainuser = $this->getDataGenerator()->create_user();
        $title = manager::create_title((object) [
            'name' => 'Visual Professor',
            'abbreviation' => 'Visual Prof.',
            'enabled' => 1,
            'sortorder' => 10,
        ]);
        manager::set_user_title((int) $titleduser->id, (int) $title->id);

        $result = get_user_titles::execute([
            (int) $titleduser->id,
            (int) $plainuser->id,
        ]);

        $this->assertSame([
            [
                'userid' => (int) $titleduser->id,
                'abbreviation' => 'Visual Prof.',
                'fullname' => fullname($titleduser),
            ],
        ], $result);
    }


    /**
     * Tests that arbitrary user ids do not expose titles when profiles are not visible.
     *
     * @return void
     */
    public function test_execute_filters_users_without_profile_visibility(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->forceloginforprofiles = 1;

        $viewer = $this->getDataGenerator()->create_user();
        $target = $this->getDataGenerator()->create_user();
        $title = manager::create_title((object) [
            'name' => 'Private Professor',
            'abbreviation' => 'Private Prof.',
            'enabled' => 1,
            'sortorder' => 20,
        ]);
        manager::set_user_title((int) $target->id, (int) $title->id);

        $this->setUser($viewer);
        $result = get_user_titles::execute([(int) $target->id]);

        $this->assertSame([], $result);
    }

    /**
     * Tests that a user can still retrieve their own assigned title.
     *
     * @return void
     */
    public function test_execute_allows_current_user_profile(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->forceloginforprofiles = 1;

        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Self',
            'lastname' => 'Visible',
        ]);
        $title = manager::create_title((object) [
            'name' => 'Self Professor',
            'abbreviation' => 'Self Prof.',
            'enabled' => 1,
            'sortorder' => 30,
        ]);
        manager::set_user_title((int) $user->id, (int) $title->id);

        $this->setUser($user);
        $result = get_user_titles::execute([(int) $user->id]);

        $this->assertSame([
            [
                'userid' => (int) $user->id,
                'abbreviation' => 'Self Prof.',
                'fullname' => fullname($user),
            ],
        ], $result);
    }
}
