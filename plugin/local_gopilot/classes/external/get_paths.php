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
use local_gopilot\local\steps;

/**
 * local_gopilot_get_paths: learning paths of a course.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_paths extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Learning paths with steps of the current user.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $USER;
        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = context_course::instance($courseid);
        self::validate_context($context);
        if (!has_capability('local/gopilot:coach', $context)) {
            require_capability('local/gopilot:use', $context);
        }
        $course = get_course($courseid);
        return [
            'course' => [
                'id' => (int)$course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
            ],
            'paths' => (new steps())->paths($courseid, $USER->id),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => structures::course(),
            'paths' => new external_multiple_structure(new external_single_structure([
                'sectionid' => new external_value(PARAM_INT, 'Section id'),
                'section' => new external_value(PARAM_INT, 'Section number'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'summary' => new external_value(PARAM_TEXT, 'Summary as plain text'),
                'meta' => structures::meta(),
                'completed' => new external_value(PARAM_INT, 'Completed tracked steps'),
                'total' => new external_value(PARAM_INT, 'Tracked steps'),
                'steps' => new external_multiple_structure(structures::step()),
            ])),
        ]);
    }
}
