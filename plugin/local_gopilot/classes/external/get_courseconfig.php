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
use local_gopilot\local\steps;

/**
 * local_gopilot_get_courseconfig: teaching weeks, thresholds and path metadata of a course.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_courseconfig extends external_api {
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
     * Get the configuration.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/gopilot:configure', $context);
        return self::build($courseid);
    }

    /**
     * Build the result structure (also used by save_courseconfig).
     *
     * @param int $courseid
     * @return array
     */
    public static function build(int $courseid): array {
        global $USER;
        $config = new courseconfig($courseid);
        $steps = new steps();
        $modinfo = get_fast_modinfo($courseid, $USER->id);
        $course = $modinfo->get_course();
        $paths = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0) {
                continue;
            }
            $pathsteps = [];
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->modname === 'label' || !$cm->has_view()) {
                    continue;
                }
                $pathsteps[] = [
                    'cmid' => (int)$cm->id,
                    'name' => $cm->get_formatted_name(),
                    'modname' => $cm->modname,
                    'meta' => $steps->meta($courseid, 'cm', (int)$cm->id),
                ];
            }
            $paths[] = [
                'sectionid' => (int)$section->id,
                'name' => get_section_name($course, $section),
                'meta' => $steps->meta($courseid, 'section', (int)$section->id),
                'steps' => $pathsteps,
            ];
        }
        return [
            'courseid' => $courseid,
            'usedefaults' => $config->uses_defaults(),
            'thresholds' => $config->get_thresholds(),
            'defaults' => courseconfig::DEFAULTS,
            'paths' => $paths,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'usedefaults' => new external_value(PARAM_BOOL, 'Default thresholds are used'),
            'thresholds' => structures::thresholds(),
            'defaults' => structures::thresholds(),
            'paths' => new external_multiple_structure(new external_single_structure([
                'sectionid' => new external_value(PARAM_INT, 'Section id'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'meta' => structures::meta(),
                'steps' => new external_multiple_structure(new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                    'modname' => new external_value(PARAM_PLUGIN, 'Module name'),
                    'meta' => structures::meta(),
                ])),
            ])),
        ]);
    }
}
