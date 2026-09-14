<?php

declare(strict_types=1);

use SAEF\CaseStudy\MqttDiscoveryExporter\Deployment\MqttSupersessionOwnerMigration;

require_once __DIR__ . '/../deployment/MqttSupersessionMigrationEnvironment.php';
require_once __DIR__ . '/../deployment/MqttSupersessionOwnerMigration.php';

try {
    $arguments = parseRendererArguments(array_slice($argv, 1));
    $input = readRendererJson($arguments['input']);
    $plan = null;
    $expectedPlanSha256 = '';
    if ($arguments['operation'] !== 'preflight') {
        $preflight = readRendererJson($arguments['preflight']);
        $plan = $preflight['reviewPlan'] ?? null;
        $expectedPlanSha256 = $preflight['planSha256'] ?? '';
        if (
            !is_array($plan)
            || !is_string($expectedPlanSha256)
            || preg_match('/^[a-f0-9]{64}$/D', $expectedPlanSha256) !== 1
            || !hash_equals(
                $expectedPlanSha256,
                hash('sha256', MqttSupersessionOwnerMigration::canonicalJson($plan))
            )
        ) {
            throw new RuntimeException('Preflight plan identity is invalid.');
        }
    }

    $interfaceSource = readRendererSource(
        __DIR__ . '/../deployment/MqttSupersessionMigrationEnvironment.php'
    );
    $migrationSource = readRendererSource(
        __DIR__ . '/../deployment/MqttSupersessionOwnerMigration.php'
    );
    $environmentSource = readRendererSource(
        __DIR__ . '/../deployment/MqttSupersessionSymconEnvironment.php'
    );
    $runIdentity = hash(
        'sha256',
        MqttSupersessionOwnerMigration::canonicalJson([
            'operation' => $arguments['operation'],
            'input' => $input,
            'planSha256' => $expectedPlanSha256,
        ])
    );
    $namespace = 'SAEF\\CaseStudy\\MqttDiscoveryExporter\\Deployment\\Run'
        . strtoupper(substr($runIdentity, 0, 16));
    $interfaceSource = replaceRendererNamespace($interfaceSource, $namespace, false);
    $migrationSource = replaceRendererNamespace($migrationSource, $namespace, true);
    $environmentSource = replaceRendererNamespace($environmentSource, $namespace, false);

    $loaders = [];
    foreach ($input['owners'] ?? [] as $owner) {
        if (!is_array($owner) || !is_int($owner['ownerScriptId'] ?? null)) {
            throw new RuntimeException('Migration owner input is invalid.');
        }
        $loader = base64_decode((string)($owner['configurationLoaderBase64'] ?? ''), true);
        if (
            !is_string($loader)
            || !hash_equals(
                (string)($owner['configurationLoaderSha256'] ?? ''),
                hash('sha256', $loader)
            )
        ) {
            throw new RuntimeException('Migration configuration loader identity differs.');
        }
        if (substr_count($loader, 'MqttDiscoveryExporterCore') !== 1) {
            throw new RuntimeException('Migration configuration loader Core reference differs.');
        }
        $loaders[$owner['ownerScriptId']] = str_replace(
            'MqttDiscoveryExporterCore',
            '\\SAEF\\CaseStudy\\MqttDiscoveryExporter\\MqttDiscoveryExporterCore',
            $loader
        );
    }
    if (count($loaders) !== 2) {
        throw new RuntimeException('Migration renderer requires exactly two owner loaders.');
    }

    $script = $migrationSource . "\n" . $interfaceSource . "\n" . $environmentSource . "\n";
    $script .= renderRunner(
        $arguments['operation'],
        $input,
        $plan,
        $expectedPlanSha256,
        $loaders
    );
    writeRendererOutput($arguments['output'], $script);

    fwrite(STDOUT, json_encode([
        'formatVersion' => 1,
        'outcome' => 'rendered',
        'operation' => $arguments['operation'],
        'expectedPlanSha256' => $expectedPlanSha256,
        'scriptSha256' => hash('sha256', $script),
        'scriptBytes' => strlen($script),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'MQTT supersession script rendering failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $arguments
 *
 * @return array{input: string, operation: string, output: string, preflight: string}
 */
function parseRendererArguments(array $arguments): array
{
    $result = ['input' => '', 'operation' => '', 'output' => '', 'preflight' => ''];
    foreach ($arguments as $argument) {
        foreach (['input', 'operation', 'output', 'preflight'] as $name) {
            $prefix = '--' . $name . '=';
            if (str_starts_with($argument, $prefix)) {
                if ($result[$name] !== '') {
                    throw new InvalidArgumentException('Renderer argument is duplicated.');
                }
                $result[$name] = substr($argument, strlen($prefix));
                continue 2;
            }
        }
        throw new InvalidArgumentException('Unsupported renderer argument.');
    }
    if (
        $result['input'] === ''
        || $result['output'] === ''
        || !in_array($result['operation'], ['preflight', 'apply', 'inspect', 'rollback'], true)
        || ($result['operation'] !== 'preflight' && $result['preflight'] === '')
    ) {
        throw new InvalidArgumentException(
            'Usage: php render-supersession-owner-migration-script.php '
            . '--input=FILE --operation=OP --output=FILE [--preflight=FILE]'
        );
    }
    foreach ([$result['input'], $result['preflight']] as $path) {
        if ($path !== '' && (!is_file($path) || is_link($path))) {
            throw new RuntimeException('Renderer input file is missing or linked.');
        }
    }

    return $result;
}

/** @return array<string, mixed> */
function readRendererJson(string $path): array
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException('Renderer JSON input cannot be read.');
    }
    $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException('Renderer JSON input must be an object.');
    }

    return $decoded;
}

function readRendererSource(string $path): string
{
    $source = file_get_contents($path);
    if (!is_string($source) || !str_starts_with($source, "<?php\n")) {
        throw new RuntimeException('Renderer implementation source is invalid.');
    }

    return $source;
}

function replaceRendererNamespace(string $source, string $namespace, bool $retainHeader): string
{
    $pattern = '/^<\?php\n\ndeclare\(strict_types=1\);\n\nnamespace '
        . 'SAEF\\\\CaseStudy\\\\MqttDiscoveryExporter\\\\Deployment;\n/D';
    $replacement = $retainHeader
        ? "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n"
        : '';
    $result = preg_replace($pattern, $replacement, $source, 1, $count);
    if (!is_string($result) || $count !== 1) {
        throw new RuntimeException('Renderer implementation namespace differs.');
    }

    return $result;
}

/**
 * @param array<string, mixed> $input
 * @param array<string, mixed>|null $plan
 * @param array<int, string> $loaders
 */
function renderRunner(
    string $operation,
    array $input,
    ?array $plan,
    string $expectedPlanSha256,
    array $loaders
): string {
    $loaderSource = [];
    foreach ($loaders as $ownerID => $body) {
        $loaderSource[] = "    {$ownerID} => static function (): array {\n"
            . indentRendererSource($body, 8)
            . "\n    },";
    }
    $renderedLoaders = implode("\n", $loaderSource);
    $inputBase64 = base64_encode(json_encode(
        $input,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ));
    $planBase64 = $plan === null ? '' : base64_encode(json_encode(
        $plan,
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ));
    $operationLiteral = var_export($operation, true);
    $expectedPlanLiteral = var_export($expectedPlanSha256, true);

    return <<<PHP

\$input = json_decode(base64_decode('{$inputBase64}', true), true, 64, JSON_THROW_ON_ERROR);
\$planEncoded = '{$planBase64}';
\$plan = \$planEncoded === ''
    ? null
    : json_decode(base64_decode(\$planEncoded, true), true, 64, JSON_THROW_ON_ERROR);
\$configurationLoaders = [
{$renderedLoaders}
];
\$configurationHasher = static fn (array \$configuration): string =>
    \\SAEF_CreateConfigurationHash(\$configuration);
\$diagnosticsInitializer = static fn (int \$ownerID, array \$configuration): array =>
    \\SAEF\\CaseStudy\\MqttDiscoveryExporter\\MqttDiscoveryExporterRuntime::initializeDiagnostics(
        \$ownerID,
        \$configuration
    );
\$operation = {$operationLiteral};
\$expectedPlanSha256 = {$expectedPlanLiteral};

try {
    \$environment = new MqttSupersessionSymconEnvironment((string)\$input['claimRoot']);
    \$runtimeIdentity = MqttSupersessionSymconEnvironment::activeRuntimeIdentity();
    if (\$operation === 'preflight') {
        \$result = MqttSupersessionOwnerMigration::preflight(
            \$input,
            \$environment,
            \$configurationLoaders,
            \$configurationHasher,
            \$runtimeIdentity
        );
    } elseif (!is_array(\$plan)) {
        throw new RuntimeException('Migration plan is missing.');
    } elseif (\$operation === 'apply') {
        \$result = MqttSupersessionOwnerMigration::apply(
            \$input,
            \$plan,
            \$expectedPlanSha256,
            \$environment,
            \$configurationLoaders,
            \$configurationHasher,
            \$diagnosticsInitializer,
            \$runtimeIdentity
        );
    } elseif (\$operation === 'inspect') {
        \$result = MqttSupersessionOwnerMigration::inspect(
            \$input,
            \$plan,
            \$expectedPlanSha256,
            \$environment,
            \$configurationLoaders,
            \$configurationHasher,
            \$runtimeIdentity
        );
    } elseif (\$operation === 'rollback') {
        \$result = MqttSupersessionOwnerMigration::rollback(
            \$input,
            \$plan,
            \$expectedPlanSha256,
            \$environment,
            \$configurationLoaders
        );
    } else {
        throw new RuntimeException('Migration operation is unsupported.');
    }
} catch (\\Throwable \$exception) {
    \$result = [
        'formatVersion' => 1,
        'timestampUtc' => (new \\DateTimeImmutable('now', new \\DateTimeZone('UTC')))
            ->format('Y-m-d\\TH:i:s.u\\Z'),
        'phase' => 'mqtt_supersession_owner_migration',
        'targetId' => MqttSupersessionOwnerMigration::TARGET_ID,
        'operation' => \$operation,
        'outcome' => 'failed',
        'exitCode' => 10,
        'planSha256' => \$expectedPlanSha256,
        'failureType' => \$exception::class,
        'failureDetailSha256' => hash('sha256', \$exception->getMessage()),
        'mutationAttempted' => false,
        'sourceMutationAttempted' => false,
        'eventMutationAttempted' => false,
        'diagnosticsInitializationAttempted' => false,
        'claimAttempted' => false,
        'claimCreated' => false,
        'mqttPublishAttempted' => false,
        'deviceActionAttempted' => false,
    ];
}

echo json_encode(\$result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

PHP;
}

function indentRendererSource(string $source, int $spaces): string
{
    $indent = str_repeat(' ', $spaces);

    return implode("\n", array_map(
        static fn (string $line): string => $indent . $line,
        explode("\n", rtrim($source, "\r\n"))
    ));
}

function writeRendererOutput(string $path, string $contents): void
{
    if (file_exists($path)) {
        throw new RuntimeException('Rendered output already exists.');
    }
    $directory = dirname($path);
    if (!is_dir($directory)) {
        throw new RuntimeException('Rendered output directory is missing.');
    }
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
        @unlink($temporary);
        throw new RuntimeException('Rendered output could not be written.');
    }
    chmod($temporary, 0600);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Rendered output could not be committed atomically.');
    }
}
