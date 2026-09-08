<?php

declare(strict_types=1);

require_once __DIR__ . '/deployment/ScopeBoundApproval.php';

const SAEF_APPROVED_DEPLOYMENT_CONFIRMATION = 'Jetzt anwenden';
const SAEF_APPROVED_DEPLOYMENT_MAX_JSON_BYTES = 1_048_576;

try {
    $options = parseApprovedDeploymentOptions(array_slice($argv, 1));
    if ($options['mode'] === 'prepare') {
        prepareApprovedDeploymentPlan($options);
        exit(0);
    }
    $plan = readApprovedDeploymentJson($options['plan'], SAEF_APPROVED_DEPLOYMENT_MAX_JSON_BYTES);
    $plan = normalizeSaefDeploymentApprovalPlan($plan);
    assertApprovedDeploymentRunnerBaseline($plan);
    assertApprovedDeploymentPackage($options['package'], $plan);
    $secret = readApprovedDeploymentSecret($options['secret-record']);

    $preflight = runApprovedDeploymentTransport(
        $options['transport'],
        [$options['ssh-alias'], 'preflight', $plan['deploymentId']]
    );
    $preflightRecord = decodeApprovedDeploymentResponse($preflight['stdout']);
    if (
        ($preflightRecord['success'] ?? null) !== true
        || ($preflightRecord['outcome'] ?? null) !== 'passed'
        || !isset($preflightRecord['approvalPlan'])
        || !is_array($preflightRecord['approvalPlan'])
    ) {
        throw new RuntimeException('Deployment preflight did not return an approval plan.');
    }
    $serverPlan = normalizeSaefDeploymentApprovalPlan($preflightRecord['approvalPlan']);
    if (hashSaefDeploymentApprovalValue($serverPlan) !== hashSaefDeploymentApprovalValue($plan)) {
        throw new RuntimeException('Deployment preflight plan differs from the reviewed local plan.');
    }

    $approval = createSaefDeploymentApproval(
        $plan,
        $options['approver-identity'],
        $options['execution-host-identity'],
        $secret,
        time(),
        $options['lifetime-seconds']
    );
    $envelope = encodeApprovedDeploymentEnvelope($plan, $approval);
    $activation = runApprovedDeploymentTransport(
        $options['transport'],
        [
            $options['ssh-alias'],
            'activate',
            $plan['deploymentId'],
            'approved',
            $envelope,
        ],
        false
    );
    $status = runApprovedDeploymentTransport(
        $options['transport'],
        [$options['ssh-alias'], 'status', $plan['deploymentId']],
        false
    );
    $statusRecord = decodeApprovedDeploymentResponse($status['stdout']);
    if (
        $activation['exitCode'] !== 0
        && ($statusRecord['success'] ?? null) === true
        && (
            (($statusRecord['phase'] ?? null) === 'preflight'
                && ($statusRecord['outcome'] ?? null) === 'passed')
            || ($statusRecord['outcome'] ?? null) === 'aborted'
        )
    ) {
        $activation = runApprovedDeploymentTransport(
            $options['transport'],
            [
                $options['ssh-alias'],
                'activate',
                $plan['deploymentId'],
                'approved',
                $envelope,
            ],
            false
        );
        $status = runApprovedDeploymentTransport(
            $options['transport'],
            [$options['ssh-alias'], 'status', $plan['deploymentId']],
            false
        );
        $statusRecord = decodeApprovedDeploymentResponse($status['stdout']);
    }
    if (
        $status['exitCode'] !== 0
        || ($statusRecord['success'] ?? null) !== true
        || ($statusRecord['outcome'] ?? null) !== 'activated'
    ) {
        throw new RuntimeException(
            'Approved deployment did not reach an independently confirmed activated outcome.'
        );
    }
    if ($activation['exitCode'] !== 0) {
        fwrite(STDERR, "Activation feedback was lost or failed; status readback proved activation.\n");
    }

    fwrite(
        STDOUT,
        json_encode(
            [
                'formatVersion' => 1,
                'operation' => 'approved-activation',
                'outcome' => 'activated',
                'exitCode' => 0,
                'deploymentId' => $plan['deploymentId'],
                'targetId' => $plan['targetId'],
                'approvalPlanSha256' => hashSaefDeploymentApprovalValue($plan),
                'statusReadbackConfirmed' => true,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    if (function_exists('sodium_memzero')) {
        sodium_memzero($secret);
    } else {
        $secret = '';
    }
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Approved deployment failed: ' . $throwable->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $arguments
 *
 * @return array{
 *   mode:'prepare',ssh-alias:string,package:string,transport:string,lifetime-seconds:int,prepare-plan:string
 * }|array{
 *   mode:'apply',ssh-alias:string,package:string,transport:string,lifetime-seconds:int,
 *   plan:string,secret-record:string,approver-identity:string,execution-host-identity:string
 * }
 */
function parseApprovedDeploymentOptions(array $arguments): array
{
    $values = [];
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new InvalidArgumentException(approvedDeploymentUsage());
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if ($name === '' || array_key_exists($name, $values)) {
            throw new InvalidArgumentException('Approved deployment option is invalid or repeated.');
        }
        $values[$name] = $value;
    }
    $allowed = [
        'ssh-alias',
        'package',
        'plan',
        'secret-record',
        'approver-identity',
        'execution-host-identity',
        'transport',
        'lifetime-seconds',
        'confirm',
        'prepare-plan',
    ];
    if (array_diff(array_keys($values), $allowed) !== []) {
        throw new InvalidArgumentException('Approved deployment option is unsupported.');
    }
    foreach (['ssh-alias', 'package'] as $required) {
        if (!isset($values[$required]) || $values[$required] === '') {
            throw new InvalidArgumentException(approvedDeploymentUsage());
        }
    }
    if (preg_match('/^[A-Za-z0-9_.-]+$/D', $values['ssh-alias']) !== 1) {
        throw new InvalidArgumentException('SSH target must be a configured alias.');
    }
    $transport = $values['transport'] ?? dirname(__DIR__)
        . '/deployments/symcon/windows/saef-deploy';
    $transport = realpath($transport);
    if ($transport === false || !is_file($transport) || is_link($transport)) {
        throw new InvalidArgumentException('Deployment transport is missing or unsafe.');
    }
    $lifetime = filter_var(
        $values['lifetime-seconds'] ?? '300',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 900]]
    );
    if (!is_int($lifetime)) {
        throw new InvalidArgumentException('Approval lifetime is outside the allowed bound.');
    }

    $base = [
        'ssh-alias' => $values['ssh-alias'],
        'package' => approvedDeploymentRealFile($values['package'], 'Deployment package'),
        'transport' => $transport,
        'lifetime-seconds' => $lifetime,
    ];
    if (isset($values['prepare-plan'])) {
        $applyOnly = [
            'plan',
            'secret-record',
            'approver-identity',
            'execution-host-identity',
            'confirm',
            'lifetime-seconds',
        ];
        foreach ($applyOnly as $name) {
            if (isset($values[$name])) {
                throw new InvalidArgumentException('Prepare mode contains an apply-only option.');
            }
        }
        $base['mode'] = 'prepare';
        $base['prepare-plan'] = approvedDeploymentOutputPath($values['prepare-plan']);

        return $base;
    }
    foreach (['plan', 'secret-record', 'approver-identity', 'execution-host-identity'] as $required) {
        if (!isset($values[$required]) || $values[$required] === '') {
            throw new InvalidArgumentException(approvedDeploymentUsage());
        }
    }
    if (($values['confirm'] ?? '') !== SAEF_APPROVED_DEPLOYMENT_CONFIRMATION) {
        throw new InvalidArgumentException('Exact confirmation "Jetzt anwenden" is required.');
    }
    $base['mode'] = 'apply';
    $base['plan'] = approvedDeploymentRealFile($values['plan'], 'Deployment approval plan');
    $base['secret-record'] = approvedDeploymentRealFile(
        $values['secret-record'],
        'Deployment approval secret record'
    );
    $base['approver-identity'] = $values['approver-identity'];
    $base['execution-host-identity'] = $values['execution-host-identity'];

    return $base;
}

function approvedDeploymentUsage(): string
{
    return 'Usage: php tools/apply-approved-symcon-deployment.php '
        . '--ssh-alias=<alias> --package=<zip> --prepare-plan=<plan.local.json> | '
        . '--ssh-alias=<alias> --package=<zip> --plan=<plan.local.json> '
        . '--secret-record=<secret.local.json> --approver-identity=<identity> '
        . '--execution-host-identity=<identity> --confirm="Jetzt anwenden"';
}

function approvedDeploymentOutputPath(string $path): string
{
    if (!str_ends_with($path, '.local.json') || file_exists($path) || is_link($path)) {
        throw new InvalidArgumentException('Prepared plan output must be a new *.local.json file.');
    }
    $directory = realpath(dirname($path));
    if ($directory === false || !is_dir($directory) || is_link($directory)) {
        throw new InvalidArgumentException('Prepared plan output directory is missing or unsafe.');
    }

    return $directory . DIRECTORY_SEPARATOR . basename($path);
}

function approvedDeploymentRealFile(string $path, string $label): string
{
    $resolved = realpath($path);
    if ($resolved === false || !is_file($resolved) || is_link($resolved)) {
        throw new InvalidArgumentException($label . ' is missing or unsafe.');
    }

    return $resolved;
}

/**
 * @param array{
 *   mode:'prepare',ssh-alias:string,package:string,transport:string,lifetime-seconds:int,prepare-plan:string
 * } $options
 */
function prepareApprovedDeploymentPlan(array $options): void
{
    $deploymentId = readApprovedDeploymentPackageId($options['package']);
    runApprovedDeploymentTransport(
        $options['transport'],
        [$options['ssh-alias'], 'stage', $options['package']]
    );
    $preflight = runApprovedDeploymentTransport(
        $options['transport'],
        [$options['ssh-alias'], 'preflight', $deploymentId]
    );
    $response = decodeApprovedDeploymentResponse($preflight['stdout']);
    if (
        ($response['success'] ?? null) !== true
        || ($response['outcome'] ?? null) !== 'passed'
        || !isset($response['approvalPlan'])
        || !is_array($response['approvalPlan'])
    ) {
        throw new RuntimeException('Deployment preflight did not return an approval plan.');
    }
    $plan = normalizeSaefDeploymentApprovalPlan($response['approvalPlan']);
    assertApprovedDeploymentRunnerBaseline($plan);
    assertApprovedDeploymentPackage($options['package'], $plan);
    if ($plan['deploymentId'] !== $deploymentId) {
        throw new RuntimeException('Prepared deployment identity differs from its package.');
    }
    $contents = json_encode(
        $plan,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    $temporary = $options['prepare-plan'] . '.tmp-' . bin2hex(random_bytes(8));
    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600)) {
            throw new RuntimeException('Prepared approval plan cannot be written securely.');
        }
        if (!rename($temporary, $options['prepare-plan'])) {
            throw new RuntimeException('Prepared approval plan cannot be committed atomically.');
        }
    } finally {
        if (file_exists($temporary)) {
            unlink($temporary);
        }
    }
    fwrite(
        STDOUT,
        json_encode(
            [
                'formatVersion' => 1,
                'operation' => 'prepare-approved-deployment',
                'outcome' => 'prepared',
                'exitCode' => 0,
                'deploymentId' => $plan['deploymentId'],
                'targetId' => $plan['targetId'],
                'approvalPlanSha256' => hashSaefDeploymentApprovalValue($plan),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
}

function readApprovedDeploymentPackageId(string $packagePath): string
{
    $archive = new ZipArchive();
    if ($archive->open($packagePath, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('Deployment package archive cannot be opened.');
    }
    try {
        $manifestIndexes = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);
            if (is_array($stat) && $stat['name'] === 'deployment.json') {
                $manifestIndexes[] = $index;
            }
        }
        if (count($manifestIndexes) !== 1) {
            throw new RuntimeException('Deployment package must contain exactly one deployment.json manifest.');
        }
        $index = $manifestIndexes[0];
        $stat = $archive->statIndex($index);
        if (!is_array($stat) || $stat['size'] < 2 || $stat['size'] > 1_048_576) {
            throw new RuntimeException('Deployment package manifest size is invalid.');
        }
        $contents = $archive->getFromIndex($index, 1_048_577);
        if (!is_string($contents)) {
            throw new RuntimeException('Deployment package manifest cannot be read.');
        }
        $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $deploymentId = is_array($manifest) ? ($manifest['deploymentId'] ?? null) : null;
        if (!is_string($deploymentId) || preg_match('/^saef-[a-z0-9][a-z0-9.-]{0,63}$/D', $deploymentId) !== 1) {
            throw new RuntimeException('Deployment package identity is invalid.');
        }

        return $deploymentId;
    } finally {
        $archive->close();
    }
}

/** @return array<string, mixed> */
function readApprovedDeploymentJson(string $path, int $maximumBytes): array
{
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > $maximumBytes) {
        throw new RuntimeException('Approved deployment JSON size is invalid.');
    }
    $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException('Approved deployment JSON must be an object.');
    }

    return $value;
}

/** @param array<string, mixed> $plan */
function assertApprovedDeploymentRunnerBaseline(array $plan): void
{
    $expected = ['activePackageSha256', 'adapterPolicySha256', 'channelPolicySha256'];
    $actual = array_keys($plan['expectedBaselineIdentities']);
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        throw new RuntimeException('Windows approval runner baseline fields differ.');
    }
}

/** @param array<string, mixed> $plan */
function assertApprovedDeploymentPackage(string $packagePath, array $plan): void
{
    $bytes = filesize($packagePath);
    $sha256 = hash_file('sha256', $packagePath);
    if (
        !is_int($bytes)
        || !is_string($sha256)
        || $bytes !== $plan['package']['bytes']
        || $sha256 !== $plan['package']['sha256']
    ) {
        throw new RuntimeException('Deployment package differs from the approved plan.');
    }
}

function readApprovedDeploymentSecret(string $path): string
{
    $record = readApprovedDeploymentJson($path, 4096);
    assertSaefDeploymentApprovalKeys(
        $record,
        ['formatVersion', 'encoding', 'secretBase64'],
        'Deployment approval secret record'
    );
    if (($record['formatVersion'] ?? null) !== 1 || ($record['encoding'] ?? null) !== 'base64') {
        throw new RuntimeException('Deployment approval secret record is invalid.');
    }
    $secret = base64_decode((string) ($record['secretBase64'] ?? ''), true);
    if (!is_string($secret) || strlen($secret) < 32 || strlen($secret) > 64) {
        throw new RuntimeException('Deployment approval secret length is invalid.');
    }

    return $secret;
}

/**
 * @param array<string, mixed> $plan
 * @param array<string, mixed> $approval
 */
function encodeApprovedDeploymentEnvelope(array $plan, array $approval): string
{
    $json = encodeSaefDeploymentApprovalValue([
        'formatVersion' => 1,
        'plan' => $plan,
        'approval' => $approval,
    ]);

    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

/**
 * @param list<string> $arguments
 *
 * @return array{exitCode:int,stdout:string,stderr:string}
 */
function runApprovedDeploymentTransport(
    string $transport,
    array $arguments,
    bool $requireSuccess = true
): array {
    $process = proc_open(
        array_merge([$transport], $arguments),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Deployment transport cannot be started.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $result = [
        'exitCode' => $exitCode,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
    if ($requireSuccess && $exitCode !== 0) {
        throw new RuntimeException('Deployment transport rejected a required phase.');
    }

    return $result;
}

/** @return array<string, mixed> */
function decodeApprovedDeploymentResponse(string $output): array
{
    $lines = array_values(
        array_filter(
            array_map('trim', explode("\n", $output)),
            static fn (string $line): bool => $line !== ''
        )
    );
    if ($lines === []) {
        throw new RuntimeException('Deployment status response is empty.');
    }
    $value = json_decode($lines[array_key_last($lines)], true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($value) || array_is_list($value)) {
        throw new RuntimeException('Deployment status response is invalid.');
    }

    return $value;
}
