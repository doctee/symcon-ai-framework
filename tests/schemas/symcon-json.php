<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$validatorPath = $projectRoot . '/tools/validate-symcon-json.php';
$positivePath = $projectRoot . '/case-studies/media-carousel/distribution/library.json';
$scratchRoot = sys_get_temp_dir() . '/saef-symcon-schema-' . bin2hex(random_bytes(8));

if (!mkdir($scratchRoot, 0700) && !is_dir($scratchRoot)) {
    throw new RuntimeException('Cannot create Symcon schema test directory.');
}

/**
 * @return array{exitCode: int, output: string}
 */
function saefRunSchemaValidator(string $validatorPath, string $targetPath): array
{
    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($validatorPath)
        . ' '
        . escapeshellarg($targetPath)
        . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [
        'exitCode' => $exitCode,
        'output' => implode("\n", $output),
    ];
}

try {
    $positive = saefRunSchemaValidator($validatorPath, $positivePath);

    if ($positive['exitCode'] !== 0 || !str_contains($positive['output'], 'PASS: 1')) {
        throw new RuntimeException('Valid Symcon metadata did not pass schema validation.');
    }

    $source = file_get_contents($positivePath);

    if ($source === false) {
        throw new RuntimeException('Cannot read positive Symcon metadata fixture.');
    }

    $decoded = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
    unset($decoded['id']);
    $missingRequiredRoot = $scratchRoot . '/missing-required';
    mkdir($missingRequiredRoot, 0700);
    file_put_contents(
        $missingRequiredRoot . '/library.json',
        json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );

    $missingRequired = saefRunSchemaValidator($validatorPath, $missingRequiredRoot);

    if (
        $missingRequired['exitCode'] === 0
        || !str_contains($missingRequired['output'], 'required')
    ) {
        throw new RuntimeException('Missing required metadata did not fail schema validation.');
    }

    $decoded = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
    $decoded['$schema'] = 'https://example.invalid/librarySchema.json';
    $wrongSchemaRoot = $scratchRoot . '/wrong-schema';
    mkdir($wrongSchemaRoot, 0700);
    file_put_contents(
        $wrongSchemaRoot . '/library.json',
        json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );

    $wrongSchema = saefRunSchemaValidator($validatorPath, $wrongSchemaRoot);

    if (
        $wrongSchema['exitCode'] === 0
        || !str_contains($wrongSchema['output'], 'expected official $schema URL')
    ) {
        throw new RuntimeException('Wrong schema declaration did not fail validation.');
    }

    fwrite(STDOUT, "PASS: Symcon JSON schema validation accepts valid metadata and fails closed.\n");
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scratchRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($scratchRoot);
}
