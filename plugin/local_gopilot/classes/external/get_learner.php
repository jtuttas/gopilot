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

namespace local_gopilot\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_gopilot\ampel\calculator;
use local_gopilot\local\access;
use local_gopilot\local\weekdata;

/**
 * local_gopilot_get_learner: weeks of one learner in a course (coach view).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_learner extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'userid' => new external_value(PARAM_INT, 'Learner user id'),
        ]);
    }

    /**
     * Learner detail.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function execute(int $courseid, int $userid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'userid' => $userid]);
        $courseid = $params['courseid'];
        $userid = $params['userid'];
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/gopilot:coach', $context);
        access::require_learner($courseid, $userid);
        if (!access::can_read_course($courseid, $USER->id, $userid)) {
            throw new \required_capability_exception($context, 'local/gopilot:coach', 'nopermissions', '');
        }

        $now = time();
        $data = new weekdata($now);
        $weeks = [];
        foreach (weekdata::weeks_with_data($userid, $courseid) as $record) {
            $weeks[] = $data->learnerweek($record, $courseid);
        }
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        return [
            'learner' => ['id' => (int)$user->id, 'fullname' => fullname($user)],
            'ampel' => (new calculator($now, $data->steps()))->compute_and_store($userid, $courseid),
            'weeks' => $weeks,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'learner' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
            ]),
            'ampel' => structures::ampel(),
            'weeks' => new external_multiple_structure(structures::learnerweek()),
        ]);
    }
}
