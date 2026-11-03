# ADR 0005: Mobile-first PWA mit React und TypeScript

- Status: angenommen
- Datum: 2026-11-03

## Kontext

Hauptnutzer sind Azubis, die Wochenplan und Reflexion oft außerhalb des Unterrichts pflegen (Betrieb, unterwegs, abends). Lehrkräfte nutzen die Lerndoku vor allem am Rechner. Die App spricht ausschließlich über Moodle-Webservices und Plugin-Funktionen mit dem Backend (ADR 0001).

## Entscheidung

- GoPilot ist eine **mobile-first Progressive Web App** (installierbar, Push-Benachrichtigungen), mit eigenem Desktop-Layout für die Lerndoku.
- Stack: **React + TypeScript**, Vite, TanStack Query für Webservice-Aufrufe, `vite-plugin-pwa` für Service Worker, Offline und Push.
- Das Plugin `local_gopilot` wird, wie von Moodle vorgegeben, in PHP entwickelt.

## Verworfene Alternativen

- **Desktop-first:** passt nicht zur Nutzung durch Azubis außerhalb des Unterrichts.
- **Native Apps:** App-Store-Aufwand ohne Mehrwert für Version 1.
- **Vue + TypeScript:** gleichwertig möglich, kleineres Ökosystem, am Ausbildungsmarkt weniger verbreitet.

## Folgen

- Typen für Moodle- und Plugin-Webservices werden zentral gepflegt.
- **Push** verschickt das Plugin `local_gopilot` selbst: Es speichert die Push-Abos, versendet Web-Push mit VAPID (PHP-Bibliothek `minishlink/web-push`) und reagiert auf Moodle-Ereignisse und geplante Aufgaben. Zusätzlich laufen die Benachrichtigungen über die Moodle-Nachrichten-API, sodass auch die üblichen Moodle-Kanäle wie E-Mail genutzt werden. Es gibt keinen separaten Push-Server.
- Das Repo eignet sich als Lern- und Projektmaterial für FIAE-Klassen.
