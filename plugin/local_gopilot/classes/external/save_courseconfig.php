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
use local_gopilot\local\courseconfig;

/**
 * local_gopilot_save_courseconfig: save teaching weeks and thresholds.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_courseconfig extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'teachingweeks' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
                'Teaching weeks',
                VALUE_DEFAULT,
                []
            ),
            'usedefaults' => new external_value(PARAM_BOOL, 'Use default thresholds', VALUE_DEFAULT, true),
            'thresholds' => structures::thresholds(false),
        ]);
    }

    /**
     * Save the configuration.
     *
     * @param int $courseid
     * @param string[] $teachingweeks
     * @param bool $usedefaults
     * @param array|null $thresholds
     * @return array like get_courseconfig
     */
    public static function execute(
        int $courseid,
        array $teachingweeks = [],
        bool $usedefaults = true,
        ?array $thresholds = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'teachingweeks' => $teachingweeks,
            'usedefaults' => $usedefaults,
            'thresholds' => $thresholds ?? [],
        ]);
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/gopilot:configure', $context);
        courseconfig::save(
            $params['courseid'],
            $params['teachingweeks'],
            $params['usedefaults'],
            $params['thresholds'] ?? []
        );
        return get_courseconfig::build($params['courseid']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_courseconfig::execute_returns();
    }
}
