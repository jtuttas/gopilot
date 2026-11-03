#!/usr/bin/env bash
# Moodle starten; beim ersten Mal installieren und Testdaten anlegen.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

[ -d "$WORK_DIR/moodle" ] || { echo "Zuerst deploy/dev/setup.sh ausführen."; exit 1; }

"$COMPOSE" up -d
"$WORK_DIR/moodle-docker/bin/moodle-docker-wait-for-db"

# Moodle-Code ins Volume kopieren: beim ersten Start und nach einem Update von .dev/moodle.
"$COMPOSE" exec -T webserver sh -c '
    cmp -s /opt/moodle-src/version.php /var/www/html/version.php &&
        cmp -s /opt/moodle-src/config.php /var/www/html/config.php && exit 0
    echo "Kopiere Moodle-Code ins Volume (dauert einige Minuten) ..."
    cp -a /opt/moodle-src/. /var/www/html/
'

installed="$("$COMPOSE" exec -T db psql -U moodle -d moodle -tAc "SELECT 1 FROM m_config WHERE name = 'version'" 2>/dev/null | tail -n 1 || true)"  # moodle-docker-compose schreibt vorab einen Hinweis auf stdout
if [ "$installed" != "1" ]; then
    echo "Installiere Moodle ..."
    "$COMPOSE" exec -T webserver php admin/cli/install_database.php \
        --agree-license --fullname="GoPilot Dev" --shortname="gopilot" \
        --summary="Lokale Entwicklungsinstanz" \
        --adminuser=admin --adminpass='Admin-Test-123!' --adminemail=admin@example.com
else
    # Plugin-Änderungen (version.php) übernehmen.
    "$COMPOSE" exec -T webserver php admin/cli/upgrade.php --non-interactive
fi

"$COMPOSE" exec -T webserver php //opt/gopilot-dev/seed.php  # "//" verhindert die Pfadumschreibung von Git Bash

echo "Moodle läuft auf http://localhost:${MOODLE_DOCKER_WEB_PORT}"
