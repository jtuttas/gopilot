# GoPilot installieren

Anleitung für die Moodle-Administration und die Person, die den Webserver der App betreut.

GoPilot besteht aus zwei Teilen, die getrennt installiert werden:

| Teil | Was | Wohin |
|---|---|---|
| **Moodle-Plugin** `local_gopilot` | Daten, Webservices, Förder-Ampel, Benachrichtigungen, Web-Push | in die bestehende Moodle-Instanz, Ordner `local/gopilot` |
| **App** (PWA) | statische Dateien (HTML, JavaScript, CSS) | auf einen eigenen Webspace mit eigener (Sub-)Domain, z. B. `https://gopilot.schule.de` |

Die App spricht direkt aus dem Browser mit Moodle. Einen eigenen App-Server oder eine eigene Datenbank gibt es nicht.

Die Einrichtung der Kurse durch die Lehrkräfte beschreibt [Moodle-Kurs für GoPilot vorbereiten](anleitung/lehrkraefte-moodle.md), die Bedienung [GoPilot benutzen](anleitung/app-gopilot.md).

## 1. Voraussetzungen prüfen

**Moodle**

- [ ] Moodle **4.2 oder neuer** (entwickelt und getestet mit 4.5 LTS). Die Version steht unter **Website-Administration → Mitteilungen** ganz unten.
- [ ] Moodle läuft über **HTTPS**.
- [ ] Der **Moodle-Cron** läuft regelmäßig (empfohlen: jede Minute). GoPilot nutzt ihn für Erinnerungen, die nächtliche Neuberechnung der Ampeln und das Festschreiben abgeschlossener Wochen.
- [ ] PHP hat die Erweiterung **OpenSSL** mit Unterstützung für die Kurve P-256. Das ist bei üblichen PHP-Installationen der Fall und wird für Web-Push gebraucht.
- [ ] Der Moodle-Server darf **ausgehende HTTPS-Verbindungen** zu den Push-Diensten der Browser aufbauen (z. B. `fcm.googleapis.com`, `*.push.apple.com`, `updates.push.services.mozilla.com`). Ohne das funktioniert alles außer Push.
- [ ] Die Lernenden und Lehrkräfte melden sich in Moodle mit **Benutzername und Kennwort** an (manuelle Konten oder LDAP). Konten, die sich nur per Single Sign-on anmelden (OAuth 2, SAML, Microsoft, IServ), können GoPilot in Version 1 nicht nutzen.

**Webspace für die App**

- [ ] Eine eigene Domain oder Subdomain mit **HTTPS**, z. B. `gopilot.schule.de`. Die App muss an der **Wurzel** dieser Domain liegen (`https://gopilot.schule.de/`), nicht in einem Unterordner wie `https://schule.de/gopilot/`.
- [ ] Der Webserver kann statische Dateien ausliefern und unbekannte Pfade auf `index.html` umleiten (siehe Abschnitt 4).

**Zum Bauen der App** (einmalig auf einem beliebigen Rechner)

- [ ] Node.js 22 oder neuer, Git.

## 2. Moodle-Plugin installieren

### 2.1 Plugin-Paket erzeugen

Im Repository liegt das Plugin unter `plugin/local_gopilot`. Moodle erwartet ein ZIP mit einem Ordner `gopilot` darin:

```bash
git archive --format=zip --prefix=gopilot/ -o local_gopilot.zip HEAD:plugin/local_gopilot
```

Der Befehl packt den **committeten** Stand (`HEAD`), nicht ungespeicherte Änderungen. Für eine bestimmte Version statt `HEAD` den Namen des Tags angeben, z. B. `v0.2.0:plugin/local_gopilot`.

### 2.2 Installieren

**Variante A: über die Moodle-Oberfläche**

1. **Website-Administration → Plugins → Plugin installieren**.
2. `local_gopilot.zip` hochladen, Plugin-Typ „Lokales Plugin (local)“ wählen und **Plugin aus der ZIP-Datei installieren** klicken.
3. Den Hinweisen bis zur Datenbank-Aktualisierung folgen.

Ist der Upload gesperrt (`$CFG->disableupdateautodeploy = true;` in `config.php`), nehmen Sie Variante B.

**Variante B: auf dem Server**

```bash
cd /pfad/zu/moodle
unzip /tmp/local_gopilot.zip -d local/
php admin/cli/upgrade.php --non-interactive
```

Danach muss `local/gopilot/version.php` existieren. Die Dateien müssen für den Webserver-Benutzer lesbar sein.

Bei der Installation legt das Plugin seine Tabellen an, außerdem die Rechte `local/gopilot:use`, `:coach` und `:configure`, den Webservice-Dienst **GoPilot** (Kurzname `gopilot`), vier Benachrichtigungsarten und drei geplante Aufgaben.

### 2.3 Plugin-Einstellungen

Nach der Installation öffnet Moodle die Einstellungen des Plugins. Später finden Sie sie unter **Website-Administration → Plugins → Lokale Plugins → GoPilot**.

| Einstellung | Wert |
|---|---|
| **App-Adresse** (`appurl`) | `https://gopilot.schule.de`, ohne Schrägstrich am Ende. Damit verlinken Moodle-Nachrichten (z. B. E-Mails) direkt in die App. Leer = Nachrichten ohne Link. |
| VAPID-Schlüssel und Kontakt | werden im nächsten Schritt erzeugt, nicht von Hand eintragen |

## 3. Moodle für GoPilot einrichten

Die folgenden Schritte sind mit Screenshots im Administrationsteil von [Moodle-Kurs für GoPilot vorbereiten](anleitung/lehrkraefte-moodle.md#für-die-moodle-administration) beschrieben.

### 3.1 Webservices freischalten

1. **Website-Administration → Allgemein → Zusatzoptionen**: **Webservices aktivieren** einschalten.
2. **Website-Administration → Server → Webservices → Protokolle verwalten**: **Protokoll REST** aktivieren.
3. **Website-Administration → Server → Webservices → Externe Services**: Der Dienst **GoPilot** muss aktiviert und für **Alle Nutzer/innen** freigegeben sein. Das stellt das Plugin selbst so ein. Fügen Sie dem Dienst keine weiteren Funktionen hinzu.

### 3.2 Anmeldung über die App erlauben

Die App holt sich mit Benutzername und Kennwort ein Token über `login/token.php`. Dafür braucht die Rolle **Authentifizierte/r Nutzer/in** zwei Rechte:

1. **Website-Administration → Nutzer/innen → Rechte ändern → Rollen verwalten**.
2. Bei **Authentifizierte/r Nutzer/in** auf das Zahnrad klicken.
3. Erlauben:
   - `moodle/webservice:createtoken`
   - `webservice/rest:use`
4. **Änderungen speichern**.

Wer GoPilot nur für einen Teil der Personen öffnen möchte, legt stattdessen eine eigene Rolle mit diesen Rechten an und weist sie global zu.

### 3.3 Web-Push einrichten

Für Push-Nachrichten auf Handys braucht Moodle ein Schlüsselpaar (VAPID). Einmalig auf dem Server ausführen:

```bash
cd /pfad/zu/moodle
php local/gopilot/cli/generate_vapid.php --subject=mailto:moodle-admin@schule.de
```

Die Schlüssel landen in den Plugin-Einstellungen. Der Kontakt (`--subject`) ist für die Push-Dienste der Browser bestimmt, falls es Probleme gibt.

**Achtung:** Erzeugen Sie die Schlüssel später nicht neu (`--force`). Sonst funktionieren alle bestehenden Push-Abos nicht mehr, und die Lernenden müssen Push in der App erneut einschalten.

Ohne Schlüssel funktioniert GoPilot vollständig, nur ohne Push. Benachrichtigungen kommen dann über die üblichen Moodle-Kanäle (Moodle-Benachrichtigungen, E-Mail).

### 3.4 Benachrichtigungen prüfen

Unter **Website-Administration → Allgemein → Mitteilungen → Benachrichtigungseinstellungen** erscheinen die vier GoPilot-Nachrichten:

| Nachricht (Bezeichnung in Moodle) | Empfänger:in | Standard |
|---|---|---|
| Neues Coaching-Feedback in GoPilot | Lernende bzw. Lehrkraft | Web, E-Mail, Moodle-App |
| Lernende:r hat eine Kursreflexion in GoPilot gespeichert | Lehrkräfte des Kurses | nur Web |
| Erinnerung an die Wochenreflexion in GoPilot (freitags 14 Uhr, nur in Schulwochen) | Lernende | Web, E-Mail, Moodle-App |
| Deine GoPilot-Ampel wechselt auf Gelb oder Rot (nachts) | Lernende | Web, E-Mail, Moodle-App |

Hier können Sie die Kanäle für die ganze Instanz anpassen.

### 3.5 Geplante Aufgaben prüfen

Unter **Website-Administration → Server → Aufgaben → Geplante Aufgaben** stehen drei Aufgaben von `local_gopilot`:

| Aufgabe (Bezeichnung in Moodle) | Zeit |
|---|---|
| Lernende an die Reflexion erinnern (GoPilot) | freitags 14:00 |
| GoPilot-Ampeln neu berechnen | täglich 02:30 |
| Abschlussstand abgeschlossener GoPilot-Wochen einfrieren | täglich 00:10 |

Die Zeiten gelten in der Zeitzone der Moodle-Instanz und lassen sich dort ändern.

### 3.6 CORS: nichts tun

Die App läuft auf einer anderen Domain als Moodle. Moodle sendet an `login/token.php` und `webservice/rest/server.php` von sich aus `Access-Control-Allow-Origin: *`. **Richten Sie am Moodle-Webserver keine eigenen CORS-Header für diese Adressen ein.** Doppelte Header führen dazu, dass der Browser die Antworten verwirft (siehe [ADR 0007](adr/0007-app-auf-eigener-domain.md)).

## 4. App bauen und ausliefern

### 4.1 Bauen

Die Adresse der Moodle-Instanz wird beim Bauen fest eingetragen:

```bash
git clone <Repository-URL> gopilot
cd gopilot/app
npm ci
VITE_MOODLE_URL=https://moodle.schule.de npm run build
```

Unter Windows in der PowerShell stattdessen:

```powershell
$env:VITE_MOODLE_URL = 'https://moodle.schule.de'; npm run build
```

`VITE_MOODLE_URL` ist die Adresse, unter der Moodle im Browser aufgerufen wird, ohne Schrägstrich am Ende. Das Ergebnis liegt in `app/dist/`.

### 4.2 Hochladen

Den **Inhalt** von `app/dist/` in das Wurzelverzeichnis des Webspaces kopieren, sodass `https://gopilot.schule.de/index.html` und `https://gopilot.schule.de/sw.js` erreichbar sind.

### 4.3 Webserver konfigurieren

Zwei Dinge sind nötig:

1. **SPA-Fallback:** Unbekannte Pfade liefern `index.html` aus. Sonst führen Links aus Benachrichtigungen wie `https://gopilot.schule.de/woche/2026-11-02` beim ersten Aufruf ins Leere.
2. **Kein Cache für `index.html` und `sw.js`**, damit Updates der App ankommen. Die Dateien unter `assets/` haben Prüfsummen im Namen und dürfen lange gecacht werden.

**nginx**

```nginx
server {
    listen 443 ssl http2;
    server_name gopilot.schule.de;
    root /var/www/gopilot;

    # ssl_certificate ... / ssl_certificate_key ...

    location / {
        try_files $uri /index.html;
    }
    location = /index.html { add_header Cache-Control "no-cache"; }
    location = /sw.js      { add_header Cache-Control "no-cache"; }
    location /assets/      { add_header Cache-Control "public, max-age=31536000, immutable"; }
}
```

**Apache** (`.htaccess` im Wurzelverzeichnis der App oder im VirtualHost, `mod_rewrite` und `mod_headers` aktiv)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ /index.html [L]

<FilesMatch "^(index\.html|sw\.js)$">
    Header set Cache-Control "no-cache"
</FilesMatch>
```

## 5. Installation prüfen

### 5.1 Schnittstelle prüfen

Mit einem Testkonto (nicht dem Admin-Konto). `<Benutzername>` und `<Kennwort>` ersetzen:

```bash
curl -s -D - -X POST https://moodle.schule.de/login/token.php \
  -H "Origin: https://gopilot.schule.de" \
  --data-urlencode "username=<Benutzername>" \
  --data-urlencode "password=<Kennwort>" \
  --data-urlencode "service=gopilot"
```

Erwartet:

- in der Antwort genau **eine** Zeile `Access-Control-Allow-Origin: *` und
- am Ende `{"token":"…"}`.

Mit diesem Token:

```bash
curl -s -X POST https://moodle.schule.de/webservice/rest/server.php \
  --data-urlencode "wstoken=<Token>" \
  --data-urlencode "wsfunction=local_gopilot_get_overview" \
  --data-urlencode "moodlewsrestformat=json"
```

Erwartet ist JSON mit `currentweek` und den Kursen des Kontos.

### 5.2 App prüfen

1. `https://gopilot.schule.de` öffnen und mit einem Lernenden-Konto anmelden. Die Startseite zeigt die Kurse.
2. `https://gopilot.schule.de/lernpfade` direkt im Browser aufrufen. Es muss die App erscheinen, kein Fehler 404 (SPA-Fallback).
3. Unter **Mehr → Einstellungen** Push einschalten. Der Browser fragt nach der Erlaubnis.
4. Mit einem Lehrkräfte-Konto anmelden: In der Seitenleiste erscheint **Lerndoku**.

## 6. Fehlersuche

| Meldung oder Verhalten | Ursache | Lösung |
|---|---|---|
| App: „Webservices sind in Moodle nicht aktiviert“ bzw. `enablewsdescription` | Webservices aus | 3.1, Schritt 1 |
| `token.php` antwortet `servicenotavailable` | Dienst GoPilot deaktiviert oder Plugin nicht installiert | 3.1, Schritt 3; Plugin-Übersicht prüfen |
| `token.php` antwortet `cannotcreatetoken` | Recht `moodle/webservice:createtoken` fehlt, oder es wurde ein Admin-Konto benutzt (Moodle gibt Admins hier grundsätzlich kein Token) | 3.2; mit einem normalen Konto testen |
| Anmeldung klappt, die App meldet aber sofort wieder ab; Webservice antwortet `accessexception` | Recht `webservice/rest:use` fehlt | 3.2 |
| Anmeldung schlägt bei manchen Personen mit „Ungültige Anmeldedaten“ fehl | Konto nutzt nur SSO, hat kein Moodle-Kennwort | Moodle-Kennwort vergeben oder Person kann GoPilot nicht nutzen (ADR 0006) |
| Browser-Konsole: „Access-Control-Allow-Origin … multiple values“ | zusätzlicher CORS-Header am Moodle-Webserver | eigenen Header entfernen (3.6) |
| Browser-Konsole: „No 'Access-Control-Allow-Origin' header“ | Proxy oder Firewall vor Moodle entfernt Header, oder falsche `VITE_MOODLE_URL` | `curl`-Test aus 5.1; Adresse beim Bauen prüfen |
| Seite lädt, nach Neuladen einer Unterseite Fehler 404 | SPA-Fallback fehlt | 4.3 |
| Nach einem Update zeigt die App weiter die alte Version | `index.html` oder `sw.js` werden gecacht | Cache-Header aus 4.3; App einmal schließen und neu öffnen |
| Push lässt sich nicht einschalten („nicht eingerichtet“) | VAPID-Schlüssel fehlen | 3.3 |
| Push eingeschaltet, kommt aber nicht an | Moodle-Server darf nicht nach außen; auf dem iPhone App nicht installiert | Firewall-Freigabe (Abschnitt 1); in Safari „Zum Home-Bildschirm“ |
| Keine Erinnerungen, Ampeln über Nacht unverändert | Moodle-Cron läuft nicht | Cron einrichten, unter **Geplante Aufgaben** „Letzter Lauf“ prüfen |
| Ampel-Signale Planerfüllung und Reflexion sind immer grau | im Kurs keine Unterrichtswochen hinterlegt | Lehrkraft pflegt sie in der App unter **Kurseinstellungen** |

## 7. Aktualisieren

**Plugin:** neues Paket erzeugen (2.1), den Ordner `local/gopilot` vollständig ersetzen (alten Ordner vorher löschen, nicht darüber kopieren) und `php admin/cli/upgrade.php --non-interactive` ausführen oder **Website-Administration → Mitteilungen** aufrufen. Die Daten bleiben erhalten.

**App:** neu bauen (4.1) und den Inhalt des Webspaces durch den neuen Inhalt von `app/dist/` ersetzen. Geöffnete Apps laden die neue Version beim nächsten Start automatisch.

Plugin und App gehören zusammen: Aktualisieren Sie beide aus demselben Stand des Repositorys.

## 8. Deinstallieren

1. **Website-Administration → Plugins → Plugin-Übersicht**, bei „GoPilot“ auf **Deinstallieren**. Dabei werden **alle GoPilot-Daten gelöscht**: Wochenpläne, Reflexionen, Selbsteinschätzungen, Coaching-Feedback, Kurseinstellungen und Push-Abos. Die Lerndaten in Moodle (Kurse, Aktivitätsabschlüsse) bleiben unberührt.
2. Den Ordner `local/gopilot` vom Server löschen.
3. Den Webspace der App leeren.
4. Wenn nicht mehr für andere Zwecke gebraucht: die Rechte aus 3.2 wieder entfernen und Webservices abschalten.

Lernende können ihre Lernbiografie vorher in der App als PDF sichern.
