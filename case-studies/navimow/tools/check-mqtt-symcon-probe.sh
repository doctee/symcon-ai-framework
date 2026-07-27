#!/bin/sh

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
PROBE_ROOT="$ROOT/case-studies/navimow/tools/symcon-mqtt-spike-library"

php "$ROOT/case-studies/navimow/tests/mqtt-symcon-probe.php"

find "$PROBE_ROOT" -type f -name '*.php' -exec php -l {} \;

"$ROOT/vendor/bin/phpcs" \
    --standard="$ROOT/phpcs.xml" \
    "$PROBE_ROOT" \
    "$ROOT/case-studies/navimow/tests/mqtt-symcon-probe.php"

"$ROOT/vendor/bin/phpstan" analyse \
    --configuration="$ROOT/case-studies/navimow/tools/phpstan-mqtt-symcon-probe.neon" \
    --memory-limit=256M \
    --debug \
    --no-progress \
    "$PROBE_ROOT"

printf '%s\n' 'Navimow MQTT Symcon probe gate passed.'
