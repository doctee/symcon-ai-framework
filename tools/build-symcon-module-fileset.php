<?php

declare(strict_types=1);

const SAEF_MODULE_FILESET_FORMAT_VERSION = 1;
const SAEF_MODULE_FILESET_BUILDER_VERSION = '1.0.0';
const SAEF_MODULE_FILESET_LICENSE = 'PolyForm-Noncommercial-1.0.0';
const SAEF_MODULE_FILESET_LICENSE_URL =
    'https://polyformproject.org/licenses/noncommercial/1.0.0/';

final class SaefSymconModuleFilesetBuilder
{
    private string $projectRoot;

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
        [$manifest, $manifestPath] = $this->loadManifest($manifestArgument);
        $outputs = [];
        $sourceMap = [];
        foreach ($manifest['files'] as $mapping) {
            $source = $this->readCanonicalSource($mapping['source']);
            $target = $manifest['outputDirectory'] . '/' . $mapping['target'];
            $outputs[$target] = $source;
            $sourceMap[] = [
                'source' => $mapping['source'],
                'target' => $mapping['target'],
                'sha256' => hash('sha256', $source),
                'bytes' => strlen($source),
            ];
        }

        $filesetHash = $this->filesetHash($manifest, $outputs);
        $metadata = [
            'formatVersion' => SAEF_MODULE_FILESET_FORMAT_VERSION,
            'manifest' => $manifestPath,
            'name' => $manifest['name'],
            'phpMinimum' => $manifest['phpMinimum'],
            'files' => $sourceMap,
            'filesetSha256' => $filesetHash,
            'builderVersion' => SAEF_MODULE_FILESET_BUILDER_VERSION,
            'license' => [
                'identifier' => SAEF_MODULE_FILESET_LICENSE,
                'url' => SAEF_MODULE_FILESET_LICENSE_URL,
            ],
        ];
        $outputs[$manifest['outputDirectory'] . '/fileset.sources.json'] =
            $this->prettyJson($metadata);
        $outputs[$manifest['outputDirectory'] . '/fileset.sha256'] =
            $filesetHash . "  fileset\n";
        ksort($outputs, SORT_STRING);

        return $outputs;
    }

    /**
     * @return array{
     *     0: array{
     *         name: string,
     *         phpMinimum: string,
     *         outputDirectory: string,
     *         files: list<array{source: string, target: string}>
     *     },
     *     1: string
     * }
     */
    private function loadManifest(string $argument): array
    {
        $absolute = realpath(str_starts_with($argument, '/')
            ? $argument
            : getcwd() . '/' . $argument);
        if ($absolute === false || !is_file($absolute)) {
            throw new RuntimeException('Module fileset manifest does not exist.');
        }
        $manifestPath = $this->relativeProjectPath($absolute);
        $decoded = json_decode(
            (string) file_get_contents($absolute),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if (
            !is_array($decoded) || array_keys($decoded) !== [
            'formatVersion',
            'name',
            'phpMinimum',
            'outputDirectory',
            'files',
            ]
        ) {
            throw new RuntimeException('Module fileset manifest fields are invalid.');
        }
        if ($decoded['formatVersion'] !== SAEF_MODULE_FILESET_FORMAT_VERSION) {
            throw new RuntimeException('Module fileset format version is unsupported.');
        }
        if (!is_string($decoded['name']) || preg_match('/^[a-z][a-z0-9-]*$/', $decoded['name']) !== 1) {
            throw new RuntimeException('Module fileset name is invalid.');
        }
        if (!is_string($decoded['phpMinimum']) || preg_match('/^\d+\.\d+$/', $decoded['phpMinimum']) !== 1) {
            throw new RuntimeException('Module fileset PHP minimum is invalid.');
        }
        if (!is_string($decoded['outputDirectory'])) {
            throw new RuntimeException('Module fileset output directory is invalid.');
        }
        $outputDirectory = $this->validateOutputDirectory($decoded['outputDirectory']);
        $files = $this->validateMappings($decoded['files']);

        return [[
            'name' => $decoded['name'],
            'phpMinimum' => $decoded['phpMinimum'],
            'outputDirectory' => $outputDirectory,
            'files' => $files,
        ], $manifestPath];
    }

    /** @return list<array{source: string, target: string}> */
    private function validateMappings(mixed $mappings): array
    {
        if (!is_array($mappings) || $mappings === [] || !array_is_list($mappings)) {
            throw new RuntimeException('Module fileset mappings must be a non-empty list.');
        }

        $result = [];
        $previousSource = null;
        $targets = [];
        foreach ($mappings as $mapping) {
            if (!is_array($mapping) || array_keys($mapping) !== ['source', 'target']) {
                throw new RuntimeException('Module fileset mapping fields are invalid.');
            }
            $source = $mapping['source'] ?? null;
            $target = $mapping['target'] ?? null;
            if (!is_string($source) || !is_string($target)) {
                throw new RuntimeException('Module fileset mapping paths must be strings.');
            }
            $source = $this->validateSourcePath($source);
            $target = $this->validateTargetPath($target);
            if ($previousSource !== null && $source <= $previousSource) {
                throw new RuntimeException('Module fileset sources must be sorted and unique.');
            }
            if (isset($targets[$target])) {
                throw new RuntimeException('Module fileset targets must be unique.');
            }
            $previousSource = $source;
            $targets[$target] = true;
            $result[] = ['source' => $source, 'target' => $target];
        }

        return $result;
    }

    private function validateSourcePath(string $path): string
    {
        $path = $this->validateRelativePath($path, 'source');
        if (
            !str_starts_with($path, 'case-studies/open-meteo/distribution/')
            && !str_starts_with($path, 'case-studies/media-carousel/distribution/')
            && !str_starts_with($path, 'case-studies/navimow/distribution/')
            && !str_starts_with($path, 'case-studies/owntracks-position-map/candidate/')
            && !str_starts_with($path, 'case-studies/owntracks-position-map/distribution/')
            && !in_array($path, [
                'helpers/common/Validation.php',
                'helpers/diagnostics/ConfigurationHash.php',
                'helpers/object/EnsureProfile.php',
            ], true)
        ) {
            throw new RuntimeException('Module fileset source is outside approved roots.');
        }

        return $path;
    }

    private function validateTargetPath(string $path): string
    {
        $path = $this->validateRelativePath($path, 'target');
        if (!preg_match('/\.(css|html|js|json|php|txt)$/', $path)) {
            throw new RuntimeException('Module fileset target type is unsupported.');
        }

        return $path;
    }

    private function validateOutputDirectory(string $path): string
    {
        $path = $this->validateRelativePath($path, 'output directory');
        if (!str_starts_with($path, 'dist/symcon/')) {
            throw new RuntimeException('Module fileset output directory is outside dist/symcon.');
        }

        return $path;
    }

    private function validateRelativePath(string $path, string $kind): string
    {
        if (
            $path === ''
            || str_contains($path, '\\')
            || str_contains($path, '://')
            || str_starts_with($path, '/')
        ) {
            throw new RuntimeException('Module fileset ' . $kind . ' path is unsafe.');
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Module fileset ' . $kind . ' path is unsafe.');
            }
        }

        return $path;
    }

    private function readCanonicalSource(string $sourcePath): string
    {
        $absolute = $this->projectRoot . '/' . $sourcePath;
        $resolved = realpath($absolute);
        if (
            $resolved === false
            || !is_file($resolved)
            || is_link($absolute)
            || $this->relativeProjectPath($resolved) !== $sourcePath
        ) {
            throw new RuntimeException('Module fileset source is not canonical.');
        }
        $source = file_get_contents($resolved);
        if ($source === false || str_contains($source, "\r")) {
            throw new RuntimeException('Module fileset source must be readable LF-only content.');
        }

        return $source;
    }

    /**
     * @param array<string, mixed>  $manifest
     * @param array<string, string> $outputs
     */
    private function filesetHash(array $manifest, array $outputs): string
    {
        $context = hash_init('sha256');
        hash_update($context, "SAEF-SYMCON-MODULE-FILESET\0" . SAEF_MODULE_FILESET_BUILDER_VERSION . "\0");
        hash_update(
            $context,
            json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );
        ksort($outputs, SORT_STRING);
        foreach ($outputs as $path => $contents) {
            hash_update($context, strlen($path) . "\0" . $path . $contents);
        }

        return hash_final($context);
    }

    private function relativeProjectPath(string $absolute): string
    {
        $normalized = str_replace('\\', '/', $absolute);
        $prefix = $this->projectRoot . '/';
        if (!str_starts_with($normalized, $prefix)) {
            throw new RuntimeException('Module fileset path is outside project root.');
        }

        return substr($normalized, strlen($prefix));
    }

    /** @param array<string, mixed> $value */
    private function prettyJson(array $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";
    }
}

/**
 * @param list<string> $arguments
 *
 * @return array{manifest: string, check: bool, outputRoot: string}
 */
function parseModuleFilesetArguments(array $arguments, string $projectRoot): array
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
            throw new InvalidArgumentException('Unknown module fileset option.');
        } elseif ($manifest === '') {
            $manifest = $argument;
        } else {
            throw new InvalidArgumentException('Only one module fileset manifest is supported.');
        }
    }
    if ($manifest === '' || $outputRoot === '' || !str_starts_with($outputRoot, '/')) {
        throw new InvalidArgumentException('Module fileset arguments are incomplete.');
    }
    return ['manifest' => $manifest, 'check' => $check, 'outputRoot' => rtrim($outputRoot, '/')];
}

/** @param array<string, string> $outputs */
function writeModuleFilesetOutputs(array $outputs, string $outputRoot): void
{
    foreach ($outputs as $relative => $contents) {
        $absolute = $outputRoot . '/' . $relative;
        $directory = dirname($absolute);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create module fileset output directory.');
        }
        $temporary = $directory . '/.' . basename($absolute) . '.tmp.' . getmypid();
        if (file_put_contents($temporary, $contents) !== strlen($contents) || !rename($temporary, $absolute)) {
            throw new RuntimeException('Cannot write module fileset output.');
        }
    }
}

/** @param array<string, string> $outputs */
function checkModuleFilesetOutputs(array $outputs, string $projectRoot): void
{
    $sidecarPaths = array_values(array_filter(
        array_keys($outputs),
        static fn (string $path): bool => str_ends_with($path, '/fileset.sha256')
    ));
    if (count($sidecarPaths) !== 1) {
        throw new RuntimeException('Generated module fileset output root is ambiguous.');
    }
    $relativeRoot = dirname($sidecarPaths[0]);
    $absoluteRoot = $projectRoot . '/' . $relativeRoot;
    if (!is_dir($absoluteRoot) || is_link($absoluteRoot)) {
        throw new RuntimeException('Generated module fileset output root is missing or unsafe.');
    }

    $expected = [];
    foreach ($outputs as $relative => $contents) {
        $prefix = $relativeRoot . '/';
        if (!str_starts_with($relative, $prefix)) {
            throw new RuntimeException('Generated module fileset output escaped its root.');
        }
        $expected[substr($relative, strlen($prefix))] = $contents;
    }
    ksort($expected, SORT_STRING);

    $actual = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        if ($item->isLink()) {
            throw new RuntimeException('Generated module fileset contains a symbolic link.');
        }
        if (!$item->isFile()) {
            continue;
        }
        $relative = str_replace(
            '\\',
            '/',
            substr($item->getPathname(), strlen($absoluteRoot) + 1)
        );
        $contents = file_get_contents($item->getPathname());
        if ($contents === false) {
            throw new RuntimeException('Generated module fileset target is unreadable.');
        }
        $actual[$relative] = $contents;
    }
    ksort($actual, SORT_STRING);

    if ($actual !== $expected) {
        throw new RuntimeException('Generated module fileset has missing, stale or additional targets.');
    }
}

try {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $options = parseModuleFilesetArguments($argv, $projectRoot);
    $outputs = (new SaefSymconModuleFilesetBuilder($projectRoot))->build($options['manifest']);
    if ($options['check']) {
        checkModuleFilesetOutputs($outputs, $options['outputRoot']);
        fwrite(STDOUT, "Symcon module fileset artifacts are current.\n");
    } else {
        writeModuleFilesetOutputs($outputs, $options['outputRoot']);
        fwrite(STDOUT, 'Built ' . count($outputs) . " Symcon module fileset artifacts.\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Module fileset build failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
