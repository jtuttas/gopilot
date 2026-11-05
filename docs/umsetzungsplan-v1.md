# Umsetzungsplan GoPilot Version 1

Stand: 2026-11-03. Grundlage sind [CONTEXT.md](../CONTEXT.md) und die ADRs 0001–0007. Begriffe werden wie im Glossar verwendet.

> **Umsetzungsstand:** M0 bis M7 sind umgesetzt. Die verbindliche Schnittstelle steht in [api-v1.md](api-v1.md). Abweichungen vom Plan: Das Plugin braucht Moodle 4.2 oder neuer; Web-Push ist ohne `minishlink/web-push` direkt mit OpenSSL umgesetzt (VAPID ES256, aes128gcm nach RFC 8291); die UI-Bibliothek ist Mantine 7. Offen ist M8 (Pilot) und ein Test des Push-Versands an echte Geräte.

## 1. Überblick

GoPilot besteht aus zwei Artefakten in einem Repository:

```
gopilot/
├── plugin/local_gopilot/   Moodle-Plugin (PHP): Daten, Webservices, Ampel, Push
├── app/                    PWA (React + TypeScript + Vite)
└── deploy/                 Entwicklungsumgebung und Hosting der App
```

Die Fachlogik liegt im Plugin, vor allem die Ampel-Berechnung. Die App zeigt an und erfasst Eingaben. So rechnen Lerndoku, Lernenden-Ansicht und Push-Auslöser mit derselben Logik.

### Annahmen (vor Start bestätigen)

- **Moodle 4.1 LTS oder neuer**, mit HTTPS (nötig für Web-Push und Service Worker).
- **Die App läuft auf einer eigenen Domain** (ADR 0007), z. B. `https://gopilot.schule.de`, als statische Dateien mit HTTPS. Moodle erlaubt Aufrufe von fremden Ursprüngen an `login/token.php` und `webservice/rest/server.php` selbst (`Access-Control-Allow-Origin: *`); am Moodle-Webserver ist keine Konfiguration nötig (ADR 0007).
- In der Moodle-Administration sind Webservices, das REST-Protokoll und der Dienst `gopilot` aktiviert, und Lernende wie Lehrkräfte dürfen Tokens erzeugen (ADR 0006).

## 2. Plugin `local_gopilot`

### 2.1 Datenbanktabellen

| Tabelle | Inhalt | Wichtige Felder |
|---|---|---|
| `local_gopilot_week` | Planungswoche einer:eines Lernenden | `userid`, `weekstart` (Montag 00:00), `goal` (Wochenziel), `generalreflection` (allgemeine Reflexion), `reflectedat`; eindeutig `(userid, weekstart)` |
| `local_gopilot_planitem` | Lernschritt im Wochenplan | `weekid`, `courseid`, `cmid`, `completedatclose` (Abschlussstand bei Wochenende, für die Lernbiografie eingefroren) |
| `local_gopilot_coursereflection` | Kursreflexion | `weekid`, `courseid`, `text` |
| `local_gopilot_selfassessment` | Selbsteinschätzung pro Lernpfad | `weekid`, `courseid`, `sectionid`, `level` (1 sicher … 4 komme nicht klar), `comment` |
| `local_gopilot_feedback` | Coaching-Feedback-Faden | `weekid`, `courseid`, `authorid`, `message`, `timecreated` |
| `local_gopilot_courseconfig` | Kurs-Metadaten | `courseid`, `teachingweeks` (seit 0.4.0 ungenutzt, ADR 0008), `thresholds` (JSON, leer = Standardwerte) |
| `local_gopilot_pathmeta` | Pfad-Metadaten | `cmid` bzw. `sectionid`, `phase`, `duration` (Minuten), `level` |
| `local_gopilot_ampel` | Zuletzt berechnete Förder-Ampel | `userid`, `courseid`, `colour`, `signals` (JSON mit Einzelsignalen und Begründung), `timecomputed` |
| `local_gopilot_pushsub` | Web-Push-Abos | `userid`, `endpoint`, `p256dh`, `auth`, `timecreated` |

Die Lerndaten (Abschlüsse, Soll-Termine, Abschnitte) liest das Plugin direkt aus den Moodle-Kerntabellen bzw. über die Completion-API. Nichts davon wird dupliziert.

### 2.2 Berechtigungen

| Capability | Kontext | Standardrollen | Zweck |
|---|---|---|---|
| `local/gopilot:use` | Kurs | student | Lernpfade sehen, planen, reflektieren |
| `local/gopilot:coach` | Kurs | teacher, editingteacher | Lerndoku, Kursreflexionen lesen, Feedback geben |
| `local/gopilot:configure` | Kurs | editingteacher | Schwellwerte, Pfad-Metadaten pflegen |

Sichtbarkeitsregeln (ADR 0003/0004): Wochenziel und allgemeine Reflexion darf jede Person mit `coach` in *irgendeinem* Kurs der:des Lernenden lesen. Kursreflexion, Selbsteinschätzung, Feedback und Ampel eines Kurses nur mit `coach` in *diesem* Kurs. Lernende sehen nur ihre eigenen Daten.

### 2.3 Webservice-Funktionen (Dienst `gopilot`)

**Lernende**

| Funktion | Zweck |
|---|---|
| `local_gopilot_get_overview` | Startseite: Kurse, aktuelle Planungswoche, eigene Ampeln mit Signalen |
| `local_gopilot_get_paths` | Lernpfade eines Kurses: Abschnitte, Lernschritte, Abschlussstatus, Soll-Termine, Pfad-Metadaten |
| `local_gopilot_get_week` | Wochenplan, Wochenziel, Reflexionen und Feedback einer Planungswoche |
| `local_gopilot_save_plan` | Wochenziel und Lernschritte einer Woche speichern |
| `local_gopilot_save_reflection` | Allgemeine Reflexion, Kursreflexionen und Selbsteinschätzungen speichern |
| `local_gopilot_get_biography` | Lernbiografie (alle Wochen, seitenweise) |

**Lerncoach**

| Funktion | Zweck |
|---|---|
| `local_gopilot_get_lerndoku` | Alle Lernenden eines Kurses mit Ampel und Signalen |
| `local_gopilot_get_learner` | Detailansicht einer:eines Lernenden im Kurs (Wochen, Kursreflexionen, Feedback-Faden) |
| `local_gopilot_add_feedback` | Coaching-Feedback an einer Kursreflexion |
| `local_gopilot_get_courseconfig` / `local_gopilot_save_courseconfig` | Schwellwerte |
| `local_gopilot_save_pathmeta` | Pfad-Metadaten |

**Beide**

| Funktion | Zweck |
|---|---|
| `local_gopilot_register_push` / `local_gopilot_unregister_push` | Web-Push-Abo verwalten |

Dazu kommen aus dem Moodle-Kern: `core_webservice_get_site_info` (eigene Person) und `core_completion_update_activity_completion_status_manually` (Lernschritt manuell abhaken).

### 2.4 Ampel-Berechnung

Eine PHP-Klasse `\local_gopilot\ampel\calculator` berechnet für `(userid, courseid)` die vier Signale nach ADR 0003:

1. **Planerfüllung:** Basis sind die geplanten Lernschritte dieses Kurses in der letzten abgeschlossenen Planungswoche.
2. **Pfadrückstand:** Lernschritte des Kurses mit Abschlussverfolgung und `completionexpected` bzw. Fälligkeitsdatum in der Vergangenheit, die nicht abgeschlossen sind.
3. **Fehlende Reflexion:** aufeinanderfolgende vergangene Wochen mit geplanten Lernschritten des Kurses, aber ohne Kursreflexion (ADR 0008).
4. **Selbsteinschätzung:** schlechtester Wert der letzten Reflexion in diesem Kurs.

Gesamtfarbe = schlechtestes Signal. Schwellwerte kommen aus `courseconfig`, ersatzweise aus den Standardwerten. Das Ergebnis enthält zu jedem Signal einen Begründungstext, den die App direkt anzeigen kann (z. B. „2 von 6 geplanten Schritten erledigt“).

Diese Klasse ist der fachlich kritischste Teil und bekommt vollständige PHPUnit-Tests, auch für die Randfälle Woche ohne Plan, Lernschritt ohne Termin und Kurs ohne Einstellungen.

### 2.5 Hintergrundaufgaben und Benachrichtigungen

| Auslöser | Umsetzung | Benachrichtigung |
|---|---|---|
| Neues Coaching-Feedback | direkt in `add_feedback` | an Lernende:n |
| Reflexion fällig | geplante Aufgabe, freitags 14 Uhr | an Lernende, die den Kurs diese Woche geplant, aber nicht reflektiert haben |
| Neue Reflexion | Ereignis beim Speichern | an Kurslehrkräfte (Moodle-Einstellung, Standard: nur Web) |
| Ampel neu berechnen | geplante Aufgabe nachts plus nach jedem Speichern von Plan oder Reflexion | bei Wechsel zu Gelb oder Rot: an Lernende:n |

Alle Benachrichtigungen laufen über einen Moodle-Message-Provider (ADR 0005). Ein eigener Message-Output bzw. ein Hook verschickt zusätzlich Web-Push an die Abos aus `local_gopilot_pushsub`, mit der Bibliothek `minishlink/web-push` und VAPID-Schlüsseln aus den Plugin-Einstellungen.

## 3. App (PWA)

### 3.1 Technik

- React + TypeScript + Vite; TanStack Query für alle Webservice-Aufrufe; React Router.
- `vite-plugin-pwa` (Service Worker, Manifest, Push-Empfang).
- UI-Komponenten: Mantine oder shadcn/ui (Entscheidung beim Setup).
- Ein Ordner `src/api/` mit typisierten Wrappern pro Webservice-Funktion. Alle Aufrufe gehen per `POST` als `application/x-www-form-urlencoded` mit `wstoken` im Formular, damit sie ohne CORS-Preflight auskommen (ADR 0007). Die Typen werden von Hand aus den `external_*`-Definitionen des Plugins gepflegt.
- PDF-Export der Lernbiografie läuft im Browser (`@react-pdf/renderer`), ohne Server.

### 3.2 Ansichten

**Lernende (mobile-first)**

1. **Anmeldung:** Formular mit Benutzername und Passwort, dann `login/token.php?service=gopilot`. Nur das Token wird gespeichert.
2. **Start:** aktuelle Woche mit Wochenziel und geplanten Schritten samt Haken, dazu eigene Ampeln pro Kurs mit Begründung.
3. **Lernpfade:** Kurs, dann Lernpfad, dann Lernschritte mit Status, Soll-Termin, Metadaten und Link zur Moodle-Aktivität.
4. **Wochenplan:** Lernschritte aus allen Kursen auswählen und Wochenziel formulieren.
5. **Reflexion:** allgemeine Reflexion (Hinweis „sichtbar für alle deine Lehrkräfte“), dann pro Kurs die Kursreflexion und die Selbsteinschätzung pro bearbeitetem Lernpfad.
6. **Lernbiografie:** Wochenliste mit Plan, Reflexion und Feedback-Fäden, dazu der PDF-Export.

**Lerncoach (Desktop-Layout)**

7. **Lerndoku:** Kursauswahl, dann eine Tabelle aller Lernenden mit Ampel, Einzelsignalen und letzter Reflexion, sortierbar nach Ampelfarbe.
8. **Lernenden-Detail:** Wochen, Kursreflexionen, allgemeine Reflexion (nur lesend) und Feedback-Faden mit Eingabefeld.
9. **Kurseinstellungen:** Schwellwerte und Pfad-Metadaten (Unterrichtswochen entfielen mit ADR 0008).

## 4. Meilensteine

Jeder Meilenstein endet mit etwas, das sich in einer Test-Moodle-Instanz vorführen lässt.

| # | Meilenstein | Inhalt | Ergebnis |
|---|---|---|---|
| M0 | Entwicklungsumgebung | Repo, `moodle-docker` mit Moodle 4.x, Testkurs mit 2 Lernsituationen (Soll-Termine gesetzt), Testkonten; CI mit PHPUnit, Moodle-Codecheck, ESLint und Vitest | Lokale Moodle-Instanz mit Testdaten |
| M1 | Durchstich | Plugin-Grundgerüst (`version.php`, `db/install.xml`, `db/access.php`, `db/services.php`), Dienst `gopilot`, `get_overview`, `get_paths`; App mit Login, Start und Lernpfaden (nur lesend), Auslieferung auf der App-Domain | Lernende melden sich an und sehen ihre Lernpfade |
| M2 | Lernplaner | Tabellen `week`, `planitem`; `get_week`, `save_plan`; Wochenplan-Ansicht, Abhaken über die Kern-Completion-API | Wochenplan anlegen und abarbeiten |
| M3 | Reflexion | Tabellen `coursereflection`, `selfassessment`; `save_reflection`; Reflexions-Ansicht mit Sichtbarkeitshinweis | Woche reflektieren |
| M4 | Coaching | Tabelle `feedback`; `get_lerndoku` (vorerst ohne Ampel), `get_learner`, `add_feedback`; Message-Provider; Lerndoku- und Detailansicht | Lehrkraft liest und kommentiert Reflexionen |
| M5 | Förder-Ampel | `courseconfig`, `ampel`, Klasse `calculator` mit Tests, geplante Aufgabe; Kurseinstellungen; Ampel in Start und Lerndoku | Ampeln mit Begründung sichtbar |
| M6 | Push und Erinnerungen | VAPID, `pushsub`, Push-Versand, Aufgabe „Reflexion fällig“, Ampelwechsel-Benachrichtigung; PWA-Installation und Push-Empfang | Benachrichtigungen kommen auf dem Handy an |
| M7 | Lernbiografie | `get_biography`, Biografie-Ansicht, PDF-Export, Pfad-Metadaten-Pflege | Vollständige Version 1 |
| M8 | Pilot | Einsatz in einer Klasse über einen Block, Schwellwerte justieren, Rückmeldungen von Azubis und Lehrkräften sammeln | Entscheidung über Version 2 |

M1 ist bewusst ein schmaler Durchstich durch alle Schichten (Login, Webservice, Plugin, App, Auslieferung). Dabei zeigen sich die größten technischen Risiken früh: Token-Erzeugung, CORS, Rechte.

## 5. Risiken

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| Moodle-Konten nutzen SSO | Login über `login/token.php` scheitert für diese Konten (ADR 0006) | Vor M1 an der Ziel-Instanz prüfen |
| Admin-Einstellungen fehlen (Webservices, Token-Recht) | App kann sich nicht verbinden | Checkliste für die Moodle-Administration in M1 |
| Die Zielinstanz setzt zusätzlich eigene CORS-Header (doppelter `Access-Control-Allow-Origin`) oder ist älter und sendet keine | Browser blockiert alle Aufrufe von der App-Domain an Moodle | Im Durchstich M1 gegen die Zielinstanz prüfen; ggf. Webserver-Konfiguration anpassen |
| Wenige Soll-Termine in den Kursen | Pfadrückstand bleibt stumm | Soll-Termine im Lernsituationen-Skill standardmäßig setzen |
| Web-Push auf iOS nur bei installierter PWA | iPhone-Nutzer:innen ohne Installation erhalten keine Pushes | Installationshinweis in der App; Moodle-Benachrichtigung als Rückfall |
| Ampel löst zu oft oder zu selten aus | Akzeptanzverlust | Schwellwerte pro Kurs; Justierung im Pilot (M8) |

## 6. Offene Punkte für den Start

1. Moodle-Version und Anmeldeart der Ziel-Instanz prüfen.
2. An der Zielinstanz prüfen, dass `login/token.php` genau einen `Access-Control-Allow-Origin`-Header liefert (ADR 0007).
3. ~~UI-Bibliothek wählen (Mantine oder shadcn/ui).~~ Entschieden: Mantine 7.
