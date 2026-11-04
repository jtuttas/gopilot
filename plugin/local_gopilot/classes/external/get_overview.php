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
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_gopilot\ampel\calculator;
use local_gopilot\local\access;
use local_gopilot\local\weekdata;
use local_gopilot\local\weeks;

/**
 * local_gopilot_get_overview: start page of the app.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_overview extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Courses, current week and own Ampels.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), []);
        self::validate_context(context_system::instance());

        $now = time();
        $data = new weekdata($now);
        $calculator = new calculator($now, $data->steps());
        $current = weeks::current($now);
        $courses = [];
        foreach (access::enrolled_courses($USER->id) as $course) {
            $context = context_course::instance($course->id);
            $canuse = access::is_learner($course->id, $USER->id);
            $cancoach = has_capability('local/gopilot:coach', $context);
            $canconfigure = has_capability('local/gopilot:configure', $context);
            if (!$canuse && !$cancoach && !$canconfigure) {
                continue;
            }
            $courses[] = [
                'id' => (int)$course->id,
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'canuse' => $canuse,
                'cancoach' => $cancoach,
                'canconfigure' => $canconfigure,
                'progress' => $data->steps()->progress($course->id, $USER->id),
                'ampel' => $canuse ? $calculator->compute_and_store($USER->id, $course->id)
                    : calculator::empty_result($course->id),
            ];
        }
        return [
            'userid' => (int)$USER->id,
            'fullname' => fullname($USER),
            'currentweek' => $current,
            'courses' => $courses,
            'week' => $data->week($USER->id, $current),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name'),
            'currentweek' => new external_value(PARAM_RAW, 'Monday of the current week'),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course id'),
                'shortname' => new external_value(PARAM_TEXT, 'Short name'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'canuse' => new external_value(PARAM_BOOL, 'Learner in this course'),
                'cancoach' => new external_value(PARAM_BOOL, 'Learning coach in this course'),
                'canconfigure' => new external_value(PARAM_BOOL, 'May configure the course'),
                'progress' => structures::progress(),
                'ampel' => structures::ampel(),
            ])),
            'week' => structures::week(),
        ]);
    }
}
