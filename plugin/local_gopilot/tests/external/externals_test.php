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

use core_external\external_api;
use local_gopilot\local\weeks;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the web service functions of local_gopilot.
 *
 * @package    local_gopilot
 * @category   test
 * @copyright  2026 Jörg Tuttas
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_gopilot\external\get_overview
 * @covers     \local_gopilot\external\get_paths
 * @covers     \local_gopilot\external\get_week
 * @covers     \local_gopilot\external\save_plan
 * @covers     \local_gopilot\external\save_reflection
 * @covers     \local_gopilot\external\get_biography
 * @covers     \local_gopilot\external\get_lerndoku
 * @covers     \local_gopilot\external\get_learner
 * @covers     \local_gopilot\external\add_feedback
 * @covers     \local_gopilot\external\get_courseconfig
 * @covers     \local_gopilot\external\save_courseconfig
 * @covers     \local_gopilot\external\save_pathmeta
 * @covers     \local_gopilot\external\get_push_config
 * @covers     \local_gopilot\external\register_push
 * @covers     \local_gopilot\external\unregister_push
 * @covers     \local_gopilot\local\planner
 * @covers     \local_gopilot\local\weekdata
 */
final class externals_test extends \externallib_advanced_testcase {
    /** @var \stdClass course A */
    protected $coursea;

    /** @var \stdClass course B */
    protected $courseb;

    /** @var \stdClass[] course modules of course A */
    protected $cmsa = [];

    /** @var \stdClass course module of course B */
    protected $cmb;

    /** @var \stdClass learner in A and B */
    protected $learner;

    /** @var \stdClass other learner in A */
    protected $other;

    /** @var \stdClass editing teacher in A */
    protected $teachera;

    /** @var \stdClass editing teacher in B only */
    protected $teacherb;

    /**
     * Two courses, one learner in both, a teacher per course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->coursea = $gen->create_course(['enablecompletion' => 1, 'numsections' => 2, 'fullname' => 'LF5']);
        $this->courseb = $gen->create_course(['enablecompletion' => 1, 'numsections' => 1, 'fullname' => 'LF8']);
        for ($i = 0; $i < 3; $i++) {
            $page = $gen->create_module('page', [
                'course' => $this->coursea->id, 'section' => 1 + ($i % 2), 'completion' => COMPLETION_TRACKING_MANUAL,
                'completionexpected' => $i == 0 ? time() - DAYSECS : 0,
            ]);
            $this->cmsa[] = get_coursemodule_from_instance('page', $page->id);
        }
        $gen->create_module('label', ['course' => $this->coursea->id, 'section' => 1]);
        $page = $gen->create_module('page', ['course' => $this->courseb->id, 'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->cmb = get_coursemodule_from_instance('page', $page->id);

        $this->learner = $gen->create_user(['firstname' => 'Ada', 'lastname' => 'Azubi']);
        $this->other = $gen->create_user();
        $this->teachera = $gen->create_user(['firstname' => 'Lena', 'lastname' => 'Lehrkraft']);
        $this->teacherb = $gen->create_user();
        $gen->enrol_user($this->learner->id, $this->coursea->id, 'student');
        $gen->enrol_user($this->learner->id, $this->courseb->id, 'student');
        $gen->enrol_user($this->other->id, $this->coursea->id, 'student');
        $gen->enrol_user($this->teachera->id, $this->coursea->id, 'editingteacher');
        $gen->enrol_user($this->teacherb->id, $this->courseb->id, 'editingteacher');
    }

    /**
     * Call a web service function like the REST server does, with return value cleaning.
     *
     * @param string $classname short class name in local_gopilot\external
     * @param array $args
     * @return mixed
     */
    protected function call(string $classname, array $args = []) {
        $class = '\\local_gopilot\\external\\' . $classname;
        $result = $class::execute(...$args);
        return external_api::clean_returnvalue($class::execute_returns(), $result);
    }

    /**
     * Assert that a call throws a moodle_exception with the error code.
     *
     * @param string $errorcode
     * @param string $classname
     * @param array $args
     */
    protected function assert_error(string $errorcode, string $classname, array $args): void {
        try {
            $this->call($classname, $args);
            $this->fail('Exception ' . $errorcode . ' expected');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    /**
     * Course part of a week structure.
     *
     * @param array $week
     * @param int $courseid
     * @return array
     */
    protected function courseweek(array $week, int $courseid): array {
        foreach ($week['courses'] as $course) {
            if ($course['courseid'] == $courseid) {
                return $course;
            }
        }
        $this->fail('Course missing in week');
    }

    /**
     * save_plan and get_week round trip.
     */
    public function test_save_plan_get_week(): void {
        $this->setUser($this->learner);
        $week = weeks::current();
        $cmids = [$this->cmsa[1]->id, $this->cmb->id, $this->cmsa[0]->id];
        $result = $this->call('save_plan', [$week, 'SQL lernen', $cmids]);
        $this->assertSame($week, $result['week']);
        $this->assertSame('SQL lernen', $result['goal']);
        $this->assertSame(array_map('intval', $cmids), array_column($result['items'], 'cmid'));
        $this->assertFalse($result['closed']);
        $this->assertTrue($result['items'][2]['overdue']);
        $this->assertTrue($result['items'][2]['manual']);
        $this->assertStringContainsString('/mod/page/view.php', $result['items'][0]['url']);

        $get = $this->call('get_week', [$week]);
        $this->assertEquals($result, $get);
        $this->assertCount(2, $get['courses']);
        $a = $this->courseweek($get, $this->coursea->id);
        $this->assertCount(2, $a['selfassessments'], 'One entry per path with planned steps');
        $this->assertSame([0, 0], array_column($a['selfassessments'], 'level'));
        $this->assertSame('', $a['reflection']);

        // Replace the plan.
        $result = $this->call('save_plan', [$week, '', [$this->cmsa[2]->id]]);
        $this->assertCount(1, $result['items']);
        $this->assertSame('', $result['goal']);

        // A week without data.
        $empty = $this->call('get_week', [weeks::add($week, 5)]);
        $this->assertSame([], $empty['items']);
        $this->assertSame(0, $empty['reflectedat']);
    }

    /**
     * save_plan errors: invalid week, closed week, course without use.
     */
    public function test_save_plan_errors(): void {
        $this->setUser($this->learner);
        $this->assert_error('invalidweek', 'save_plan', ['2026-11-04', '', []]);
        $this->assert_error('weekclosed', 'save_plan', [weeks::add(weeks::current(), -1), '', []]);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1]);
        $this->assert_error('notalearner', 'save_plan', [weeks::current(), '', [$page->cmid]]);
        $this->assert_error('invalidweek', 'get_week', ['gestern']);
    }

    /**
     * save_reflection stores everything, notifies the coaches and fires the event.
     */
    public function test_save_reflection(): void {
        global $DB;
        $this->setUser($this->learner);
        $week = weeks::add(weeks::current(), -1);
        $section = $DB->get_record('course_sections', ['course' => $this->coursea->id, 'section' => 1]);
        $sink = $this->redirectMessages();
        $events = $this->redirectEvents();
        $result = $this->call('save_reflection', [$week, 'Gute Woche', [
            ['courseid' => $this->coursea->id, 'reflection' => 'JOINs verstanden',
                'selfassessments' => [['sectionid' => $section->id, 'level' => 3, 'comment' => 'Normalisierung']]],
        ]]);
        $this->assertSame('Gute Woche', $result['generalreflection']);
        $this->assertGreaterThan(0, $result['reflectedat']);
        $this->assertTrue($result['closed']);
        $a = $this->courseweek($result, $this->coursea->id);
        $this->assertSame('JOINs verstanden', $a['reflection']);
        $this->assertSame([['sectionid' => (int)$section->id, 'name' => get_section_name($this->coursea, $section),
            'level' => 3, 'comment' => 'Normalisierung']], $a['selfassessments']);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('reflection', $messages[0]->eventtype);
        $this->assertEquals($this->teachera->id, $messages[0]->useridto);

        $saved = array_filter($events->get_events(), function ($event) {
            return $event instanceof \local_gopilot\event\reflection_saved;
        });
        $this->assertCount(1, $saved);

        // Ampel was recalculated: self-assessment "unsure" = yellow.
        $ampel = $DB->get_record('local_gopilot_ampel', ['userid' => $this->learner->id, 'courseid' => $this->coursea->id]);
        $this->assertNotEmpty($ampel);

        // Saving again does not notify for an unchanged reflection.
        $sink->clear();
        $this->call('save_reflection', [$week, 'Gute Woche!', [
            ['courseid' => $this->coursea->id, 'reflection' => 'JOINs verstanden', 'selfassessments' => []],
        ]]);
        $this->assertCount(0, $sink->get_messages());
    }

    /**
     * save_reflection is only allowed for the current and the previous week, and only in own courses.
     */
    public function test_save_reflection_errors(): void {
        $this->setUser($this->learner);
        $current = weeks::current();
        $this->assert_error('reflectionclosed', 'save_reflection', [weeks::add($current, -2), '', []]);
        $this->assert_error('reflectionclosed', 'save_reflection', [weeks::add($current, 1), '', []]);
        $course = $this->getDataGenerator()->create_course();
        $this->assert_error('notalearner', 'save_reflection', [$current, '',
            [['courseid' => $course->id, 'reflection' => 'x', 'selfassessments' => []]]]);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('save_reflection', [$current, '', [['courseid' => $this->coursea->id, 'reflection' => '',
            'selfassessments' => [['sectionid' => 999999, 'level' => 1, 'comment' => '']]]]]);
    }

    /**
     * Feedback thread: coach and learner write, the other side is notified, the thread is visible to both.
     */
    public function test_feedback_thread(): void {
        $week = weeks::current();
        $sink = $this->redirectMessages();

        $this->setUser($this->teachera);
        $entry = $this->call('add_feedback', [$this->coursea->id, $this->learner->id, $week, 'Gut gemacht!']);
        $this->assertTrue($entry['isteacher']);
        $this->assertSame('Lena Lehrkraft', $entry['authorname']);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->learner->id, $messages[0]->useridto);
        $this->assertSame('feedback', $messages[0]->eventtype);

        $sink->clear();
        $this->setUser($this->learner);
        $reply = $this->call('add_feedback', [$this->coursea->id, $this->learner->id, $week, 'Danke']);
        $this->assertFalse($reply['isteacher']);
        $this->assertEquals($this->teachera->id, $sink->get_messages()[0]->useridto);

        $get = $this->call('get_week', [$week]);
        $thread = $this->courseweek($get, $this->coursea->id)['feedback'];
        $this->assertSame(['Gut gemacht!', 'Danke'], array_column($thread, 'message'));
        $this->assertSame([], $this->courseweek($get, $this->courseb->id)['feedback']);
    }

    /**
     * Feedback permissions: only the coach of the course or the learner personally.
     */
    public function test_feedback_permissions(): void {
        $week = weeks::current();
        $this->setUser($this->other);
        $this->expectException(\required_capability_exception::class);
        $this->call('add_feedback', [$this->coursea->id, $this->learner->id, $week, 'Hallo']);
    }

    /**
     * A coach of another course of the learner cannot write in this course.
     */
    public function test_feedback_other_coach(): void {
        $this->setUser($this->teacherb);
        $this->expectException(\moodle_exception::class);
        $this->call('add_feedback', [$this->coursea->id, $this->learner->id, weeks::current(), 'Hallo']);
    }

    /**
     * Visibility in get_learner: goal and general reflection yes, data of other courses no.
     */
    public function test_get_learner_visibility(): void {
        global $DB;
        $week = weeks::current();
        $this->setUser($this->learner);
        $this->call('save_plan', [$week, 'Mein Ziel', [$this->cmsa[0]->id, $this->cmb->id]]);
        $this->call('save_reflection', [$week, 'Allgemein', [
            ['courseid' => $this->coursea->id, 'reflection' => 'Reflexion A', 'selfassessments' => []],
            ['courseid' => $this->courseb->id, 'reflection' => 'Reflexion B', 'selfassessments' => []],
        ]]);

        $this->setUser($this->teachera);
        $this->call('add_feedback', [$this->coursea->id, $this->learner->id, $week, 'Feedback A']);
        $result = $this->call('get_learner', [$this->coursea->id, $this->learner->id]);
        $this->assertSame('Ada Azubi', $result['learner']['fullname']);
        $this->assertCount(1, $result['weeks']);
        $lw = $result['weeks'][0];
        $this->assertSame('Mein Ziel', $lw['goal']);
        $this->assertSame('Allgemein', $lw['generalreflection']);
        $this->assertSame('Reflexion A', $lw['reflection']);
        $this->assertSame([(int)$this->cmsa[0]->id], array_column($lw['items'], 'cmid'));
        $this->assertSame(['Feedback A'], array_column($lw['feedback'], 'message'));
        $this->assertSame((int)$this->coursea->id, $result['ampel']['courseid']);
        $this->assertCount(4, $result['ampel']['signals']);

        // Teacher B sees course B only.
        $this->setUser($this->teacherb);
        $result = $this->call('get_learner', [$this->courseb->id, $this->learner->id]);
        $this->assertSame('Reflexion B', $result['weeks'][0]['reflection']);
        $this->assertSame('Mein Ziel', $result['weeks'][0]['goal']);
        $this->assertSame([], $result['weeks'][0]['feedback']);
        $this->assertTrue(\local_gopilot\local\access::can_read_general($this->teacherb->id, $this->learner->id));
        $this->assertFalse(\local_gopilot\local\access::can_read_course(
            $this->coursea->id,
            $this->teacherb->id,
            $this->learner->id
        ));
        $this->assertFalse(\local_gopilot\local\access::can_read_general($this->other->id, $this->learner->id));
        try {
            $this->call('get_learner', [$this->coursea->id, $this->learner->id]);
            $this->fail('Teacher B must not read course A');
        } catch (\moodle_exception $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        }

        // Teacher A cannot open a non-learner.
        $this->setUser($this->teachera);
        $this->assert_error('notalearner', 'get_learner', [$this->coursea->id, $this->teacherb->id]);
        $this->assertNotEmpty($DB->get_records('local_gopilot_feedback'));
    }

    /**
     * get_overview for learner and teacher.
     */
    public function test_get_overview(): void {
        $this->setUser($this->learner);
        $result = $this->call('get_overview');
        $this->assertEquals($this->learner->id, $result['userid']);
        $this->assertSame(weeks::current(), $result['currentweek']);
        $this->assertCount(2, $result['courses']);
        $a = $result['courses'][array_search($this->coursea->id, array_column($result['courses'], 'id'))];
        $this->assertTrue($a['canuse']);
        $this->assertFalse($a['cancoach']);
        $this->assertSame(['completed' => 0, 'total' => 3], $a['progress']);
        $this->assertSame('green', $a['ampel']['colour'], 'One overdue step is still green');
        $this->assertSame(weeks::current(), $result['week']['week']);

        $this->setUser($this->teachera);
        $result = $this->call('get_overview');
        $this->assertCount(1, $result['courses']);
        $this->assertFalse($result['courses'][0]['canuse']);
        $this->assertTrue($result['courses'][0]['cancoach']);
        $this->assertTrue($result['courses'][0]['canconfigure']);
        $this->assertSame('none', $result['courses'][0]['ampel']['colour']);
        $this->assertCount(4, $result['courses'][0]['ampel']['signals']);
        $this->assertSame([], $result['week']['courses']);
    }

    /**
     * get_lerndoku lists learners with Ampel; learners may not call it.
     */
    public function test_get_lerndoku(): void {
        $week = weeks::add(weeks::current(), -1);
        $this->setUser($this->learner);
        $this->call('save_reflection', [$week, '', [
            ['courseid' => $this->coursea->id, 'reflection' => str_repeat('x', 250), 'selfassessments' => []],
        ]]);
        $this->call('save_plan', [weeks::current(), 'SQL lernen',
            [$this->cmsa[0]->id, $this->cmsa[1]->id, $this->cmb->id]]);
        $completion = new \completion_info($this->coursea);
        $completion->update_state(\cm_info::create($this->cmsa[0]), COMPLETION_COMPLETE, $this->learner->id);

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $class = $this->getDataGenerator()->create_cohort(['name' => 'FIAE24A']);
        $hidden = $this->getDataGenerator()->create_cohort(['name' => 'Versteckt', 'visible' => 0]);
        cohort_add_member($class->id, $this->learner->id);
        cohort_add_member($hidden->id, $this->learner->id);

        $this->setUser($this->teachera);
        $result = $this->call('get_lerndoku', [$this->coursea->id]);
        $this->assertSame(weeks::current(), $result['currentweek']);
        $this->assertCount(2, $result['learners']);
        $row = $result['learners'][array_search($this->learner->id, array_column($result['learners'], 'userid'))];
        $this->assertSame($week, $row['lastreflection']['week']);
        $this->assertSame(200, \core_text::strlen($row['lastreflection']['text']));
        $this->assertSame(0, $row['unreadfeedback']);
        $this->assertSame(3, $row['progress']['total']);
        $this->assertSame([['id' => (int)$class->id, 'name' => 'FIAE24A']], $row['cohorts']);
        $other = $result['learners'][1 - array_search($row, $result['learners'], true)];
        $this->assertSame([], $other['cohorts'], 'Learner without global group');
        $this->assertSame(['goal' => 'SQL lernen', 'planned' => 2, 'completed' => 1, 'plannedall' => 3], $row['thisweek']);
        $this->assertSame(['goal' => '', 'planned' => 0, 'completed' => 0, 'plannedall' => 0], $other['thisweek']);

        $this->setUser($this->learner);
        $this->expectException(\required_capability_exception::class);
        $this->call('get_lerndoku', [$this->coursea->id]);
    }

    /**
     * Course configuration round trip and validation.
     */
    public function test_courseconfig(): void {
        $this->setUser($this->teachera);
        $result = $this->call('get_courseconfig', [$this->coursea->id]);
        $this->assertArrayNotHasKey('teachingweeks', $result);
        $this->assertTrue($result['usedefaults']);
        $this->assertSame(70, $result['thresholds']['plangreen']);
        $this->assertSame($result['defaults'], $result['thresholds']);
        $this->assertCount(2, $result['paths']);
        $this->assertCount(2, $result['paths'][0]['steps'], 'Labels are no learning steps');

        $thresholds = ['plangreen' => 80, 'planyellow' => 50, 'backlogyellow' => 1, 'backlogred' => 3,
            'reflectionyellow' => 1, 'reflectionred' => 2];
        $result = $this->call('save_courseconfig', [$this->coursea->id, false, $thresholds]);
        $this->assertFalse($result['usedefaults']);
        $this->assertSame(80, $result['thresholds']['plangreen']);
        $this->assertSame(70, $result['defaults']['plangreen']);

        $result = $this->call('save_courseconfig', [$this->coursea->id, true, $thresholds]);
        $this->assertTrue($result['usedefaults']);
        $this->assertSame(70, $result['thresholds']['plangreen']);

        $this->assert_error(
            'invalidthresholds',
            'save_courseconfig',
            [$this->coursea->id, false, ['backlogyellow' => 4, 'backlogred' => 4] + $thresholds]
        );

        $this->setUser($this->learner);
        $this->expectException(\required_capability_exception::class);
        $this->call('get_courseconfig', [$this->coursea->id]);
    }

    /**
     * Path metadata appears in get_paths and get_courseconfig.
     */
    public function test_pathmeta_and_paths(): void {
        global $DB;
        $section = $DB->get_record('course_sections', ['course' => $this->coursea->id, 'section' => 1]);
        $this->setUser($this->teachera);
        $result = $this->call('save_pathmeta', [$this->coursea->id, [
            ['type' => 'section', 'id' => $section->id, 'phase' => '', 'duration' => 90, 'level' => 2],
            ['type' => 'cm', 'id' => $this->cmsa[0]->id, 'phase' => 'Informieren', 'duration' => 15, 'level' => 1],
        ]]);
        $this->assertTrue($result['success']);
        $this->call('save_pathmeta', [$this->coursea->id, [
            ['type' => 'cm', 'id' => $this->cmsa[0]->id, 'phase' => 'Planen', 'duration' => 20, 'level' => 1],
        ]]);
        $this->assertSame(2, $DB->count_records('local_gopilot_pathmeta'));

        $this->setUser($this->learner);
        $paths = $this->call('get_paths', [$this->coursea->id]);
        $this->assertSame('LF5', $paths['course']['fullname']);
        $this->assertCount(2, $paths['paths']);
        $this->assertSame(1, $paths['paths'][0]['section']);
        $this->assertSame(['phase' => '', 'duration' => 90, 'level' => 2], $paths['paths'][0]['meta']);
        $this->assertSame(['phase' => 'Planen', 'duration' => 20, 'level' => 1], $paths['paths'][0]['steps'][0]['meta']);
        $this->assertSame(2, $paths['paths'][0]['total']);

        $this->setUser($this->teachera);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('save_pathmeta', [$this->coursea->id, [['type' => 'cm', 'id' => $this->cmb->id]]]);
    }

    /**
     * Learning biography: weeks with data, newest first, paged.
     */
    public function test_biography(): void {
        $this->setUser($this->learner);
        $current = weeks::current();
        $this->call('save_plan', [$current, 'Ziel', []]);
        $this->call('save_plan', [weeks::add($current, 1), '', [$this->cmsa[0]->id]]);
        $this->call('save_plan', [weeks::add($current, 2), '', []]);
        $result = $this->call('get_biography', [0, 1]);
        $this->assertSame(2, $result['total']);
        $this->assertSame(weeks::add($current, 1), $result['weeks'][0]['week']);
        $result = $this->call('get_biography', [1, 1]);
        $this->assertSame($current, $result['weeks'][0]['week']);
    }

    /**
     * Push subscriptions: one owner per endpoint.
     */
    public function test_push_subscriptions(): void {
        global $DB;
        $this->setUser($this->learner);
        $config = $this->call('get_push_config');
        $this->assertFalse($config['enabled']);
        $this->assertSame('', $config['publickey']);

        set_config('vapidpublickey', 'BPub', 'local_gopilot');
        set_config('vapidprivatekey', 'priv', 'local_gopilot');
        $this->assertSame(['enabled' => true, 'publickey' => 'BPub'], $this->call('get_push_config'));

        $endpoint = 'https://push.example.com/send/abc:123';
        $this->assertTrue($this->call('register_push', [$endpoint, 'BKey_-', 'auth'])['success']);
        $this->call('register_push', [$endpoint, 'BKey_-', 'auth']);
        $this->assertSame(1, $DB->count_records('local_gopilot_pushsub'));

        $this->setUser($this->other);
        $this->call('register_push', [$endpoint, 'BKey2', 'auth2']);
        $this->assertSame(1, $DB->count_records('local_gopilot_pushsub', ['userid' => $this->other->id]));
        $this->assertSame(0, $DB->count_records('local_gopilot_pushsub', ['userid' => $this->learner->id]));

        $this->setUser($this->learner);
        $this->call('unregister_push', [$endpoint]);
        $this->assertSame(1, $DB->count_records('local_gopilot_pushsub'), 'Only own subscriptions are removed');
        $this->setUser($this->other);
        $this->call('unregister_push', [$endpoint]);
        $this->assertSame(0, $DB->count_records('local_gopilot_pushsub'));

        $this->expectException(\invalid_parameter_exception::class);
        $this->call('register_push', ['http://insecure.example.com/x', 'a', 'b']);
    }

    /**
     * The service contains all functions of the contract.
     */
    public function test_service_functions(): void {
        global $DB;
        $service = $DB->get_record('external_services', ['shortname' => 'gopilot'], '*', MUST_EXIST);
        $functions = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$service->id]
        );
        $expected = ['get_overview', 'get_paths', 'get_week', 'save_plan', 'save_reflection', 'get_biography',
            'get_lerndoku', 'get_learner', 'add_feedback', 'get_courseconfig', 'save_courseconfig', 'save_pathmeta',
            'get_push_config', 'register_push', 'unregister_push'];
        foreach ($expected as $name) {
            $this->assertContains('local_gopilot_' . $name, $functions);
        }
        $this->assertContains('core_webservice_get_site_info', $functions);
        $this->assertContains('core_completion_update_activity_completion_status_manually', $functions);
    }
}
