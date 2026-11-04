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
use core_external\external_single_structure;
use core_external\external_value;
use local_gopilot\local\planner;
use local_gopilot\local\weekdata;

/**
 * local_gopilot_add_feedback: add an entry to the feedback thread (coach or the learner).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_feedback extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'userid' => new external_value(PARAM_INT, 'Learner user id'),
            'week' => new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }

    /**
     * Add feedback.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $week
     * @param string $message
     * @return array feedbackentry
     */
    public static function execute(int $courseid, int $userid, string $week, string $message): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'userid' => $userid, 'week' => $week, 'message' => $message]
        );
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        if ($params['userid'] != $USER->id) {
            require_capability('local/gopilot:coach', $context);
        } else {
            require_capability('local/gopilot:use', $context);
        }
        $record = (new planner())->add_feedback(
            $USER->id,
            $params['courseid'],
            $params['userid'],
            $params['week'],
            $params['message']
        );
        return (new weekdata())->feedbackentry($record, $params['userid']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return structures::feedbackentry();
    }
}
