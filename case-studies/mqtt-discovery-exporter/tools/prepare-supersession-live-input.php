<?php

declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionOwnerMigration;
use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionOwnerSource;

require_once __DIR__ . '/../deployment/MqttSupersessionOwnerMigration.php';
require_once __DIR__ . '/../deployment/MqttSupersessionOwnerSource.php';

try {
    $arguments = parseArguments(array_slice($argv, 1));
    $baseline = readJsonFile($arguments['baseline']);
    assertBaseline($baseline);

    $sourceMapPath = dirname(__DIR__, 3)
        . '/dist/symcon/saef-mqtt-discovery-exporter/fileset.sources.json';
    $filesetHashPath = dirname(__DIR__, 3)
        . '/dist/symcon/saef-mqtt-discovery-exporter/fileset.sha256';
    $sourceMap = readJsonFile($sourceMapPath);
    $filesetHash = readFilesetHash($filesetHashPath);
    if (($sourceMap['filesetSha256'] ?? null) !== $filesetHash) {
        throw new RuntimeException('Tracked MQTT fileset identity differs.');
    }

    $sourceHashes = [];
    foreach ($sourceMap['orderedSources'] ?? [] as $source) {
        if (is_array($source) && is_string($source['path'] ?? null) && is_string($source['sha256'] ?? null)) {
            $sourceHashes[$source['path']] = $source['sha256'];
        }
    }
    $runtimePath = 'case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';
    $corePath = 'case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterCore.php';
    $candidateRuntimeHash = $sourceHashes[$runtimePath] ?? null;
    $candidateCoreHash = $sourceHashes[$corePath] ?? null;
    assertSha256($candidateRuntimeHash, 'Candidate Runtime hash');
    assertSha256($candidateCoreHash, 'Candidate Core hash');

    $owners = [];
    foreach ($arguments['owners'] as $ownerID => $sourcePath) {
        $baselineOwner = $baseline['owners'][(string)$ownerID] ?? null;
        if (!is_array($baselineOwner)) {
            throw new RuntimeException('Owner is absent from the private baseline.');
        }
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Owner source cannot be read.');
        }
        $prepared = MqttSupersessionOwnerSource::prepare($source);
        if (!hash_equals($baselineOwner['sourceSha256'], $prepared['originalSourceSha256'])) {
            throw new RuntimeException('Owner source differs from the private baseline.');
        }

        $commandCount = $baselineOwner['commandEventCount'] ?? null;
        $eventCount = $baselineOwner['eventCount'] ?? null;
        if (!is_int($commandCount) || !is_int($eventCount) || $commandCount < 0 || $eventCount < $commandCount) {
            throw new RuntimeException('Owner baseline event counts are invalid.');
        }
        $owners[] = [
            'ownerScriptId' => $ownerID,
            'originalSourceSha256' => $prepared['originalSourceSha256'],
            'candidateSourceSha256' => $prepared['candidateSourceSha256'],
            'configurationLoaderSha256' => $prepared['configurationLoaderSha256'],
            'expectedEventCount' => $eventCount,
            'expectedCommandEventCount' => $commandCount,
            'expectedStateEventCount' => $eventCount - $commandCount,
            'originalSourceBase64' => base64_encode($prepared['originalSource']),
            'candidateSourceBase64' => base64_encode($prepared['candidateSource']),
            'configurationLoaderBase64' => base64_encode($prepared['configurationLoaderBody']),
        ];
    }
    usort($owners, static fn (array $left, array $right): int => $left['ownerScriptId'] <=> $right['ownerScriptId']);
    if (count($owners) !== 2 || count($owners) !== count($baseline['owners'])) {
        throw new RuntimeException('The migration input must cover the exact two-owner baseline.');
    }

    $input = [
        'formatVersion' => 1,
        'purpose' => MqttSupersessionOwnerMigration::PURPOSE,
        'targetId' => MqttSupersessionOwnerMigration::TARGET_ID,
        'repositoryBaseCommit' => $arguments['repositoryCommit'],
        'claimRoot' => $arguments['claimRoot'],
        'historicalRuntimeSha256' => $baseline['runtime']['runtimeSha256'],
        'historicalCoreSha256' => $baseline['runtime']['coreSha256'],
        'candidateRuntimeSha256' => $candidateRuntimeHash,
        'candidateCoreSha256' => $candidateCoreHash,
        'candidateFilesetSha256' => $filesetHash,
        'owners' => $owners,
    ];

    $json = json_encode(
        $input,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    writePrivateFile($arguments['output'], $json);

    fwrite(STDOUT, json_encode([
        'formatVersion' => 1,
        'outcome' => 'prepared',
        'repositoryBaseCommit' => $arguments['repositoryCommit'],
        'artifactCanonicalSha256' => hash(
            'sha256',
            MqttSupersessionOwnerMigration::canonicalJson($input)
        ),
        'candidateFilesetSha256' => $filesetHash,
        'ownerCount' => count($owners),
        'eventCount' => array_sum(array_column($owners, 'expectedEventCount')),
        'outputSha256' => hash('sha256', $json),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'MQTT supersession input preparation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $arguments
 *
 * @return array{
 *     baseline: string,
 *     claimRoot: string,
 *     output: string,
 *     repositoryCommit: string,
 *     owners: array<int, string>
 * }
 */
function parseArguments(array $arguments): array
{
    $result = [
        'baseline' => '',
        'claimRoot' => '',
        'output' => '',
        'repositoryCommit' => '',
        'owners' => [],
    ];
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--baseline=')) {
            $result['baseline'] = substr($argument, strlen('--baseline='));
        } elseif (str_starts_with($argument, '--output=')) {
            $result['output'] = substr($argument, strlen('--output='));
        } elseif (str_starts_with($argument, '--claim-root=')) {
            $result['claimRoot'] = substr($argument, strlen('--claim-root='));
        } elseif (str_starts_with($argument, '--repository-commit=')) {
            $result['repositoryCommit'] = substr($argument, strlen('--repository-commit='));
        } elseif (str_starts_with($argument, '--owner=')) {
            $specification = substr($argument, strlen('--owner='));
            if (preg_match('/^([1-9][0-9]*):(.*)$/D', $specification, $matches) !== 1) {
                throw new InvalidArgumentException('Owner argument must use ID:PATH.');
            }
            $ownerID = (int)$matches[1];
            if (isset($result['owners'][$ownerID]) || $matches[2] === '') {
                throw new InvalidArgumentException('Owner argument is duplicated or empty.');
            }
            $result['owners'][$ownerID] = $matches[2];
        } else {
            throw new InvalidArgumentException('Unsupported argument: ' . $argument);
        }
    }

    if (
        $result['baseline'] === ''
        || $result['claimRoot'] === ''
        || strlen($result['claimRoot']) > 1024
        || str_contains($result['claimRoot'], "\0")
        || $result['output'] === ''
        || preg_match('/^[0-9a-f]{40}$/D', $result['repositoryCommit']) !== 1
        || count($result['owners']) !== 2
    ) {
        throw new InvalidArgumentException(
            'Usage: php prepare-supersession-live-input.php '
            . '--baseline=FILE --claim-root=PATH --repository-commit=SHA '
            . '--owner=ID:FILE --owner=ID:FILE --output=FILE'
        );
    }
    foreach ([$result['baseline'], ...array_values($result['owners'])] as $path) {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Private input file is missing or linked.');
        }
    }

    return $result;
}

/** @return array<string, mixed> */
function readJsonFile(string $path): array
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException('JSON input cannot be read.');
    }
    $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('JSON input must be an object.');
    }

    return $decoded;
}

/** @param array<string, mixed> $baseline */
function assertBaseline(array $baseline): void
{
    if (
        ($baseline['formatVersion'] ?? null) !== 1
        || ($baseline['mutationAttempted'] ?? null) !== false
        || !is_array($baseline['runtime'] ?? null)
        || !is_array($baseline['owners'] ?? null)
        || count($baseline['owners']) !== 2
    ) {
        throw new RuntimeException('Private baseline contract is invalid.');
    }
    assertSha256($baseline['runtime']['runtimeSha256'] ?? null, 'Historical Runtime hash');
    assertSha256($baseline['runtime']['coreSha256'] ?? null, 'Historical Core hash');
}

function readFilesetHash(string $path): string
{
    $contents = file_get_contents($path);
    if (!is_string($contents) || preg_match('/^([0-9a-f]{64})  fileset\n$/D', $contents, $matches) !== 1) {
        throw new RuntimeException('Tracked MQTT fileset hash file is invalid.');
    }

    return $matches[1];
}

function assertSha256(mixed $value, string $label): void
{
    if (!is_string($value) || preg_match('/^[0-9a-f]{64}$/D', $value) !== 1) {
        throw new RuntimeException($label . ' is invalid.');
    }
}

function writePrivateFile(string $path, string $contents): void
{
    if (file_exists($path)) {
        throw new RuntimeException('Private output already exists.');
    }
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('Private output directory is missing.');
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
        @unlink($temporary);
        throw new RuntimeException('Private output could not be written.');
    }
    chmod($temporary, 0600);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Private output could not be committed atomically.');
    }
}
