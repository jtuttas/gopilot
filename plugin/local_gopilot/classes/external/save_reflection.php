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

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_gopilot\local\planner;
use local_gopilot\local\weekdata;

/**
 * local_gopilot_save_reflection: general reflection, course reflections and self-assessments.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_reflection extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'week' => new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
            'generalreflection' => new external_value(PARAM_TEXT, 'General reflection', VALUE_DEFAULT, ''),
            'courses' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'reflection' => new external_value(PARAM_TEXT, 'Course reflection', VALUE_DEFAULT, ''),
                'selfassessments' => new external_multiple_structure(new external_single_structure([
                    'sectionid' => new external_value(PARAM_INT, 'Section id'),
                    'level' => new external_value(PARAM_INT, '0 not given, 1 confident ... 4 lost'),
                    'comment' => new external_value(PARAM_TEXT, 'Comment', VALUE_DEFAULT, ''),
                ]), 'Self-assessments', VALUE_DEFAULT, []),
            ]), 'Course parts', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Save the reflection.
     *
     * @param string $week
     * @param string $generalreflection
     * @param array $courses
     * @return array week
     */
    public static function execute(string $week, string $generalreflection = '', array $courses = []): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['week' => $week, 'generalreflection' => $generalreflection, 'courses' => $courses]
        );
        self::validate_context(context_user::instance($USER->id));
        $now = time();
        (new planner($now))->save_reflection($USER->id, $params['week'], $params['generalreflection'], $params['courses']);
        return (new weekdata($now))->week($USER->id, $params['week']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return structures::week();
    }
}
