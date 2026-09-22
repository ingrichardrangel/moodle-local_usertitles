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

namespace local_usertitles\event;

use local_usertitles\manager;

/**
 * Tests for User titles audit events.
 *
 * @package   local_usertitles
 * @copyright 2026 Richard Rangel
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_usertitles\event\title_created
 * @covers    \local_usertitles\event\title_deleted
 * @covers    \local_usertitles\event\title_updated
 * @covers    \local_usertitles\event\user_title_updated
 */
final class events_test extends \advanced_testcase {
    /**
     * Tests the title lifecycle events.
     *
     * @return void
     */
    public function test_title_lifecycle_events(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $sink = $this->redirectEvents();
        $title = manager::create_title((object) [
            'name' => 'Event Professor',
            'abbreviation' => 'Event Prof.',
            'enabled' => 1,
            'sortorder' => 10,
        ]);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(title_created::class, $events[0]);
        $this->assertSame((int) $title->id, (int) $events[0]->objectid);

        $sink->clear();
        $title->name = 'Updated Event Professor';
        manager::update_title($title);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(title_updated::class, $events[0]);
        $this->assertSame((int) $title->id, (int) $events[0]->objectid);

        $sink->clear();
        manager::delete_title((int) $title->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(title_deleted::class, $events[0]);
        $this->assertSame((int) $title->id, (int) $events[0]->objectid);
        $sink->close();
    }

    /**
     * Tests assignment and removal audit events.
     *
     * @return void
     */
    public function test_user_title_updated_event(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $title = manager::create_title((object) [
            'name' => 'Assigned Professor',
            'abbreviation' => 'Assigned Prof.',
            'enabled' => 1,
            'sortorder' => 10,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEvents();
        $assignment = manager::set_user_title((int) $user->id, (int) $title->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(user_title_updated::class, $events[0]);
        $this->assertSame((int) $assignment->id, (int) $events[0]->objectid);
        $this->assertSame((int) $user->id, (int) $events[0]->relateduserid);
        $this->assertSame((int) $title->id, (int) $events[0]->other['titleid']);

        $sink->clear();
        manager::set_user_title((int) $user->id, 0);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(user_title_updated::class, $events[0]);
        $this->assertSame((int) $user->id, (int) $events[0]->relateduserid);
        $this->assertSame(0, (int) $events[0]->other['titleid']);
        $sink->close();
    }
}
