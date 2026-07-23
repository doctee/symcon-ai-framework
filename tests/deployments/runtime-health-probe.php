<?php

declare(strict_types=1);

function ExampleRequiredFunction(): void
{
}

$_IPS = [
    'SAEF_RUNTIME_HEALTH_CONTRACT' => json_encode(
        ['ExampleRequiredFunction', 'function_exists'],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
    ),
];

ob_start();
require __DIR__ . '/../../deployments/symcon/windows/SaefRuntimeHealthProbe.php';
$output = ob_get_clean();
$result = json_decode((string) $output, true, 16, JSON_THROW_ON_ERROR);

if (
    ($result['formatVersion'] ?? null) !== 1
    || ($result['success'] ?? null) !== true
    || ($result['requiredFunctionCount'] ?? null) !== 2
    || ($result['missingFunctionCount'] ?? null) !== 0
) {
    throw new RuntimeException('Runtime health probe rejected an available function contract.');
}

fwrite(STDOUT, "PASS: Runtime health probe verified the bounded global function contract.\n");

$probePath = realpath(__DIR__ . '/../../deployments/symcon/windows/SaefRuntimeHealthProbe.php');
if ($probePath === false) {
    throw new RuntimeException('Runtime health probe source is missing.');
}
$missingContract = json_encode(['SAEF_FunctionThatMustNotExist'], JSON_THROW_ON_ERROR);
$childCode = sprintf(
    '$_IPS = ["SAEF_RUNTIME_HEALTH_CONTRACT" => %s]; require %s;',
    var_export($missingContract, true),
    var_export($probePath, true)
);
$process = proc_open(
    [PHP_BINARY, '-r', $childCode],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
if (!is_resource($process)) {
    throw new RuntimeException('Cannot start isolated missing-function probe.');
}
$missingOutput = stream_get_contents($pipes[1]);
$missingError = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$missingExitCode = proc_close($process);
$missingResult = json_decode((string) $missingOutput, true, 16, JSON_THROW_ON_ERROR);
if (
    $missingExitCode !== 0
    || $missingError !== ''
    || ($missingResult['success'] ?? null) !== false
    || ($missingResult['missingFunctionCount'] ?? null) !== 1
) {
    throw new RuntimeException('Runtime health probe accepted a missing required function.');
}

fwrite(STDOUT, "PASS: Runtime health probe rejected a missing global function.\n");
