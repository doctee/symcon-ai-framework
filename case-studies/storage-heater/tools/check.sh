#!/bin/sh
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd -P)
cd "$root"
vendor_dir=$(tools/resolve-composer-vendor-dir.sh .)
php tools/validate-symcon-json.php case-studies/storage-heater/distribution
php tools/build-symcon-module-fileset.php deployments/symcon/storage-heater-module.fileset.json --check
php tools/publish-symcon-module.php --contract=deployments/symcon/storage-heater-publication.json --check
"$vendor_dir/bin/phpstan" analyse --configuration=case-studies/storage-heater/phpstan.neon --no-progress --debug
"$vendor_dir/bin/phpcs" --standard=phpcs.xml case-studies/storage-heater/distribution case-studies/storage-heater/tests
php case-studies/storage-heater/tests/fireplace.php
if [ "$#" -gt 0 ]; then
    php case-studies/storage-heater/tests/runtime.php "$1"
else
    destination=private/test-artifacts/storage-heater
    python3 case-studies/storage-heater/tools/package.py --output "$destination/module.zip"
    python3 -m zipfile -e "$destination/module.zip" "$destination/package"
    php case-studies/storage-heater/tests/runtime.php "$destination/package"
fi
