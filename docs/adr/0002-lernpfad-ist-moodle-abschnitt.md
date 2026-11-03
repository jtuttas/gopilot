# ADR 0002: Ein Lernpfad ist ein Moodle-Kursabschnitt (Lernsituation)

- Status: angenommen
- Datum: 2026-11-03

## Kontext

Lernpfade sind das Kernkonzept von GoPilot. Die Kurse an der BBS sind bereits nach Lernsituationen und Phasen in Abschnitte gegliedert und werden u. a. mit dem Skill `moodle-lernsituationen-creator` erzeugt.

## Entscheidung

- Ein **Lernpfad** entspricht genau einem Abschnitt eines Moodle-Kurses.
- Die Aktivitäten des Abschnitts sind die **Lernschritte**, in Moodle-Reihenfolge.
- Erledigt-Status = Moodle-Aktivitätsabschluss; Pflicht/Wahl und Freischaltung über Moodle-Voraussetzungen.
- Zusätzliche **Pfad-Metadaten** (Phase, geschätzte Dauer, Niveau) liegen im Plugin `local_gopilot`.

## Verworfene Alternative

- **Eigene Pfad-Entität im Plugin** mit kursübergreifenden Verweisen und Verzweigungen: flexibler, erfordert aber einen zweiten Editor neben Moodle.

## Folgen

- Lehrkräfte arbeiten weiter ausschließlich in Moodle.
- Kursübergreifende Pfade sind in Version 1 nicht möglich.
- Niveaustufen/Verzweigungen können später über Pfad-Metadaten ergänzt werden.
