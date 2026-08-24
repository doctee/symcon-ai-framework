<?php

declare(strict_types=1);

const SAEF_SYMCON_FILESET_BUILDER_VERSION = '1.0.0';
const SAEF_SYMCON_FILESET_FRAMEWORK_VERSION = '0.4.0';
const SAEF_SYMCON_FILESET_LICENSE = 'PolyForm-Noncommercial-1.0.0';
const SAEF_SYMCON_FILESET_LICENSE_URL = 'https://polyformproject.org/licenses/noncommercial/1.0.0/';

/**
 * Builds a deterministic filesystem deployment tree without transforming its
 * canonical PHP sources.
 */
final class SaefSymconFilesetBuilder
{
    private string $projectRoot;

    /** @var array<string, string> */
    private array $sources = [];

    /** @var array<string, int> */
    private array $visitState = [];

    /** @var list<string> */
    private array $orderedSources = [];

    public function __construct(string $projectRoot)
    {
        $resolved = realpath($projectRoot);
        if ($resolved === false) {
            throw new RuntimeException('Project root does not exist.');
        }
        $this->projectRoot = str_replace('\\', '/', $resolved);
    }

    /** @return array<string, string> */
    public function build(string $manifestArgument): array
    {
        $this->sources = [];
        $this->visitState = [];
        $this->orderedSources = [];

        [$manifest, $manifestPath] = $this->loadManifest($manifestArgument);
        $this->visit($manifest['entry']);

        $functionExports = [];
        $classExports = [];
        $guardConstants = [];
        foreach ($this->orderedSources as $sourcePath) {
            $source = $this->sources[$sourcePath];
            if (str_starts_with($sourcePath, 'helpers/')) {
                $functionExports = array_merge($functionExports, $this->extractFunctions($source));
            } else {
                $classExports = array_merge($classExports, $this->extractClasses($source));
            }
            $guardConstants = array_merge($guardConstants, $this->extractGuardConstants($source));
        }
        $functionExports = array_values(array_unique($functionExports));
        $classExports = array_values(array_unique($classExports));
        $guardConstants = array_values(array_unique($guardConstants));
        sort($functionExports, SORT_STRING);
        sort($classExports, SORT_STRING);
        sort($guardConstants, SORT_STRING);

        if ($functionExports !== $manifest['functionExports']) {
            throw new RuntimeException('Fileset function export mismatch.');
        }
        if ($classExports !== $manifest['classExports']) {
            throw new RuntimeException('Fileset class export mismatch.');
        }

        $outputDirectory = $manifest['outputDirectory'];
        $bootstrap = $this->renderBootstrap(
            $manifest['entry'],
            $functionExports,
            $classExports,
            $guardConstants
        );
        $filesetHash = $this->createFilesetHash($manifest, $bootstrap);
        $outputs = [];
        foreach ($this->orderedSources as $sourcePath) {
            $outputs[$outputDirectory . '/' . $sourcePath] = $this->sources[$sourcePath];
        }
        $outputs[$outputDirectory . '/bootstrap.php'] = $bootstrap;

        $sourceMap = [
            'formatVersion' => 1,
            'manifest' => $manifestPath,
            'name' => $manifest['name'],
            'entry' => $manifest['entry'],
            'phpMinimum' => $manifest['phpMinimum'],
            'orderedSources' => array_map(
                fn (string $path): array => [
                    'path' => $path,
                    'sha256' => hash('sha256', $this->sources[$path]),
                ],
                $this->orderedSources
            ),
            'bootstrapSha256' => hash('sha256', $bootstrap),
            'filesetSha256' => $filesetHash,
            'functionExports' => $functionExports,
            'classExports' => $classExports,
            'guardConstants' => $guardConstants,
            'frameworkVersion' => SAEF_SYMCON_FILESET_FRAMEWORK_VERSION,
            'builderVersion' => SAEF_SYMCON_FILESET_BUILDER_VERSION,
            'license' => [
                'identifier' => SAEF_SYMCON_FILESET_LICENSE,
                'url' => SAEF_SYMCON_FILESET_LICENSE_URL,
            ],
        ];
        $outputs[$outputDirectory . '/fileset.sources.json'] = $this->prettyJson($sourceMap);
        $outputs[$outputDirectory . '/fileset.sha256'] = $filesetHash . "  fileset\n";
        ksort($outputs, SORT_STRING);

        return $outputs;
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function loadManifest(string $argument): array
    {
        $absolute = realpath(str_starts_with($argument, '/') ? $argument : getcwd() . '/' . $argument);
        if ($absolute === false || !is_file($absolute)) {
            throw new RuntimeException('Fileset manifest does not exist: ' . $argument);
        }
        $manifestPath = $this->relativeProjectPath($absolute);
        $source = file_get_contents($absolute);
        if ($source === false) {
            throw new RuntimeException('Cannot read fileset manifest.');
        }
        $decoded = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Fileset manifest must be an object.');
        }
        $fields = [
            'formatVersion',
            'name',
            'entry',
            'phpMinimum',
            'outputDirectory',
            'functionExports',
            'classExports',
        ];
        if (array_keys($decoded) !== $fields || $decoded['formatVersion'] !== 1) {
            throw new RuntimeException('Fileset manifest fields do not match formatVersion 1.');
        }
        if (!is_string($decoded['name']) || preg_match('/^[a-z][a-z0-9-]*$/', $decoded['name']) !== 1) {
            throw new RuntimeException('Fileset name is invalid.');
        }
        if (!is_string($decoded['entry'])) {
            throw new RuntimeException('Fileset entry must be a string.');
        }
        $entry = $this->validateSourcePath($decoded['entry']);
        $candidateNamespace = match (true) {
            str_starts_with($entry, 'case-studies/mqtt-discovery-exporter/candidate/')
                => 'MqttDiscoveryExporter',
            str_starts_with($entry, 'case-studies/control-light/candidate/')
                => 'ControlLight',
            default => throw new RuntimeException('Fileset entry must be an approved case-study candidate source.'),
        };
        if (!is_string($decoded['phpMinimum']) || preg_match('/^\d+\.\d+$/', $decoded['phpMinimum']) !== 1) {
            throw new RuntimeException('Fileset phpMinimum is invalid.');
        }
        if (!is_string($decoded['outputDirectory'])) {
            throw new RuntimeException('Fileset outputDirectory must be a string.');
        }
        $outputDirectory = $this->validateOutputDirectory($decoded['outputDirectory']);
        $functionExports = $this->validateExports($decoded['functionExports'], '/^SAEF_[A-Za-z0-9_]+$/');
        $classExports = $this->validateExports(
            $decoded['classExports'],
            '/^SAEF\\\\CaseStudy\\\\' . $candidateNamespace . '\\\\[A-Za-z][A-Za-z0-9_]+$/'
        );

        return [[
            'formatVersion' => 1,
            'name' => $decoded['name'],
            'entry' => $entry,
            'phpMinimum' => $decoded['phpMinimum'],
            'outputDirectory' => $outputDirectory,
            'functionExports' => $functionExports,
            'classExports' => $classExports,
        ], $manifestPath];
    }

    /** @return list<string> */
    private function validateExports(mixed $exports, string $pattern): array
    {
        if (!is_array($exports) || $exports === []) {
            throw new RuntimeException('Fileset exports must be a non-empty list.');
        }
        $validated = [];
        foreach ($exports as $export) {
            if (!is_string($export) || preg_match($pattern, $export) !== 1) {
                throw new RuntimeException('Fileset export is invalid.');
            }
            $validated[] = $export;
        }
        $sorted = $validated;
        sort($sorted, SORT_STRING);
        if ($validated !== $sorted || count($validated) !== count(array_unique($validated))) {
            throw new RuntimeException('Fileset exports must be sorted and unique.');
        }

        return $validated;
    }

    private function visit(string $sourcePath): void
    {
        $state = $this->visitState[$sourcePath] ?? 0;
        if ($state === 2) {
            return;
        }
        if ($state === 1) {
            throw new RuntimeException('Fileset dependency cycle at: ' . $sourcePath);
        }
        $this->visitState[$sourcePath] = 1;
        $absolutePath = $this->projectRoot . '/' . $sourcePath;
        $resolvedPath = realpath($absolutePath);
        if (
            $resolvedPath === false
            || !is_file($resolvedPath)
            || $this->relativeProjectPath($resolvedPath) !== $sourcePath
        ) {
            throw new RuntimeException(
                'Fileset source must resolve to its canonical path inside the project root: ' . $sourcePath
            );
        }
        $source = file_get_contents($resolvedPath);
        if ($source === false || str_contains($source, "\r")) {
            throw new RuntimeException('Cannot read LF-only fileset source: ' . $sourcePath);
        }
        $tokens = token_get_all($source, TOKEN_PARSE);
        $this->sources[$sourcePath] = $source;
        $dependencies = $this->discoverDependencies($tokens, $sourcePath);
        sort($dependencies, SORT_STRING);
        foreach ($dependencies as $dependency) {
            $this->visit($dependency);
        }
        $this->visitState[$sourcePath] = 2;
        $this->orderedSources[] = $sourcePath;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return list<string>
     */
    private function discoverDependencies(array $tokens, string $sourcePath): array
    {
        $dependencies = [];
        foreach ($tokens as $index => $token) {
            if (!is_array($token)) {
                continue;
            }
            if (in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE], true)) {
                throw new RuntimeException('Includes are not allowed in fileset sources: ' . $sourcePath);
            }
            if (!in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE], true)) {
                continue;
            }
            $significant = [];
            for ($cursor = $index; $cursor < count($tokens); $cursor++) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $significant[] = $candidate;
                if ($candidate === ';') {
                    break;
                }
            }
            if (
                count($significant) !== 5
                || !is_array($significant[1])
                || $significant[1][0] !== T_DIR
                || $significant[2] !== '.'
                || !is_array($significant[3])
                || $significant[3][0] !== T_CONSTANT_ENCAPSED_STRING
                || $significant[4] !== ';'
            ) {
                throw new RuntimeException('Fileset requires must use __DIR__ and one literal path.');
            }
            $literal = $this->decodeSingleQuotedString($significant[3][1]);
            $dependency = $this->normalizePath(dirname($sourcePath) . '/' . $literal);
            $dependency = $this->validateSourcePath($dependency);
            if (!is_file($this->projectRoot . '/' . $dependency)) {
                throw new RuntimeException('Fileset dependency does not exist: ' . $dependency);
            }
            $dependencies[] = $dependency;
        }

        return array_values(array_unique($dependencies));
    }

    /** @return list<string> */
    private function extractFunctions(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $functions = [];

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    if (preg_match('/^SAEF_[A-Za-z0-9_]+$/', $candidate[1]) === 1) {
                        $functions[] = $candidate[1];
                    }
                    break;
                }
                if ($candidate === '(') {
                    break;
                }
            }
        }

        return $functions;
    }

    /** @return list<string> */
    private function extractClasses(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $namespace = '';
        $classes = [];
        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                    $part = $tokens[$cursor];
                    if ($part === ';' || $part === '{') {
                        break;
                    }
                    if (
                        is_array($part)
                        && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)
                    ) {
                        $namespace .= $part[1];
                    }
                }
            }
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }
            for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    $classes[] = $namespace . '\\' . $candidate[1];
                    break;
                }
                if (
                    !is_array($candidate)
                    || !in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
                ) {
                    break;
                }
            }
        }
        if ($namespace === '') {
            throw new RuntimeException('Candidate fileset source has no namespace.');
        }

        return $classes;
    }

    /** @return list<string> */
    private function extractGuardConstants(string $source): array
    {
        $tokens = token_get_all($source, TOKEN_PARSE);
        $constants = [];

        foreach ($tokens as $index => $token) {
            if (
                !is_array($token)
                || $token[0] !== T_STRING
                || strtolower($token[1]) !== 'defined'
            ) {
                continue;
            }

            $significant = [];
            for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
                $candidate = $tokens[$cursor];
                if (
                    is_array($candidate)
                    && in_array(
                        $candidate[0],
                        [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                        true
                    )
                ) {
                    continue;
                }
                $significant[] = $candidate;
                if (count($significant) === 3) {
                    break;
                }
            }

            if (
                count($significant) !== 3
                || $significant[0] !== '('
                || !is_array($significant[1])
                || $significant[1][0] !== T_CONSTANT_ENCAPSED_STRING
                || $significant[2] !== ')'
            ) {
                continue;
            }

            $constant = $this->decodeSingleQuotedString($significant[1][1]);
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $constant) === 1) {
                $constants[] = $constant;
            }
        }

        return $constants;
    }

    /** @param list<string> $functions @param list<string> $classes @param list<string> $constants */
    private function renderBootstrap(string $entry, array $functions, array $classes, array $constants): string
    {
        $functionLines = array_map(static fn (string $value): string => "    '" . $value . "',", $functions);
        $classLines = array_map(static fn (string $value): string => "    '" . $value . "',", $classes);
        $constantLines = array_map(static fn (string $value): string => "    '" . $value . "',", $constants);

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '/** GENERATED FILE — DO NOT EDIT. */',
            '$saefFilesetConflicts = [];',
            'foreach ([',
            ...$functionLines,
            '] as $saefFilesetFunction) {',
            '    if (function_exists($saefFilesetFunction)) {',
            "        \$saefFilesetConflicts[] = 'function ' . \$saefFilesetFunction;",
            '    }',
            '}',
            'foreach ([',
            ...$classLines,
            '] as $saefFilesetClass) {',
            '    if (class_exists($saefFilesetClass, false)) {',
            "        \$saefFilesetConflicts[] = 'class ' . \$saefFilesetClass;",
            '    }',
            '}',
            'foreach ([',
            ...$constantLines,
            '] as $saefFilesetConstant) {',
            '    if (defined($saefFilesetConstant)) {',
            "        \$saefFilesetConflicts[] = 'constant ' . \$saefFilesetConstant;",
            '    }',
            '}',
            'if ($saefFilesetConflicts !== []) {',
            "    throw new RuntimeException('SAEF fileset namespace conflict: ' . implode(', ', \$saefFilesetConflicts));",
            '}',
            'unset($saefFilesetConflicts, $saefFilesetFunction, $saefFilesetClass, $saefFilesetConstant);',
            '',
            "require_once __DIR__ . '/" . $entry . "';",
            '',
        ]);
    }

    /** @param array<string, mixed> $manifest */
    private function createFilesetHash(array $manifest, string $bootstrap): string
    {
        $context = hash_init('sha256');
        hash_update($context, "SAEF-SYMCON-FILESET\0" . SAEF_SYMCON_FILESET_BUILDER_VERSION . "\0");
        hash_update($context, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        foreach ($this->orderedSources as $path) {
            hash_update($context, strlen($path) . "\0" . $path . $this->sources[$path]);
        }
        hash_update($context, $bootstrap);

        return hash_final($context);
    }

    private function validateSourcePath(string $path): string
    {
        if ($path === '' || str_contains($path, '\\') || str_contains($path, '://') || str_starts_with($path, '/')) {
            throw new RuntimeException('Invalid fileset source path: ' . $path);
        }
        $normalized = $this->normalizePath($path);
        if (
            $normalized !== $path
            || !str_ends_with($path, '.php')
            || (!str_starts_with($path, 'helpers/')
                && !str_starts_with($path, 'case-studies/mqtt-discovery-exporter/candidate/')
                && !str_starts_with($path, 'case-studies/control-light/candidate/'))
        ) {
            throw new RuntimeException('Fileset source path is outside allowed roots: ' . $path);
        }

        return $normalized;
    }

    private function validateOutputDirectory(string $path): string
    {
        if (
            $path === ''
            || str_contains($path, '\\')
            || str_contains($path, '://')
            || str_starts_with($path, '/')
            || $this->normalizePath($path) !== $path
            || !str_starts_with($path, 'dist/symcon/')
        ) {
            throw new RuntimeException('Invalid fileset output directory.');
        }

        return $path;
    }

    private function normalizePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new RuntimeException('Fileset path traversal escapes project root.');
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function decodeSingleQuotedString(string $literal): string
    {
        if (strlen($literal) < 2 || $literal[0] !== "'" || $literal[strlen($literal) - 1] !== "'") {
            throw new RuntimeException('Fileset dependency path must be single quoted.');
        }

        return str_replace(["\\\\", "\\'"], ["\\", "'"], substr($literal, 1, -1));
    }

    private function relativeProjectPath(string $absolute): string
    {
        $normalized = str_replace('\\', '/', $absolute);
        $prefix = $this->projectRoot . '/';
        if (!str_starts_with($normalized, $prefix)) {
            throw new RuntimeException('Fileset path is outside project root.');
        }

        return substr($normalized, strlen($prefix));
    }

    /** @param array<string, mixed> $value */
    private function prettyJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
    }
}

/** @return array{manifest: string, check: bool, outputRoot: string} */
function parseFilesetArguments(array $arguments, string $projectRoot): array
{
    array_shift($arguments);
    $manifest = '';
    $check = false;
    $outputRoot = $projectRoot;
    foreach ($arguments as $argument) {
        if ($argument === '--check') {
            $check = true;
        } elseif (str_starts_with($argument, '--output-root=')) {
            $outputRoot = substr($argument, strlen('--output-root='));
        } elseif (str_starts_with($argument, '--')) {
            throw new InvalidArgumentException('Unknown fileset option: ' . $argument);
        } elseif ($manifest === '') {
            $manifest = $argument;
        } else {
            throw new InvalidArgumentException('Only one fileset manifest is supported.');
        }
    }
    if ($manifest === '' || $outputRoot === '' || !str_starts_with($outputRoot, '/')) {
        throw new InvalidArgumentException(
            'Usage: php tools/build-symcon-fileset.php [--check] [--output-root=PATH] MANIFEST'
        );
    }
    if ($check && $outputRoot !== $projectRoot) {
        throw new InvalidArgumentException('--check cannot use --output-root.');
    }

    return ['manifest' => $manifest, 'check' => $check, 'outputRoot' => rtrim($outputRoot, '/')];
}

/** @param array<string, string> $outputs */
function writeFilesetOutputs(array $outputs, string $outputRoot): void
{
    foreach ($outputs as $relative => $contents) {
        $absolute = $outputRoot . '/' . $relative;
        $directory = dirname($absolute);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create fileset output directory.');
        }
        $temporary = $directory . '/.' . basename($absolute) . '.tmp.' . getmypid();
        if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $absolute)) {
            throw new RuntimeException('Cannot write fileset output: ' . $relative);
        }
    }
}

/** @param array<string, string> $outputs */
function checkFilesetOutputs(array $outputs, string $projectRoot): void
{
    $drift = [];
    foreach ($outputs as $relative => $contents) {
        $current = is_file($projectRoot . '/' . $relative)
            ? file_get_contents($projectRoot . '/' . $relative)
            : false;
        if ($current !== $contents) {
            $drift[] = $relative;
        }
    }
    if ($drift !== []) {
        throw new RuntimeException("Generated fileset artifacts are missing or stale:\n- " . implode("\n- ", $drift));
    }
}

try {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $options = parseFilesetArguments($argv, $projectRoot);
    $outputs = (new SaefSymconFilesetBuilder($projectRoot))->build($options['manifest']);
    if ($options['check']) {
        checkFilesetOutputs($outputs, $projectRoot);
        fwrite(STDOUT, "Symcon fileset artifacts are current.\n");
    } else {
        writeFilesetOutputs($outputs, $options['outputRoot']);
        fwrite(STDOUT, 'Built ' . count($outputs) . " Symcon fileset artifacts.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fileset build failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
