<?php
declare(strict_types=1);

use SAEF\CaseStudy\ControlLight\ControlLightRuntime;

/**
 * ControlLight v2 wrapper template.
 *
 * This repository candidate is not a live deployment artifact. Installation
 * ObjectIDs belong only in a private copy or migration manifest.
 */

require_once __DIR__ . '/ControlLightRuntime.php';

$configuration = [
    'version' => 'ControlLight-v2-candidate',
    'preset' => 'Z2M',
    'authoritativeFeedback' => true,

    /*
     * Availability is never a command gate. It is inspected only after a
     * missing authoritative confirmation to classify the operational failure.
     * The Z2M preset uses the boolean device_status variable by default.
     */

    /*
     * Required migration decision before an instance may pass live preflight:
     * - reported: DIMMER mirrors retained device brightness while switched off.
     * - effective: DIMMER is zero while STATE is false.
     */
    'brightnessSemantics' => 'reported',

    'alarmID' => 0,
    'alarmIDIsAlarmActive' => true,
    'confirmation' => [
        'timeoutMilliseconds' => 3 * 1000,
        'pollIntervalMilliseconds' => 100,
    ],
    'semaphore' => [
        'timeoutMilliseconds' => 5 * 1000,
    ],
    'externalTriggers' => [],
];

$sourceIPS = [
    'SENDER' => $_IPS['SENDER'] ?? '',
    'VARIABLE' => $_IPS['VARIABLE'] ?? 0,
    'VALUE' => $_IPS['VALUE'] ?? null,
];

ControlLightRuntime::run((int)($_IPS['SELF'] ?? 0), $sourceIPS, $configuration);
