<?php

declare(strict_types=1);

const SAEF_RUNTIME_HEALTH_MAX_FUNCTIONS = 256;
const SAEF_RUNTIME_HEALTH_MAX_CONTRACT_BYTES = 32768;

$contractJson = $_IPS['SAEF_RUNTIME_HEALTH_CONTRACT'] ?? null;
if (!is_string($contractJson) || $contractJson === '' || strlen($contractJson) > SAEF_RUNTIME_HEALTH_MAX_CONTRACT_BYTES) {
    throw new RuntimeException('Runtime health contract is missing or unbounded.');
}
$requiredFunctions = json_decode($contractJson, true, 16, JSON_THROW_ON_ERROR);
if (!is_array($requiredFunctions) || $requiredFunctions === [] || count($requiredFunctions) > SAEF_RUNTIME_HEALTH_MAX_FUNCTIONS) {
    throw new RuntimeException('Runtime health function list is invalid.');
}
$previous = null;
$missingCount = 0;
foreach ($requiredFunctions as $function) {
    if (
        !is_string($function)
        || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,127}$/D', $function) !== 1
        || ($previous !== null && strcmp($previous, $function) >= 0)
    ) {
        throw new RuntimeException('Runtime health function contract is invalid.');
    }
    if (!function_exists($function)) {
        $missingCount++;
    }
    $previous = $function;
}

echo json_encode(
    [
        'formatVersion' => 1,
        'success' => $missingCount === 0,
        'requiredFunctionCount' => count($requiredFunctions),
        'missingFunctionCount' => $missingCount,
        'contractSha256' => hash('sha256', $contractJson),
    ],
    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
);
