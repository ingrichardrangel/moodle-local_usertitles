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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns title abbreviations for visual display.
 *
 * @package   local_usertitles
 * @copyright 2026 Richard Rangel
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_titles extends external_api {
    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Moodle user id')
            ),
        ]);
    }

    /**
     * Returns assigned title abbreviations for users whose profiles the caller may view.
     *
     * @param array $userids Moodle user identifiers.
     * @return array
     */
    public static function execute(array $userids): array {
        global $CFG, $DB;

        ['userids' => $userids] = self::validate_parameters(
            self::execute_parameters(),
            ['userids' => $userids]
        );

        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$userids) {
            return [];
        }
        if (count($userids) > 200) {
            throw new \invalid_parameter_exception('A maximum of 200 user ids may be requested.');
        }

        require_once($CFG->dirroot . '/user/lib.php');

        // Authorize every requested target user before reading title assignments.
        [$userinsql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'targetuserid');
        $usersql = "SELECT u.id, u.deleted,
                           u.firstname, u.lastname, u.firstnamephonetic,
                           u.lastnamephonetic, u.middlename, u.alternatename
                      FROM {user} u
                     WHERE u.id {$userinsql}
                       AND u.deleted = 0";

        $visibleusers = [];
        foreach ($DB->get_records_sql($usersql, $userparams) as $user) {
            $usercontext = \context_user::instance((int) $user->id);
            self::validate_context($usercontext);
            if (!self::can_view_profile($user, $usercontext)) {
                continue;
            }
            $visibleusers[(int) $user->id] = $user;
        }

        if (!$visibleusers) {
            return [];
        }

        [$titleinsql, $titleparams] = $DB->get_in_or_equal(
            array_keys($visibleusers),
            SQL_PARAMS_NAMED,
            'visibleuserid'
        );
        $titlesql = "SELECT a.userid, t.abbreviation
                       FROM {local_usertitles_assignment} a
                       JOIN {local_usertitles_title} t ON t.id = a.titleid
                      WHERE a.userid {$titleinsql}";

        $result = [];
        foreach ($DB->get_records_sql($titlesql, $titleparams) as $record) {
            $user = $visibleusers[(int) $record->userid];
            $result[] = [
                'userid' => (int) $record->userid,
                'abbreviation' => (string) $record->abbreviation,
                'fullname' => fullname($user),
            ];
        }
        return $result;
    }

    /**
     * Checks whether the current user may view a target user's profile.
     *
     * Newer Moodle versions expose the check as \core\user::can_view_profile(). Moodle 4.5
     * through 5.2 provide the equivalent user_can_view_profile() function.
     *
     * @param \stdClass $user Target user record.
     * @param \context_user $usercontext Target user's context.
     * @return bool
     */
    private static function can_view_profile(\stdClass $user, \context_user $usercontext): bool {
        if (method_exists(\core\user::class, 'can_view_profile')) {
            return \core\user::can_view_profile($user, null, $usercontext);
        }

        return user_can_view_profile($user, null, $usercontext);
    }

    /**
     * Describes the returned data.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Moodle user id'),
                'abbreviation' => new external_value(PARAM_TEXT, 'Assigned title abbreviation'),
                'fullname' => new external_value(PARAM_TEXT, 'Moodle formatted full name'),
            ])
        );
    }
}
