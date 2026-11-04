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
use local_gopilot\local\weekdata;

/**
 * local_gopilot_get_biography: all weeks with data of the current user, newest first.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_biography extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Page, from 0', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Weeks per page', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Get one page of the learning biography.
     *
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function execute(int $page = 0, int $perpage = 10): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['page' => $page, 'perpage' => $perpage]);
        self::validate_context(context_user::instance($USER->id));
        $page = max(0, $params['page']);
        $perpage = max(1, min(100, $params['perpage']));
        $records = weekdata::weeks_with_data($USER->id);
        $data = new weekdata();
        $weeks = [];
        foreach (array_slice($records, $page * $perpage, $perpage) as $record) {
            $weeks[] = $data->week($USER->id, $record->week);
        }
        return ['total' => count($records), 'weeks' => $weeks];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Number of weeks with data'),
            'weeks' => new external_multiple_structure(structures::week()),
        ]);
    }
}
