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
use local_gopilot\ampel\calculator;
use local_gopilot\local\steps;
use local_gopilot\local\weeks;

/**
 * local_gopilot_get_lerndoku: all learners of a course with Ampel (coach view).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_lerndoku extends external_api {
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
     * Lerndoku of a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $DB;
        ['courseid' => $courseid] = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/gopilot:coach', $context);

        $now = time();
        $steps = new steps($now);
        $calculator = new calculator($now, $steps);
        $course = get_course($courseid);
        $current = weeks::current($now);

        $users = get_enrolled_users($context, 'local/gopilot:use', 0, 'u.*', 'u.lastname ASC, u.firstname ASC', 0, 0, true);
        $sql = "SELECT cr.id, cr.text, w.week
                  FROM {local_gopilot_coursereflection} cr
                  JOIN {local_gopilot_week} w ON w.id = cr.weekid
                 WHERE w.userid = :userid AND cr.courseid = :courseid
              ORDER BY w.week DESC";
        $cohorts = self::cohorts(array_keys($users));
        $learners = [];
        foreach ($users as $user) {
            $last = ['week' => '', 'text' => ''];
            foreach ($DB->get_records_sql($sql, ['userid' => $user->id, 'courseid' => $courseid]) as $row) {
                if (trim((string)$row->text) !== '') {
                    $last = ['week' => $row->week, 'text' => \core_text::substr(trim($row->text), 0, 200)];
                    break;
                }
            }
            $learners[] = [
                'userid' => (int)$user->id,
                'fullname' => fullname($user),
                'ampel' => $calculator->compute_and_store($user->id, $courseid),
                'progress' => $steps->progress($courseid, $user->id),
                'lastreflection' => $last,
                'unreadfeedback' => 0,
                'cohorts' => $cohorts[$user->id] ?? [],
            ];
        }
        return [
            'course' => ['id' => (int)$course->id, 'fullname' => format_string($course->fullname, true, ['context' => $context])],
            'currentweek' => $current,
            'learners' => $learners,
        ];
    }

    /**
     * Visible cohorts (global groups, e.g. classes) of the learners.
     *
     * @param int[] $userids
     * @return array userid => list of ['id' => int, 'name' => string], sorted by name
     */
    protected static function cohorts(array $userids): array {
        global $DB;
        $result = [];
        foreach (array_chunk($userids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $sql = "SELECT cm.id, cm.userid, c.id AS cohortid, c.name, c.contextid
                      FROM {cohort_members} cm
                      JOIN {cohort} c ON c.id = cm.cohortid
                     WHERE c.visible = 1 AND cm.userid $insql
                  ORDER BY c.name ASC, c.id ASC";
            foreach ($DB->get_records_sql($sql, $params) as $row) {
                $context = \context::instance_by_id($row->contextid, IGNORE_MISSING) ?: \context_system::instance();
                $result[(int)$row->userid][] = [
                    'id' => (int)$row->cohortid,
                    'name' => format_string($row->name, true, ['context' => $context]),
                ];
            }
        }
        return $result;
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => structures::course(),
            'currentweek' => new external_value(PARAM_RAW, 'Monday of the current week'),
            'learners' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'ampel' => structures::ampel(),
                'progress' => structures::progress(),
                'lastreflection' => new external_single_structure([
                    'week' => new external_value(PARAM_RAW, 'Week of the last course reflection, empty = never'),
                    'text' => new external_value(PARAM_TEXT, 'Course reflection, max. 200 characters'),
                ]),
                'unreadfeedback' => new external_value(PARAM_INT, 'Reserved, 0 in version 1'),
                'cohorts' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Cohort id'),
                    'name' => new external_value(PARAM_TEXT, 'Cohort name, e.g. the class'),
                ]), 'Visible cohorts (global groups) of the learner, sorted by name'),
            ])),
        ]);
    }
}
