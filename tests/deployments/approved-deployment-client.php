<?php

declare(strict_types=1);

function failApprovedDeploymentClient(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertApprovedDeploymentClient(bool $condition, string $message): void
{
    if (!$condition) {
        failApprovedDeploymentClient($message);
    }
}

/** @return array{exitCode:int,stdout:string,stderr:string} */
function runApprovedDeploymentClient(array $command, array $environment): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $environment
    );
    if (!is_resource($process)) {
        failApprovedDeploymentClient('Cannot start approved deployment client.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exitCode' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

function removeApprovedDeploymentClientTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

$root = dirname(__DIR__, 2);
$client = $root . '/tools/apply-approved-symcon-deployment.php';
$transportSource = (string) file_get_contents(
    $root . '/deployments/symcon/windows/saef-deploy'
);
$workspace = sys_get_temp_dir() . '/saef-approved-client-' . bin2hex(random_bytes(8));
mkdir($workspace, 0700, true);

try {
    $packagePath = $workspace . '/package.zip';
    $planPath = $workspace . '/plan.local.json';
    $secretPath = $workspace . '/secret.local.json';
    $transportPath = $workspace . '/transport';
    $logPath = $workspace . '/transport.log';
    $preflightResponsePath = $workspace . '/preflight-response.json';
    $package = 'bounded-test-package';
    $secret = str_repeat('s', 32);
    file_put_contents($packagePath, $package);
    $plan = [
        'formatVersion' => 1,
        'channelVersion' => 8,
        'deploymentId' => 'saef-test-module-01',
        'targetId' => 'test-module',
        'adapterProfile' => 'saef-test-module-v1',
        'qualificationProfile' => 'saef-windows-powershell-5.1-test-v1',
        'postflightProfile' => 'saef-test-module-health-v1',
        'package' => [
            'sha256' => hash('sha256', $package),
            'bytes' => strlen($package),
        ],
        'operations' => ['qualify', 'stage', 'preflight', 'activate', 'postflight', 'rollback'],
        'expectedBaselineIdentities' => [
            'activePackageSha256' => hash('sha256', 'active'),
            'adapterPolicySha256' => hash('sha256', 'adapter-policy'),
            'channelPolicySha256' => hash('sha256', 'channel-policy'),
        ],
        'channelHostBindingSha256' => hash('sha256', 'channel-host'),
        'riskScope' => [
            'allowlistChange' => false,
            'serviceRestart' => false,
            'providerContact' => false,
            'publication' => false,
            'retentionDeletion' => false,
            'activeIdentityReseal' => false,
        ],
    ];
    file_put_contents(
        $planPath,
        json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
    file_put_contents(
        $secretPath,
        json_encode(
            ['formatVersion' => 1, 'encoding' => 'base64', 'secretBase64' => base64_encode($secret)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    file_put_contents(
        $preflightResponsePath,
        json_encode(
            [
                'success' => true,
                'operation' => 'preflight',
                'outcome' => 'passed',
                'exitCode' => 0,
                'approvalPlan' => $plan,
            ],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    file_put_contents(
        $transportPath,
        <<<'SH'
#!/bin/sh
set -eu
printf '%s|%s|%s|%s\n' "$1" "$2" "${3-}" "${4-}" >> "$SAEF_APPROVED_TEST_LOG"
case "$2" in
    stage) printf '%s\n' '{"success":true,"operation":"stage","outcome":"staged","exitCode":0}' ;;
    preflight) cat "$SAEF_APPROVED_TEST_PREFLIGHT_RESPONSE" ;;
    activate)
        if [ "${SAEF_APPROVED_TEST_RESUME-0}" = '1' ]; then
            activation_count=$(grep -c '|activate|' "$SAEF_APPROVED_TEST_LOG")
            if [ "$activation_count" -eq 1 ]; then exit 10; fi
        fi
        if [ "${SAEF_APPROVED_TEST_LOST_FEEDBACK-0}" = '1' ]; then exit 255; fi
        printf '%s\n' '{"success":true,"operation":"activate","outcome":"activated","exitCode":0}'
        ;;
    status)
        if [ "${SAEF_APPROVED_TEST_RESUME-0}" = '1' ]; then
            activation_count=$(grep -c '|activate|' "$SAEF_APPROVED_TEST_LOG")
            if [ "$activation_count" -eq 1 ]; then
                printf '%s\n' '{"success":true,"operation":"status","phase":"preflight","outcome":"passed","exitCode":0}'
                exit 0
            fi
        fi
        printf '%s\n' '{"success":true,"operation":"status","outcome":"activated","exitCode":0}'
        ;;
    *) exit 64 ;;
esac
SH
    );
    chmod($transportPath, 0700);

    $baseCommand = [
        PHP_BINARY,
        $client,
        '--ssh-alias=saef-test',
        '--package=' . $packagePath,
        '--plan=' . $planPath,
        '--secret-record=' . $secretPath,
        '--approver-identity=approved-user',
        '--execution-host-identity=approved-host',
        '--transport=' . $transportPath,
        '--confirm=Jetzt anwenden',
    ];
    $environment = array_merge(
        $_ENV,
        [
            'SAEF_APPROVED_TEST_LOG' => $logPath,
            'SAEF_APPROVED_TEST_PREFLIGHT_RESPONSE' => $preflightResponsePath,
        ]
    );
    $result = runApprovedDeploymentClient($baseCommand, $environment);
    assertApprovedDeploymentClient($result['exitCode'] === 0, 'Happy path failed.');
    $summary = json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
    assertApprovedDeploymentClient(
        is_array($summary)
            && ($summary['outcome'] ?? null) === 'activated'
            && ($summary['statusReadbackConfirmed'] ?? null) === true,
        'Client success summary differs.'
    );
    $calls = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    assertApprovedDeploymentClient(
        is_array($calls)
            && count($calls) === 3
            && str_starts_with($calls[1], 'saef-test|activate|saef-test-module-01|approved')
            && $calls[2] === 'saef-test|status|saef-test-module-01|',
        'Client transport sequence differs.'
    );
    assertApprovedDeploymentClient(
        !str_contains($result['stdout'] . $result['stderr'], base64_encode($secret)),
        'Client output exposes the approval secret.'
    );

    unlink($logPath);
    $lostFeedback = runApprovedDeploymentClient(
        $baseCommand,
        array_merge($environment, ['SAEF_APPROVED_TEST_LOST_FEEDBACK' => '1'])
    );
    assertApprovedDeploymentClient(
        $lostFeedback['exitCode'] === 0
            && str_contains($lostFeedback['stderr'], 'status readback proved activation'),
        'Lost activation feedback was not recovered through status.'
    );

    unlink($logPath);
    $resumed = runApprovedDeploymentClient(
        $baseCommand,
        array_merge($environment, ['SAEF_APPROVED_TEST_RESUME' => '1'])
    );
    $resumeCalls = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    assertApprovedDeploymentClient(
        $resumed['exitCode'] === 0
            && is_array($resumeCalls)
            && count($resumeCalls) === 5
            && str_contains($resumeCalls[1], '|activate|')
            && str_contains($resumeCalls[3], '|activate|'),
        'Resumable pre-mutation activation did not reuse the same approval flow.'
    );

    $preparedPackagePath = $workspace . '/prepared-package.zip';
    $preparedPlanPath = $workspace . '/prepared-plan.local.json';
    $archive = new ZipArchive();
    assertApprovedDeploymentClient(
        $archive->open($preparedPackagePath, ZipArchive::CREATE | ZipArchive::EXCL) === true,
        'Cannot create preparation package fixture.'
    );
    $archive->addFromString(
        'deployment.json',
        json_encode(
            ['formatVersion' => 1, 'deploymentId' => 'saef-test-module-01'],
            JSON_THROW_ON_ERROR
        )
    );
    $archive->close();

    $wrongCasePackagePath = $workspace . '/wrong-case-package.zip';
    $archive = new ZipArchive();
    assertApprovedDeploymentClient(
        $archive->open($wrongCasePackagePath, ZipArchive::CREATE | ZipArchive::EXCL) === true,
        'Cannot create wrong-case package fixture.'
    );
    $archive->addFromString('Deployment.json', '{"deploymentId":"saef-test-module-01"}');
    $archive->close();
    $wrongCaseResult = runApprovedDeploymentClient(
        [
            PHP_BINARY,
            $client,
            '--ssh-alias=saef-test',
            '--package=' . $wrongCasePackagePath,
            '--transport=' . $transportPath,
            '--prepare-plan=' . $workspace . '/wrong-case-plan.local.json',
        ],
        $environment
    );
    assertApprovedDeploymentClient(
        $wrongCaseResult['exitCode'] === 1
            && str_contains($wrongCaseResult['stderr'], 'exactly one deployment.json'),
        'Preparation accepted a package with a case-variant manifest name.'
    );

    $preparedPlan = $plan;
    $preparedPlan['package'] = [
        'sha256' => hash_file('sha256', $preparedPackagePath),
        'bytes' => filesize($preparedPackagePath),
    ];
    file_put_contents(
        $preflightResponsePath,
        json_encode(
            [
                'success' => true,
                'operation' => 'preflight',
                'outcome' => 'passed',
                'exitCode' => 0,
                'approvalPlan' => $preparedPlan,
            ],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    unlink($logPath);
    $prepared = runApprovedDeploymentClient(
        [
            PHP_BINARY,
            $client,
            '--ssh-alias=saef-test',
            '--package=' . $preparedPackagePath,
            '--transport=' . $transportPath,
            '--prepare-plan=' . $preparedPlanPath,
        ],
        $environment
    );
    $prepareCalls = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    assertApprovedDeploymentClient(
        $prepared['exitCode'] === 0
            && file_exists($preparedPlanPath)
            && is_array($prepareCalls)
            && count($prepareCalls) === 2
            && str_contains($prepareCalls[0], '|stage|')
            && str_contains($prepareCalls[1], '|preflight|'),
        'Read-only preparation did not stage and materialize the server plan.'
    );

    $badConfirmation = $baseCommand;
    $badConfirmation[array_key_last($badConfirmation)] = '--confirm=apply';
    $callsBeforeRejection = count((array) file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $rejected = runApprovedDeploymentClient($badConfirmation, $environment);
    $callsAfterRejection = count((array) file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    assertApprovedDeploymentClient(
        $rejected['exitCode'] === 1 && $callsAfterRejection === $callsBeforeRejection,
        'Incorrect confirmation was accepted.'
    );

    $clientSource = (string) file_get_contents($client);
    assertApprovedDeploymentClient(
        str_contains($clientSource, "SAEF_APPROVED_DEPLOYMENT_CONFIRMATION = 'Jetzt anwenden'")
            && !str_contains($clientSource, 'shell_exec')
            && !str_contains($clientSource, 'exec(')
            && !str_contains($clientSource, 'system('),
        'Client contains an unsafe or incomplete execution boundary.'
    );
    assertApprovedDeploymentClient(
        str_contains($transportSource, 'activate <deployment-id> [approved <approval-envelope>]')
            && str_contains($transportSource, 'activate $deployment_id approved $approval_envelope'),
        'POSIX transport does not expose the bounded approved activation mode.'
    );
} finally {
    removeApprovedDeploymentClientTree($workspace);
}

fwrite(STDOUT, "approved-deployment-client: ok\n");
