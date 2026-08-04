<?php

declare(strict_types=1);

$fixtureDirectory = __DIR__ . '/../fixtures/mqtt';

/**
 * @return array<string, mixed>
 */
function loadMqttFixture(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read MQTT fixture.');
    }

    $decoded = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('MQTT fixture must be a JSON object.');
    }

    return $decoded;
}

function assertMqttFixture(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertExactLocationTopic(array $fixture): void
{
    assertMqttFixture(
        ($fixture['topic'] ?? null)
            === '/downlink/vehicle/DEVICE_001/realtimeDate/location',
        'MQTT location fixture has an unexpected topic.'
    );
    assertMqttFixture(
        ($fixture['channel'] ?? null) === 'location',
        'MQTT location fixture has an unexpected channel.'
    );
    assertMqttFixture(
        !str_contains((string) $fixture['topic'], '#')
            && !str_contains((string) $fixture['topic'], '+'),
        'MQTT fixture topic must not contain a wildcard.'
    );
}

function assertExactStateTopic(array $fixture): void
{
    assertMqttFixture(
        ($fixture['topic'] ?? null)
            === '/downlink/vehicle/DEVICE_001/realtimeDate/state',
        'MQTT state fixture has an unexpected topic.'
    );
    assertMqttFixture(
        ($fixture['channel'] ?? null) === 'state',
        'MQTT state fixture has an unexpected channel.'
    );
}

$credential = loadMqttFixture($fixtureDirectory . '/credential-shape.json');
assertMqttFixture(($credential['code'] ?? null) === 1, 'Credential fixture must be successful.');
$credentialData = $credential['data'] ?? null;
assertMqttFixture(is_array($credentialData), 'Credential data is missing.');
assertMqttFixture(
    ($credentialData['userName'] ?? null) === 'USER_001',
    'MQTT username is not sanitized.'
);
assertMqttFixture(
    ($credentialData['pwdInfo'] ?? null) === 'REDACTED_MQTT_PASSWORD',
    'MQTT password is not sanitized.'
);
assertMqttFixture(
    ($credentialData['mqttHost'] ?? null) === 'REDACTED_MQTT_ENDPOINT'
        && ($credentialData['mqttUrl'] ?? null) === 'REDACTED_MQTT_ENDPOINT',
    'MQTT endpoint is not sanitized.'
);
assertMqttFixture(
    ($credentialData['subTopics'] ?? null) === ['mapChange', 'realtime'],
    'MQTT subTopics shape changed.'
);

$pose = loadMqttFixture($fixtureDirectory . '/location-pose-partial.json');
assertExactLocationTopic($pose);
$posePayload = $pose['payload'] ?? null;
assertMqttFixture(
    is_array($posePayload) && count($posePayload) === 1 && is_array($posePayload[0]),
    'Pose fixture must contain one payload object.'
);
$poseEntry = $posePayload[0];
assertMqttFixture(is_string($poseEntry['postureTheta'] ?? null), 'postureTheta type changed.');
assertMqttFixture(is_float($poseEntry['postureX'] ?? null), 'postureX type changed.');
assertMqttFixture(is_float($poseEntry['postureY'] ?? null), 'postureY type changed.');
assertMqttFixture(is_int($poseEntry['time'] ?? null), 'Location time type changed.');
assertMqttFixture(is_int($poseEntry['type'] ?? null), 'Location type field changed.');
assertMqttFixture(
    is_int($poseEntry['vehicleState'] ?? null),
    'MQTT vehicleState must remain numeric until mapped by evidence.'
);

$partial = loadMqttFixture($fixtureDirectory . '/location-type-3-partial.json');
assertExactLocationTopic($partial);
$partialPayload = $partial['payload'] ?? null;
assertMqttFixture(
    is_array($partialPayload)
        && count($partialPayload) === 1
        && is_array($partialPayload[0]),
    'Partial fixture must contain one payload object.'
);
$partialEntry = $partialPayload[0];
assertMqttFixture(
    array_keys($partialEntry) === ['time', 'type'],
    'Partial location shape must retain only observed fields.'
);
assertMqttFixture(
    !array_key_exists('vehicleState', $partialEntry),
    'Absent vehicleState must remain absent.'
);

foreach (
    [
        'state-running.json' => 'isRunning',
        'state-docking.json' => 'isDocking',
        'state-docked.json' => 'isDocked',
    ] as $file => $expectedState
) {
    $stateFixture = loadMqttFixture($fixtureDirectory . '/' . $file);
    assertExactStateTopic($stateFixture);
    $statePayload = $stateFixture['payload'] ?? null;
    assertMqttFixture(
        is_array($statePayload)
            && ($statePayload['device_id'] ?? null) === 'DEVICE_001'
            && ($statePayload['state'] ?? null) === $expectedState
            && is_int($statePayload['battery'] ?? null)
            && is_int($statePayload['timestamp'] ?? null),
        sprintf('MQTT state fixture %s changed shape.', $file)
    );
}

foreach (
    [
        'location-running.json' => 4,
        'location-docking.json' => 5,
        'location-docked.json' => 2,
    ] as $file => $expectedState
) {
    $stateFixture = loadMqttFixture($fixtureDirectory . '/' . $file);
    assertExactLocationTopic($stateFixture);
    $statePayload = $stateFixture['payload'] ?? null;
    assertMqttFixture(
        is_array($statePayload)
            && count($statePayload) === 1
            && is_array($statePayload[0])
            && ($statePayload[0]['vehicleState'] ?? null) === $expectedState,
        sprintf('MQTT numeric state fixture %s changed shape.', $file)
    );
}

$typeFour = loadMqttFixture(
    $fixtureDirectory . '/location-type-4-no-time.json'
);
assertExactLocationTopic($typeFour);
$typeFourPayload = $typeFour['payload'] ?? null;
assertMqttFixture(
    is_array($typeFourPayload)
        && count($typeFourPayload) === 1
        && is_array($typeFourPayload[0])
        && $typeFourPayload[0] === [
            'taskDelay' => true,
            'type' => 4,
        ],
    'Timestamp-less type-4 fixture changed shape.'
);

$transportEvidence = loadMqttFixture(
    $fixtureDirectory . '/transport-subscription-schema-live-v3.json'
);
assertMqttFixture(
    ($transportEvidence['payloadIncluded'] ?? null) === false,
    'Transport evidence must remain payload-free.'
);
$transportSubscriptions = $transportEvidence['subscriptions'] ?? null;
$transportDelivery = $transportEvidence['delivery'] ?? null;
$transportCleanup = $transportEvidence['cleanup'] ?? null;
assertMqttFixture(
    is_array($transportSubscriptions)
        && ($transportSubscriptions['count'] ?? null) === 4
        && ($transportSubscriptions['canonicalQosCount'] ?? null) === 4
        && (
            $transportSubscriptions['legacyQualityOfServiceCount'] ?? null
        ) === 0
        && ($transportSubscriptions['invalidCount'] ?? null) === 0
        && ($transportSubscriptions['wildcardPresent'] ?? null) === false,
    'Corrected live subscription evidence changed.'
);
assertMqttFixture(
    is_array($transportDelivery)
        && ($transportDelivery['coreHealthy'] ?? null) === true
        && ($transportDelivery['productiveReceiverDelta'] ?? null) === 1
        && ($transportDelivery['siblingProbeDelta'] ?? null) === 1
        && ($transportDelivery['siblingAcceptedMessages'] ?? null) === 1
        && ($transportDelivery['classification'] ?? null) === 'both-received',
    'Corrected live delivery evidence changed.'
);
assertMqttFixture(
    is_array($transportCleanup)
        && !in_array(false, array_values($transportCleanup), true),
    'Corrected live cleanup evidence changed.'
);

$transientReadiness = loadMqttFixture(
    $fixtureDirectory . '/core-resume-transient-core-readiness.json'
);
$transientMetadata = $transientReadiness['metadata'] ?? null;
$transientPreReady = $transientReadiness['preReady'] ?? null;
$transientPostReady = $transientReadiness['postReady'] ?? null;
assertMqttFixture(
    is_array($transientMetadata)
        && ($transientMetadata['synthetic'] ?? null) === true
        && is_array($transientPreReady)
        && is_array($transientPostReady),
    'Transient Core-readiness fixture metadata changed.'
);
assertMqttFixture(
    ($transientPreReady['changedKernelEpoch'] ?? null) === true
        && (
            $transientPreReady['mqttConfigurationUnavailableReads']
                ?? null
        ) === 3
        && ($transientPreReady['coreTransportAlreadyActive'] ?? null)
            === true
        && ($transientPreReady['expectedConfigurationReads'] ?? null)
            === 0
        && ($transientPreReady['expectedCoreOperations'] ?? null) === 0
        && ($transientPreReady['expectedLifecycleState'] ?? null)
            === 'kernel-start-awaiting-ready'
        && ($transientPreReady['expectedTimerMilliseconds'] ?? null)
            === 0
        && ($transientPreReady['expectedReconciledAt'] ?? null) === 0,
    'Transient Core-readiness pre-ready contract changed.'
);
assertMqttFixture(
    ($transientPostReady['restoreCoreReadinessBeforeMessage'] ?? null)
        === true
        && ($transientPostReady['reconciliationDelaySeconds'] ?? null)
            === 15
        && ($transientPostReady['expectedLifecycleState'] ?? null)
            === 'ShadowActive'
        && ($transientPostReady['expectedTransitionReason'] ?? null)
            === 'core-resumed'
        && ($transientPostReady['expectedClassification'] ?? null)
            === 'healthy'
        && (
            $transientPostReady['expectedCoreResumeObservationDelta']
                ?? null
        ) === 1
        && (
            $transientPostReady['expectedConnectionAttemptDelta']
                ?? null
        ) === 0
        && ($transientPostReady['expectedCoreOperations'] ?? null) === 0,
    'Transient Core-readiness post-ready contract changed.'
);

$postReadyUnhealthy = loadMqttFixture(
    $fixtureDirectory . '/core-resume-post-ready-unhealthy-live.json'
);
$postReadyMetadata = $postReadyUnhealthy['metadata'] ?? null;
$postReadyBefore = $postReadyUnhealthy['preRestart'] ?? null;
$postReadyProjection =
    $postReadyUnhealthy['firstReconciledProjection'] ?? null;
$postReadyCleanup = $postReadyUnhealthy['cleanup'] ?? null;
assertMqttFixture(
    is_array($postReadyMetadata)
        && ($postReadyMetadata['sanitized'] ?? null) === true
        && ($postReadyMetadata['sourceStep'] ?? null) === 181
        && ($postReadyMetadata['payloadIncluded'] ?? null) === false
        && (
            $postReadyMetadata['credentialValuesIncluded'] ?? null
        ) === false
        && is_array($postReadyBefore)
        && is_array($postReadyProjection)
        && is_array($postReadyCleanup),
    'Post-ready unhealthy live metadata changed.'
);
assertMqttFixture(
    ($postReadyBefore['lifecycleState'] ?? null) === 'ShadowActive'
        && ($postReadyBefore['coreHealthy'] ?? null) === true
        && (
            $postReadyBefore['credentialFieldsPresent'] ?? null
        ) === true
        && ($postReadyBefore['connectionAttempts'] ?? null) === 12
        && ($postReadyBefore['connectionSuccesses'] ?? null) === 4
        && ($postReadyBefore['connectionFailures'] ?? null) === 0
        && ($postReadyBefore['coreResumeObservations'] ?? null) === 0,
    'Post-ready unhealthy pre-restart contract changed.'
);
assertMqttFixture(
    ($postReadyProjection['newKernelEpoch'] ?? null) === true
        && (
            $postReadyProjection['reconciliationDelaySeconds'] ?? null
        ) === 15
        && ($postReadyProjection['lifecycleState'] ?? null)
            === 'ReconnectScheduled'
        && ($postReadyProjection['transitionReason'] ?? null)
            === 'core-disconnected'
        && ($postReadyProjection['classification'] ?? null)
            === 'unhealthy-with-credentials'
        && ($postReadyProjection['coreHealthy'] ?? null) === false
        && (
            $postReadyProjection['credentialFieldsPresentAfterClassification']
                ?? null
        ) === false
        && ($postReadyProjection['connectionAttemptDelta'] ?? null) === 0
        && ($postReadyProjection['connectionSuccessDelta'] ?? null) === 0
        && ($postReadyProjection['connectionFailureDelta'] ?? null) === 0
        && (
            $postReadyProjection['coreResumeObservationDelta'] ?? null
        ) === 0
        && (
            $postReadyProjection['receivedDeltaSinceFinalBaseline'] ?? null
        ) === 2
        && (
            $postReadyProjection['acceptedDeltaSinceFinalBaseline'] ?? null
        ) === 2
        && ($postReadyProjection['rejectedDelta'] ?? null) === 0
        && ($postReadyProjection['receiveDeltaWindow'] ?? null)
            === 'final-pre-restart-baseline-to-first-reconciled-projection'
        && ($postReadyProjection['receiveTimingRelativeToRestart'] ?? null)
            === 'unresolved'
        && ($postReadyProjection['topologyHashUnchanged'] ?? null)
            === true,
    'Post-ready unhealthy first projection changed.'
);
assertMqttFixture(
    ($postReadyCleanup['normalDisableCalls'] ?? null) === 1
        && ($postReadyCleanup['normalApplyChangesCalls'] ?? null) === 1
        && ($postReadyCleanup['explicitDisconnectCalls'] ?? null) === 0
        && ($postReadyCleanup['featureDisabled'] ?? null) === true
        && ($postReadyCleanup['lifecycleDisabled'] ?? null) === true
        && ($postReadyCleanup['nextAttemptAt'] ?? null) === 0
        && ($postReadyCleanup['webSocketInactive'] ?? null) === true
        && ($postReadyCleanup['credentialFieldsEmpty'] ?? null) === true
        && ($postReadyCleanup['compatibilityPass'] ?? null) === true
        && ($postReadyCleanup['observationSeconds'] ?? null) >= 60,
    'Post-ready unhealthy cleanup contract changed.'
);

$boundedObservation = loadMqttFixture(
    $fixtureDirectory . '/core-resume-bounded-health-observation.json'
);
$boundedMetadata = $boundedObservation['metadata'] ?? null;
$boundedSchedule = $boundedObservation['schedule'] ?? null;
$boundedCases = $boundedObservation['cases'] ?? null;
assertMqttFixture(
    is_array($boundedMetadata)
        && ($boundedMetadata['synthetic'] ?? null) === true
        && ($boundedMetadata['payloadIncluded'] ?? null) === false
        && ($boundedMetadata['deviceIdentityIncluded'] ?? null) === false
        && ($boundedMetadata['credentialValuesIncluded'] ?? null) === false
        && is_array($boundedSchedule)
        && ($boundedSchedule['absoluteOffsetsSeconds'] ?? null)
            === [15, 30, 60, 90, 120, 180]
        && ($boundedSchedule['deadlineSeconds'] ?? null) === 180
        && ($boundedSchedule['maximumObservations'] ?? null) === 6
        && is_array($boundedCases),
    'Bounded Core-resume observation metadata changed.'
);
$delayed30 = $boundedCases['delayed30'] ?? null;
$neverReady = $boundedCases['neverReady'] ?? null;
assertMqttFixture(
    is_array($delayed30)
        && count($delayed30['timeline'] ?? []) === 2
        && ($delayed30['expectedState'] ?? null) === 'ShadowActive'
        && ($delayed30['expectedReason'] ?? null) === 'core-resumed'
        && ($delayed30['expectedClassification'] ?? null) === 'healthy'
        && ($delayed30['expectedCoreResumeObservationDelta'] ?? null) === 1
        && ($delayed30['expectedConnectionOperationDelta'] ?? null) === 0
        && ($delayed30['expectedCoreMutationCount'] ?? null) === 0,
    'Delayed-30 Core-resume observation contract changed.'
);
assertMqttFixture(
    is_array($neverReady)
        && count($neverReady['timeline'] ?? []) === 6
        && ($neverReady['expectedPendingState'] ?? null)
            === 'CoreResumeObserving'
        && ($neverReady['expectedPendingReason'] ?? null)
            === 'core-readiness-pending'
        && ($neverReady['expectedPendingClassification'] ?? null)
            === 'pending-with-credentials'
        && ($neverReady['expectedFinalState'] ?? null)
            === 'ReconnectScheduled'
        && ($neverReady['expectedFinalReason'] ?? null)
            === 'core-disconnected'
        && ($neverReady['expectedFinalClassification'] ?? null)
            === 'unhealthy-with-credentials'
        && ($neverReady['expectedUnexpectedDisconnectDelta'] ?? null) === 1
        && ($neverReady['expectedConnectionOperationDelta'] ?? null) === 0
        && ($neverReady['expectedFinalCoreMutationCount'] ?? null) === 7,
    'Never-ready Core-resume observation contract changed.'
);

$publicText = '';
foreach (glob($fixtureDirectory . '/*.json') ?: [] as $path) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to scan MQTT fixture.');
    }
    $publicText .= $contents;
}
foreach (
    [
        'PRIVATE-',
        'Bearer ',
        'access_token',
        'refresh_token',
        '/Users/',
    ] as $forbidden
) {
    assertMqttFixture(
        !str_contains($publicText, $forbidden),
        sprintf('MQTT fixtures contain forbidden text: %s', $forbidden)
    );
}

echo "Navimow MQTT fixture checks passed.\n";
