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
use invalid_parameter_exception;

/**
 * local_gopilot_save_pathmeta: save metadata of learning paths and steps.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_pathmeta extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'items' => new external_multiple_structure(new external_single_structure([
                'type' => new external_value(PARAM_ALPHA, 'section or cm'),
                'id' => new external_value(PARAM_INT, 'Section id or course module id'),
                'phase' => new external_value(PARAM_TEXT, 'Phase', VALUE_DEFAULT, ''),
                'duration' => new external_value(PARAM_INT, 'Minutes', VALUE_DEFAULT, 0),
                'level' => new external_value(PARAM_INT, '0 none, 1 basic, 2 standard, 3 expert', VALUE_DEFAULT, 0),
            ])),
        ]);
    }

    /**
     * Save metadata.
     *
     * @param int $courseid
     * @param array $items
     * @return array
     */
    public static function execute(int $courseid, array $items): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'items' => $items]);
        $courseid = $params['courseid'];
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/gopilot:configure', $context);

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        foreach ($params['items'] as $item) {
            $table = ['section' => 'course_sections', 'cm' => 'course_modules'][$item['type']] ?? null;
            if (!$table) {
                throw new invalid_parameter_exception('Invalid type ' . $item['type']);
            }
            if (!$DB->record_exists($table, ['id' => $item['id'], 'course' => $courseid])) {
                throw new invalid_parameter_exception('Item ' . $item['type'] . ' ' . $item['id'] . ' is not part of the course');
            }
            if ($item['level'] < 0 || $item['level'] > 3 || $item['duration'] < 0) {
                throw new invalid_parameter_exception('Invalid level or duration');
            }
            $data = (object)[
                'courseid' => $courseid,
                'type' => $item['type'],
                'itemid' => $item['id'],
                'phase' => \core_text::substr(trim($item['phase']), 0, 255),
                'duration' => $item['duration'],
                'itemlevel' => $item['level'],
                'timemodified' => $now,
            ];
            $existing = $DB->get_record('local_gopilot_pathmeta', ['type' => $item['type'], 'itemid' => $item['id']]);
            if ($existing) {
                $data->id = $existing->id;
                $DB->update_record('local_gopilot_pathmeta', $data);
            } else {
                $DB->insert_record('local_gopilot_pathmeta', $data);
            }
        }
        $transaction->allow_commit();
        return ['success' => true];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Saved'),
        ]);
    }
}
