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

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_gopilot\local\autologin;

/**
 * local_gopilot_get_moodle_link: one-time link that logs the user into Moodle and opens a page.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_moodle_link extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'url' => new external_value(PARAM_URL, 'Moodle page to open, e.g. the URL of an activity'),
        ]);
    }

    /**
     * Create the link.
     *
     * @param string $url
     * @return array
     */
    public static function execute(string $url): array {
        $params = self::validate_parameters(self::execute_parameters(), ['url' => $url]);
        self::validate_context(context_system::instance());
        return [
            'url' => autologin::create_link($params['url']),
            'validuntil' => time() + autologin::KEY_TTL,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'url' => new external_value(PARAM_URL, 'Link to open in the browser, valid once'),
            'validuntil' => new external_value(PARAM_INT, 'The link expires at this time'),
        ]);
    }
}
