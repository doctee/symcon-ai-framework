<?php

declare(strict_types=1);

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

const SAEF_SYMCON_SCHEMA_DEFAULT_ROOTS = [
    'case-studies',
    'deployments/symcon/windows/adapters',
    'dist/symcon',
];

$projectRoot = dirname(__DIR__);
$configuredVendorDir = getenv('COMPOSER_VENDOR_DIR') ?: 'vendor';
$vendorDir = str_starts_with($configuredVendorDir, DIRECTORY_SEPARATOR)
    ? $configuredVendorDir
    : $projectRoot . DIRECTORY_SEPARATOR . $configuredVendorDir;
$autoloadPath = $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Symcon schema validation failed: Composer autoload is unavailable.\n");
    exit(1);
}

require $autoloadPath;

/**
 * @return object
 */
function saefDecodeJsonFile(string $path): object
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Cannot read JSON file: ' . $path);
    }

    $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);

    if (!is_object($decoded)) {
        throw new RuntimeException('JSON root must be an object: ' . $path);
    }

    return $decoded;
}

/**
 * @return list<string>
 */
function saefCollectSchemaTargets(string $path, array $supportedNames): array
{
    if (is_link($path)) {
        throw new RuntimeException('Schema target must not be a symbolic link: ' . $path);
    }

    if (is_file($path)) {
        $basename = basename($path);

        if (!isset($supportedNames[$basename])) {
            throw new RuntimeException('Unsupported Symcon metadata filename: ' . $path);
        }

        return [$path];
    }

    if (!is_dir($path)) {
        throw new RuntimeException('Schema target does not exist: ' . $path);
    }

    $targets = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if ($file->isLink()) {
            throw new RuntimeException(
                'Schema target tree contains a symbolic link: ' . $file->getPathname()
            );
        }

        if (isset($supportedNames[$file->getBasename()])) {
            $targets[] = $file->getPathname();
        }
    }

    sort($targets, SORT_STRING);

    return $targets;
}

try {
    $schemaRoot = $projectRoot . '/standards/schemas/symcon';
    $manifest = saefDecodeJsonFile($schemaRoot . '/manifest.json');

    if (
        ($manifest->formatVersion ?? null) !== 1
        || !is_string($manifest->retrievedAtUtc ?? null)
        || !is_array($manifest->schemas ?? null)
        || count($manifest->schemas) !== 4
    ) {
        throw new RuntimeException('Symcon schema manifest contract differs.');
    }

    $expectedSchemaFiles = [
        'formSchema.json' => 'form.json',
        'librarySchema.json' => 'library.json',
        'localeSchema.json' => 'locale.json',
        'moduleSchema.json' => 'module.json',
    ];
    $schemasByMetadataName = [];
    $urlsByMetadataName = [];
    $actualSchemaFiles = [];

    foreach ($manifest->schemas as $entry) {
        if (
            !is_object($entry)
            || !is_string($entry->file ?? null)
            || !is_string($entry->sourceUrl ?? null)
            || !is_string($entry->sha256 ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $entry->sha256)
            || !isset($expectedSchemaFiles[$entry->file])
        ) {
            throw new RuntimeException('Symcon schema manifest entry is invalid.');
        }

        if (isset($actualSchemaFiles[$entry->file])) {
            throw new RuntimeException('Symcon schema manifest contains a duplicate file.');
        }

        $schemaPath = $schemaRoot . '/' . $entry->file;

        if (!is_file($schemaPath) || is_link($schemaPath)) {
            throw new RuntimeException('Symcon schema snapshot is missing or unsafe: ' . $entry->file);
        }

        $actualHash = hash_file('sha256', $schemaPath);

        if (!is_string($actualHash) || !hash_equals($entry->sha256, $actualHash)) {
            throw new RuntimeException('Symcon schema snapshot hash differs: ' . $entry->file);
        }

        $metadataName = $expectedSchemaFiles[$entry->file];
        $schemasByMetadataName[$metadataName] = saefDecodeJsonFile($schemaPath);
        $urlsByMetadataName[$metadataName] = $entry->sourceUrl;
        $actualSchemaFiles[$entry->file] = true;
    }

    if (array_keys($actualSchemaFiles) !== array_keys($expectedSchemaFiles)) {
        throw new RuntimeException('Symcon schema manifest inventory differs.');
    }

    $inputPaths = array_slice($argv, 1);

    if ($inputPaths === []) {
        $inputPaths = array_map(
            static fn (string $path): string => $projectRoot . '/' . $path,
            SAEF_SYMCON_SCHEMA_DEFAULT_ROOTS
        );
    }

    $targets = [];

    foreach ($inputPaths as $inputPath) {
        $resolvedPath = str_starts_with($inputPath, DIRECTORY_SEPARATOR)
            ? $inputPath
            : $projectRoot . '/' . $inputPath;

        foreach (saefCollectSchemaTargets($resolvedPath, $schemasByMetadataName) as $target) {
            $targets[$target] = true;
        }
    }

    $targetPaths = array_keys($targets);
    sort($targetPaths, SORT_STRING);

    if ($targetPaths === []) {
        throw new RuntimeException('No Symcon module metadata files were found.');
    }

    $validator = new Validator();
    $formatter = new ErrorFormatter();
    $errors = [];

    foreach ($targetPaths as $targetPath) {
        $metadataName = basename($targetPath);
        $data = saefDecodeJsonFile($targetPath);
        $declaredSchema = $data->{'$schema'} ?? null;

        if ($declaredSchema !== $urlsByMetadataName[$metadataName]) {
            $errors[] = $targetPath . ': does not declare the expected official $schema URL';
            continue;
        }

        $result = $validator->validate($data, $schemasByMetadataName[$metadataName]);

        if ($result->isValid()) {
            continue;
        }

        $validationError = $result->error();
        $messages = $validationError === null
            ? ['unknown schema validation failure']
            : $formatter->formatFlat($validationError);

        foreach ($messages as $message) {
            $errors[] = $targetPath . ': ' . $message;
        }
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, $error . "\n");
        }

        exit(1);
    }

    fwrite(STDOUT, sprintf(
        "PASS: %d Symcon module metadata files match the pinned official schemas.\n",
        count($targetPaths)
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Symcon schema validation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
