#!/usr/bin/env bash
# Einmalig: moodle-docker und Moodle-Quellcode nach .dev/ holen.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

mkdir -p "$WORK_DIR"

if [ ! -d "$WORK_DIR/moodle-docker" ]; then
    git clone --depth 1 https://github.com/moodlehq/moodle-docker.git "$WORK_DIR/moodle-docker"
fi

if [ ! -d "$WORK_DIR/moodle" ]; then
    git clone --depth 1 --branch "$MOODLE_BRANCH" https://github.com/moodle/moodle.git "$WORK_DIR/moodle"
fi

cp "$WORK_DIR/moodle-docker/config.docker-template.php" "$WORK_DIR/moodle/config.php"
cp "$DEV_DIR/local.yml" "$WORK_DIR/moodle-docker/local.yml"

echo "Setup fertig. Weiter mit: deploy/dev/start.sh"
