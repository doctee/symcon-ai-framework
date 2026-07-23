<?php

declare(strict_types=1);

/** GENERATED FILE — DO NOT EDIT. */
$saefFilesetConflicts = [];
foreach ([
    'SAEF_AppendErrorRingBufferEntry',
    'SAEF_ClearErrorRingBuffer',
    'SAEF_CreateConfigurationHash',
    'SAEF_CreateIgnoredConfigurationKeyMap',
    'SAEF_EnsureCyclicScriptEvent',
    'SAEF_EnsureErrorRingBufferVariable',
    'SAEF_EnsureLink',
    'SAEF_EnsureRegistryVariable',
    'SAEF_EnsureScript',
    'SAEF_EnsureStatisticsVariables',
    'SAEF_EnsureTriggeredScriptEvent',
    'SAEF_EnsureVariable',
    'SAEF_GetStatisticVariableType',
    'SAEF_GetWaitMetadataKey',
    'SAEF_IncrementStatistic',
    'SAEF_NormalizeConfigurationForHash',
    'SAEF_NormalizeConfigurationHashValue',
    'SAEF_ReadErrorRingBuffer',
    'SAEF_ReadRegistry',
    'SAEF_SetStatisticTimestamp',
    'SAEF_UpdateRegistryEntry',
    'SAEF_ValidateErrorRingBufferCapacity',
    'SAEF_ValidateErrorRingBufferVariable',
    'SAEF_ValidateIdent',
    'SAEF_ValidateModuleGuid',
    'SAEF_ValidateObjectName',
    'SAEF_ValidateParentObject',
    'SAEF_ValidateRegistryVariable',
    'SAEF_ValidateScriptType',
    'SAEF_ValidateVariableType',
    'SAEF_ValidateWaitForVariableArguments',
    'SAEF_WaitForVariable',
    'SAEF_WaitLookbackMatches',
    'SAEF_WaitValueMatches',
    'SAEF_WriteRegistry',
] as $saefFilesetFunction) {
    if (function_exists($saefFilesetFunction)) {
        $saefFilesetConflicts[] = 'function ' . $saefFilesetFunction;
    }
}
foreach ([
    'SAEF\CaseStudy\ControlLight\ControlLightCommandException',
    'SAEF\CaseStudy\ControlLight\ControlLightCore',
    'SAEF\CaseStudy\ControlLight\ControlLightRuntime',
] as $saefFilesetClass) {
    if (class_exists($saefFilesetClass, false)) {
        $saefFilesetConflicts[] = 'class ' . $saefFilesetClass;
    }
}
foreach ([
    'SAEF_ERROR_RING_BUFFER_MAX_CAPACITY',
    'SAEF_HELPER_CONFIGURATION_HASH',
    'SAEF_HELPER_ENSURE_EVENT',
    'SAEF_HELPER_ENSURE_LINK',
    'SAEF_HELPER_ENSURE_SCRIPT',
    'SAEF_HELPER_ENSURE_VARIABLE',
    'SAEF_HELPER_ERROR_RING_BUFFER',
    'SAEF_HELPER_REGISTRY',
    'SAEF_HELPER_STATISTICS',
    'SAEF_HELPER_VALIDATION',
    'SAEF_HELPER_WAIT_FOR_VARIABLE',
    'SAEF_RUN_AUTOMATION_ACTION_GUID',
    'SAEF_WAIT_CHANGED',
    'SAEF_WAIT_UPDATED',
] as $saefFilesetConstant) {
    if (defined($saefFilesetConstant)) {
        $saefFilesetConflicts[] = 'constant ' . $saefFilesetConstant;
    }
}
if ($saefFilesetConflicts !== []) {
    throw new RuntimeException('SAEF fileset namespace conflict: ' . implode(', ', $saefFilesetConflicts));
}
unset($saefFilesetConflicts, $saefFilesetFunction, $saefFilesetClass, $saefFilesetConstant);

require_once __DIR__ . '/case-studies/control-light/candidate/ControlLightFileset.php';
