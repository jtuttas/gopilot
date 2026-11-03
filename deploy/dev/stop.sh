#!/usr/bin/env bash
# Moodle stoppen. Mit "--reset" werden auch Datenbank und Daten gelöscht.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

if [ "${1:-}" = "--reset" ]; then
    "$COMPOSE" down -v
else
    "$COMPOSE" stop
fi
