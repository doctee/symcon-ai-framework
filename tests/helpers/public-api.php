<?php
declare(strict_types=1);

require_once __DIR__ . '/../../stubs/symcon.php';

$helperFiles = [
    'helpers/common/Validation.php',
    'helpers/object/EnsureCategory.php',
    'helpers/object/EnsureVariable.php',
    'helpers/object/EnsureEvent.php',
    'helpers/object/EnsureScript.php',
    'helpers/object/EnsureDummy.php',
    'helpers/object/EnsureLink.php',
    'helpers/object/EnsureInstance.php',
    'helpers/object/EnsureProfile.php',
    'helpers/variable/WaitForVariable.php',
    'helpers/diagnostics/ConfigurationHash.php',
    'helpers/diagnostics/Registry.php',
    'helpers/diagnostics/Statistics.php',
    'helpers/diagnostics/ErrorRingBuffer.php',
];

foreach ($helperFiles as $helperFile) {
    require_once __DIR__ . '/../../' . $helperFile;
}

$expectedPublicFunctions = [
    'SAEF_AppendErrorRingBufferEntry',
    'SAEF_ClearErrorRingBuffer',
    'SAEF_CreateConfigurationHash',
    'SAEF_EnsureCategory',
    'SAEF_EnsureCyclicScriptEvent',
    'SAEF_EnsureDummy',
    'SAEF_EnsureErrorRingBufferVariable',
    'SAEF_EnsureInstance',
    'SAEF_EnsureLink',
    'SAEF_EnsureProfile',
    'SAEF_EnsureRegistryVariable',
    'SAEF_EnsureScript',
    'SAEF_EnsureStatisticsVariables',
    'SAEF_EnsureTriggeredScriptEvent',
    'SAEF_EnsureVariable',
    'SAEF_IncrementStatistic',
    'SAEF_NormalizeConfigurationForHash',
    'SAEF_ReadErrorRingBuffer',
    'SAEF_ReadRegistry',
    'SAEF_SetStatisticTimestamp',
    'SAEF_UpdateRegistryEntry',
    'SAEF_ValidateIdent',
    'SAEF_ValidateModuleGuid',
    'SAEF_ValidateObjectName',
    'SAEF_ValidateParentObject',
    'SAEF_ValidateScriptType',
    'SAEF_ValidateVariableType',
    'SAEF_WaitForVariable',
    'SAEF_WriteRegistry',
];

$expectedInternalFunctions = [
    'SAEF_CreateIgnoredConfigurationKeyMap',
    'SAEF_GetStatisticVariableType',
    'SAEF_GetWaitMetadataKey',
    'SAEF_NormalizeConfigurationHashValue',
    'SAEF_ValidateErrorRingBufferCapacity',
    'SAEF_ValidateErrorRingBufferVariable',
    'SAEF_ValidateRegistryVariable',
    'SAEF_ValidateWaitForVariableArguments',
    'SAEF_WaitLookbackMatches',
    'SAEF_WaitValueMatches',
];

$actualPublicFunctions = [];
$actualInternalFunctions = [];
$definedFunctions = get_defined_functions()['user'];

foreach ($definedFunctions as $functionName) {
    if (!str_starts_with($functionName, 'saef_')) {
        continue;
    }

    $reflection = new ReflectionFunction($functionName);
    $canonicalName = $reflection->getName();
    $documentation = $reflection->getDocComment() ?: '';

    if (str_contains($documentation, '@internal')) {
        $actualInternalFunctions[] = $canonicalName;
    } else {
        $actualPublicFunctions[] = $canonicalName;
    }
}

sort($expectedPublicFunctions);
sort($expectedInternalFunctions);
sort($actualPublicFunctions);
sort($actualInternalFunctions);

if ($actualPublicFunctions !== $expectedPublicFunctions) {
    throw new RuntimeException(sprintf(
        "Public SAEF function inventory differs.\nExpected: %s\nActual: %s",
        implode(', ', $expectedPublicFunctions),
        implode(', ', $actualPublicFunctions)
    ));
}

if ($actualInternalFunctions !== $expectedInternalFunctions) {
    throw new RuntimeException(sprintf(
        "Internal SAEF function inventory differs.\nExpected: %s\nActual: %s",
        implode(', ', $expectedInternalFunctions),
        implode(', ', $actualInternalFunctions)
    ));
}

$expectedPublicConstants = [
    'SAEF_ERROR_RING_BUFFER_MAX_CAPACITY' => 100,
    'SAEF_WAIT_CHANGED' => 1,
    'SAEF_WAIT_UPDATED' => 2,
];

foreach ($expectedPublicConstants as $constantName => $expectedValue) {
    if (!defined($constantName) || constant($constantName) !== $expectedValue) {
        throw new RuntimeException('Public SAEF constant contract differs: ' . $constantName);
    }
}

fwrite(STDOUT, sprintf(
    "PASS: SAEF public API contains %d functions, %d constants and %d internal functions.\n",
    count($actualPublicFunctions),
    count($expectedPublicConstants),
    count($actualInternalFunctions)
));
