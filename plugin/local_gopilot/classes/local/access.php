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

namespace local_gopilot\local;

use context_course;
use moodle_exception;

/**
 * Access rules (docs/umsetzungsplan-v1.md, section 2.2; ADR 0004).
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Courses the user is enrolled in (active enrolments), keyed by id.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function enrolled_courses(int $userid): array {
        return enrol_get_users_courses($userid, true, 'id, shortname, fullname, enablecompletion');
    }

    /**
     * Whether the user is a learner in the course (capability use, without admin override).
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_learner(int $courseid, int $userid): bool {
        return has_capability('local/gopilot:use', context_course::instance($courseid), $userid, false);
    }

    /**
     * Whether the user is a learning coach in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_coach(int $courseid, int $userid): bool {
        return has_capability('local/gopilot:coach', context_course::instance($courseid), $userid);
    }

    /**
     * Courses in which the user is a learner, keyed by id.
     *
     * @param int $userid
     * @return \stdClass[]
     */
    public static function learner_courses(int $userid): array {
        $result = [];
        foreach (self::enrolled_courses($userid) as $course) {
            if (self::is_learner($course->id, $userid)) {
                $result[$course->id] = $course;
            }
        }
        return $result;
    }

    /**
     * Whether the viewer may read the course-independent parts (weekly goal, general reflection) of the learner:
     * the viewer is the learner or a coach in any course in which the learner is a learner.
     *
     * @param int $viewerid
     * @param int $learnerid
     * @return bool
     */
    public static function can_read_general(int $viewerid, int $learnerid): bool {
        if ($viewerid == $learnerid) {
            return true;
        }
        foreach (self::learner_courses($learnerid) as $course) {
            if (self::is_coach($course->id, $viewerid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the viewer may read course data (course reflection, self-assessment, feedback, Ampel) of the learner.
     *
     * @param int $courseid
     * @param int $viewerid
     * @param int $learnerid
     * @return bool
     */
    public static function can_read_course(int $courseid, int $viewerid, int $learnerid): bool {
        if (!self::is_learner($courseid, $learnerid)) {
            return false;
        }
        return $viewerid == $learnerid || self::is_coach($courseid, $viewerid);
    }

    /**
     * Throw unless the user is a learner in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @throws moodle_exception
     */
    public static function require_learner(int $courseid, int $userid): void {
        if (!self::is_learner($courseid, $userid)) {
            throw new moodle_exception('notalearner', 'local_gopilot');
        }
    }
}
