<?php
// Testdaten für die lokale GoPilot-Entwicklungsumgebung.
//
// Läuft im Webserver-Container: php /opt/gopilot-dev/seed.php
// Idempotent: Existiert der Testkurs schon, passiert nichts.
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

if ($DB->record_exists('course', ['shortname' => 'GOPILOT-LF5'])) {
    cli_writeln('Testdaten existieren bereits.');
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
