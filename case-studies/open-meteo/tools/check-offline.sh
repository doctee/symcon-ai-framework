#!/usr/bin/env bash

set -euo pipefail

case_study_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
repository_dir="$(cd "${case_study_dir}/../.." && pwd)"

while IFS= read -r file; do
    php -l "${file}" >/dev/null
done < <(find \
    "${case_study_dir}/distribution" \
    "${case_study_dir}/tests" \
    -type f -name '*.php' -print | sort)

for test in \
    request-builder \
    response-parser \
    interval-alignment \
    solar-calculator \
    state-reducer \
    module-scaffold \
    module-fileset \
    publication
do
    php "${case_study_dir}/tests/${test}.php"
done

"${repository_dir}/vendor/bin/phpstan" analyse \
    --debug \
    --no-progress \
    --configuration="${case_study_dir}/phpstan.neon"

"${repository_dir}/vendor/bin/phpcs" \
    --standard="${repository_dir}/phpcs.xml" \
    "${case_study_dir}/distribution" \
    "${case_study_dir}/tests"

echo "open-meteo offline checks: ok"
