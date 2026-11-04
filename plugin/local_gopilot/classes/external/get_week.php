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
use core_external\external_single_structure;
use core_external\external_value;
use local_gopilot\local\weekdata;
use local_gopilot\local\weeks;

/**
 * local_gopilot_get_week: one planning week of the current user.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_week extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'week' => new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
        ]);
    }

    /**
     * Get the week.
     *
     * @param string $week
     * @return array
     */
    public static function execute(string $week): array {
        global $USER;
        ['week' => $week] = self::validate_parameters(self::execute_parameters(), ['week' => $week]);
        self::validate_context(context_user::instance($USER->id));
        weeks::require_valid($week);
        return (new weekdata())->week($USER->id, $week);
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
