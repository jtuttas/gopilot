# ADR 0001: Eigene Web-App mit Moodle als Backend und Plugin `local_gopilot`

- Status: angenommen
- Datum: 2026-11-03

## Kontext

GoPilot soll Azubis an der BBS beim selbstgesteuerten Lernen unterstützen, mit einer Oberfläche im Stil von scobees (Lernpfad, Lernplaner, Lerndoku, Lerncoaching). Moodle ist an der Schule bereits gesetzt, Kurse liegen dort nach Lernsituationen gegliedert vor.

## Entscheidung

- Die Oberfläche ist eine eigene Web-App, die Moodle über die Webservice-API nutzt (Kurse, Aktivitäten, Abschlüsse, Noten).
- Daten, die Moodle nicht kennt (z. B. Ziele, Reflexionen, Planung), speichert ein eigenes Moodle-Plugin `local_gopilot` und stellt sie als Webservice-Funktionen bereit.
- Anmeldung über Moodle (Login/SSO), die App erhält ein Webservice-Token.

## Verworfene Alternativen

- **Reines Moodle-Plugin/Theme:** gebunden an Moodles UI-Stack und Seitenstruktur, schwer lernendenzentriert zu gestalten.
- **Eigene App mit eigener Datenbank:** doppelte Datenhaltung, eigener Datenschutz- und Backup-Aufwand.

## Folgen

- Alle Daten bleiben in Moodle (DSGVO, Backup, Rechte an einer Stelle).
- Es müssen zwei Artefakte gepflegt werden: App und Plugin.
- Die Webservice-Funktionen von Moodle und Plugin werden zur zentralen Schnittstelle.
