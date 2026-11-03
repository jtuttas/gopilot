# ADR 0007: Die App läuft auf einer eigenen Domain

- Status: angenommen
- Datum: 2026-11-03

## Kontext

Die PWA ruft die Moodle-Webservices (`webservice/rest/server.php`) und den Token-Endpunkt (`login/token.php`) auf (ADR 0001, ADR 0006). Zur Wahl stand, die App über das Plugin unter der Moodle-Domain auszuliefern oder sie auf einer eigenen Domain zu betreiben.

## Entscheidung

- Die App wird auf einer **eigenen Domain** ausgeliefert, z. B. `https://gopilot.schule.de`, getrennt von Moodle.
- Das Plugin `local_gopilot` enthält kein App-Bundle.

## Verworfene Alternative

- **Auslieferung unter der Moodle-Domain** (`/local/gopilot/app/` aus dem Plugin): kein CORS-Problem, kein weiterer Webspace, aber App-Releases sind an Plugin-Updates in Moodle gebunden.

## Folgen

- App und Plugin werden unabhängig voneinander ausgeliefert. App-Updates brauchen keinen Eingriff in Moodle.
- Es wird ein eigener Webspace mit HTTPS für die App benötigt (statische Dateien).
- **CORS:** Moodle sendet an `login/token.php` und an den Webservice-Antworten von `webservice/rest/server.php` selbst `Access-Control-Allow-Origin: *` (geprüft mit Moodle 4.5 LTS, `login/token.php` und `lib/filelib.php`). Am Moodle-Webserver ist deshalb **keine Konfiguration nötig**.
  - Die App ruft beide Endpunkte per `POST` mit `Content-Type: application/x-www-form-urlencoded` und ohne eigene Header auf. Das sind CORS-„simple requests“ ohne Preflight. Der Token wird als Formularfeld `wstoken` übergeben, nicht als Header.
  - Es werden keine Cookies verwendet. Damit genügt der Platzhalter `*`, `Access-Control-Allow-Credentials` ist nicht nötig.
  - Am Moodle-Webserver dürfen **keine eigenen** `Access-Control-Allow-Origin`-Header für diese Endpunkte gesetzt werden: Zusammen mit dem Header von Moodle entstünden zwei Werte, und der Browser verwirft die Antwort.
  - Ursprünglich war geplant, die Header in der Webserver-Konfiguration zu setzen, beschränkt auf die App-Domain. Das wurde beim Aufbau der Entwicklungsumgebung (M0) verworfen, als sich zeigte, dass Moodle die Header schon sendet. Ein Reverse-Proxy auf der App-Domain bleibt verworfen, weil dann Login-Daten über einen weiteren Server liefen.
- Der Service Worker und die Push-Abos gehören zur App-Domain. Links auf Moodle-Aktivitäten öffnen Moodle in einem neuen Tab und erfordern dort ggf. eine eigene Moodle-Anmeldung.
