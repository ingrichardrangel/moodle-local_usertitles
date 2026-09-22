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

namespace local_usertitles;

/**
 * Tests for the title manager.
 *
 * @package   local_usertitles
 * @copyright 2026 Richard Rangel
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_usertitles\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Creates a title for use in tests.
     *
     * @param string $name Title name.
     * @param string $abbreviation Title abbreviation.
     * @param int $sortorder Sort order.
     * @param bool $enabled Whether the title is enabled.
     * @return \stdClass
     */
    private function create_title(
        string $name = 'Associate Professor',
        string $abbreviation = 'Assoc. Prof.',
        int $sortorder = 20,
        bool $enabled = true
    ): \stdClass {
        return manager::create_title((object) [
            'name' => $name,
            'abbreviation' => $abbreviation,
            'enabled' => $enabled ? 1 : 0,
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Tests title creation, assignment, and name formatting.
     *
     * @return void
     */
    public function test_create_assign_and_format_title(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Jane',
            'lastname' => 'Smith',
        ]);

        manager::set_user_title((int) $user->id, (int) $title->id);

        $this->assertSame('Assoc. Prof. Jane Smith', manager::format_name((int) $user->id));
        $this->assertSame('Jane Smith', manager::format_name((int) $user->id, false));
    }

    /**
     * Tests title ordering and filtering of disabled titles.
     *
     * @return void
     */
    public function test_get_titles_orders_and_filters_disabled_titles(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $DB->delete_records('local_usertitles_title');

        $third = $this->create_title('Third title', 'Third', 30);
        $first = $this->create_title('First title', 'First', 10);
        $disabled = $this->create_title('Disabled title', 'Disabled', 20, false);

        $alltitles = array_values(manager::get_titles());
        $this->assertSame(
            [(int) $first->id, (int) $disabled->id, (int) $third->id],
            array_map(static fn($title): int => (int) $title->id, $alltitles)
        );

        $enabledtitles = array_values(manager::get_titles(false));
        $this->assertSame(
            [(int) $first->id, (int) $third->id],
            array_map(static fn($title): int => (int) $title->id, $enabledtitles)
        );
    }

    /**
     * Tests that duplicate abbreviations are rejected.
     *
     * @return void
     */
    public function test_duplicate_abbreviation_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_title('Professor A', 'Dup.');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorabbreviationexists', 'local_usertitles'));
        $this->create_title('Professor B', 'Dup.');
    }

    /**
     * Tests that new assignments cannot use disabled titles.
     *
     * @return void
     */
    public function test_disabled_title_cannot_be_newly_assigned(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title('Inactive Professor', 'Inactive Prof.', 20, false);
        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errorinvalidtitle', 'local_usertitles'));
        manager::set_user_title((int) $user->id, (int) $title->id);
    }

    /**
     * Tests that an existing assignment may remain when its title is disabled.
     *
     * @return void
     */
    public function test_existing_disabled_title_can_be_kept(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title('Retained Professor', 'Ret. Prof.');
        $user = $this->getDataGenerator()->create_user();
        manager::set_user_title((int) $user->id, (int) $title->id);

        $title->enabled = 0;
        manager::update_title($title);
        $assignment = manager::set_user_title((int) $user->id, (int) $title->id);

        $this->assertNotNull($assignment);
        $this->assertSame((int) $title->id, (int) $assignment->titleid);
    }

    /**
     * Tests removing an existing title assignment.
     *
     * @return void
     */
    public function test_remove_assignment(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title();
        $user = $this->getDataGenerator()->create_user();
        manager::set_user_title((int) $user->id, (int) $title->id);

        $this->assertNull(manager::set_user_title((int) $user->id, 0));
        $this->assertFalse(manager::get_user_assignment((int) $user->id));
    }

    /**
     * Tests that title assignment never changes a blank alternate name.
     *
     * @return void
     */
    public function test_assignment_preserves_blank_alternate_name(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('syncalternatename', 1, 'local_usertitles');

        $title = $this->create_title('Test Professor', 'Test Prof.', 30);
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Alex',
            'lastname' => 'Taylor',
            'alternatename' => '',
        ]);

        manager::set_user_title((int) $user->id, (int) $title->id);
        $this->assertSame('', $DB->get_field('user', 'alternatename', ['id' => $user->id]));

        manager::set_user_title((int) $user->id, 0);
        $this->assertSame('', $DB->get_field('user', 'alternatename', ['id' => $user->id]));
    }

    /**
     * Tests that title assignment preserves an independent alternate name.
     *
     * @return void
     */
    public function test_assignment_preserves_existing_alternate_name(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('syncalternatename', 1, 'local_usertitles');

        $title = $this->create_title('Visiting Professor', 'Vis. Prof.', 40);
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Morgan',
            'lastname' => 'Lee',
            'alternatename' => 'Mo',
        ]);

        manager::set_user_title((int) $user->id, (int) $title->id);

        $this->assertSame('Mo', $DB->get_field('user', 'alternatename', ['id' => $user->id]));
        $assignment = manager::get_user_assignment((int) $user->id);
        $this->assertNull($assignment->syncedvalue);
    }

    /**
     * Tests the synchronization status lifecycle.
     *
     * @return void
     */
    public function test_sync_user_statuses(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title('Synchronized Professor', 'Sync Prof.');
        $user = $this->getDataGenerator()->create_user(['alternatename' => '']);
        manager::set_user_title((int) $user->id, (int) $title->id);

        $this->assertSame(manager::SYNC_UPDATED, manager::sync_user((int) $user->id, true));
        $this->assertSame('Sync Prof.', $DB->get_field('user', 'alternatename', ['id' => $user->id]));
        $this->assertSame(manager::SYNC_UNCHANGED, manager::sync_user((int) $user->id, true));

        $DB->set_field('user', 'alternatename', 'Independent value', ['id' => $user->id]);
        $this->assertSame(manager::SYNC_CONFLICT, manager::sync_user((int) $user->id, true));

        $assignment = manager::get_user_assignment((int) $user->id);
        $this->assertNull($assignment->syncedvalue);
    }

    /**
     * Tests synchronization when no assignment exists.
     *
     * @return void
     */
    public function test_sync_user_returns_missing_without_assignment(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        $this->assertSame(manager::SYNC_MISSING, manager::sync_user((int) $user->id, true));
    }

    /**
     * Tests that clearing synchronization removes only the tracked value.
     *
     * @return void
     */
    public function test_clear_user_sync_removes_tracked_alternate_name(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title('Clear Professor', 'Clear Prof.');
        $user = $this->getDataGenerator()->create_user(['alternatename' => '']);
        manager::set_user_title((int) $user->id, (int) $title->id);
        manager::sync_user((int) $user->id, true);

        $this->assertTrue(manager::clear_user_sync((int) $user->id));
        $this->assertSame('', $DB->get_field('user', 'alternatename', ['id' => $user->id]));
        $assignment = manager::get_user_assignment((int) $user->id);
        $this->assertNull($assignment->syncedvalue);
    }

    /**
     * Tests that deleting a title removes its assignments.
     *
     * @return void
     */
    public function test_delete_title_removes_assignments(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $title = $this->create_title('Guest Professor', 'Guest Prof.', 50);
        $user = $this->getDataGenerator()->create_user();
        manager::set_user_title((int) $user->id, (int) $title->id);

        manager::delete_title((int) $title->id);

        $this->assertFalse($DB->record_exists('local_usertitles_title', ['id' => $title->id]));
        $this->assertFalse($DB->record_exists('local_usertitles_assignment', ['userid' => $user->id]));
    }
}
