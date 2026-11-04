# ADR 0008: Keine Unterrichtswochen, der Wochenplan bestimmt die Reflexionswochen

- Status: angenommen
- Datum: 2026-11-04
- Ersetzt: die Unterrichtswochen aus CONTEXT.md und den Zusatz „nur Schulwoche“ in ADR 0003

## Kontext

Bis Version 0.3 pflegte jede Kurslehrkraft im Plugin eine Liste von Unterrichtswochen. Nur in diesen Schulwochen wirkten die Signale Planerfüllung und fehlende Reflexion, und nur dann kam freitags die Reflexions-Erinnerung.

Im Blocksystem (Blöcke Rot, Gelb, Blau) sind aber nicht alle Lernenden eines Kurses gleichzeitig in der Schule. Wochenlisten pro Kurs passen dann nicht, Listen pro Klasse wären viel Pflegeaufwand. Gleichzeitig kann jede:r Lernende in jeder Woche selbstständig lernen; die Anwesenheit in der Schule ist für das selbstgesteuerte Lernen nicht entscheidend.

Ohne Ersatz würde jede Woche zählen: Ferien und Betriebswochen würden das Reflexions-Signal rot färben.

## Entscheidung

- Es gibt keine Unterrichtswochen und keine Unterscheidung Schulwoche/Betriebswoche mehr.
- **Planerfüllung:** Bezugswoche ist die zuletzt abgeschlossene Woche. Aktiv, sobald darin Lernschritte des Kurses geplant waren.
- **Fehlende Reflexion:** Es zählen nur abgeschlossene Wochen, in denen die:der Lernende Lernschritte des Kurses geplant hat. Wert = Anzahl dieser Wochen in Folge (neueste zuerst) ohne Kursreflexion.
- **Freitags-Erinnerung:** nur an Lernende, die in der laufenden Woche Lernschritte des Kurses geplant und ihn noch nicht reflektiert haben.
- Die Spalte `teachingweeks` bleibt in `local_gopilot_courseconfig` erhalten, wird aber nicht mehr gelesen oder geschrieben.

## Folgen

- Kein Pflegeaufwand für Wochen; Ferien und Betriebswochen färben die Ampel nicht.
- Wer gar nicht plant, bekommt keine Plan- und Reflexionssignale. Abgehängte Lernende zeigt dann nur der Pfadrückstand, und der nur bei Lernschritten mit Soll-Termin. Lehrkräfte sollten daher Soll-Termine setzen.
- API: `isschoolweek` und `teachingweeks` entfallen; `save_courseconfig` hat keinen Parameter `teachingweeks` mehr (Plugin 0.4.0).
