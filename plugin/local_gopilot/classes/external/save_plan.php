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
 * local_gopilot_save_plan: weekly goal and planned steps.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_plan extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'week' => new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
            'goal' => new external_value(PARAM_TEXT, 'Weekly goal', VALUE_DEFAULT, ''),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id'),
                'Planned steps',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Replace the plan of a week.
     *
     * @param string $week
     * @param string $goal
     * @param int[] $cmids
     * @return array week
     */
    public static function execute(string $week, string $goal = '', array $cmids = []): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['week' => $week, 'goal' => $goal, 'cmids' => $cmids]
        );
        self::validate_context(context_user::instance($USER->id));
        $now = time();
        (new planner($now))->save_plan($USER->id, $params['week'], $params['goal'], $params['cmids']);
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
