#!/bin/sh

set -eu

case_study_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
repository_dir=$(CDPATH= cd -- "$case_study_dir/../.." && pwd)
vendor_dir=$(
    "$repository_dir/tools/resolve-composer-vendor-dir.sh" \
        "$repository_dir"
)

cd "$repository_dir"

php case-studies/navimow/tests/mqtt-fixtures.php
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tests/mqtt-envelope.php
php case-studies/navimow/tests/mqtt-parser.php
php case-studies/navimow/tests/mqtt-symcon-probe.php
php case-studies/navimow/tests/mqtt-shadow-payload.php
php case-studies/navimow/tests/mqtt-position-diagnostics.php
php case-studies/navimow/tests/mqtt-receiver-scaffold.php
php case-studies/navimow/tests/mqtt-account-ingestion.php
php case-studies/navimow/tests/mqtt-task-observation-ledger.php
php case-studies/navimow/tests/path-zone-prototype.php
php case-studies/navimow/tests/map-geometry-reducer.php
php case-studies/navimow/tests/mqtt-shadow-diagnostics.php
php case-studies/navimow/tests/mqtt-pilot-checkpoints.php
php case-studies/navimow/tests/mqtt-shadow-reconciliation.php
php case-studies/navimow/tests/mqtt-transport-lifecycle.php
php case-studies/navimow/tools/validate-distribution.php

"$vendor_dir/bin/phpcs" \
    case-studies/navimow/distribution/NavimowAccount/module.php \
    case-studies/navimow/distribution/NavimowDevice/module.php \
    case-studies/navimow/distribution/NavimowMqttReceiver/module.php \
    case-studies/navimow/distribution/libs/Navimow/ApiClient.php \
    case-studies/navimow/distribution/libs/Navimow/MqttCredentialMapper.php \
    case-studies/navimow/distribution/libs/Navimow/MqttEnvelopeException.php \
    case-studies/navimow/distribution/libs/Navimow/MqttEnvelopeParser.php \
    case-studies/navimow/distribution/libs/Navimow/MqttPayloadException.php \
    case-studies/navimow/distribution/libs/Navimow/MqttPayloadParser.php \
    case-studies/navimow/distribution/libs/Navimow/MqttPartialStateAccumulator.php \
    case-studies/navimow/distribution/libs/Navimow/MqttPositionDiagnostic.php \
    case-studies/navimow/distribution/libs/Navimow/MqttTaskObservationLedger.php \
    case-studies/navimow/distribution/libs/Navimow/MqttTransportConfiguration.php \
    case-studies/navimow/distribution/libs/Navimow/PayloadMapper.php \
    case-studies/navimow/candidate/MapGeometryReducer.php

"$vendor_dir/bin/phpstan" analyse \
    --configuration=phpstan.neon \
    --memory-limit=512M \
    --debug \
    --no-progress \
    case-studies/navimow/distribution/NavimowAccount/module.php \
    case-studies/navimow/distribution/NavimowDevice/module.php \
    case-studies/navimow/distribution/NavimowMqttReceiver/module.php \
    case-studies/navimow/distribution/libs/Navimow/ApiClient.php \
    case-studies/navimow/distribution/libs/Navimow/MqttCredentialMapper.php \
    case-studies/navimow/distribution/libs/Navimow/MqttPayloadParser.php \
    case-studies/navimow/distribution/libs/Navimow/MqttPositionDiagnostic.php \
    case-studies/navimow/distribution/libs/Navimow/MqttTaskObservationLedger.php \
    case-studies/navimow/distribution/libs/Navimow/MqttTransportConfiguration.php \
    case-studies/navimow/candidate/MapGeometryReducer.php

printf '%s\n' "Navimow MQTT shadow offline checks passed."
