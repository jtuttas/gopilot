# GoPilot

Frontend für Moodle, das das selbstgesteuerte Lernen von Auszubildenden an berufsbildenden Schulen fördert. Inspiriert von scobees.

- Fachlicher Kontext und Glossar: [CONTEXT.md](CONTEXT.md)
- Architekturentscheidungen: [docs/adr/](docs/adr/)
- Umsetzungsplan: [docs/umsetzungsplan-v1.md](docs/umsetzungsplan-v1.md)

## Aufbau

```
plugin/local_gopilot/   Moodle-Plugin (PHP): Daten, Webservices, Ampel, Push
app/                    PWA (React + TypeScript + Vite)
deploy/dev/             Lokale Moodle-Entwicklungsumgebung (moodle-docker)
```

## Lokale Entwicklung

Voraussetzungen: Git, Docker Desktop, Node.js 22 oder neuer, eine Bash (unter Windows: Git Bash).

### Moodle starten

```bash
deploy/dev/setup.sh
```

```bash
deploy/dev/start.sh
```

`setup.sh` holt einmalig moodle-docker und Moodle 4.5 LTS nach `.dev/`. `start.sh` startet die Container, installiert Moodle beim ersten Mal und legt Testdaten an:

- Kurse `GOPILOT-LF5` (zwei Lernsituationen) und `GOPILOT-LF8` (eine Lernsituation), mit Soll-Terminen rund um die aktuelle Woche
- Lehrkraft `lehrkraft1` und Azubis `azubi1` bis `azubi3` mit unterschiedlichen Lernständen

Die Zugangsdaten der Testkonten stehen in [deploy/dev/seed.php](deploy/dev/seed.php). Moodle läuft danach auf http://localhost:8000.

Das Plugin ist direkt aus `plugin/local_gopilot` eingehängt. Nach einer Änderung an `version.php` übernimmt ein erneuter Aufruf von `start.sh` das Upgrade.

```bash
deploy/dev/stop.sh
```

`stop.sh --reset` löscht zusätzlich Datenbank und Moodle-Daten.

### App starten

```bash
cd app && npm install && npm run dev
```

Die App läuft auf http://localhost:5173 und spricht Moodle über `VITE_MOODLE_URL` aus `app/.env.development` an. Moodle erlaubt den Zugriff von fremden Ursprüngen an `login/token.php` und `webservice/rest/server.php` selbst (ADR 0007).

### Tests

```bash
cd app && npm run lint && npm test && npm run build
```

Plugin-Tests (PHPUnit, Code-Checker) laufen in der CI über `moodle-plugin-ci`, siehe [.github/workflows/ci.yml](.github/workflows/ci.yml).
