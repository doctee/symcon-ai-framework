<?php

declare(strict_types=1);

$distribution = dirname(__DIR__) . '/distribution';
$errors = [];

$libraryFile = $distribution . '/library.json';
if (!is_file($libraryFile)) {
    $errors[] = 'library.json is missing.';
} else {
    validateJsonFile($libraryFile, $errors);
}

$allowedSupportDirectories = ['libs'];
$moduleDirectories = [];

foreach (new DirectoryIterator($distribution) as $entry) {
    if ($entry->isDot() || !$entry->isDir()) {
        continue;
    }

    $name = $entry->getFilename();
    if (in_array($name, $allowedSupportDirectories, true)) {
        continue;
    }

    $moduleFile = $entry->getPathname() . '/module.json';
    if (!is_file($moduleFile)) {
        $errors[] = sprintf(
            'First-level directory "%s" has no module.json.',
            $name
        );
        continue;
    }

    $moduleDirectories[] = $name;
    validateJsonFile($moduleFile, $errors);

    foreach (['form.json', 'locale.json'] as $jsonFile) {
        $path = $entry->getPathname() . '/' . $jsonFile;
        if (!is_file($path)) {
            $errors[] = sprintf('%s is missing in %s.', $jsonFile, $name);
            continue;
        }

        validateJsonFile($path, $errors);
    }

    if (!is_file($entry->getPathname() . '/module.php')) {
        $errors[] = sprintf('module.php is missing in %s.', $name);
    }
}

sort($moduleDirectories);
$expectedModules = [
    'NavimowAccount',
    'NavimowConfigurator',
    'NavimowDevice',
    'NavimowMqttReceiver',
];

if ($moduleDirectories !== $expectedModules) {
    $errors[] = sprintf(
        'Unexpected module set: %s.',
        implode(', ', $moduleDirectories)
    );
}

if ($errors !== []) {
    fwrite(STDERR, "Navimow distribution validation failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Navimow distribution structure is valid.\n");

function validateJsonFile(string $path, array &$errors): void
{
    try {
        json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        $errors[] = sprintf(
            'Invalid JSON in %s: %s',
            $path,
            $exception->getMessage()
        );
    }
}
