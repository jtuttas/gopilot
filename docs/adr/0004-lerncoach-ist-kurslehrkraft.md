# ADR 0004: Lerncoach ist die Lehrkraft des jeweiligen Moodle-Kurses

- Status: angenommen
- Datum: 2026-11-03

## Kontext

Ein Azubi hat mehrere Moodle-Kurse (Lernfelder) mit verschiedenen Lehrkräften. Wochenplan und Reflexion umfassen Lernschritte aus mehreren Kursen. Es war zu klären, wer Lerncoach ist.

## Entscheidung

- **Lerncoach** ist, wer im jeweiligen Moodle-Kurs die Rolle Lehrkraft hat. Es gibt keine eigene Coach-Zuordnung.
- Rechte leiten sich vollständig aus den Moodle-Rollen ab.
- Eine Lehrkraft sieht und kommentiert die kursbezogenen Teile: Lernpfade, Lernschritte, Selbsteinschätzungen, Signale und Coaching-Feedback ihres Kurses.

## Verworfene Alternative

- **Ein fester Coach je Klasse** (z. B. Klassenleitung) mit Gesamtsicht über alle Kurse: näher am scobees-Bild einer festen Lernbegleitung, erfordert aber eine eigene Coach-Zuordnung im Plugin (z. B. über Kohorten) und weicht von der Moodle-Rechtelogik ab.

## Folgen

- Kein zusätzliches Rechtemodell, keine Pflege von Zuordnungen.
- Förder-Ampel und Signale werden **pro Kurs** berechnet.
- Reflexion und Coaching-Feedback werden nach Kursen gegliedert, mit je einem Feedback-Faden pro Kurs.
- Die kursübergreifenden Teile (Wochenziel, allgemeine Reflexion) können alle Kurslehrkräfte des Azubis **lesen, aber nicht kommentieren**. Beim Schreiben sehen Azubis den Hinweis „sichtbar für alle deine Lehrkräfte“.
- Niemand hat die Gesamtsicht über alle Kurse eines Azubis außer dem Azubi selbst.
