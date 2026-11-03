# ADR 0006: Anmeldung per Benutzername und Passwort über `login/token.php`

- Status: angenommen
- Datum: 2026-11-03

## Kontext

Die PWA braucht ein Moodle-Webservice-Token (ADR 0001). Zur Wahl standen der Mobile-Launch-Ablauf von Moodle (`admin/tool/mobile/launch.php`, SSO-fähig) und die direkte Token-Anforderung mit Zugangsdaten.

## Entscheidung

- Die Anmeldung erfolgt ausschließlich über die Moodle-API: Die App hat ein eigenes Anmeldeformular und fordert über `login/token.php` mit Benutzername und Passwort ein Token für den Webservice `gopilot` an. Es gibt keine eigene Benutzerverwaltung und keinen Umweg über Browser-Weiterleitungen.
- Der Webservice `gopilot` wird vom Plugin `local_gopilot` definiert und gibt nur die Funktionen frei, die die App braucht.

## Verworfene Alternative

- **Mobile-Launch mit SSO:** funktioniert mit jeder Moodle-Anmeldeart, und die App sieht nie ein Passwort. Erfordert aber aktivierte Mobile Webservices und eine eingetragene Weiterleitungs-URL, außerdem ist der Ablauf aufwendiger.

## Folgen

- Funktioniert nur für Konten mit manueller oder LDAP-Authentifizierung, **nicht bei reinem SSO** (OAuth2, SAML, Microsoft, IServ). Falls die Schule auf SSO umstellt, muss diese Entscheidung überprüft werden.
- Das Passwort läuft durch die App. Daraus folgen Pflichten:
  - nur über HTTPS
  - das Passwort wird nie gespeichert oder geloggt, nur das Token
  - das Token wird beim Abmelden verworfen
- Webservice-Login und der Dienst `gopilot` müssen in der Moodle-Administration aktiviert sein.
