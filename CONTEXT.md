# GoPilot – Domänenkontext

Frontend für Moodle zur Förderung des selbstgesteuerten Lernens, inspiriert von scobees.

## Zielgruppe (Version 1)

- **Lernende** sind Auszubildende an einer berufsbildenden Schule (BBS), z. B. FIAE/FIDP an der MMBbS. Sie sind blockweise in der Schule.
- **Lerncoach** ist die Lehrkraft. Sie begleitet, gibt Feedback und erkennt Förderbedarfe, statt frontal zu unterrichten.
- Nicht in Version 1: Eltern, Schulleitung, Schulträger, Ausbildungsbetriebe.
- Ausbildungsbetriebe erhalten keinen Zugang. Lernende können ihre Lernbiografie selbst exportieren und entscheiden, ob sie sie weitergeben (Datenhoheit bei den Lernenden). Ein Zugang für Betriebe mit Freigabe durch die Lernenden ist ein Kandidat für Version 2.

## Nutzung

- GoPilot ist eine **mobile-first Progressive Web App**: installierbar, Push-Benachrichtigungen (z. B. neues Coaching-Feedback, Reflexion fällig), kein App Store.
- Die **Lerndoku** hat zusätzlich ein eigenes Desktop-Layout für Lehrkräfte.
- Die App läuft auf einer eigenen Domain, getrennt von Moodle (ADR 0007).

## Glossar

| Begriff | Bedeutung | Quelle |
|---|---|---|
| Lernende:r | Auszubildende:r an der BBS, nutzt GoPilot zum selbstgesteuerten Lernen | entschieden |
| Lerncoach | Lehrkraft in begleitender Rolle; konkret jede:r mit der Rolle Lehrkraft im jeweiligen Moodle-Kurs, zuständig nur für diesen Kurs (ADR 0004) | entschieden |
| Lernpfad | Eine Lernsituation, abgebildet als Abschnitt eines Moodle-Kurses (ADR 0002) | entschieden |
| Lernschritt | Eine Aktivität im Abschnitt, in Moodle-Reihenfolge; erledigt = Moodle-Aktivitätsabschluss | entschieden |
| Pfad-Metadaten | Zusatzinfos zu Lernpfad/Lernschritt im Plugin (Phase, Dauer, Niveau) | entschieden |
| Lernplaner | Planungswerkzeug der Lernenden; Planungseinheit ist die Planungswoche | entschieden |
| Planungswoche | Kalenderwoche Mo–So; jede Woche kann geplant und reflektiert werden | entschieden |
| Geplante Woche | Planungswoche, in der eine:r Lernende:r Lernschritte eines Kurses geplant hat; nur solche Wochen zählen für das Signal fehlende Reflexion und die Reflexions-Erinnerung. Unterrichtswochen, Schulwochen und Betriebswochen gibt es nicht mehr (ADR 0008) | entschieden |
| Wochenplan | Auswahl von Lernschritten (aus beliebigen Lernpfaden) für eine Planungswoche plus Wochenziel; gespeichert im Plugin | entschieden |
| Wochenziel | Von der/dem Lernenden selbst formuliertes Ziel für die Planungswoche; für alle Kurslehrkräfte lesbar | entschieden |
| Reflexion | Rückblick am Wochenende: geschafft, Gründe, nächste Schritte; der Lerncoach kann antworten | entschieden |
| Allgemeine Reflexion | Kursübergreifender Teil der Reflexion; wie das Wochenziel für alle Kurslehrkräfte lesbar, nicht kommentierbar | entschieden |
| Kursreflexion | Teil der Reflexion zu den Lernpfaden eines Kurses (inkl. Selbsteinschätzung); nur für die Lehrkräfte dieses Kurses sichtbar | entschieden |
| Coaching-Feedback | Antwort des Lerncoachs an der Kursreflexion als Gesprächsfaden pro Woche und Kurs; im Plugin gespeichert, Moodle-Benachrichtigung nur als Hinweis | entschieden |
| Lernbiografie | Chronologische Folge aller Wochen einer/eines Lernenden: Wochenplan, Wochenziel, Reflexion, Coaching-Feedback | entschieden |
| Lernbiografie-Export | PDF-Export der Lernbiografie oder einzelner Wochen, nur durch die/den Lernende:n selbst ausgelöst | entschieden |
| Lerndoku | Sicht des Lerncoachs auf die Lernentwicklung der Lernenden seines Kurses, mit Förder-Ampel (ADR 0003); eigenes Desktop-Layout | entschieden |
| Förder-Ampel | Grün/Gelb/Rot pro Lernende:r und Kurs, Gesamtfarbe = schlechtestes Signal, aus transparenten Signalen berechnet; ein Hinweis, keine Note; Lernende sehen nur ihre eigene, der Lerncoach alle seiner Kurse | entschieden |
| Signal | Einzelner Auslöser der Förder-Ampel: Planerfüllung, Pfadrückstand, fehlende Reflexion, Selbsteinschätzung | entschieden |
| Planerfüllung | Anteil der im Wochenplan gewählten Lernschritte, die am Wochenende abgeschlossen sind | entschieden |
| Pfadrückstand | Lernschritte, deren Soll-Termin verstrichen ist, die aber nicht abgeschlossen sind | entschieden |
| Soll-Termin | Moodle-Einstellung „Abschluss erwartet am“ (`completionexpected`) bzw. Fälligkeitsdatum eines Lernschritts; ohne Termin kein Pfadrückstand-Signal | entschieden |
| Selbsteinschätzung | In der Reflexion pro bearbeitetem Lernpfad: 4-stufige Skala (sicher, geht so, unsicher, komme nicht klar) plus optionaler Freitext; „unsicher“ = gelb, „komme nicht klar“ = rot | entschieden |
| Schwellwerte | Grenzen, ab denen ein Signal gelb/rot wird; Standardwerte in ADR 0003, pro Kurs von der Lehrkraft anpassbar (außer Selbsteinschätzung) | entschieden |

## Bewusst nicht in Version 1

- Kompetenzraster (mögliche spätere Verfeinerung der Selbsteinschätzung)
- Kursübergreifende oder verzweigte Lernpfade (siehe ADR 0002)
- Zugang für Ausbildungsbetriebe
- Private Notiz in der Reflexion: Alles, was Lernende in GoPilot schreiben, ist für mindestens eine Lehrkraft sichtbar. GoPilot verspricht keine Privatheit, die technisch nur eingeschränkt gälte.
- Anmeldung per SSO. Die Anmeldung läuft über die Moodle-API (`login/token.php`, siehe ADR 0006).
- Ein eigenes Datenschutzkonzept ist vorerst nicht vorgesehen.
- Schulbib und Netzwerk-Bibliothek (scobees). Teilen von Lernpfaden läuft über Moodle-Bordmittel (Backup/Restore, Kursimport, Inhaltsspeicher). Da Lernpfade Moodle-Abschnitte sind, sind geteilte Pfade automatisch GoPilot-fähig.

## Entscheidungen (ADRs)

- [0001](docs/adr/0001-eigene-app-mit-moodle-plugin.md) Eigene Web-App mit Moodle als Backend und Plugin `local_gopilot`
- [0002](docs/adr/0002-lernpfad-ist-moodle-abschnitt.md) Ein Lernpfad ist ein Moodle-Kursabschnitt
- [0003](docs/adr/0003-foerder-ampel-aus-transparenten-signalen.md) Förder-Ampel aus transparenten Signalen
- [0004](docs/adr/0004-lerncoach-ist-kurslehrkraft.md) Lerncoach ist die Kurslehrkraft
- [0005](docs/adr/0005-pwa-mit-react-und-typescript.md) Mobile-first PWA mit React und TypeScript, Push über das Plugin
- [0006](docs/adr/0006-login-mit-benutzername-und-passwort.md) Login per Benutzername und Passwort
- [0007](docs/adr/0007-app-auf-eigener-domain.md) Die App läuft auf einer eigenen Domain
