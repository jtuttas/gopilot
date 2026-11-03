# Gemeinsame Einstellungen für die lokale Moodle-Entwicklungsumgebung.
# Wird von setup.sh, start.sh und stop.sh eingebunden.

DEV_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && (pwd -W 2>/dev/null || pwd))"
REPO_ROOT="$(cd "$DEV_DIR/../.." && (pwd -W 2>/dev/null || pwd))"
WORK_DIR="$REPO_ROOT/.dev"

MOODLE_BRANCH="MOODLE_405_STABLE"   # Moodle 4.5 LTS

export COMPOSE_PROJECT_NAME=gopilot
export MOODLE_DOCKER_WWWROOT="$WORK_DIR/moodle"
export MOODLE_DOCKER_DB=pgsql
export MOODLE_DOCKER_PHP_VERSION=8.3
export MOODLE_DOCKER_WEB_PORT=8000
export GOPILOT_PLUGIN_DIR="$REPO_ROOT/plugin/local_gopilot"
export GOPILOT_DEV_DIR="$DEV_DIR"

COMPOSE="$WORK_DIR/moodle-docker/bin/moodle-docker-compose"
