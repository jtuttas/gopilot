# API-Vertrag Version 1 (Dienst `gopilot`)

Verbindliche Schnittstelle zwischen Plugin `local_gopilot` und App. Plugin (`classes/external/*`) und App (`app/src/api/`) halten sich genau an diese Namen und Typen. Änderungen zuerst hier eintragen.

## Allgemeines

- Aufruf: `POST {moodle}/webservice/rest/server.php`, `Content-Type: application/x-www-form-urlencoded`, Felder `wstoken`, `wsfunction`, `moodlewsrestformat=json` und die Parameter. Listen und Objekte in Moodle-Notation: `cmids[0]=5&cmids[1]=7`, `courses[0][courseid]=2&courses[0][selfassessments][0][level]=3`.
- Fehler: Moodle liefert HTTP 200 mit `{"exception": "...", "errorcode": "...", "message": "..."}`. Die App wirft dann einen Fehler mit `message`. Bei `errorcode` `invalidtoken` oder `accessexception` meldet die App ab.
- Anmeldung: `POST {moodle}/login/token.php` mit `username`, `password`, `service=gopilot`. Antwort `{"token": "..."}` oder `{"error": "...", "errorcode": "..."}`.
- **Woche** (`week`): Datum des Montags als Zeichenkette `YYYY-MM-DD`, ausgewertet in der Zeitzone der Moodle-Instanz (`core_date::get_server_timezone()`). Das Plugin prüft, dass es ein Montag ist (`invalidweek`). Eine Woche ist **abgeschlossen**, wenn ihr Sonntag 23:59:59 vorbei ist.
- Zeitpunkte sind Unix-Sekunden (`int`), `0` heißt „nicht gesetzt“.
- Freitexte sind reiner Text (`PARAM_TEXT`), keine HTML-Formatierung.
- Booleans kommen als `true`/`false` (PARAM_BOOL), Parameter als `0`/`1`.

## Gemeinsame Strukturen

### `step` (Lernschritt)

| Feld | Typ | Bedeutung |
|---|---|---|
| `cmid` | int | Kursmodul-ID |
| `courseid` | int | |
| `sectionid` | int | Lernpfad (Abschnitt) |
| `name` | string | Name der Aktivität |
| `modname` | string | z. B. `page`, `assign`, `quiz` |
| `url` | string | Link zur Aktivität in Moodle |
| `tracked` | bool | hat Abschlussbedingung |
| `completed` | bool | erledigt (inkl. bestanden) |
| `manual` | bool | Lernende dürfen selbst abhaken (`COMPLETION_TRACKING_MANUAL`) |
| `expected` | int | Soll-Termin: `completionexpected`, sonst Fälligkeitsdatum (`assign.duedate`, `quiz.timeclose`), sonst 0 |
| `overdue` | bool | `expected > 0`, vorbei und nicht erledigt |
| `meta` | `meta` | Pfad-Metadaten |

Nur sichtbare, für die Person verfügbare Kursmodule, ohne Textfelder (`label`) und ohne Abschnitt 0.

### `meta` (Pfad-Metadaten)

| Feld | Typ | Bedeutung |
|---|---|---|
| `phase` | string | frei, z. B. „Informieren“, leer = keine |
| `duration` | int | Minuten, 0 = keine Angabe |
| `level` | int | Niveau 0 = keine Angabe, 1 Basis, 2 Standard, 3 Experte |

### `ampel`

| Feld | Typ | Bedeutung |
|---|---|---|
| `courseid` | int | |
| `colour` | string | `green`, `yellow`, `red`; `none`, wenn kein Signal aktiv ist |
| `timecomputed` | int | |
| `signals` | list of `signal` | immer alle vier, in der Reihenfolge `plan`, `backlog`, `reflection`, `selfassessment` |

`signal`: `key` (string, s. o.), `active` (bool, ob das Signal auswertbar ist), `colour` (`green`/`yellow`/`red`/`none`), `value` (int, Rohwert: Prozent, Anzahl überfälliger Schritte, Anzahl Wochen, Stufe 1–4; 0 wenn inaktiv), `reason` (string, deutscher Begründungstext für die Anzeige, z. B. „2 von 6 geplanten Lernschritten erledigt (33 %)“, bei inaktiv z. B. „Keine Schulwoche“).

### `feedbackentry`

`id` int, `authorid` int, `authorname` string, `isteacher` bool (Autor:in ist nicht die:der Lernende), `message` string, `timecreated` int.

### `selfassessment`

`sectionid` int, `name` string (Name des Lernpfads), `level` int (0 = nicht angegeben, 1 sicher, 2 geht so, 3 unsicher, 4 komme nicht klar), `comment` string.

### `courseweek` (Kursteil einer Woche)

| Feld | Typ | Bedeutung |
|---|---|---|
| `courseid` | int | |
| `fullname` | string | |
| `isschoolweek` | bool | Woche ist Unterrichtswoche dieses Kurses |
| `reflection` | string | Kursreflexion, leer = keine |
| `selfassessments` | list of `selfassessment` | für jeden Lernpfad mit geplanten Schritten dieser Woche ein Eintrag (auch wenn `level` 0) |
| `feedback` | list of `feedbackentry` | Faden zu dieser Woche und diesem Kurs, älteste zuerst |

### `week`

| Feld | Typ | Bedeutung |
|---|---|---|
| `week` | string | Montag `YYYY-MM-DD` |
| `closed` | bool | Woche abgeschlossen |
| `goal` | string | Wochenziel |
| `generalreflection` | string | allgemeine Reflexion |
| `reflectedat` | int | Zeitpunkt der letzten gespeicherten Reflexion, 0 = keine |
| `items` | list of `step` | geplante Lernschritte; bei abgeschlossenen Wochen ist `completed` der eingefrorene Stand zum Wochenende (`completedatclose`) |
| `courses` | list of `courseweek` | jeder Kurs, in dem die Person `use` hat |

## Funktionen für Lernende (Capability `local/gopilot:use`)

### `local_gopilot_get_overview`

Parameter: keine.

Rückgabe:

| Feld | Typ |
|---|---|
| `userid` | int |
| `fullname` | string |
| `currentweek` | string (Montag dieser Woche) |
| `courses` | list: `id`, `shortname`, `fullname`, `canuse` bool, `cancoach` bool, `canconfigure` bool, `isschoolweek` bool (aktuelle Woche), `progress` (`completed` int, `total` int über alle Lernschritte mit Abschlussbedingung), `ampel` (`ampel`, nur sinnvoll bei `canuse`; sonst `colour` = `none`) |
| `week` | `week` (aktuelle Woche) |

Alle Kurse, in denen die Person `use`, `coach` oder `configure` hat. Die Ampel wird beim Aufruf neu berechnet und gespeichert.

### `local_gopilot_get_paths`

Parameter: `courseid` int.

Rückgabe: `course` (`id`, `fullname`), `paths`: list of `sectionid`, `section` (Nummer), `name`, `summary` (Text ohne HTML), `meta` (`meta` des Abschnitts), `completed` int, `total` int, `steps` (list of `step`).

### `local_gopilot_get_week`

Parameter: `week` string.

Rückgabe: `week`.

### `local_gopilot_save_plan`

Parameter: `week` string, `goal` string, `cmids` list of int.

Ersetzt die Planeinträge der Woche. Erlaubt nur für die aktuelle und künftige Wochen (`weekclosed`). Jedes `cmid` muss in einem Kurs mit `use` liegen. Rückgabe: `week`.

### `local_gopilot_save_reflection`

Parameter: `week` string, `generalreflection` string, `courses` list of: `courseid` int, `reflection` string, `selfassessments` list of (`sectionid` int, `level` int 0–4, `comment` string).

Erlaubt für die aktuelle Woche und die vorige Woche (Reflexion am Wochenende oder Montag danach), sonst `reflectionclosed`. Setzt `reflectedat`. Löst Ereignis und Benachrichtigung an die Kurslehrkräfte aus und berechnet die Ampel neu. Rückgabe: `week`.

### `local_gopilot_get_biography`

Parameter: `page` int (ab 0), `perpage` int (Standard 10).

Rückgabe: `total` int (Anzahl Wochen mit Daten), `weeks` list of `week`, neueste zuerst. Nur Wochen, in denen es einen Plan, ein Ziel oder eine Reflexion gibt.

## Funktionen für den Lerncoach (Capability `local/gopilot:coach` im Kurs)

### `local_gopilot_get_lerndoku`

Parameter: `courseid` int.

Rückgabe: `course` (`id`, `fullname`), `currentweek` string, `isschoolweek` bool, `learners` list of:

| Feld | Typ |
|---|---|
| `userid` | int |
| `fullname` | string |
| `ampel` | `ampel` (neu berechnet) |
| `progress` | `completed`, `total` |
| `lastreflection` | `week` string (leer = nie), `text` string (Kursreflexion, auf 200 Zeichen gekürzt) |
| `unreadfeedback` | int (0 in V1, reserviert) |

Lernende = alle mit `use` im Kurs.

### `local_gopilot_get_learner`

Parameter: `courseid` int, `userid` int.

Rückgabe: `learner` (`id`, `fullname`), `ampel`, `weeks` list of `learnerweek`, neueste zuerst, nur Wochen mit Daten:

`learnerweek`: `week` string, `closed` bool, `isschoolweek` bool, `goal` string, `generalreflection` string, `reflectedat` int, `items` (list of `step`, nur Lernschritte dieses Kurses), `reflection` string, `selfassessments`, `feedback`.

Wochenziel und allgemeine Reflexion sind für jeden Lerncoach eines Kurses der Person lesbar, alles andere nur für diesen Kurs.

### `local_gopilot_add_feedback`

Parameter: `courseid` int, `userid` int (die:der Lernende), `week` string, `message` string.

Auch die:der Lernende selbst darf antworten (`use` im Kurs und `userid` = eigene ID), der Faden ist ein Gespräch. Benachrichtigt die jeweils andere Seite. Rückgabe: `feedbackentry`.

## Funktionen für Kurseinstellungen (Capability `local/gopilot:configure`)

### `local_gopilot_get_courseconfig`

Parameter: `courseid` int.

Rückgabe:

| Feld | Typ |
|---|---|
| `courseid` | int |
| `teachingweeks` | list of string (Montage) |
| `usedefaults` | bool |
| `thresholds` | `thresholds` (wirksame Werte) |
| `defaults` | `thresholds` (Standardwerte) |
| `paths` | list of `sectionid`, `name`, `meta`, `steps` (list of `cmid`, `name`, `modname`, `meta`) |

`thresholds`: `plangreen` int (70, ab diesem Prozentwert grün), `planyellow` int (40, ab diesem Wert gelb, darunter rot), `backlogyellow` int (2), `backlogred` int (4), `reflectionyellow` int (1), `reflectionred` int (2).

### `local_gopilot_save_courseconfig`

Parameter: `courseid` int, `teachingweeks` list of string, `usedefaults` bool, `thresholds` (`thresholds`, wird ignoriert bei `usedefaults`).

Prüft `planyellow < plangreen`, `backlogyellow < backlogred`, `reflectionyellow < reflectionred`, sonst `invalidthresholds`. Rückgabe wie `get_courseconfig`.

### `local_gopilot_save_pathmeta`

Parameter: `courseid` int, `items` list of: `type` string (`section` oder `cm`), `id` int, `phase` string, `duration` int, `level` int.

Rückgabe: `success` bool.

## Push (Capability: angemeldet)

### `local_gopilot_get_push_config`

Rückgabe: `enabled` bool (VAPID-Schlüssel vorhanden), `publickey` string (VAPID, Base64-URL).

### `local_gopilot_register_push`

Parameter: `endpoint` string, `p256dh` string, `auth` string. Rückgabe: `success` bool. Ein Endpoint gehört höchstens einer Person (ersetzt alte Einträge).

### `local_gopilot_unregister_push`

Parameter: `endpoint` string. Rückgabe: `success` bool.

## Funktionen aus dem Moodle-Kern im Dienst

- `core_webservice_get_site_info`
- `core_completion_update_activity_completion_status_manually` (`cmid`, `completed`): Lernschritt abhaken

## Ampel-Regeln (Berechnung im Plugin)

Für `(userid, courseid)` zum Zeitpunkt der Berechnung:

1. **`plan` (Planerfüllung):** Bezugswoche ist die zuletzt abgeschlossene Woche. Aktiv nur, wenn sie eine Schulwoche des Kurses ist **und** die Person darin mindestens einen Lernschritt dieses Kurses geplant hat. Wert = Anteil erledigter geplanter Schritte (erledigt bis Sonntag 23:59:59, also `completedatclose` bzw. `timemodified` des Abschlusses ≤ Wochenende), ganzzahlig gerundet. Grün ab `plangreen`, gelb ab `planyellow`, sonst rot. Begründung „4 von 6 geplanten Lernschritten erledigt (67 %)“. Inaktiv: „Keine Schulwoche“ bzw. „Kein Lernschritt dieses Kurses geplant“.
2. **`backlog` (Pfadrückstand):** immer aktiv, wenn der Kurs Abschlussverfolgung hat. Wert = Anzahl `overdue`-Lernschritte im Kurs. Gelb ab `backlogyellow`, rot ab `backlogred`. Begründung „3 Lernschritte überfällig“ bzw. „Keine überfälligen Lernschritte“.
3. **`reflection` (fehlende Reflexion):** aktiv, wenn der Kurs Unterrichtswochen hat und mindestens eine abgeschlossene Schulwoche existiert. Wert = Anzahl der zuletzt abgeschlossenen Schulwochen in Folge (von der neuesten rückwärts) ohne Kursreflexion. Gelb ab `reflectionyellow`, rot ab `reflectionred`. Inaktiv ohne Unterrichtswochen: „Keine Unterrichtswochen hinterlegt“.
4. **`selfassessment`:** aktiv, wenn es eine Woche mit Selbsteinschätzung (`level` > 0) in diesem Kurs gibt. Wert = schlechteste Stufe der neuesten solchen Woche. 1–2 grün, 3 gelb, 4 rot. Begründung „Selbsteinschätzung „unsicher“ in LS 5.2 (KW 45)“.

Gesamtfarbe = schlechteste Farbe der aktiven Signale, `none`, wenn keins aktiv ist.

## Benachrichtigungen (Message-Provider des Plugins)

| Provider | Empfänger | Auslöser |
|---|---|---|
| `feedback` | Lernende:r bzw. Lerncoach | neuer Eintrag im Feedback-Faden |
| `reflection` | Lerncoaches des Kurses | Lernende:r speichert Kursreflexion (Standard: nur Web) |
| `reflectiondue` | Lernende:r | geplante Aufgabe freitags 14 Uhr, Schulwoche, noch keine Reflexion |
| `ampelchange` | Lernende:r | Ampel eines Kurses wechselt auf Gelb oder Rot |

Jede Nachricht wird zusätzlich als Web-Push an die Abos der Person verschickt (Titel = Betreff, Text = kurze Nachricht, `url` = Pfad in der App). Die App kennt diese Pfade als Routen, auch als Deep-Link:

| Pfad | Ansicht | genutzt von |
|---|---|---|
| `/woche/{week}` | Woche mit Plan, Reflexion und Feedback-Faden | `feedback` an Lernende, `reflectiondue` |
| `/lerndoku/{courseid}/{userid}` | Lernenden-Detail | `feedback` und `reflection` an den Lerncoach |
| `/` | Start | `ampelchange` |

Links in den Moodle-Nachrichten selbst (z. B. per E-Mail) setzt das Plugin nur, wenn die Admin-Einstellung `local_gopilot | appurl` die Adresse der App enthält.

Das Plugin benötigt Moodle 4.2 oder neuer (`core_external\external_api`).
