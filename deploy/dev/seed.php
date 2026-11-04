<?php
// Testdaten für die lokale GoPilot-Entwicklungsumgebung.
//
// Läuft im Webserver-Container: php /opt/gopilot-dev/seed.php
// Idempotent: Existiert der Testkurs schon, werden keine Kurse/Konten angelegt.
// Die GoPilot-Daten (Kurseinstellungen, Wochenpläne, Reflexionen, Feedback) werden
// nur angelegt, solange local_gopilot_week leer ist.
//
// Testkonten (nur für die lokale Entwicklungsumgebung):
//   admin       / Admin-Test-123!       (aus start.sh)
//   lehrkraft1  / Lehrkraft-Test-1!     Lehrkraft (editingteacher) in beiden Kursen
//   azubi1..3   / Azubi-Test-1!         Lernende in beiden Kursen

define('CLI_SCRIPT', true);

require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

\core\session\manager::set_user(get_admin());

// Deutsche Oberfläche wie an der Schule.
if (!get_string_manager()->translation_exists('de', false)) {
    $langimport = new \tool_langimport\controller();
    $langimport->install_languagepacks(['de']);
}
set_config('lang', 'de');

if ($DB->record_exists('course', ['shortname' => 'GOPILOT-LF5'])) {
    cli_writeln('Testkurse existieren bereits.');
    gopilot_seed_plugin_data();
    exit(0);
}

// Webservices für die App freischalten (ADR 0006).
set_config('enablewebservices', 1);
set_config('webserviceprotocols', 'rest');
set_config('enablecompletion', 1);
$systemcontext = context_system::instance();
foreach (['moodle/webservice:createtoken', 'webservice/rest:use'] as $capability) {
    assign_capability($capability, CAP_ALLOW, $CFG->defaultuserroleid, $systemcontext->id, true);
}

$generator = new testing_data_generator();

// Montag dieser Woche als Bezugspunkt für die Soll-Termine.
$monday = strtotime('monday this week');
$day = DAYSECS;

/**
 * Kurs mit Lernpfaden (= Abschnitten) und Lernschritten (= Aktivitäten) anlegen.
 *
 * @param testing_data_generator $generator
 * @param array $coursedata
 * @param array $paths Abschnittsname => Liste von Lernschritten
 * @return stdClass
 */
function gopilot_seed_course(testing_data_generator $generator, array $coursedata, array $paths): stdClass {
    $course = $generator->create_course($coursedata + [
        'format' => 'topics',
        'numsections' => count($paths),
        'enablecompletion' => 1,
    ]);
    $sectionnum = 1;
    foreach ($paths as $name => $steps) {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        course_update_section($course, $section, ['name' => $name]);
        foreach ($steps as $step) {
            $record = [
                'course' => $course->id,
                'section' => $sectionnum,
                'name' => $step['name'],
                'completion' => COMPLETION_TRACKING_MANUAL,
                'completionexpected' => $step['expected'] ?? 0,
            ];
            if ($step['type'] === 'assign') {
                $record['duedate'] = $step['expected'] ?? 0;
                $record['intro'] = 'Abgabe: ' . $step['name'];
            } else {
                $record['content'] = '<p>Material: ' . s($step['name']) . '</p>';
            }
            $generator->create_module($step['type'], $record);
        }
        $sectionnum++;
    }
    return $course;
}

$lf5 = gopilot_seed_course($generator, [
    'fullname' => 'LF5 Software zur Verwaltung von Daten anpassen',
    'shortname' => 'GOPILOT-LF5',
], [
    'LS 5.1 Datenbank für den Kundenservice entwerfen' => [
        ['type' => 'page', 'name' => 'Einstieg: Der Auftrag', 'expected' => $monday - 7 * $day],
        ['type' => 'page', 'name' => 'Entity-Relationship-Modell', 'expected' => $monday - 5 * $day],
        ['type' => 'page', 'name' => 'Normalisierung', 'expected' => $monday - 3 * $day],
        ['type' => 'assign', 'name' => 'ER-Modell abgeben', 'expected' => $monday - 1 * $day],
    ],
    'LS 5.2 SQL-Abfragen für Auswertungen' => [
        ['type' => 'page', 'name' => 'SELECT und WHERE', 'expected' => $monday + 1 * $day],
        ['type' => 'page', 'name' => 'JOINs', 'expected' => $monday + 3 * $day],
        ['type' => 'page', 'name' => 'Gruppieren und Aggregieren'],
        ['type' => 'assign', 'name' => 'Auswertung abgeben', 'expected' => $monday + 9 * $day],
    ],
]);

$lf8 = gopilot_seed_course($generator, [
    'fullname' => 'LF8 Daten systemübergreifend bereitstellen',
    'shortname' => 'GOPILOT-LF8',
], [
    'LS 8.1 REST-Schnittstelle nutzen' => [
        ['type' => 'page', 'name' => 'HTTP und JSON', 'expected' => $monday + 2 * $day],
        ['type' => 'page', 'name' => 'API-Dokumentation lesen', 'expected' => $monday + 4 * $day],
        ['type' => 'assign', 'name' => 'API-Client abgeben', 'expected' => $monday + 11 * $day],
    ],
]);

$teacher = $generator->create_user([
    'username' => 'lehrkraft1', 'password' => 'Lehrkraft-Test-1!',
    'firstname' => 'Lena', 'lastname' => 'Lehrkraft', 'email' => 'lehrkraft1@example.com',
]);
$learners = [];
foreach ([1 => 'Ada', 2 => 'Ben', 3 => 'Can'] as $i => $firstname) {
    $learners[$i] = $generator->create_user([
        'username' => 'azubi' . $i, 'password' => 'Azubi-Test-1!',
        'firstname' => $firstname, 'lastname' => 'Azubi', 'email' => "azubi{$i}@example.com",
    ]);
}

foreach ([$lf5, $lf8] as $course) {
    $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
    foreach ($learners as $learner) {
        $generator->enrol_user($learner->id, $course->id, 'student');
    }
}

// Unterschiedliche Lernstände, damit später alle Ampelfarben vorkommen:
// azubi1 ist im Plan, azubi2 hängt etwas hinterher, azubi3 hat noch nichts erledigt.
$done = [1 => 4, 2 => 2, 3 => 0];
$lf5cms = array_values(get_fast_modinfo($lf5)->get_cms());
$completion = new completion_info($lf5);
foreach ($done as $i => $count) {
    foreach (array_slice($lf5cms, 0, $count) as $cm) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $learners[$i]->id);
    }
}

cli_writeln('Testdaten angelegt: 2 Kurse, 1 Lehrkraft, 3 Azubis.');
gopilot_seed_plugin_data();

/**
 * GoPilot-Daten anlegen, damit alle Ampelfarben vorkommen (idempotent über local_gopilot_week).
 *
 * azubi1: LF5 grün (Plan erfüllt, reflektiert, „sicher“), LF8 gelb (Plan 50 %, Reflexion fehlt, „unsicher“).
 * azubi2: LF5 rot („komme nicht klar“, Plan 50 %), LF8 rot (2 Schulwochen ohne Reflexion), Feedback-Faden.
 * azubi3: nichts geplant und reflektiert, viele überfällige Schritte: rot.
 *
 * @return void
 */
function gopilot_seed_plugin_data(): void {
    global $DB;
    if (!$DB->get_manager()->table_exists('local_gopilot_week')) {
        cli_writeln('Plugin local_gopilot ist nicht installiert, GoPilot-Daten übersprungen.');
        return;
    }

    // Web-Push-Schlüssel für die Entwicklung.
    if (!\local_gopilot\push\sender::is_enabled()) {
        $keys = \local_gopilot\push\vapid::generate_keys();
        set_config('vapidpublickey', $keys['publickey'], 'local_gopilot');
        set_config('vapidprivatekey', $keys['privatekey'], 'local_gopilot');
        set_config('vapidsubject', 'mailto:admin@example.com', 'local_gopilot');
        cli_writeln('VAPID-Schlüssel erzeugt.');
    }

    if ($DB->record_exists('local_gopilot_week', [])) {
        cli_writeln('GoPilot-Daten existieren bereits.');
        return;
    }

    $weeks = '\local_gopilot\local\weeks';
    $current = $weeks::current();
    $w = function (int $offset) use ($weeks, $current): string {
        return $weeks::add($current, $offset);
    };
    $lf5 = $DB->get_record('course', ['shortname' => 'GOPILOT-LF5'], '*', MUST_EXIST);
    $lf8 = $DB->get_record('course', ['shortname' => 'GOPILOT-LF8'], '*', MUST_EXIST);
    $teacher = $DB->get_record('user', ['username' => 'lehrkraft1'], '*', MUST_EXIST);
    $users = [];
    foreach ([1, 2, 3] as $i) {
        $users[$i] = $DB->get_record('user', ['username' => 'azubi' . $i], '*', MUST_EXIST);
    }

    // Kurseinstellungen mit Standard-Schwellwerten (macht die Kurse für die nächtliche Ampel bekannt).
    \local_gopilot\local\courseconfig::save($lf5->id, true, []);
    \local_gopilot\local\courseconfig::save($lf8->id, true, []);

    $cms5 = array_values(array_filter(get_fast_modinfo($lf5)->get_cms(), fn($cm) => $cm->modname !== 'label'));
    $cms8 = array_values(get_fast_modinfo($lf8)->get_cms());
    $section = function (stdClass $course, int $num) use ($DB): int {
        return (int)$DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => $num]);
    };
    $now = time();

    $week = function (stdClass $user, string $week, string $goal, string $general) use ($DB, $now): int {
        return (int)$DB->insert_record('local_gopilot_week', (object)[
            'userid' => $user->id, 'week' => $week, 'goal' => $goal, 'generalreflection' => $general,
            'reflectedat' => $general !== '' ? $now - DAYSECS : 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    };
    $plan = function (int $weekid, array $items) use ($DB, $now): void {
        // Items: [cm, completedatclose or null].
        foreach ($items as $i => [$cm, $done]) {
            $DB->insert_record('local_gopilot_planitem', (object)[
                'weekid' => $weekid, 'courseid' => $cm->course, 'cmid' => $cm->id, 'sortorder' => $i,
                'completedatclose' => $done, 'timecreated' => $now,
            ]);
        }
    };
    $reflect = function (int $weekid, stdClass $course, string $text) use ($DB, $now): void {
        $DB->insert_record('local_gopilot_coursereflection', (object)[
            'weekid' => $weekid, 'courseid' => $course->id, 'text' => $text, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    };
    $assess = function (int $weekid, stdClass $course, int $sectionid, int $level, string $comment) use ($DB, $now): void {
        $DB->insert_record('local_gopilot_selfassessment', (object)[
            'weekid' => $weekid, 'courseid' => $course->id, 'sectionid' => $sectionid, 'selflevel' => $level,
            'remark' => $comment, 'timemodified' => $now,
        ]);
    };
    $feedback = function (int $weekid, stdClass $course, stdClass $author, string $message, int $time) use ($DB): void {
        $DB->insert_record('local_gopilot_feedback', (object)[
            'weekid' => $weekid, 'courseid' => $course->id, 'authorid' => $author->id, 'message' => $message,
            'timecreated' => $time,
        ]);
    };

    // Azubi 1.
    $id = $week($users[1], $w(-3), 'Schuljahr starten', 'Neue Klasse, neue Lernfelder.');
    $reflect($id, $lf5, 'Lernfeld 5 kennengelernt.');
    $reflect($id, $lf8, 'HTTP-Grundlagen wiederholt.');
    $id = $week($users[1], $w(-2), 'LS 5.1 anfangen', 'Guter Einstieg in die Datenbanken.');
    $reflect($id, $lf5, 'Den Auftrag verstanden und mit dem ER-Modell begonnen.');
    $assess($id, $lf5, $section($lf5, 1), 2, '');
    $id = $week($users[1], $w(-1), 'ER-Modell fertigstellen und mit der REST-API starten',
        'Die Woche lief gut, nur für LF8 blieb wenig Zeit.');
    $plan($id, [[$cms5[0], 1], [$cms5[1], 1], [$cms5[2], 1], [$cms5[3], 1], [$cms8[0], 1], [$cms8[1], 0]]);
    $reflect($id, $lf5, 'ER-Modell abgegeben, die Normalisierung hat am längsten gedauert.');
    $assess($id, $lf5, $section($lf5, 1), 1, '');
    $reflect($id, $lf8, '');
    $assess($id, $lf8, $section($lf8, 1), 3, 'Die API-Dokumentation ist schwer zu lesen.');
    $feedback($id, $lf5, $teacher, 'Starke Woche! Achte bei der Normalisierung auf die 3. Normalform.', $now - 3 * HOURSECS);
    $id = $week($users[1], $w(0), 'SQL-Grundlagen lernen', '');
    $plan($id, [[$cms5[4], null], [$cms5[5], null], [$cms8[1], null]]);

    // Azubi 2.
    $id = $week($users[2], $w(-1), 'LS 5.1 aufholen', 'Ich war zwei Tage krank.');
    $plan($id, [[$cms5[0], 1], [$cms5[1], 1], [$cms5[2], 0], [$cms5[3], 0]]);
    $reflect($id, $lf5, 'Die Normalisierung verstehe ich noch nicht.');
    $assess($id, $lf5, $section($lf5, 1), 4, 'Normalformen sind mir unklar.');
    $feedback($id, $lf5, $teacher, 'Melde dich gern, dann gehen wir die Normalisierung zusammen durch.',
        $now - 2 * HOURSECS);
    $feedback($id, $lf5, $users[2], 'Danke, ich komme am Mittwoch vorbei.', $now - HOURSECS);

    // Ampeln einmal berechnen, damit die Lerndoku sofort Werte hat.
    $calculator = new \local_gopilot\ampel\calculator();
    foreach ($users as $user) {
        foreach ([$lf5, $lf8] as $course) {
            $result = $calculator->compute_and_store($user->id, $course->id);
            cli_writeln("Ampel {$user->username} {$course->shortname}: {$result['colour']}");
        }
    }
    cli_writeln('GoPilot-Daten angelegt.');
}
