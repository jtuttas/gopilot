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

use cm_info;

/**
 * Builds learning steps (course modules) and learning paths (sections) from modinfo and completion data.
 *
 * One instance caches per request; create a new instance for each web service call or task run.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class steps {
    /** @var int reference time */
    protected $now;

    /** @var array courseid => ['section' => [id => meta], 'cm' => [id => meta]] */
    protected $meta = [];

    /** @var array courseid => [cmid => due date] */
    protected $duedates = [];

    /** @var array "userid:courseid" => [cmid => step] */
    protected $steps = [];

    /** @var array "userid:courseid" => [cmid => completion record] */
    protected $completion = [];

    /**
     * Constructor.
     *
     * @param int|null $now reference time for "overdue", default time()
     */
    public function __construct(?int $now = null) {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        $this->now = $now ?? time();
    }

    /**
     * Empty path metadata.
     *
     * @return array
     */
    public static function empty_meta(): array {
        return ['phase' => '', 'duration' => 0, 'level' => 0];
    }

    /**
     * Path metadata of a section or course module.
     *
     * @param int $courseid
     * @param string $type "section" or "cm"
     * @param int $itemid
     * @return array phase, duration, level
     */
    public function meta(int $courseid, string $type, int $itemid): array {
        global $DB;
        if (!isset($this->meta[$courseid])) {
            $this->meta[$courseid] = ['section' => [], 'cm' => []];
            foreach ($DB->get_records('local_gopilot_pathmeta', ['courseid' => $courseid]) as $record) {
                $this->meta[$courseid][$record->type][(int)$record->itemid] = [
                    'phase' => (string)$record->phase,
                    'duration' => (int)$record->duration,
                    'level' => (int)$record->itemlevel,
                ];
            }
        }
        return $this->meta[$courseid][$type][$itemid] ?? self::empty_meta();
    }

    /**
     * Due dates of assignments (duedate) and quizzes (timeclose) of a course, by cmid.
     *
     * @param \course_modinfo $modinfo
     * @return array cmid => timestamp
     */
    protected function duedates(\course_modinfo $modinfo): array {
        global $DB;
        $courseid = (int)$modinfo->get_course_id();
        if (!isset($this->duedates[$courseid])) {
            $dates = [];
            $sources = ['assign' => 'duedate', 'quiz' => 'timeclose'];
            foreach ($sources as $modname => $field) {
                $instances = $modinfo->get_instances_of($modname);
                if (!$instances) {
                    continue;
                }
                $values = $DB->get_records_menu($modname, ['course' => $courseid], '', 'id, ' . $field);
                foreach ($instances as $instanceid => $cm) {
                    $dates[$cm->id] = (int)($values[$instanceid] ?? 0);
                }
            }
            $this->duedates[$courseid] = $dates;
        }
        return $this->duedates[$courseid];
    }

    /**
     * Completion records of a user in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array cmid => record (completionstate, timemodified)
     */
    public function completion_records(int $courseid, int $userid): array {
        global $DB;
        $key = $userid . ':' . $courseid;
        if (!isset($this->completion[$key])) {
            $sql = "SELECT cmc.coursemoduleid, cmc.completionstate, cmc.timemodified
                      FROM {course_modules_completion} cmc
                      JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = :courseid AND cmc.userid = :userid";
            $this->completion[$key] = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        }
        return $this->completion[$key];
    }

    /**
     * Whether a completion state counts as completed (incl. passed).
     *
     * @param int $state
     * @return bool
     */
    public static function is_complete_state(int $state): bool {
        return $state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS;
    }

    /**
     * Whether the user completed the course module now.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $userid
     * @return bool
     */
    public function is_completed(int $courseid, int $cmid, int $userid): bool {
        $records = $this->completion_records($courseid, $userid);
        return isset($records[$cmid]) && self::is_complete_state((int)$records[$cmid]->completionstate);
    }

    /**
     * Whether the user had completed the course module at the given time.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $userid
     * @param int $time
     * @return bool
     */
    public function completed_by(int $courseid, int $cmid, int $userid, int $time): bool {
        $records = $this->completion_records($courseid, $userid);
        return isset($records[$cmid]) && self::is_complete_state((int)$records[$cmid]->completionstate)
            && (int)$records[$cmid]->timemodified <= $time;
    }

    /**
     * Whether a course module counts as learning step for the given modinfo user.
     *
     * @param cm_info $cm
     * @return bool
     */
    public static function is_step(cm_info $cm): bool {
        return $cm->sectionnum > 0 && $cm->modname !== 'label' && $cm->has_view() && $cm->visible && $cm->uservisible;
    }

    /**
     * Build the step structure of one course module.
     *
     * @param cm_info $cm
     * @param int $userid
     * @return array
     */
    protected function build(cm_info $cm, int $userid): array {
        $courseid = (int)$cm->course;
        $tracked = (int)$cm->completion !== COMPLETION_TRACKING_NONE;
        $completed = $tracked && $this->is_completed($courseid, (int)$cm->id, $userid);
        $expected = (int)$cm->completionexpected;
        if (!$expected) {
            $expected = (int)($this->duedates($cm->get_modinfo())[$cm->id] ?? 0);
        }
        return [
            'cmid' => (int)$cm->id,
            'courseid' => $courseid,
            'sectionid' => (int)$cm->section,
            'name' => $cm->get_formatted_name(),
            'modname' => $cm->modname,
            'url' => $cm->url ? $cm->url->out(false) : '',
            'tracked' => $tracked,
            'completed' => $completed,
            'manual' => (int)$cm->completion === COMPLETION_TRACKING_MANUAL,
            'expected' => $expected,
            // Steps without completion tracking can never be completed, so they are never overdue.
            'overdue' => $tracked && $expected > 0 && $expected < $this->now && !$completed,
            'meta' => $this->meta($courseid, 'cm', (int)$cm->id),
        ];
    }

    /**
     * All learning steps of a course visible to the user, in course order.
     *
     * @param int $courseid
     * @param int $userid
     * @return array cmid => step
     */
    public function for_course(int $courseid, int $userid): array {
        $key = $userid . ':' . $courseid;
        if (!isset($this->steps[$key])) {
            $modinfo = get_fast_modinfo($courseid, $userid);
            $result = [];
            foreach ($modinfo->get_section_info_all() as $section) {
                if ($section->section == 0 || empty($modinfo->sections[$section->section])) {
                    continue;
                }
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    if (self::is_step($cm)) {
                        $result[(int)$cm->id] = $this->build($cm, $userid);
                    }
                }
            }
            $this->steps[$key] = $result;
        }
        return $this->steps[$key];
    }

    /**
     * One learning step, or null if it does not exist or is not visible for the user.
     *
     * @param int $courseid
     * @param int $cmid
     * @param int $userid
     * @return array|null
     */
    public function get(int $courseid, int $cmid, int $userid): ?array {
        return $this->for_course($courseid, $userid)[$cmid] ?? null;
    }

    /**
     * Progress over all tracked steps.
     *
     * @param int $courseid
     * @param int $userid
     * @return array completed, total
     */
    public function progress(int $courseid, int $userid): array {
        $completed = 0;
        $total = 0;
        foreach ($this->for_course($courseid, $userid) as $step) {
            if ($step['tracked']) {
                $total++;
                $completed += $step['completed'] ? 1 : 0;
            }
        }
        return ['completed' => $completed, 'total' => $total];
    }

    /**
     * Learning paths (sections except 0) visible to the user.
     *
     * @param int $courseid
     * @param int $userid
     * @return array list of path structures (see get_paths)
     */
    public function paths(int $courseid, int $userid): array {
        $modinfo = get_fast_modinfo($courseid, $userid);
        $course = $modinfo->get_course();
        $steps = $this->for_course($courseid, $userid);
        $paths = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->section == 0 || !$section->uservisible || !$section->visible) {
                continue;
            }
            $pathsteps = array_values(array_filter($steps, function ($step) use ($section) {
                return $step['sectionid'] == $section->id;
            }));
            $completed = 0;
            $total = 0;
            foreach ($pathsteps as $step) {
                if ($step['tracked']) {
                    $total++;
                    $completed += $step['completed'] ? 1 : 0;
                }
            }
            $summary = trim(html_to_text(format_text(
                (string)$section->summary,
                $section->summaryformat,
                ['context' => \context_course::instance($courseid), 'noclean' => false]
            ), 0, false));
            $paths[] = [
                'sectionid' => (int)$section->id,
                'section' => (int)$section->section,
                'name' => get_section_name($course, $section),
                'summary' => $summary,
                'meta' => $this->meta($courseid, 'section', (int)$section->id),
                'completed' => $completed,
                'total' => $total,
                'steps' => $pathsteps,
            ];
        }
        return $paths;
    }

    /**
     * Section name by section id (empty if unknown).
     *
     * @param int $courseid
     * @param int $sectionid
     * @return string
     */
    public static function section_name(int $courseid, int $sectionid): string {
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_section_info_all() as $section) {
            if ((int)$section->id === $sectionid) {
                return get_section_name($modinfo->get_course(), $section);
            }
        }
        return '';
    }
}
