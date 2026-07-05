<?php

declare(strict_types=1);

$directories = [
    'helpers',
    'templates',
    'examples',
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
