<?php

declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionMigrationEnvironment;
use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionOwnerMigration;
use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionOwnerSource;
use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionSymconEnvironment;

require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/deployment/MqttSupersessionMigrationEnvironment.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/deployment/MqttSupersessionOwnerSource.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/deployment/MqttSupersessionOwnerMigration.php';
require_once __DIR__ . '/../../case-studies/mqtt-discovery-exporter/deployment/MqttSupersessionSymconEnvironment.php';

final class SupersessionMigrationFakeEnvironment implements MqttSupersessionMigrationEnvironment
{
    /** @var array<int, string> */
    private array $scripts = [];

    /** @var array<int, array<string, mixed>> */
    private array $objects = [];

    /** @var array<int, array<string, mixed>> */
    private array $events = [];

    /** @var array<int, array<string, mixed>> */
    private array $variables = [];

    /** @var array<int, bool|int|float|string> */
    private array $values = [];

    /** @var array<string, int> */
    private array $idents = [];

    private bool $semaphoreBusy = false;
    private int $mutationCount = 0;
    private int $nextID = 1000;

    /** @var array<string, array{nonceSha256: string, state: string}> */
    private array $claims = [];

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-14T12:00:00.000000Z');
    }

    public function nonce(): string
    {
        return '0123456789abcdef0123456789abcdef';
    }

    public function semaphoreEnter(string $name, int $timeoutMilliseconds): bool
    {
        if ($name === '' || $timeoutMilliseconds < 0 || $this->semaphoreBusy) {
            return false;
        }
        $this->semaphoreBusy = true;

        return true;
    }

    public function semaphoreLeave(string $name): bool
    {
        if ($name === '' || !$this->semaphoreBusy) {
            return false;
        }
        $this->semaphoreBusy = false;

        return true;
    }

    public function scriptExists(int $scriptID): bool
    {
        return isset($this->scripts[$scriptID]);
    }

    public function getScriptContent(int $scriptID): string
    {
        if (!$this->scriptExists($scriptID)) {
            throw new RuntimeException('Unknown fake script.');
        }

        return $this->scripts[$scriptID];
    }

    public function setScriptContent(int $scriptID, string $source): void
    {
        if (!$this->scriptExists($scriptID)) {
            throw new RuntimeException('Unknown fake script.');
        }
        ++$this->mutationCount;
        $this->scripts[$scriptID] = $source;
    }

    public function objectExists(int $objectID): bool
    {
        return isset($this->objects[$objectID]);
    }

    /** @return array<string, mixed> */
    public function getObject(int $objectID): array
    {
        if (!$this->objectExists($objectID)) {
            throw new RuntimeException('Unknown fake object.');
        }

        return $this->objects[$objectID];
    }

    /** @return array<string, mixed> */
    public function getEvent(int $eventID): array
    {
        if (!isset($this->events[$eventID])) {
            throw new RuntimeException('Unknown fake event.');
        }

        return $this->events[$eventID];
    }

    public function setEventActive(int $eventID, bool $active): void
    {
        if (!isset($this->events[$eventID])) {
            throw new RuntimeException('Unknown fake event.');
        }
        ++$this->mutationCount;
        $this->events[$eventID]['EventActive'] = $active;
    }

    public function getObjectIDByIdent(string $ident, int $parentID): int|false
    {
        return $this->idents[$parentID . ':' . $ident] ?? false;
    }

    /** @return array<string, mixed> */
    public function getVariable(int $variableID): array
    {
        if (!isset($this->variables[$variableID])) {
            throw new RuntimeException('Unknown fake variable.');
        }

        return $this->variables[$variableID];
    }

    public function getValue(int $variableID): bool|int|float|string
    {
        if (!array_key_exists($variableID, $this->values)) {
            throw new RuntimeException('Unknown fake value.');
        }

        return $this->values[$variableID];
    }

    public function createClaim(string $planSha256, string $nonce): bool
    {
        if (isset($this->claims[$planSha256])) {
            return false;
        }
        $this->claims[$planSha256] = [
            'nonceSha256' => hash('sha256', $nonce),
            'state' => 'claimed',
        ];

        return true;
    }

    public function getClaimState(string $planSha256, string $nonce): ?string
    {
        $claim = $this->claims[$planSha256] ?? null;
        if ($claim === null) {
            return null;
        }
        if (!hash_equals($claim['nonceSha256'], hash('sha256', $nonce))) {
            throw new RuntimeException('Fake claim nonce differs.');
        }

        return $claim['state'];
    }

    public function transitionClaim(
        string $planSha256,
        string $nonce,
        string $expectedState,
        string $newState
    ): void {
        if ($this->getClaimState($planSha256, $nonce) !== $expectedState) {
            throw new RuntimeException('Fake claim transition is stale.');
        }
        $this->claims[$planSha256]['state'] = $newState;
    }

    public function addOwner(int $ownerID, string $source, array $configuration, callable $hasher): void
    {
        $this->scripts[$ownerID] = $source;
        $this->objects[$ownerID] = self::object($ownerID, 0, '', 3);
        $diagnosticsID = $this->addObject($ownerID, 'MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS', 0);
        $registryID = $this->addVariable($diagnosticsID, 'MANAGED_STATE_REGISTRY', 3, '');
        $commandVariableID = ++$this->nextID;
        $stateVariableID = ++$this->nextID;
        $commandEventID = $this->addEvent(
            $ownerID,
            'COMMAND_POWER',
            0,
            $commandVariableID
        );
        $stateEventID = $this->addEvent(
            $ownerID,
            'STATE_POWER',
            1,
            $stateVariableID
        );
        $configurationHash = $hasher($configuration);
        $registry = [
            'schemaVersion' => 1,
            'preparedConfigurationHash' => $configurationHash,
            'publishedConfigurationHash' => $configurationHash,
            'managedEntities' => [[
                'commandEventIDs' => ['power' => $commandEventID],
                'commandEventIdents' => ['power' => 'COMMAND_POWER'],
                'stateEventIDs' => ['power' => $stateEventID],
                'stateEventIdents' => ['power' => 'STATE_POWER'],
            ]],
            'commandIndex' => [(string)$commandVariableID => ['capability' => 'power']],
            'stateIndex' => [(string)$stateVariableID => ['capability' => 'power']],
        ];
        $this->values[$registryID] = json_encode(
            $registry,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /** @return array<string, mixed> */
    public function initializeSupersessionDiagnostics(int $ownerID): array
    {
        $diagnosticsID = $this->requiredIdent($ownerID, 'MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS');
        $arbitrationID = $this->getObjectIDByIdent('COMMAND_ARBITRATION_REGISTRY', $diagnosticsID);
        if ($arbitrationID === false) {
            $arbitrationID = $this->addVariable(
                $diagnosticsID,
                'COMMAND_ARBITRATION_REGISTRY',
                3,
                json_encode(
                    ['schemaVersion' => 1, 'channels' => []],
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                )
            );
        }
        $supersededID = $this->getObjectIDByIdent('SUPERSEDED_COMMANDS', $diagnosticsID);
        if ($supersededID === false) {
            $supersededID = $this->addVariable($diagnosticsID, 'SUPERSEDED_COMMANDS', 1, 0);
        }

        return ['arbitrationRegistryID' => $arbitrationID, 'supersededID' => $supersededID];
    }

    public function mutationCount(): int
    {
        return $this->mutationCount;
    }

    public function forceSemaphoreBusy(bool $busy): void
    {
        $this->semaphoreBusy = $busy;
    }

    public function duplicateManagedEvent(int $ownerID): void
    {
        $diagnosticsID = $this->requiredIdent($ownerID, 'MQTT_DISCOVERY_EXPORTER_DIAGNOSTICS');
        $registryID = $this->requiredIdent($diagnosticsID, 'MANAGED_STATE_REGISTRY');
        $registry = json_decode((string)$this->values[$registryID], true, 64, JSON_THROW_ON_ERROR);
        $registry['managedEntities'][] = $registry['managedEntities'][0];
        $this->values[$registryID] = json_encode(
            $registry,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function addObject(int $parentID, string $ident, int $type): int
    {
        $id = ++$this->nextID;
        $this->objects[$id] = self::object($id, $parentID, $ident, $type);
        $this->idents[$parentID . ':' . $ident] = $id;

        return $id;
    }

    private function addVariable(
        int $parentID,
        string $ident,
        int $type,
        bool|int|float|string $value
    ): int {
        $id = $this->addObject($parentID, $ident, 2);
        $this->variables[$id] = ['VariableType' => $type];
        $this->values[$id] = $value;

        return $id;
    }

    private function addEvent(int $parentID, string $ident, int $triggerType, int $triggerID): int
    {
        $id = $this->addObject($parentID, $ident, 4);
        $this->events[$id] = [
            'EventType' => 0,
            'EventTriggerType' => $triggerType,
            'EventTriggerVariableID' => $triggerID,
            'EventActionID' => MqttSupersessionOwnerMigration::AUTOMATION_ACTION_ID,
            'EventActive' => true,
        ];

        return $id;
    }

    private function requiredIdent(int $parentID, string $ident): int
    {
        $id = $this->getObjectIDByIdent($ident, $parentID);
        if ($id === false) {
            throw new RuntimeException('Unknown fake ident.');
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private static function object(int $id, int $parentID, string $ident, int $type): array
    {
        return [
            'ObjectID' => $id,
            'ParentID' => $parentID,
            'ObjectIdent' => $ident,
            'ObjectType' => $type,
        ];
    }
}

function assertSupersessionTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSupersessionSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . '.');
    }
}

/** @param class-string<Throwable> $expected */
function assertSupersessionThrows(string $expected, callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $expected) {
            return;
        }
        throw new RuntimeException($message . ' Unexpected ' . $exception::class . '.');
    }
    throw new RuntimeException($message . ' Expected exception was not thrown.');
}

function removeSupersessionFixtureDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
            continue;
        }
        unlink($item->getPathname());
    }
    rmdir($path);
}

/**
 * @param list<string> $command
 *
 * @return array{status: int, stdout: string, stderr: string}
 */
function runSupersessionCommand(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start supersession test subprocess.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'status' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/**
 * @return array{
 *     environment: SupersessionMigrationFakeEnvironment,
 *     input: array<string, mixed>,
 *     loaders: array<int, callable(): array<string, mixed>>,
 *     hasher: callable(array<string, mixed>): string,
 *     initializer: callable(int, array<string, mixed>): array<string, mixed>,
 *     identity: array{runtimeSha256: string, coreSha256: string, filesetSha256: string}
 * }
 */
function supersessionFixture(): array
{
    $environment = new SupersessionMigrationFakeEnvironment();
    $configurations = [
        101 => ['version' => 'test-a', 'devices' => []],
        102 => ['version' => 'test-b', 'devices' => []],
    ];
    $hasher = static fn (array $configuration): string => hash(
        'sha256',
        MqttSupersessionOwnerMigration::canonicalJson($configuration)
    );
    $owners = [];
    foreach ($configurations as $ownerID => $configuration) {
        $original = "<?php\n// owner {$ownerID}\n";
        $candidate = $original . "// immutable event value\n";
        $environment->addOwner($ownerID, $original, $configuration, $hasher);
        $owners[] = [
            'ownerScriptId' => $ownerID,
            'originalSourceSha256' => hash('sha256', $original),
            'candidateSourceSha256' => hash('sha256', $candidate),
            'configurationLoaderSha256' => hash('sha256', 'loader-' . $ownerID),
            'expectedEventCount' => 2,
            'expectedCommandEventCount' => 1,
            'expectedStateEventCount' => 1,
            'originalSourceBase64' => base64_encode($original),
            'candidateSourceBase64' => base64_encode($candidate),
        ];
    }
    $loaders = [
        101 => static fn (): array => $configurations[101],
        102 => static fn (): array => $configurations[102],
    ];
    $identity = [
        'runtimeSha256' => hash('sha256', 'candidate-runtime'),
        'coreSha256' => hash('sha256', 'candidate-core'),
        'filesetSha256' => hash('sha256', 'candidate-fileset'),
    ];
    $input = [
        'formatVersion' => 1,
        'purpose' => MqttSupersessionOwnerMigration::PURPOSE,
        'targetId' => MqttSupersessionOwnerMigration::TARGET_ID,
        'repositoryBaseCommit' => str_repeat('a', 40),
        'claimRoot' => '/private/fake-claims',
        'historicalRuntimeSha256' => hash('sha256', 'historical-runtime'),
        'historicalCoreSha256' => hash('sha256', 'historical-core'),
        'candidateRuntimeSha256' => $identity['runtimeSha256'],
        'candidateCoreSha256' => $identity['coreSha256'],
        'candidateFilesetSha256' => $identity['filesetSha256'],
        'owners' => $owners,
    ];
    $initializer = static fn (int $ownerID, array $configuration): array =>
        $environment->initializeSupersessionDiagnostics($ownerID);

    return compact('environment', 'input', 'loaders', 'hasher', 'initializer', 'identity');
}

/** @return array{status: array<string, mixed>, plan: array<string, mixed>, planSha256: string} */
function supersessionPreflight(array $fixture): array
{
    $status = MqttSupersessionOwnerMigration::preflight(
        $fixture['input'],
        $fixture['environment'],
        $fixture['loaders'],
        $fixture['hasher'],
        $fixture['identity']
    );

    return [
        'status' => $status,
        'plan' => $status['reviewPlan'],
        'planSha256' => $status['planSha256'],
    ];
}

$ownerSource = <<<'PHP'
<?php

use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterCore;
use SAEF\CaseStudy\MqttDiscoveryExporter\MqttDiscoveryExporterRuntime;

$configuration = MqttDiscoveryExporterCore::normalizeConfiguration(['version' => 'test']);
MqttDiscoveryExporterRuntime::dispatchTriggeredVariable(
    (int)$_IPS['SELF'],
    $configuration,
    (int)$_IPS['VARIABLE']
);
PHP;
$preparedOwner = MqttSupersessionOwnerSource::prepare($ownerSource);
assertSupersessionSame(
    1,
    substr_count($preparedOwner['candidateSource'], "\$_IPS['VALUE'] ?? null"),
    'Event-time value was not added exactly once.'
);
assertSupersessionTrue(
    str_contains($preparedOwner['configurationLoaderBody'], 'return $configuration;'),
    'Configuration loader does not return the normalized configuration.'
);
assertSupersessionThrows(
    RuntimeException::class,
    static fn (): string => MqttSupersessionOwnerSource::addEventValueSnapshot(
        str_replace('(int)$_IPS[\'VARIABLE\']', "(int)\$_IPS['VARIABLE'], \$_IPS['VALUE']", $ownerSource)
    ),
    'An already migrated owner source was accepted.'
);
assertSupersessionThrows(
    RuntimeException::class,
    static fn (): array => MqttSupersessionOwnerSource::prepare(
        str_replace(
            '$configuration =',
            '$unsafe = IPS_CreateVariable(3);' . "\n" . '$configuration =',
            $ownerSource
        )
    ),
    'A mutating configuration loader was accepted.'
);

$claimRoot = sys_get_temp_dir() . '/saef-mqtt-supersession-claim-' . bin2hex(random_bytes(8));
assertSupersessionTrue(mkdir($claimRoot, 0700), 'Claim fixture root could not be created.');
try {
    $claimEnvironment = new MqttSupersessionSymconEnvironment($claimRoot);
    $claimPlanSha256 = str_repeat('b', 64);
    $claimNonce = 'fedcba9876543210fedcba9876543210';
    assertSupersessionTrue(
        $claimEnvironment->createClaim($claimPlanSha256, $claimNonce),
        'Filesystem claim was not created.'
    );
    assertSupersessionSame(
        'claimed',
        $claimEnvironment->getClaimState($claimPlanSha256, $claimNonce),
        'Filesystem claim initial state differs.'
    );
    assertSupersessionSame(
        false,
        $claimEnvironment->createClaim($claimPlanSha256, $claimNonce),
        'Filesystem claim replay was accepted.'
    );
    $claimEnvironment->transitionClaim(
        $claimPlanSha256,
        $claimNonce,
        'claimed',
        'events_disabled'
    );
    assertSupersessionSame(
        'events_disabled',
        $claimEnvironment->getClaimState($claimPlanSha256, $claimNonce),
        'Filesystem claim transition was not persisted.'
    );
    assertSupersessionThrows(
        RuntimeException::class,
        static fn (): ?string => $claimEnvironment->getClaimState(
            $claimPlanSha256,
            '0123456789abcdef0123456789abcdef'
        ),
        'Filesystem claim accepted a mismatched nonce.'
    );
    assertSupersessionThrows(
        RuntimeException::class,
        static fn (): mixed => $claimEnvironment->transitionClaim(
            $claimPlanSha256,
            $claimNonce,
            'claimed',
            'events_disabled'
        ),
        'Filesystem claim accepted a stale transition.'
    );
} finally {
    removeSupersessionFixtureDirectory($claimRoot);
}

$rendererRoot = sys_get_temp_dir() . '/saef-mqtt-supersession-renderer-' . bin2hex(random_bytes(8));
assertSupersessionTrue(mkdir($rendererRoot, 0700), 'Renderer fixture root could not be created.');
try {
    $rendererFixture = supersessionFixture();
    $configurationLoader = <<<'PHP'
$configuration = MqttDiscoveryExporterCore::normalizeConfiguration([
    'version' => 'renderer-fixture',
    'devices' => [],
]);
return $configuration;
PHP;
    foreach ($rendererFixture['input']['owners'] as &$rendererOwner) {
        $rendererOwner['configurationLoaderSha256'] = hash('sha256', $configurationLoader);
        $rendererOwner['configurationLoaderBase64'] = base64_encode($configurationLoader);
    }
    unset($rendererOwner);

    $rendererInputPath = $rendererRoot . '/migration-input.local.json';
    $rendererOutputPath = $rendererRoot . '/preflight-script.local.php';
    $rendererPath = __DIR__
        . '/../../case-studies/mqtt-discovery-exporter/tools/render-supersession-owner-migration-script.php';
    $rendererInput = json_encode(
        $rendererFixture['input'],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . "\n";
    assertSupersessionSame(
        strlen($rendererInput),
        file_put_contents($rendererInputPath, $rendererInput, LOCK_EX),
        'Renderer fixture input could not be written.'
    );

    $renderResult = runSupersessionCommand([
        PHP_BINARY,
        $rendererPath,
        '--input=' . $rendererInputPath,
        '--operation=preflight',
        '--output=' . $rendererOutputPath,
    ]);
    assertSupersessionSame(0, $renderResult['status'], 'Renderer positive case failed.');
    $renderStatus = json_decode($renderResult['stdout'], true, 16, JSON_THROW_ON_ERROR);
    assertSupersessionSame('rendered', $renderStatus['outcome'] ?? null, 'Renderer outcome differs.');
    $renderedScript = file_get_contents($rendererOutputPath);
    assertSupersessionTrue(is_string($renderedScript), 'Rendered script cannot be read.');
    assertSupersessionSame(
        1,
        substr_count($renderedScript, 'interface MqttSupersessionMigrationEnvironment'),
        'Rendered script does not contain exactly one migration interface.'
    );
    assertSupersessionSame(
        1,
        substr_count($renderedScript, 'final class MqttSupersessionOwnerMigration'),
        'Rendered script does not contain exactly one migration transaction.'
    );
    $lintResult = runSupersessionCommand([PHP_BINARY, '-l', $rendererOutputPath]);
    assertSupersessionSame(0, $lintResult['status'], 'Rendered script is not valid PHP.');

    $replayResult = runSupersessionCommand([
        PHP_BINARY,
        $rendererPath,
        '--input=' . $rendererInputPath,
        '--operation=preflight',
        '--output=' . $rendererOutputPath,
    ]);
    assertSupersessionSame(1, $replayResult['status'], 'Renderer output replay was accepted.');
} finally {
    removeSupersessionFixtureDirectory($rendererRoot);
}

$fixture = supersessionFixture();
$preflight = supersessionPreflight($fixture);
assertSupersessionSame('ready', $preflight['status']['outcome'], 'Preflight did not become ready.');
assertSupersessionSame(0, $fixture['environment']->mutationCount(), 'Preflight mutated fake state.');
assertSupersessionSame(4, $preflight['status']['eventCount'], 'Reviewed event count differs.');
assertSupersessionTrue(
    !str_contains($preflight['status']['timestampUtc'], '.uZ'),
    'Status timestamp contains a literal microsecond marker.'
);

$apply = MqttSupersessionOwnerMigration::apply(
    $fixture['input'],
    $preflight['plan'],
    $preflight['planSha256'],
    $fixture['environment'],
    $fixture['loaders'],
    $fixture['hasher'],
    $fixture['initializer'],
    $fixture['identity']
);
assertSupersessionSame('migrated', $apply['outcome'], 'Migration did not complete.');
assertSupersessionSame(false, $apply['mqttPublishAttempted'], 'Migration reported MQTT publication.');
assertSupersessionSame(false, $apply['deviceActionAttempted'], 'Migration reported a device action.');

$inspect = MqttSupersessionOwnerMigration::inspect(
    $fixture['input'],
    $preflight['plan'],
    $preflight['planSha256'],
    $fixture['environment'],
    $fixture['loaders'],
    $fixture['hasher'],
    $fixture['identity']
);
assertSupersessionSame('migrated', $inspect['inspectionState'], 'Migrated state was not inspected.');

$mutationsBeforeReplay = $fixture['environment']->mutationCount();
$replay = MqttSupersessionOwnerMigration::apply(
    $fixture['input'],
    $preflight['plan'],
    $preflight['planSha256'],
    $fixture['environment'],
    $fixture['loaders'],
    $fixture['hasher'],
    $fixture['initializer'],
    $fixture['identity']
);
assertSupersessionSame('failed', $replay['outcome'], 'Replay was not rejected.');
assertSupersessionSame(
    $mutationsBeforeReplay,
    $fixture['environment']->mutationCount(),
    'Replay mutated state.'
);

$rollback = MqttSupersessionOwnerMigration::rollback(
    $fixture['input'],
    $preflight['plan'],
    $preflight['planSha256'],
    $fixture['environment'],
    $fixture['loaders']
);
assertSupersessionSame('rolled_back', $rollback['outcome'], 'Explicit rollback failed.');
$baselineInspect = MqttSupersessionOwnerMigration::inspect(
    $fixture['input'],
    $preflight['plan'],
    $preflight['planSha256'],
    $fixture['environment'],
    $fixture['loaders'],
    $fixture['hasher'],
    $fixture['identity']
);
assertSupersessionSame('rolled_back', $baselineInspect['inspectionState'], 'Rollback state was not retained.');
$mutationsBeforeRolledBackReplay = $fixture['environment']->mutationCount();
$rolledBackReplay = MqttSupersessionOwnerMigration::apply(
    $fixture['input'],
    $preflight['plan'],
    $preflight['planSha256'],
    $fixture['environment'],
    $fixture['loaders'],
    $fixture['hasher'],
    $fixture['initializer'],
    $fixture['identity']
);
assertSupersessionSame('failed', $rolledBackReplay['outcome'], 'Rolled-back plan replay was accepted.');
assertSupersessionSame(
    $mutationsBeforeRolledBackReplay,
    $fixture['environment']->mutationCount(),
    'Rolled-back plan replay mutated production state.'
);

$driftFixture = supersessionFixture();
$driftPreflight = supersessionPreflight($driftFixture);
$driftFixture['environment']->setScriptContent(101, '<?php // unreviewed drift');
$mutationsBeforeDriftApply = $driftFixture['environment']->mutationCount();
$driftResult = MqttSupersessionOwnerMigration::apply(
    $driftFixture['input'],
    $driftPreflight['plan'],
    $driftPreflight['planSha256'],
    $driftFixture['environment'],
    $driftFixture['loaders'],
    $driftFixture['hasher'],
    $driftFixture['initializer'],
    $driftFixture['identity']
);
assertSupersessionSame('failed', $driftResult['outcome'], 'Source drift was not rejected.');
assertSupersessionSame(
    $mutationsBeforeDriftApply,
    $driftFixture['environment']->mutationCount(),
    'Drift rejection performed an additional mutation.'
);

$rollbackFixture = supersessionFixture();
$rollbackPreflight = supersessionPreflight($rollbackFixture);
$initializationCount = 0;
$failingInitializer = static function (
    int $ownerID,
    array $configuration
) use (
    $rollbackFixture,
    &$initializationCount
): array {
    ++$initializationCount;
    $result = $rollbackFixture['environment']->initializeSupersessionDiagnostics($ownerID);
    if ($initializationCount === 2) {
        throw new RuntimeException('Synthetic diagnostics failure.');
    }

    return $result;
};
$rolledBack = MqttSupersessionOwnerMigration::apply(
    $rollbackFixture['input'],
    $rollbackPreflight['plan'],
    $rollbackPreflight['planSha256'],
    $rollbackFixture['environment'],
    $rollbackFixture['loaders'],
    $rollbackFixture['hasher'],
    $failingInitializer,
    $rollbackFixture['identity']
);
assertSupersessionSame('rolled_back', $rolledBack['outcome'], 'Automatic rollback did not complete.');
assertSupersessionSame(true, $rolledBack['rollbackSucceeded'], 'Automatic rollback is unproven.');

$partialFixture = supersessionFixture();
$partialPreflight = supersessionPreflight($partialFixture);
$firstCandidate = base64_decode(
    $partialFixture['input']['owners'][0]['candidateSourceBase64'],
    true
);
assertSupersessionTrue(is_string($firstCandidate), 'Synthetic candidate source is invalid.');
$partialFixture['environment']->setScriptContent(101, $firstCandidate);
$partialInspect = MqttSupersessionOwnerMigration::inspect(
    $partialFixture['input'],
    $partialPreflight['plan'],
    $partialPreflight['planSha256'],
    $partialFixture['environment'],
    $partialFixture['loaders'],
    $partialFixture['hasher'],
    $partialFixture['identity']
);
assertSupersessionSame(
    'manual_recovery_required',
    $partialInspect['inspectionState'],
    'Partial source state did not require manual recovery.'
);

$duplicateFixture = supersessionFixture();
$duplicateFixture['environment']->duplicateManagedEvent(101);
assertSupersessionThrows(
    RuntimeException::class,
    static fn (): array => supersessionPreflight($duplicateFixture),
    'Duplicated event ownership was accepted.'
);

$busyFixture = supersessionFixture();
$busyPreflight = supersessionPreflight($busyFixture);
$busyFixture['environment']->forceSemaphoreBusy(true);
assertSupersessionThrows(
    RuntimeException::class,
    static fn (): array => MqttSupersessionOwnerMigration::apply(
        $busyFixture['input'],
        $busyPreflight['plan'],
        $busyPreflight['planSha256'],
        $busyFixture['environment'],
        $busyFixture['loaders'],
        $busyFixture['hasher'],
        $busyFixture['initializer'],
        $busyFixture['identity']
    ),
    'Concurrent migration was accepted.'
);

$implementation = file_get_contents(
    __DIR__ . '/../../case-studies/mqtt-discovery-exporter/deployment/MqttSupersessionOwnerMigration.php'
);
$renderer = file_get_contents(
    __DIR__ . '/../../case-studies/mqtt-discovery-exporter/tools/render-supersession-owner-migration-script.php'
);
assertSupersessionTrue(is_string($implementation) && is_string($renderer), 'Migration sources are unreadable.');
foreach (['RequestAction(', 'MQTT_Publish', 'IPS_RunScript', 'IPS_RunScriptEx'] as $forbidden) {
    assertSupersessionTrue(
        !str_contains($implementation . $renderer, $forbidden),
        'Migration implementation contains a forbidden action: ' . $forbidden
    );
}

fwrite(STDOUT, "PASS: MQTT supersession owner migration contract\n");
