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
function runDeploymentChannelProcess(array $command, ?array $environment = null): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $environment
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
$healthProbePath = $root . '/deployments/symcon/windows/SaefRuntimeHealthProbe.php';
$mirrorCoordinatorPath = $root . '/deployments/symcon/windows/Invoke-SaefRuntimeMirror.ps1';
$mirrorReconcilerPath = $root . '/deployments/symcon/windows/SaefRuntimeSourceMirror.php';
$initializerPath = $root . '/deployments/symcon/windows/Initialize-SaefDeploymentChannel.ps1';
$clientPath = $root . '/deployments/symcon/windows/saef-deploy';
$policyExamplePath = $root . '/deployments/symcon/windows/deployment-channel-policy.example.json';
$builderPath = $root . '/tools/build-symcon-deployment-package.php';
$moduleBuilderPath = $root . '/tools/build-symcon-module-deployment-package.php';
$checksumPath = $root . '/deployments/symcon/windows/SHA256SUMS';

$gateway = file_get_contents($gatewayPath);
$restart = file_get_contents($restartPath);
$healthProbe = file_get_contents($healthProbePath);
$mirrorCoordinator = file_get_contents($mirrorCoordinatorPath);
$mirrorReconciler = file_get_contents($mirrorReconcilerPath);
$initializer = file_get_contents($initializerPath);
$client = file_get_contents($clientPath);
$policy = json_decode((string) file_get_contents($policyExamplePath), true, flags: JSON_THROW_ON_ERROR);
assertDeploymentChannel(is_string($gateway), 'Deployment gateway is unreadable.');
assertDeploymentChannel(is_string($restart), 'Restart coordinator is unreadable.');
assertDeploymentChannel(is_string($healthProbe), 'Runtime health probe is unreadable.');
assertDeploymentChannel(is_string($mirrorCoordinator), 'Runtime mirror coordinator is unreadable.');
assertDeploymentChannel(is_string($mirrorReconciler), 'Runtime mirror reconciler is unreadable.');
assertDeploymentChannel(is_string($initializer), 'Deployment channel initializer is unreadable.');
assertDeploymentChannel(is_string($client), 'Deployment client is unreadable.');
assertDeploymentChannel(is_array($policy), 'Deployment policy example is invalid.');
assertDeploymentChannel(
    str_contains($initializer, 'Assert-PowerShellSourceSyntax')
        && str_contains($initializer, '[Management.Automation.Language.Parser]::ParseFile'),
    'Deployment initializer does not parse-check its PowerShell sources.'
);

$requiredGatewayFragments = [
    "@('probe', 'stage', 'preflight', 'activate', 'status')",
    '$ChannelVersion = 8',
    'channelVersion = $ChannelVersion',
    '[string] $env:SSH_ORIGINAL_COMMAND',
    'maxPackageBytes',
    'maxExpandedBytes',
    'maxFileCount',
    'maxPreflightAgeSeconds',
    "@('127.0.0.1', 'localhost', '::1')",
    'expectedRestartCoordinatorSha256',
    'expectedRestartPolicySha256',
    'expectedRuntimeMirrorCoordinatorSha256',
    'expectedRuntimeMirrorReconcilerSha256',
    'Invoke-RuntimeMirrorCoordinator',
    'Invoke-StandaloneModuleAdapter',
    "@('runtime-fileset', 'standalone-module')",
    'standaloneModuleTargets',
    'Standalone module target is not uniquely allowlisted.',
    'Standalone module adapter status contract is invalid.',
    "'-AdapterPolicyPath'",
    "'-TransactionContractPath'",
    'runtime-source-mirror.local.json',
    'mirrorFailureCode',
    'allowedMirrorFailureCodes',
    'restartFailedCheck',
    'runtimeHealthCheck',
    'allowedRuntimeHealthChecks',
    "Outcome 'activated_mirror_degraded'",
    'runtimeActivated = $true',
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
    'Assert-RuntimeHealthContract',
    'Get-ManagedFilesetBootstrapToken',
    'Candidate token does not select the managed fileset bootstrap.',
    'Runtime health function contract is invalid.',
    'Managed deployment count reached policy.',
    'Managed deployment storage would exceed policy.',
    'Read-BoundedStreamBytes',
    'Get-BoundedStreamSha256',
    'Copy-BoundedStream',
    "'stage_transfer_hash'",
    "'stage_archive_contract'",
    "'stage_entry_hashes'",
    'failureCode = $script:failureCode',
    'Stream exceeded its declared byte length.',
    'Package byte count is outside policy.',
    'Start-PackageUpload',
    'Add-PackageUploadChunk',
    "-eq 'begin'",
    "-eq 'chunk'",
    "-eq 'commit'",
    '$UploadChunkBytes = 4096',
    'Another package upload is active.',
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
assertDeploymentChannel(
    substr_count($gateway, '$mirrorExit = -1') === 2,
    'Gateway does not contain both bounded runtime-mirror launch failure paths.'
);

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
    !str_contains($gateway, '[Console]::OpenStandardInput()'),
    'Gateway still depends on Windows OpenSSH standard-input forwarding.'
);
assertDeploymentChannel(
    str_contains($gateway, '$PolicyPath = Join-Path $PSScriptRoot \'deployment-channel.local.json\''),
    'Gateway policy path is not resolved after parameter binding.'
);

foreach (
    [
        "'IPS_RunScriptTextWait'",
        'Import-MachineCredential',
        'ExpectedReconcilerSha256',
        'Assert-MirrorOwnership',
        '$provenanceName',
        '$maximumParentChildren = 4096',
        "-Method 'IPS_GetChildrenIDs'",
        "failureCode = 'mirror_ownership'",
        'Existing runtime mirror cannot be adopted without pinned deployment state.',
        '[Security.Cryptography.DataProtectionScope]::LocalMachine',
        'RPC URI must use an HTTP loopback endpoint.',
    ] as $fragment
) {
    assertDeploymentChannel(
        str_contains($mirrorCoordinator, $fragment),
        "Runtime mirror coordinator fragment is missing: {$fragment}"
    );
}
foreach (['/\bInvoke-Expression\b/i', '/\biex\b/i', '/\bSet-ExecutionPolicy\b/i'] as $pattern) {
    assertDeploymentChannel(
        preg_match($pattern, $mirrorCoordinator) !== 1,
        "Forbidden runtime mirror coordinator pattern found: {$pattern}"
    );
}
assertDeploymentChannel(
    !str_contains($mirrorCoordinator, '$env:SSH_ORIGINAL_COMMAND'),
    'Runtime mirror coordinator consumes client command text.'
);
assertDeploymentChannel(
    !str_contains($mirrorCoordinator, "-Method 'IPS_GetObjectIDByIdent'"),
    'Runtime mirror ownership probe uses warning-prone direct Ident lookup.'
);
assertDeploymentChannel(
    str_contains($mirrorCoordinator, '-Value ([string] $state.mirrorSha256))) {'),
    'Runtime mirror state validation does not close its PowerShell condition.'
);
assertDeploymentChannel(
    str_contains($mirrorReconciler, '__halt_compiler();'),
    'Runtime mirror payload is not protected by __halt_compiler().'
);
assertDeploymentChannel(
    str_contains($mirrorReconciler, 'SAEF_EnsureScript'),
    'Runtime mirror does not reuse SAEF_EnsureScript().'
);
assertDeploymentChannel(
    str_contains($mirrorReconciler, "require_once \$fileset['ensureScriptPath'];"),
    'Runtime mirror does not load its hash-verified EnsureScript closure in an isolated script context.'
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
        "'Invoke-SaefRuntimeMirror.ps1'",
        "'SaefRuntimeSourceMirror.php'",
        'runtimeMirrorEnabled = $RuntimeMirrorParentID -gt 0',
        '[string] $StandaloneModuleTargetsPath',
        'Read-StandaloneModuleTargets',
        '[Management.Automation.Language.Parser]::ParseInput',
        "Join-Path \$InstallRoot 'standalone-modules'",
        'expectedAdapterPolicySha256',
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
        'stage begin $package_hash $package_bytes',
        'stage chunk $package_hash $chunk_index $chunk',
        'stage commit $package_hash',
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
    'runtimeMirrorEnabled',
    'runtimeMirrorCoordinatorPath',
    'expectedRuntimeMirrorCoordinatorSha256',
    'runtimeMirrorReconcilerPath',
    'expectedRuntimeMirrorReconcilerSha256',
    'runtimeMirrorParentID',
    'runtimeMirrorIdent',
    'runtimeMirrorName',
    'runtimeMirrorPosition',
    'standaloneModuleTargets',
    'runtimeHealthProbeEnabled',
    'runtimeHealthProbeScriptID',
    'expectedRuntimeHealthProbeSha256',
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
    'Invoke-SaefDeploymentRetentionCleanup.ps1',
    'Invoke-SaefRuntimeMirror.ps1',
    'Invoke-SaefSymconRestart.ps1',
    'SaefRuntimeHealthProbe.php',
    'SaefRuntimeSourceMirror.php',
    'deployment-channel-policy.example.json',
    'deployment-package-plan.example.json',
    'deployment-retention-plan.example.json',
    'restart-policy.json',
    'saef-deploy',
    'standalone-module-adapter-policy.example.json',
    'standalone-module-deployment-plan.example.json',
    'standalone-module-targets.example.json',
    'standalone-module-transaction.example.json',
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
    $fakeBin = $temporaryRoot . '/bin';
    mkdir($fakeBin, 0700, true);
    $sshLogPath = $temporaryRoot . '/ssh-commands.log';
    $fakeSshPath = $fakeBin . '/ssh';
    file_put_contents(
        $fakeSshPath,
        "#!/bin/sh\nfor last do :; done\nprintf '%s\\n' \"\$last\" >> \"\$SAEF_SSH_LOG\"\n"
    );
    chmod($fakeSshPath, 0700);
    $transportPackagePath = $temporaryRoot . '/transport-package.zip';
    $transportBytes = str_repeat("SAEF-chunk-transport\0", 500);
    file_put_contents($transportPackagePath, $transportBytes);
    $transport = runDeploymentChannelProcess(
        ['/bin/sh', $clientPath, 'saef-test', 'stage', $transportPackagePath],
        [
            'PATH' => $fakeBin . ':' . (string) getenv('PATH'),
            'SAEF_SSH_LOG' => $sshLogPath,
        ]
    );
    assertDeploymentChannel(
        $transport['exitCode'] === 0,
        'Chunked deployment client failed: ' . trim($transport['stderr'])
    );
    $transportCommands = file($sshLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    assertDeploymentChannel(is_array($transportCommands), 'Cannot read captured deployment commands.');
    $transportHash = hash('sha256', $transportBytes);
    $expectedChunkCount = (int) ceil(strlen($transportBytes) / 4096);
    assertDeploymentChannel(
        $transportCommands[0] === "stage begin {$transportHash} " . strlen($transportBytes),
        'Chunked deployment begin command is invalid.'
    );
    assertDeploymentChannel(
        count($transportCommands) === $expectedChunkCount + 2,
        'Chunked deployment emitted an unexpected command count.'
    );
    $reconstructedBytes = '';
    for ($index = 0; $index < $expectedChunkCount; $index++) {
        $parts = explode(' ', $transportCommands[$index + 1], 5);
        assertDeploymentChannel(
            count($parts) === 5 && $parts[0] === 'stage' && $parts[1] === 'chunk'
                && $parts[2] === $transportHash && $parts[3] === (string) $index,
            'Chunked deployment command sequence is invalid.'
        );
        $decodedChunk = base64_decode($parts[4], true);
        assertDeploymentChannel($decodedChunk !== false, 'Chunked deployment contains invalid Base64 data.');
        $reconstructedBytes .= $decodedChunk;
    }
    assertDeploymentChannel($reconstructedBytes === $transportBytes, 'Chunked deployment changed package bytes.');
    assertDeploymentChannel(
        $transportCommands[count($transportCommands) - 1] === "stage commit {$transportHash}",
        'Chunked deployment commit command is invalid.'
    );

    mkdir($filesetRoot . '/helpers', 0700, true);
    file_put_contents($filesetRoot . '/bootstrap.php', "<?php\nrequire_once __DIR__ . '/helpers/Example.php';\n");
    file_put_contents($filesetRoot . '/helpers/Example.php', "<?php\nfunction SAEF_Example(): void {}\n");
    $bootstrapPath = $temporaryRoot . '/System.Locals.ips.php';
    $oldToken = '.oldx-filesets/saef-old-fileset/bootstrap.php';
    $newToken = '.saef-filesets/saef-new-fileset/bootstrap.php';
    file_put_contents($bootstrapPath, "<?php\nrequire '{$oldToken}';\n");

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
                    'oldToken' => $oldToken,
                    'newToken' => $newToken,
                    'requiredRuntimeFunctions' => [
                        'ExampleRequiredFunction',
                        'SAEF_EnsureVariable',
                    ],
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
    $invalidPlanPath = $temporaryRoot . '/invalid-token-plan.local.json';
    $invalidPackagePath = $temporaryRoot . '/invalid-token.zip';
    $invalidPlan = json_decode((string) file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);
    $invalidPlan['newToken'] = 'x.saef-filesets/saef-new-fileset/bootstrap.php';
    $invalidPlan['outputPath'] = $invalidPackagePath;
    file_put_contents(
        $invalidPlanPath,
        json_encode($invalidPlan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
    $invalidBuild = runDeploymentChannelProcess([PHP_BINARY, $builderPath, $invalidPlanPath]);
    assertDeploymentChannel(
        $invalidBuild['exitCode'] !== 0 && !file_exists($invalidPackagePath),
        'Builder accepted a candidate token outside the exact managed fileset path contract.'
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
                str_replace($oldToken, $newToken, (string) file_get_contents($bootstrapPath))
            ),
            'Manifest candidate bootstrap hash is invalid.'
        );
        assertDeploymentChannel(
            $manifest['runtimeHealth']['requiredFunctions'] === [
                'ExampleRequiredFunction',
                'SAEF_EnsureVariable',
            ],
            'Manifest runtime health contract is invalid.'
        );
    } finally {
        $archive->close();
    }

    $moduleRoot = $temporaryRoot . '/module';
    mkdir($moduleRoot . '/ExampleModule', 0700, true);
    $libraryGuid = '{11111111-2222-3333-4444-555555555555}';
    file_put_contents(
        $moduleRoot . '/library.json',
        json_encode(['id' => $libraryGuid, 'name' => 'Synthetic'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
    );
    file_put_contents(
        $moduleRoot . '/ExampleModule/module.json',
        json_encode(['id' => '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
    );
    file_put_contents($moduleRoot . '/ExampleModule/module.php', "<?php\ndeclare(strict_types=1);\n");
    $transactionPath = $temporaryRoot . '/module-transaction.local.json';
    file_put_contents(
        $transactionPath,
        json_encode(
            [
                'formatVersion' => 1,
                'adapterProfile' => 'saef-synthetic-module-v1',
                'state' => ['rollbackPreparation' => 'adapter-required'],
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n"
    );
    $modulePackages = [];
    for ($index = 0; $index < 2; $index++) {
        $modulePackagePath = $temporaryRoot . "/module-candidate-{$index}.zip";
        $modulePlanPath = $temporaryRoot . "/module-plan-{$index}.local.json";
        file_put_contents(
            $modulePlanPath,
            json_encode(
                [
                    'formatVersion' => 1,
                    'deploymentId' => 'saef-synthetic-module-release',
                    'targetDirectoryName' => 'saef-synthetic-module-package',
                    'modulePath' => $moduleRoot,
                    'moduleTargetId' => 'saef-synthetic-module',
                    'libraryGuid' => strtolower($libraryGuid),
                    'transactionContractPath' => $transactionPath,
                    'outputPath' => $modulePackagePath,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n"
        );
        $moduleBuild = runDeploymentChannelProcess([PHP_BINARY, $moduleBuilderPath, $modulePlanPath]);
        assertDeploymentChannel(
            $moduleBuild['exitCode'] === 0,
            'Standalone module deployment package build failed: ' . trim($moduleBuild['stderr'])
        );
        $moduleResult = json_decode($moduleBuild['stdout'], true, flags: JSON_THROW_ON_ERROR);
        assertDeploymentChannel(
            $moduleResult['deploymentKind'] === 'standalone-module'
                && $moduleResult['moduleTargetId'] === 'saef-synthetic-module'
                && $moduleResult['fileCount'] === 3
                && $moduleResult['packageSha256'] === hash_file('sha256', $modulePackagePath),
            'Standalone module deployment builder returned an invalid identity.'
        );
        $modulePackages[] = $modulePackagePath;
    }
    assertDeploymentChannel(
        file_get_contents($modulePackages[0]) === file_get_contents($modulePackages[1]),
        'Independent standalone module deployment package builds are not deterministic.'
    );

    $moduleArchive = new ZipArchive();
    assertDeploymentChannel(
        $moduleArchive->open($modulePackages[0]) === true,
        'Cannot open generated standalone module deployment package.'
    );
    try {
        $moduleNames = [];
        for ($index = 0; $index < $moduleArchive->numFiles; $index++) {
            $name = $moduleArchive->getNameIndex($index);
            assertDeploymentChannel(is_string($name), 'Standalone module package contains an unnamed entry.');
            $moduleNames[] = $name;
        }
        assertDeploymentChannel(
            $moduleNames === [
                'deployment.json',
                'module-transaction.json',
                'module/ExampleModule/module.json',
                'module/ExampleModule/module.php',
                'module/library.json',
            ],
            'Standalone module package membership or order is invalid.'
        );
        $moduleManifest = json_decode(
            (string) $moduleArchive->getFromName('deployment.json'),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        assertDeploymentChannel(
            $moduleManifest['deploymentKind'] === 'standalone-module'
                && $moduleManifest['module']['targetId'] === 'saef-synthetic-module'
                && $moduleManifest['module']['libraryGuid'] === $libraryGuid
                && preg_match('/^[a-f0-9]{64}$/', $moduleManifest['module']['packageIdentitySha256']) === 1
                && $moduleManifest['module']['transactionContractSha256'] === hash(
                    'sha256',
                    (string) $moduleArchive->getFromName('module-transaction.json')
                ),
            'Standalone module manifest is invalid.'
        );
    } finally {
        $moduleArchive->close();
    }

    $invalidModulePlanPath = $temporaryRoot . '/invalid-module-plan.local.json';
    $invalidModulePackagePath = $temporaryRoot . '/invalid-module.zip';
    $invalidModulePlan = json_decode(
        (string) file_get_contents($temporaryRoot . '/module-plan-1.local.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $invalidModulePlan['libraryGuid'] = '{99999999-2222-3333-4444-555555555555}';
    $invalidModulePlan['outputPath'] = $invalidModulePackagePath;
    file_put_contents(
        $invalidModulePlanPath,
        json_encode($invalidModulePlan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
    $invalidModuleBuild = runDeploymentChannelProcess([PHP_BINARY, $moduleBuilderPath, $invalidModulePlanPath]);
    assertDeploymentChannel(
        $invalidModuleBuild['exitCode'] !== 0 && !file_exists($invalidModulePackagePath),
        'Standalone module builder accepted a divergent library identity.'
    );
} finally {
    removeDeploymentChannelTree($temporaryRoot);
}

fwrite(STDOUT, "PASS: Restricted Windows deployment channel and deterministic package contract verified.\n");
