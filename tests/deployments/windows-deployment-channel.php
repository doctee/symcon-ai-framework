<?php

declare(strict_types=1);

function failDeploymentChannel(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertDeploymentChannel(bool $condition, string $message): void
{
    if (!$condition) {
        failDeploymentChannel($message);
    }
}

/**
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function runDeploymentChannelProcess(array $command): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        failDeploymentChannel('Cannot start deployment channel test process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

function removeDeploymentChannelTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

$root = dirname(__DIR__, 2);
$gatewayPath = $root . '/deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1';
$restartPath = $root . '/deployments/symcon/windows/Invoke-SaefSymconRestart.ps1';
$initializerPath = $root . '/deployments/symcon/windows/Initialize-SaefDeploymentChannel.ps1';
$clientPath = $root . '/deployments/symcon/windows/saef-deploy';
$policyExamplePath = $root . '/deployments/symcon/windows/deployment-channel-policy.example.json';
$builderPath = $root . '/tools/build-symcon-deployment-package.php';
$checksumPath = $root . '/deployments/symcon/windows/SHA256SUMS';

$gateway = file_get_contents($gatewayPath);
$restart = file_get_contents($restartPath);
$initializer = file_get_contents($initializerPath);
$client = file_get_contents($clientPath);
$policy = json_decode((string) file_get_contents($policyExamplePath), true, flags: JSON_THROW_ON_ERROR);
assertDeploymentChannel(is_string($gateway), 'Deployment gateway is unreadable.');
assertDeploymentChannel(is_string($restart), 'Restart coordinator is unreadable.');
assertDeploymentChannel(is_string($initializer), 'Deployment channel initializer is unreadable.');
assertDeploymentChannel(is_string($client), 'Deployment client is unreadable.');
assertDeploymentChannel(is_array($policy), 'Deployment policy example is invalid.');

$requiredGatewayFragments = [
    "@('probe', 'stage', 'preflight', 'activate', 'status')",
    '[string] $env:SSH_ORIGINAL_COMMAND',
    '[Console]::OpenStandardInput()',
    'maxPackageBytes',
    'maxExpandedBytes',
    'maxFileCount',
    'maxPreflightAgeSeconds',
    "@('127.0.0.1', 'localhost', '::1')",
    'expectedRestartCoordinatorSha256',
    'expectedRestartPolicySha256',
    'Get-TokenReplacement',
    'Bootstrap tokens must have equal byte length.',
    'Bootstrap must contain exactly one active token.',
    'Bootstrap already contains the candidate token.',
    "'Global\\SAEF.DeploymentChannel'",
    'Another deployment operation is active.',
    "'channel-probe-status.local.json'",
    'Runtime readiness probe failed.',
    '-ExpectedActiveSha256 (Get-Sha256 -Path $probeBootstrapPath)',
    'Get-ManagedDeploymentUsage',
    'Managed deployment count reached policy.',
    'Managed deployment storage would exceed policy.',
    'Read-BoundedStreamBytes',
    'Get-BoundedStreamSha256',
    'Copy-BoundedStream',
    'Stream exceeded its declared byte length.',
    '$manifestFilePaths.ContainsKey($relative)',
    'Deployment requires a fresh successful preflight.',
    "Outcome 'restart_preflight_failed_rolled_back'",
    "Outcome 'restart_launch_failed_rolled_back'",
    "Outcome 'manual_recovery_required'",
    "'-CredentialPath'",
    "'-ExecutionPolicy', 'Bypass'",
    '[IO.File]::Replace',
];
foreach ($requiredGatewayFragments as $fragment) {
    assertDeploymentChannel(
        str_contains($gateway, $fragment),
        "Required gateway contract fragment is missing: {$fragment}"
    );
}

$forbiddenGatewayPatterns = [
    '/\bInvoke-Expression\b/i',
    '/\biex\b/i',
    '/Start-Sleep/i',
    '/\bSet-ExecutionPolicy\b/i',
    '/powershell(?:\.exe)?\s+-Command/i',
    '/cmd(?:\.exe)?\s+\/c/i',
    '/\$env:SSH_ORIGINAL_COMMAND\s*\|/i',
    '/Bearer\s+[A-Za-z0-9._-]+/i',
    '/[A-Z]:\\\\Users\\\\/i',
];
foreach ($forbiddenGatewayPatterns as $pattern) {
    assertDeploymentChannel(
        preg_match($pattern, $gateway) !== 1,
        "Forbidden gateway pattern found: {$pattern}"
    );
}
assertDeploymentChannel(
    str_contains($gateway, '$PolicyPath = Join-Path $PSScriptRoot \'deployment-channel.local.json\''),
    'Gateway policy path is not resolved after parameter binding.'
);
assertDeploymentChannel(
    !str_contains($gateway, '$PolicyPath = (Join-Path $PSScriptRoot'),
    'Gateway resolves PSScriptRoot inside a parameter default.'
);
foreach (['ReadToEnd()', 'ComputeHash($entryStream)', '$entryStream.CopyTo($fileStream)'] as $unsafeRead) {
    assertDeploymentChannel(
        !str_contains($gateway, $unsafeRead),
        "Gateway contains an unbounded archive read: {$unsafeRead}"
    );
}

assertDeploymentChannel(str_contains($restart, '[string] $CredentialPath'), 'CredentialPath is not supported.');
assertDeploymentChannel(str_contains($restart, 'Import-MachineCredential'), 'Machine DPAPI credential import is missing.');
assertDeploymentChannel(
    str_contains($restart, '[Security.Cryptography.DataProtectionScope]::LocalMachine'),
    'Restart coordinator does not require machine-scoped DPAPI.'
);
assertDeploymentChannel(
    str_contains($restart, 'Add-Type -AssemblyName System.Security -ErrorAction Stop'),
    'Restart coordinator does not load the Windows PowerShell DPAPI assembly.'
);
assertDeploymentChannel(
    !str_contains($restart, 'Import-Clixml'),
    'Restart coordinator still accepts user-bound CLIXML credentials.'
);
assertDeploymentChannel(
    str_contains($restart, 'Credential sources are mutually exclusive.'),
    'Mutually exclusive credential sources are not enforced.'
);
assertDeploymentChannel(
    str_contains($restart, '$PolicyPath = Join-Path $PSScriptRoot \'restart-policy.json\''),
    'Restart policy path is not resolved after parameter binding.'
);

foreach (
    [
        '[switch] $PreflightOnly',
        'Assert-Elevated',
        'Get-LocalUser -Name $DeploymentUser',
        "Get-LocalGroup -SID 'S-1-5-32-544'",
        'Deployment account must be a local administrator.',
        '$deploymentAclIdentity = \'*\' + $deploymentAccount.SID.Value',
        'Assert-SourceChecksums',
        'SAEF deployment SSH block is malformed.',
        '$saefBlockRegex.Replace($sshdConfig, \'\', 1)',
        'repairRequired',
        "Get-Service -Name 'sshd'",
        '& $sshdExecutable \'-t\' \'-f\' $sshdConfigPath',
        'AuthenticationMethods publickey',
        'PasswordAuthentication no',
        'PermitTTY no',
        'AllowTcpForwarding no',
        'PermitOpen none',
        'ForceCommand powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File',
        '[IO.File]::WriteAllText($sshdConfigPath, $updatedSshdConfig',
        'Protect-MachineCredential',
        'Add-Type -AssemblyName System.Security -ErrorAction Stop',
        '[Security.Cryptography.DataProtectionScope]::LocalMachine',
        "'rpc-credential.local.json'",
        "'rpc-credential.local.xml'",
        "-Outcome 'installed'",
        'rollbackSucceeded',
        'failedStep = $failedStep',
        'Get-FileSnapshot',
        'Restore-FileSnapshots',
    ] as $fragment
) {
    assertDeploymentChannel(
        str_contains($initializer, $fragment),
        "Initializer security fragment is missing: {$fragment}"
    );
}
assertDeploymentChannel(
    !str_contains($initializer, '[IO.File]::AppendAllText($sshdConfigPath'),
    'Initializer appends the SAEF block without reconciling Match order.'
);
assertDeploymentChannel(
    str_contains(
        $initializer,
        '$StatusPath = Join-Path $PSScriptRoot \'deployment-channel-bootstrap-status.local.json\''
    ),
    'Initializer status path is not resolved after parameter binding.'
);
foreach (['/Add-WindowsCapability/i', '/New-NetFirewallRule/i', '/\bSet-ExecutionPolicy\b/i'] as $pattern) {
    assertDeploymentChannel(
        preg_match($pattern, $initializer) !== 1,
        "Initializer performs an out-of-scope platform change: {$pattern}"
    );
}

foreach (
    [
        '-o BatchMode=yes',
        '-o ClearAllForwardings=yes',
        '-o ExitOnForwardFailure=yes',
        '-o StrictHostKeyChecking=yes',
        'ssh -T',
        'stage $package_hash',
    ] as $fragment
) {
    assertDeploymentChannel(str_contains($client, $fragment), "Client security fragment is missing: {$fragment}");
}
assertDeploymentChannel(
    preg_match('/ssh[^\n]+StrictHostKeyChecking=no/i', $client) !== 1,
    'Client disables strict host-key checking.'
);

$requiredPolicyKeys = [
    'formatVersion',
    'scriptsRoot',
    'managedFilesetRoot',
    'stateRoot',
    'activeBootstrapRelativePath',
    'restartCoordinatorPath',
    'expectedRestartCoordinatorSha256',
    'restartPolicyPath',
    'expectedRestartPolicySha256',
    'credentialPath',
    'rpcUri',
    'serviceName',
    'maxPackageBytes',
    'maxExpandedBytes',
    'maxFileCount',
    'maxPreflightAgeSeconds',
    'maxDeploymentCount',
    'maxManagedBytes',
];
foreach ($requiredPolicyKeys as $key) {
    assertDeploymentChannel(array_key_exists($key, $policy), "Policy example key is missing: {$key}");
}
assertDeploymentChannel($policy['formatVersion'] === 1, 'Policy example format is invalid.');
assertDeploymentChannel($policy['rpcUri'] === 'http://127.0.0.1:3777/api/', 'Policy RPC is not loopback-only.');
foreach (
    [
        'maxPackageBytes',
        'maxExpandedBytes',
        'maxFileCount',
        'maxPreflightAgeSeconds',
        'maxDeploymentCount',
        'maxManagedBytes',
    ] as $key
) {
    assertDeploymentChannel(is_int($policy[$key]) && $policy[$key] > 0, "Policy limit is invalid: {$key}");
}

$checksumLines = file($checksumPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
assertDeploymentChannel(is_array($checksumLines), 'Windows deployment checksums are unreadable.');
$expectedChecksumFiles = [
    'Initialize-SaefDeploymentChannel.ps1',
    'Invoke-SaefDeploymentGateway.ps1',
    'Invoke-SaefSymconRestart.ps1',
    'deployment-channel-policy.example.json',
    'deployment-package-plan.example.json',
    'restart-policy.json',
    'saef-deploy',
];
$actualChecksumFiles = [];
foreach ($checksumLines as $line) {
    assertDeploymentChannel(
        preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9_.-]+)$/', $line, $matches) === 1,
        'Windows deployment checksum line is invalid.'
    );
    $actualChecksumFiles[] = $matches[2];
    $actualHash = hash_file('sha256', dirname($checksumPath) . '/' . $matches[2]);
    assertDeploymentChannel($actualHash === $matches[1], "Windows deployment checksum drift: {$matches[2]}");
}
assertDeploymentChannel(
    $actualChecksumFiles === $expectedChecksumFiles,
    'Windows deployment checksum inventory is incomplete or unordered.'
);

$shellCheck = runDeploymentChannelProcess(['/bin/sh', '-n', $clientPath]);
assertDeploymentChannel(
    $shellCheck['exitCode'] === 0,
    'POSIX deployment client syntax failed: ' . trim($shellCheck['stderr'])
);

$temporaryRoot = sys_get_temp_dir() . '/saef-deployment-channel-' . bin2hex(random_bytes(8));
$filesetRoot = $temporaryRoot . '/fileset';
try {
    mkdir($filesetRoot . '/helpers', 0700, true);
    file_put_contents($filesetRoot . '/bootstrap.php', "<?php\nrequire_once __DIR__ . '/helpers/Example.php';\n");
    file_put_contents($filesetRoot . '/helpers/Example.php', "<?php\nfunction SAEF_Example(): void {}\n");
    $bootstrapPath = $temporaryRoot . '/System.Locals.ips.php';
    file_put_contents($bootstrapPath, "<?php\nrequire 'saef-old-fileset/bootstrap.php';\n");

    $packages = [];
    for ($index = 0; $index < 2; $index++) {
        $packagePath = $temporaryRoot . "/candidate-{$index}.zip";
        $planPath = $temporaryRoot . "/plan-{$index}.local.json";
        file_put_contents(
            $planPath,
            json_encode(
                [
                    'formatVersion' => 1,
                    'deploymentId' => 'saef-test-release',
                    'targetDirectoryName' => 'saef-new-fileset',
                    'filesetPath' => $filesetRoot,
                    'bootstrapSnapshotPath' => $bootstrapPath,
                    'oldToken' => 'saef-old-fileset',
                    'newToken' => 'saef-new-fileset',
                    'outputPath' => $packagePath,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n"
        );
        $build = runDeploymentChannelProcess([PHP_BINARY, $builderPath, $planPath]);
        assertDeploymentChannel(
            $build['exitCode'] === 0,
            'Deployment package build failed: ' . trim($build['stderr'])
        );
        $result = json_decode($build['stdout'], true, flags: JSON_THROW_ON_ERROR);
        assertDeploymentChannel($result['deploymentId'] === 'saef-test-release', 'Builder returned wrong identity.');
        assertDeploymentChannel($result['fileCount'] === 2, 'Builder returned wrong file count.');
        assertDeploymentChannel($result['packageSha256'] === hash_file('sha256', $packagePath), 'Package hash differs.');
        $packages[] = $packagePath;
    }

    assertDeploymentChannel(
        file_get_contents($packages[0]) === file_get_contents($packages[1]),
        'Independent deployment package builds are not deterministic.'
    );
    $archive = new ZipArchive();
    assertDeploymentChannel($archive->open($packages[0]) === true, 'Cannot open generated deployment package.');
    try {
        $names = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            assertDeploymentChannel(is_string($name), 'Generated package contains an unnamed entry.');
            $names[] = $name;
        }
        assertDeploymentChannel(
            $names === ['deployment.json', 'fileset/bootstrap.php', 'fileset/helpers/Example.php'],
            'Generated package membership or order is invalid.'
        );
        $manifest = json_decode((string) $archive->getFromName('deployment.json'), true, flags: JSON_THROW_ON_ERROR);
        assertDeploymentChannel(
            $manifest['bootstrap']['expectedActiveSha256'] === hash_file('sha256', $bootstrapPath),
            'Manifest active bootstrap hash is invalid.'
        );
        assertDeploymentChannel(
            $manifest['bootstrap']['expectedCandidateSha256'] === hash(
                'sha256',
                str_replace('saef-old-fileset', 'saef-new-fileset', (string) file_get_contents($bootstrapPath))
            ),
            'Manifest candidate bootstrap hash is invalid.'
        );
    } finally {
        $archive->close();
    }
} finally {
    removeDeploymentChannelTree($temporaryRoot);
}

fwrite(STDOUT, "PASS: Restricted Windows deployment channel and deterministic package contract verified.\n");
