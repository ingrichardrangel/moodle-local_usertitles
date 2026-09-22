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

use context;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_usertitles\manager;

/**
 * Privacy provider for title assignments.
 *
 * @package   local_usertitles
 * @copyright 2026 Richard Rangel
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    core_userlist_provider,
    plugin_provider {
    /**
     * Describes stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_usertitles_assignment',
            [
                'userid' => 'privacy:metadata:assignment:userid',
                'titleid' => 'privacy:metadata:assignment:titleid',
                'syncedvalue' => 'privacy:metadata:assignment:syncedvalue',
            ],
            'privacy:metadata:assignment'
        );
        return $collection;
    }

    /**
     * Returns contexts containing data for a user.
     *
     * @param int $userid User identifier.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_usertitles_assignment} a ON a.userid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND a.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Exports a user's title assignment.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_user || (int) $context->instanceid !== $userid) {
                continue;
            }

            $sql = "SELECT a.timecreated, a.timemodified, a.syncedvalue,
                           t.name, t.abbreviation, t.enabled
                      FROM {local_usertitles_assignment} a
                      JOIN {local_usertitles_title} t ON t.id = a.titleid
                     WHERE a.userid = :userid";
            $record = $DB->get_record_sql($sql, ['userid' => $userid]);
            if (!$record) {
                continue;
            }

            $data = (object) [
                'title' => $record->name,
                'abbreviation' => $record->abbreviation,
                'active' => transform::yesno((bool) $record->enabled),
                'synchronized_value' => $record->syncedvalue,
                'assigned_at' => transform::datetime($record->timecreated),
                'modified_at' => transform::datetime($record->timemodified),
            ];
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**
     * Deletes title data for every user in a context.
     *
     * @param context $context Context to clear.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context instanceof context_user) {
            manager::delete_user_assignment((int) $context->instanceid);
        }
    }

    /**
     * Deletes title data for one user.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int) $context->instanceid === $userid) {
                manager::delete_user_assignment($userid);
                return;
            }
        }
    }

    /**
     * Adds users whose data is present in a context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        $userid = (int) $context->instanceid;
        if ($DB->record_exists('local_usertitles_assignment', ['userid' => $userid])) {
            $userlist->add_user($userid);
        }
    }

    /**
     * Deletes data for an approved set of users.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_user) {
            return;
        }

        $userid = (int) $context->instanceid;
        if (in_array($userid, $userlist->get_userids(), true)) {
            manager::delete_user_assignment($userid);
        }
    }
}
