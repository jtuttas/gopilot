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

namespace local_gopilot\privacy;

use context;
use context_course;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider of local_gopilot.
 *
 * Course-independent data (weeks with goal and general reflection, push subscriptions) lives in the user context,
 * course data (plan items, course reflections, self-assessments, feedback, Ampel) in the course context.
 *
 * @package    local_gopilot
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] tables with weekid and courseid */
    const COURSE_TABLES = ['local_gopilot_planitem', 'local_gopilot_coursereflection', 'local_gopilot_selfassessment',
        'local_gopilot_feedback'];

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $tables = [
            'local_gopilot_week' => ['userid', 'week', 'goal', 'generalreflection', 'reflectedat', 'timemodified'],
            'local_gopilot_planitem' => ['weekid', 'courseid', 'cmid', 'completedatclose'],
            'local_gopilot_coursereflection' => ['weekid', 'courseid', 'text', 'timemodified'],
            'local_gopilot_selfassessment' => ['weekid', 'courseid', 'sectionid', 'selflevel', 'remark'],
            'local_gopilot_feedback' => ['weekid', 'courseid', 'authorid', 'message', 'timecreated'],
            'local_gopilot_ampel' => ['userid', 'courseid', 'colour', 'signals', 'timecomputed'],
            'local_gopilot_pushsub' => ['userid', 'endpoint', 'p256dh', 'auth', 'timecreated'],
        ];
        foreach ($tables as $table => $fields) {
            $described = [];
            foreach ($fields as $field) {
                $described[$field] = 'privacy:metadata:' . $table . ':' . $field;
            }
            $collection->add_database_table($table, $described, 'privacy:metadata:' . $table);
        }
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        $collection->add_subsystem_link('core_userkey', [], 'privacy:metadata:core_userkey');
        $collection->add_external_location_link('webpush', [
            'title' => 'privacy:metadata:webpush:title',
            'body' => 'privacy:metadata:webpush:body',
            'url' => 'privacy:metadata:webpush:url',
        ], 'privacy:metadata:webpush');
        return $collection;
    }

    /**
     * Course ids with data of the user.
     *
     * @param int $userid
     * @return int[]
     */
    protected static function course_ids(int $userid): array {
        global $DB;
        $ids = [];
        foreach (self::COURSE_TABLES as $table) {
            $sql = "SELECT DISTINCT t.courseid
                      FROM {{$table}} t
                      JOIN {local_gopilot_week} w ON w.id = t.weekid
                     WHERE w.userid = :userid";
            foreach ($DB->get_fieldset_sql($sql, ['userid' => $userid]) as $id) {
                $ids[(int)$id] = true;
            }
        }
        $sql = "SELECT DISTINCT courseid FROM {local_gopilot_feedback} WHERE authorid = :userid
                 UNION
                SELECT DISTINCT courseid FROM {local_gopilot_ampel} WHERE userid = :userid2";
        foreach ($DB->get_fieldset_sql($sql, ['userid' => $userid, 'userid2' => $userid]) as $id) {
            $ids[(int)$id] = true;
        }
        return array_keys($ids);
    }

    /**
     * Contexts with data of the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists('local_gopilot_week', ['userid' => $userid])
                || $DB->record_exists('local_gopilot_pushsub', ['userid' => $userid])
        ) {
            $contextlist->add_user_context($userid);
        }
        $courseids = self::course_ids($userid);
        if ($courseids) {
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $params['level'] = CONTEXT_COURSE;
            $contextlist->add_from_sql("SELECT id FROM {context} WHERE contextlevel = :level AND instanceid $insql", $params);
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_user) {
            $params = ['userid' => $context->instanceid];
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_gopilot_week} WHERE userid = :userid", $params);
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_gopilot_pushsub} WHERE userid = :userid", $params);
        } else if ($context instanceof context_course) {
            $params = ['courseid' => $context->instanceid];
            foreach (self::COURSE_TABLES as $table) {
                $sql = "SELECT w.userid
                          FROM {{$table}} t
                          JOIN {local_gopilot_week} w ON w.id = t.weekid
                         WHERE t.courseid = :courseid";
                $userlist->add_from_sql('userid', $sql, $params);
            }
            $userlist->add_from_sql(
                'authorid',
                "SELECT authorid FROM {local_gopilot_feedback} WHERE courseid = :courseid",
                $params
            );
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_gopilot_ampel} WHERE courseid = :courseid", $params);
        }
    }

    /**
     * Export the data of the user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $plugin = get_string('pluginname', 'local_gopilot');
        $weeks = $DB->get_records('local_gopilot_week', ['userid' => $userid], 'week ASC');
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_user && (int)$context->instanceid === $userid) {
                foreach ($weeks as $week) {
                    writer::with_context($context)->export_data([$plugin, $week->week], (object)[
                        'week' => $week->week,
                        'goal' => $week->goal,
                        'generalreflection' => $week->generalreflection,
                        'reflectedat' => $week->reflectedat ? transform::datetime($week->reflectedat) : '',
                    ]);
                }
                $subs = $DB->get_records('local_gopilot_pushsub', ['userid' => $userid]);
                if ($subs) {
                    $data = [];
                    foreach ($subs as $sub) {
                        $data[] = ['endpoint' => $sub->endpoint, 'timecreated' => transform::datetime($sub->timecreated)];
                    }
                    $subcontext = [$plugin, get_string('privacy:pushsubscriptions', 'local_gopilot')];
                    writer::with_context($context)->export_data($subcontext, (object)['subscriptions' => $data]);
                }
            } else if ($context instanceof context_course) {
                self::export_course($context, $userid, $weeks, $plugin);
            }
        }
    }

    /**
     * Export course data of the user.
     *
     * @param context_course $context
     * @param int $userid
     * @param \stdClass[] $weeks week records of the user
     * @param string $plugin plugin name
     * @return void
     */
    protected static function export_course(context_course $context, int $userid, array $weeks, string $plugin): void {
        global $DB;
        $courseid = (int)$context->instanceid;
        foreach ($weeks as $week) {
            $params = ['weekid' => $week->id, 'courseid' => $courseid];
            $items = $DB->get_records('local_gopilot_planitem', $params, 'sortorder ASC');
            $reflection = $DB->get_record('local_gopilot_coursereflection', $params);
            $assessments = $DB->get_records('local_gopilot_selfassessment', $params);
            $feedback = $DB->get_records('local_gopilot_feedback', $params, 'timecreated ASC');
            if (!$items && !$reflection && !$assessments && !$feedback) {
                continue;
            }
            $data = (object)[
                'week' => $week->week,
                'plannedcmids' => array_values(array_map(function ($item) {
                    return (int)$item->cmid;
                }, $items)),
                'reflection' => $reflection ? $reflection->text : '',
                'selfassessments' => array_values(array_map(function ($sa) {
                    return ['sectionid' => (int)$sa->sectionid, 'level' => (int)$sa->selflevel, 'comment' => $sa->remark];
                }, $assessments)),
                'feedback' => array_values(array_map(function ($entry) use ($userid) {
                    return [
                        'byyou' => transform::yesno($entry->authorid == $userid),
                        'message' => $entry->message,
                        'timecreated' => transform::datetime($entry->timecreated),
                    ];
                }, $feedback)),
            ];
            writer::with_context($context)->export_data([$plugin, $week->week], $data);
        }

        // Feedback written by the user in threads of other learners.
        $sql = "SELECT f.id, f.message, f.timecreated, w.week
                  FROM {local_gopilot_feedback} f
                  JOIN {local_gopilot_week} w ON w.id = f.weekid
                 WHERE f.authorid = :userid AND f.courseid = :courseid AND w.userid <> :userid2
              ORDER BY f.timecreated ASC";
        $authored = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid, 'userid2' => $userid]);
        if ($authored) {
            $entries = [];
            foreach ($authored as $entry) {
                $entries[] = [
                    'week' => $entry->week,
                    'message' => $entry->message,
                    'timecreated' => transform::datetime($entry->timecreated),
                ];
            }
            $subcontext = [$plugin, get_string('privacy:feedbackgiven', 'local_gopilot')];
            writer::with_context($context)->export_data($subcontext, (object)['feedback' => $entries]);
        }

        $ampel = $DB->get_record('local_gopilot_ampel', ['userid' => $userid, 'courseid' => $courseid]);
        if ($ampel) {
            $subcontext = [$plugin, get_string('privacy:ampel', 'local_gopilot')];
            writer::with_context($context)->export_data($subcontext, (object)[
                'colour' => $ampel->colour,
                'signals' => json_decode($ampel->signals, true),
                'timecomputed' => transform::datetime($ampel->timecomputed),
            ]);
        }
    }

    /**
     * Delete the course data of a user.
     *
     * @param int $userid
     * @param int $courseid
     * @return void
     */
    protected static function delete_user_course(int $userid, int $courseid): void {
        global $DB;
        $weekids = $DB->get_fieldset_select('local_gopilot_week', 'id', 'userid = ?', [$userid]);
        if ($weekids) {
            [$insql, $params] = $DB->get_in_or_equal($weekids, SQL_PARAMS_NAMED);
            $params['courseid'] = $courseid;
            foreach (self::COURSE_TABLES as $table) {
                $DB->delete_records_select($table, "weekid $insql AND courseid = :courseid", $params);
            }
        }
        $DB->delete_records('local_gopilot_feedback', ['authorid' => $userid, 'courseid' => $courseid]);
        $DB->delete_records('local_gopilot_ampel', ['userid' => $userid, 'courseid' => $courseid]);
    }

    /**
     * Delete all data of a user.
     *
     * @param int $userid
     * @return void
     */
    protected static function delete_user_all(int $userid): void {
        global $DB;
        $weekids = $DB->get_fieldset_select('local_gopilot_week', 'id', 'userid = ?', [$userid]);
        if ($weekids) {
            [$insql, $params] = $DB->get_in_or_equal($weekids, SQL_PARAMS_NAMED);
            foreach (self::COURSE_TABLES as $table) {
                $DB->delete_records_select($table, "weekid $insql", $params);
            }
        }
        $DB->delete_records('local_gopilot_week', ['userid' => $userid]);
        $DB->delete_records('local_gopilot_feedback', ['authorid' => $userid]);
        $DB->delete_records('local_gopilot_ampel', ['userid' => $userid]);
        $DB->delete_records('local_gopilot_pushsub', ['userid' => $userid]);
        \core_userkey\privacy\provider::delete_userkeys(\local_gopilot\local\autologin::SCRIPT, $userid);
    }

    /**
     * Delete the data of one user in one context.
     *
     * @param context $context
     * @param int $userid
     * @return void
     */
    protected static function delete_in_context(context $context, int $userid): void {
        if ($context instanceof context_user && (int)$context->instanceid === $userid) {
            self::delete_user_all($userid);
        } else if ($context instanceof context_course) {
            self::delete_user_course($userid, (int)$context->instanceid);
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof context_user) {
            self::delete_user_all((int)$context->instanceid);
        } else if ($context instanceof context_course) {
            $courseid = (int)$context->instanceid;
            foreach (self::COURSE_TABLES as $table) {
                $DB->delete_records($table, ['courseid' => $courseid]);
            }
            $DB->delete_records('local_gopilot_ampel', ['courseid' => $courseid]);
        }
    }

    /**
     * Delete the data of a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_in_context($context, $userid);
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        foreach ($userlist->get_userids() as $userid) {
            self::delete_in_context($context, (int)$userid);
        }
    }
}
