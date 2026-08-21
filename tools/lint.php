<?php

declare(strict_types=1);

$directories = [
    'helpers',
    'templates',
    'examples',
    'stubs',
    'tools',
    'tests/bundles',
    'tests/control-light',
    'tests/deployments',
    'tests/helpers',
    'tests/mqtt-discovery-exporter',
    'deployments/symcon/windows',
    'case-studies/mqtt-discovery-exporter/candidate',
    'case-studies/control-light/candidate',
    'case-studies/navimow/distribution',
    'case-studies/navimow/tests',
    'case-studies/navimow/tools',
    'case-studies/media-carousel/distribution',
    'case-studies/media-carousel/tests',
    'case-studies/media-carousel/tools',
    'dist/symcon',
];

$errors = 0;

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $command = sprintf(
            'php -l %s',
            escapeshellarg($file->getPathname())
        );

        passthru($command, $result);

        if ($result !== 0) {
            $errors++;
        }
    }
}

exit($errors);
