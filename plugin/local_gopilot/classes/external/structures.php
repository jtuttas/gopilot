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

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Shared return structures of docs/api-v1.md ("Gemeinsame Strukturen").
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class structures {
    /**
     * Path metadata.
     *
     * @return external_single_structure
     */
    public static function meta(): external_single_structure {
        return new external_single_structure([
            'phase' => new external_value(PARAM_TEXT, 'Phase, empty = none'),
            'duration' => new external_value(PARAM_INT, 'Minutes, 0 = not given'),
            'level' => new external_value(PARAM_INT, '0 not given, 1 basic, 2 standard, 3 expert'),
        ]);
    }

    /**
     * Learning step.
     *
     * @return external_single_structure
     */
    public static function step(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'sectionid' => new external_value(PARAM_INT, 'Section id (learning path)'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'modname' => new external_value(PARAM_PLUGIN, 'Module name'),
            'url' => new external_value(PARAM_URL, 'Link to the activity'),
            'tracked' => new external_value(PARAM_BOOL, 'Has completion tracking'),
            'completed' => new external_value(PARAM_BOOL, 'Completed (incl. passed)'),
            'manual' => new external_value(PARAM_BOOL, 'Manual completion'),
            'expected' => new external_value(PARAM_INT, 'Expected completion / due date, 0 = none'),
            'overdue' => new external_value(PARAM_BOOL, 'Expected date passed and not completed'),
            'meta' => self::meta(),
        ]);
    }

    /**
     * One Ampel signal.
     *
     * @return external_single_structure
     */
    public static function signal(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(PARAM_ALPHA, 'plan, backlog, reflection or selfassessment'),
            'active' => new external_value(PARAM_BOOL, 'Signal can be evaluated'),
            'colour' => new external_value(PARAM_ALPHA, 'green, yellow, red or none'),
            'value' => new external_value(PARAM_INT, 'Raw value'),
            'reason' => new external_value(PARAM_TEXT, 'Explanation'),
        ]);
    }

    /**
     * Ampel.
     *
     * @return external_single_structure
     */
    public static function ampel(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'colour' => new external_value(PARAM_ALPHA, 'green, yellow, red or none'),
            'timecomputed' => new external_value(PARAM_INT, 'Time computed'),
            'signals' => new external_multiple_structure(self::signal(), 'Signals plan, backlog, reflection, selfassessment'),
        ]);
    }

    /**
     * Feedback thread entry.
     *
     * @return external_single_structure
     */
    public static function feedbackentry(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Entry id'),
            'authorid' => new external_value(PARAM_INT, 'Author user id'),
            'authorname' => new external_value(PARAM_TEXT, 'Author full name'),
            'isteacher' => new external_value(PARAM_BOOL, 'Author is not the learner'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timeread' => new external_value(PARAM_INT, 'When the addressee read it, 0 = unread'),
        ]);
    }

    /**
     * Unread feedback entry for the start page.
     *
     * @return external_single_structure
     */
    public static function newfeedback(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Entry id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'coursename' => new external_value(PARAM_TEXT, 'Course full name'),
            'week' => new external_value(PARAM_RAW, 'Monday of the week'),
            'authorname' => new external_value(PARAM_TEXT, 'Author full name'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
        ]);
    }

    /**
     * Self-assessment of a learning path.
     *
     * @return external_single_structure
     */
    public static function selfassessment(): external_single_structure {
        return new external_single_structure([
            'sectionid' => new external_value(PARAM_INT, 'Section id'),
            'name' => new external_value(PARAM_TEXT, 'Learning path name'),
            'level' => new external_value(PARAM_INT, '0 not given, 1 confident ... 4 lost'),
            'comment' => new external_value(PARAM_TEXT, 'Comment'),
        ]);
    }

    /**
     * Course part of a week.
     *
     * @return external_single_structure
     */
    public static function courseweek(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'fullname' => new external_value(PARAM_TEXT, 'Course name'),
            'reflection' => new external_value(PARAM_TEXT, 'Course reflection'),
            'selfassessments' => new external_multiple_structure(self::selfassessment(), 'Self-assessments'),
            'feedback' => new external_multiple_structure(self::feedbackentry(), 'Feedback thread, oldest first'),
        ]);
    }

    /**
     * Planning week.
     *
     * @return external_single_structure
     */
    public static function week(): external_single_structure {
        return new external_single_structure([
            'week' => new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
            'closed' => new external_value(PARAM_BOOL, 'Week is closed'),
            'goal' => new external_value(PARAM_TEXT, 'Weekly goal'),
            'generalreflection' => new external_value(PARAM_TEXT, 'General reflection'),
            'reflectedat' => new external_value(PARAM_INT, 'Time of last reflection, 0 = none'),
            'items' => new external_multiple_structure(self::step(), 'Planned steps'),
            'courses' => new external_multiple_structure(self::courseweek(), 'Courses of the learner'),
        ]);
    }

    /**
     * Week of a learner restricted to one course (coach view).
     *
     * @return external_single_structure
     */
    public static function learnerweek(): external_single_structure {
        return new external_single_structure([
            'week' => new external_value(PARAM_RAW, 'Monday YYYY-MM-DD'),
            'closed' => new external_value(PARAM_BOOL, 'Week is closed'),
            'goal' => new external_value(PARAM_TEXT, 'Weekly goal'),
            'generalreflection' => new external_value(PARAM_TEXT, 'General reflection'),
            'reflectedat' => new external_value(PARAM_INT, 'Time of last reflection, 0 = none'),
            'items' => new external_multiple_structure(self::step(), 'Planned steps of this course'),
            'reflection' => new external_value(PARAM_TEXT, 'Course reflection'),
            'selfassessments' => new external_multiple_structure(self::selfassessment(), 'Self-assessments'),
            'feedback' => new external_multiple_structure(self::feedbackentry(), 'Feedback thread, oldest first'),
        ]);
    }

    /**
     * Progress.
     *
     * @return external_single_structure
     */
    public static function progress(): external_single_structure {
        return new external_single_structure([
            'completed' => new external_value(PARAM_INT, 'Completed tracked steps'),
            'total' => new external_value(PARAM_INT, 'Tracked steps'),
        ]);
    }

    /**
     * Ampel thresholds.
     *
     * @param bool $required whether fields are required (returns) or optional (parameters)
     * @return external_single_structure
     */
    public static function thresholds(bool $required = true): external_single_structure {
        $req = $required ? VALUE_REQUIRED : VALUE_OPTIONAL;
        return new external_single_structure([
            'plangreen' => new external_value(PARAM_INT, 'Plan: green from this percentage', $req),
            'planyellow' => new external_value(PARAM_INT, 'Plan: yellow from this percentage', $req),
            'backlogyellow' => new external_value(PARAM_INT, 'Backlog: yellow from this number', $req),
            'backlogred' => new external_value(PARAM_INT, 'Backlog: red from this number', $req),
            'reflectionyellow' => new external_value(PARAM_INT, 'Reflection: yellow from this number of weeks', $req),
            'reflectionred' => new external_value(PARAM_INT, 'Reflection: red from this number of weeks', $req),
        ], 'Thresholds', $req);
    }

    /**
     * Short course description (id, fullname).
     *
     * @return external_single_structure
     */
    public static function course(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course id'),
            'fullname' => new external_value(PARAM_TEXT, 'Course name'),
        ]);
    }
}
