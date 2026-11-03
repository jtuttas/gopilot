# ADR 0003: Förder-Ampel aus transparenten Signalen

- Status: angenommen
- Datum: 2026-11-03

## Kontext

Die Lerndoku soll dem Lerncoach Förderbedarfe früh zeigen. Bei mehreren Klassen mit je 25 bis 30 Azubis ist das aus Rohdaten allein nicht leistbar.

## Entscheidung

GoPilot berechnet pro Lernende:r eine **Förder-Ampel** (grün, gelb, rot) aus wenigen, nachvollziehbaren **Signalen**:

1. Planerfüllung des Wochenplans
2. Pfadrückstand gegenüber der erwarteten Phase
3. Fehlende Reflexion nach Wochenende
4. Selbsteinschätzung in der Reflexion

Die Ampel ist ein Hinweis, keine Bewertung. Der Lerncoach sieht immer, welches Signal sie ausgelöst hat.

## Verworfene Alternative

- **Nur Rohdaten anzeigen:** einfacher und neutraler, skaliert aber nicht auf mehrere Klassen.

## Folgen

- **Sollwert für Pfadrückstand** sind die Moodle-Termine der Lernschritte („Abschluss erwartet am“ bzw. Fälligkeitsdatum), nicht ein im Plugin gepflegter Zeitraum. Lernschritte ohne Termin lösen kein Signal aus.
- **Schwellwerte (Standard):**

  | Signal | grün | gelb | rot |
  |---|---|---|---|
  | Planerfüllung (nur Schulwoche) | ≥ 70 % | 40–69 % | < 40 % |
  | Pfadrückstand (überfällige Lernschritte) | 0–1 | 2–3 | ≥ 4 |
  | Fehlende Reflexion (nur Schulwoche) | vorhanden | 1 Woche fehlt | 2 Schulwochen in Folge |
  | Selbsteinschätzung (pro Lernpfad) | sicher / geht so | unsicher | komme nicht klar |

- **Gesamtfarbe** eines Kurses = schlechtestes Signal (nicht konfigurierbar).
- Die Schwellwerte für Planerfüllung, Pfadrückstand und fehlende Reflexion kann die **Kurslehrkraft pro Kurs** anpassen. Die Stufen der Selbsteinschätzung sind fest.
- Keine Blackbox-Bewertung (kein ML): wichtig für Akzeptanz und Datenschutz.
- Die Ampel fließt nicht in Noten ein.
- **Sichtbarkeit:** Lernende sehen ihre eigene Ampel samt auslösenden Signalen, als Hinweis formuliert. Es gibt keinen Vergleich mit anderen und keine Rangliste. Der Lerncoach sieht die Ampeln aller Lernenden seiner Kurse.
