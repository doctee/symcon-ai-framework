<?php

declare(strict_types=1);

namespace SAEF\CaseStudy\MqttDiscoveryExporter\Deployment;

final class MqttSupersessionSymconEnvironment implements MqttSupersessionMigrationEnvironment
{
    private const CLAIM_STATES = [
        'claimed' => 10,
        'events_disabled' => 20,
        'sources_updated' => 30,
        'diagnostics_initialized' => 40,
        'completed' => 50,
        'rolled_back' => 90,
    ];

    public function __construct(private readonly string $claimRoot)
    {
        if (
            $claimRoot === ''
            || str_contains($claimRoot, "\0")
            || !is_dir($claimRoot)
            || is_link($claimRoot)
        ) {
            throw new \RuntimeException('Migration claim root is invalid.');
        }
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function nonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function semaphoreEnter(string $name, int $timeoutMilliseconds): bool
    {
        return \IPS_SemaphoreEnter($name, $timeoutMilliseconds);
    }

    public function semaphoreLeave(string $name): bool
    {
        return \IPS_SemaphoreLeave($name);
    }

    public function scriptExists(int $scriptID): bool
    {
        return $scriptID > 0 && \IPS_ScriptExists($scriptID);
    }

    public function getScriptContent(int $scriptID): string
    {
        if (!$this->scriptExists($scriptID)) {
            throw new \RuntimeException('Migration script identity is invalid.');
        }
        return \IPS_GetScriptContent($scriptID);
    }

    public function setScriptContent(int $scriptID, string $source): void
    {
        if (!$this->scriptExists($scriptID) || $source === '') {
            throw new \RuntimeException('Migration script mutation is invalid.');
        }
        if (\IPS_SetScriptContent($scriptID, $source) === false) {
            throw new \RuntimeException('Migration script mutation failed.');
        }
        if (!hash_equals(hash('sha256', $source), hash('sha256', $this->getScriptContent($scriptID)))) {
            throw new \RuntimeException('Migration script mutation read-back differs.');
        }
    }

    public function objectExists(int $objectID): bool
    {
        return $objectID > 0 && \IPS_ObjectExists($objectID);
    }

    /** @return array<string, mixed> */
    public function getObject(int $objectID): array
    {
        if (!$this->objectExists($objectID)) {
            throw new \RuntimeException('Migration object identity is invalid.');
        }

        return \IPS_GetObject($objectID);
    }

    /** @return array<string, mixed> */
    public function getEvent(int $eventID): array
    {
        if (!$this->objectExists($eventID)) {
            throw new \RuntimeException('Migration event identity is invalid.');
        }

        return \IPS_GetEvent($eventID);
    }

    public function setEventActive(int $eventID, bool $active): void
    {
        $event = $this->getEvent($eventID);
        if (($event['EventActive'] ?? null) === $active) {
            return;
        }
        \IPS_SetEventActive($eventID, $active);
        if (($this->getEvent($eventID)['EventActive'] ?? null) !== $active) {
            throw new \RuntimeException('Migration event mutation read-back differs.');
        }
    }

    public function getObjectIDByIdent(string $ident, int $parentID): int|false
    {
        if ($ident === '' || $parentID <= 0 || !$this->objectExists($parentID)) {
            throw new \RuntimeException('Migration child lookup is invalid.');
        }
        $objectID = @\IPS_GetObjectIDByIdent($ident, $parentID);

        return $objectID === false ? false : (int)$objectID;
    }

    /** @return array<string, mixed> */
    public function getVariable(int $variableID): array
    {
        if ($variableID <= 0 || !\IPS_VariableExists($variableID)) {
            throw new \RuntimeException('Migration variable identity is invalid.');
        }

        return \IPS_GetVariable($variableID);
    }

    public function getValue(int $variableID): bool|int|float|string
    {
        if ($variableID <= 0 || !\IPS_VariableExists($variableID)) {
            throw new \RuntimeException('Migration variable identity is invalid.');
        }

        return \GetValue($variableID);
    }

    public function createClaim(string $planSha256, string $nonce): bool
    {
        $directory = $this->claimDirectory($planSha256, $nonce);
        if (!@mkdir($directory, 0700)) {
            if (is_dir($directory) && !is_link($directory)) {
                return false;
            }
            throw new \RuntimeException('Migration approval claim cannot be created.');
        }
        chmod($directory, 0700);
        $record = [
            'formatVersion' => 1,
            'planSha256' => $planSha256,
            'nonceSha256' => hash('sha256', $nonce),
        ];
        $this->writeClaimFile(
            $directory . '/00-contract.local.json',
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );
        $this->writeClaimPhase($directory, $planSha256, $nonce, 'claimed');

        return true;
    }

    public function getClaimState(string $planSha256, string $nonce): ?string
    {
        $directory = $this->claimDirectory($planSha256, $nonce);
        if (!file_exists($directory)) {
            return null;
        }
        if (!is_dir($directory) || is_link($directory)) {
            throw new \RuntimeException('Migration approval claim boundary is invalid.');
        }
        $entries = scandir($directory);
        if (!is_array($entries)) {
            throw new \RuntimeException('Migration approval claim cannot be read.');
        }
        $expectedNonceHash = hash('sha256', $nonce);
        $state = null;
        $contractSeen = false;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (!is_file($path) || is_link($path) || filesize($path) > 4096) {
                throw new \RuntimeException('Migration approval claim contains an invalid entry.');
            }
            $record = json_decode((string)file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
            if (
                !is_array($record)
                || ($record['formatVersion'] ?? null) !== 1
                || ($record['planSha256'] ?? null) !== $planSha256
                || ($record['nonceSha256'] ?? null) !== $expectedNonceHash
            ) {
                throw new \RuntimeException('Migration approval claim identity differs.');
            }
            if ($entry === '00-contract.local.json') {
                if (array_key_exists('state', $record)) {
                    throw new \RuntimeException('Migration approval claim contract differs.');
                }
                $contractSeen = true;
                continue;
            }
            if (preg_match('/^([0-9]{2})-([a-z_]+)\.local\.json$/D', $entry, $matches) !== 1) {
                throw new \RuntimeException('Migration approval claim entry is unexpected.');
            }
            $candidateState = $matches[2];
            if (
                !isset(self::CLAIM_STATES[$candidateState])
                || (int)$matches[1] !== self::CLAIM_STATES[$candidateState]
                || ($record['state'] ?? null) !== $candidateState
            ) {
                throw new \RuntimeException('Migration approval claim phase differs.');
            }
            if ($state === null || self::CLAIM_STATES[$candidateState] > self::CLAIM_STATES[$state]) {
                $state = $candidateState;
            }
        }
        if (!$contractSeen || $state === null) {
            throw new \RuntimeException('Migration approval claim phase is missing.');
        }

        return $state;
    }

    public function transitionClaim(
        string $planSha256,
        string $nonce,
        string $expectedState,
        string $newState
    ): void {
        $currentState = $this->getClaimState($planSha256, $nonce);
        if ($currentState !== $expectedState || !isset(self::CLAIM_STATES[$newState])) {
            throw new \RuntimeException('Migration approval claim transition is stale.');
        }
        $allowed = $newState === 'rolled_back'
            ? $expectedState !== 'rolled_back'
            : self::CLAIM_STATES[$newState] === self::CLAIM_STATES[$expectedState] + 10;
        if (!$allowed) {
            throw new \RuntimeException('Migration approval claim transition is invalid.');
        }
        $this->writeClaimPhase(
            $this->claimDirectory($planSha256, $nonce),
            $planSha256,
            $nonce,
            $newState
        );
    }

    /** @return array{runtimeSha256: string, coreSha256: string, filesetSha256: string} */
    public static function activeRuntimeIdentity(): array
    {
        $runtimeReflection = new \ReflectionClass(
            \SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime::class
        );
        $coreReflection = new \ReflectionClass(
            \SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore::class
        );
        $runtimePath = $runtimeReflection->getFileName();
        $corePath = $coreReflection->getFileName();
        if (!is_string($runtimePath) || !is_string($corePath)) {
            throw new \RuntimeException('Active MQTT runtime source paths are unavailable.');
        }

        $filesetRoot = dirname($runtimePath, 4);
        $expectedRuntimePath = $filesetRoot
            . '/case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php';
        $expectedCorePath = $filesetRoot
            . '/case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterCore.php';
        $filesetHashPath = $filesetRoot . '/fileset.sha256';
        foreach ([$runtimePath, $corePath, $filesetHashPath] as $path) {
            if (!is_file($path) || is_link($path)) {
                throw new \RuntimeException('Active MQTT runtime identity path is invalid.');
            }
        }
        if (
            realpath($runtimePath) !== realpath($expectedRuntimePath)
            || realpath($corePath) !== realpath($expectedCorePath)
        ) {
            throw new \RuntimeException('Active MQTT runtime source ownership differs.');
        }

        $filesetHash = file_get_contents($filesetHashPath);
        if (!is_string($filesetHash) || preg_match('/^([a-f0-9]{64})  fileset\r?\n$/D', $filesetHash, $matches) !== 1) {
            throw new \RuntimeException('Active MQTT fileset identity is invalid.');
        }

        return [
            'runtimeSha256' => hash_file('sha256', $runtimePath),
            'coreSha256' => hash_file('sha256', $corePath),
            'filesetSha256' => $matches[1],
        ];
    }

    private function claimDirectory(string $planSha256, string $nonce): string
    {
        if (
            preg_match('/^[a-f0-9]{64}$/D', $planSha256) !== 1
            || preg_match('/^[a-f0-9]{32}$/D', $nonce) !== 1
        ) {
            throw new \RuntimeException('Migration approval claim identity is invalid.');
        }

        return rtrim($this->claimRoot, '/\\') . '/' . $planSha256 . '.claim.local';
    }

    private function writeClaimPhase(
        string $directory,
        string $planSha256,
        string $nonce,
        string $state
    ): void {
        $record = [
            'formatVersion' => 1,
            'planSha256' => $planSha256,
            'nonceSha256' => hash('sha256', $nonce),
            'state' => $state,
            'timestampUtc' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s.u\Z'),
        ];
        $path = sprintf('%s/%02d-%s.local.json', $directory, self::CLAIM_STATES[$state], $state);
        $this->writeClaimFile(
            $path,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );
    }

    private function writeClaimFile(string $path, string $contents): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new \RuntimeException('Migration approval claim file already exists.');
        }
        try {
            if (fwrite($handle, $contents) !== strlen($contents) || !fflush($handle)) {
                throw new \RuntimeException('Migration approval claim file cannot be written.');
            }
        } finally {
            fclose($handle);
        }
        chmod($path, 0600);
    }
}
