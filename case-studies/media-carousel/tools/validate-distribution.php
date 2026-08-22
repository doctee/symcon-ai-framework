<?php

declare(strict_types=1);

$distribution = dirname(__DIR__) . '/distribution';
$errors = [];
$expectedRootEntries = ['MediaCarousel', 'library.json'];
$expectedModuleEntries = [
    'carousel.js',
    'form.json',
    'locale.json',
    'module.html',
    'module.json',
    'module.php',
];
$publicRepositoryUrl = 'https://github.com/doctee/saef-media-carousel';

validateExactEntries($distribution, $expectedRootEntries, $errors);
validateExactEntries($distribution . '/MediaCarousel', $expectedModuleEntries, $errors);

$library = readJsonObject($distribution . '/library.json', $errors);
$module = readJsonObject($distribution . '/MediaCarousel/module.json', $errors);
foreach (['form.json', 'locale.json'] as $jsonFile) {
    readJsonObject($distribution . '/MediaCarousel/' . $jsonFile, $errors);
}

if ($library !== []) {
    validateExactKeys(
        $library,
        ['id', 'author', 'name', 'url', 'version', 'build', 'date', 'compatibility'],
        'library.json',
        $errors
    );
    validateGuid($library['id'] ?? null, 'library GUID', $errors);
    if (($library['version'] ?? null) !== '0.2.1') {
        $errors[] = 'Library version must identify the 0.2.1 preview candidate.';
    }
    if (($library['compatibility']['version'] ?? null) !== '8.1') {
        $errors[] = 'Library compatibility must require IP-Symcon 8.1.';
    }
    if (($library['url'] ?? null) !== $publicRepositoryUrl) {
        $errors[] = 'Library URL must identify the standalone publication repository.';
    }
}

if ($module !== []) {
    validateExactKeys(
        $module,
        [
            'id', 'name', 'type', 'vendor', 'aliases', 'url',
            'parentRequirements', 'childRequirements', 'implemented', 'prefix',
        ],
        'module.json',
        $errors
    );
    validateGuid($module['id'] ?? null, 'module GUID', $errors);
    if (($module['id'] ?? null) === ($library['id'] ?? null)) {
        $errors[] = 'Library and module GUID must differ.';
    }
    if (($module['type'] ?? null) !== 3 || ($module['prefix'] ?? null) !== 'SAEFMC') {
        $errors[] = 'Module type or prefix differs from the frozen contract.';
    }
    if (($module['url'] ?? null) !== $publicRepositoryUrl) {
        $errors[] = 'Module URL must identify the standalone publication repository.';
    }
}

$html = readText($distribution . '/MediaCarousel/module.html', $errors);
$javascript = readText($distribution . '/MediaCarousel/carousel.js', $errors);
if (substr_count($html, '/* SAEF_MEDIA_CAROUSEL_SCRIPT */') !== 1) {
    $errors[] = 'HTML script injection marker must occur exactly once.';
}
foreach (['window.handleMessage', "requestAction('LoadMedia'", 'probe.onload', 'sessionStorage'] as $marker) {
    if (!str_contains($javascript, $marker)) {
        $errors[] = 'Frontend contract marker is missing: ' . $marker . '.';
    }
}
if (str_contains($javascript, '.decode()') || str_contains($javascript, 'Promise.all')) {
    $errors[] = 'Frontend must not use explicit or parallel image decoding.';
}
foreach (['PREVIEW_MAX_WIDTH', 'PREVIEW_JPEG_QUALITY', 'createPreviewMediaMessage'] as $marker) {
    if (!str_contains(readText($distribution . '/MediaCarousel/module.php', $errors), $marker)) {
        $errors[] = 'Preview contract marker is missing: ' . $marker . '.';
    }
}
foreach (['DISPLAY_MAX_WIDTH', 'createRenderedMediaMessage', 'DISPLAY_JPEG_QUALITY'] as $marker) {
    if (!str_contains(readText($distribution . '/MediaCarousel/module.php', $errors), $marker)) {
        $errors[] = 'Bounded display-image contract marker is missing: ' . $marker . '.';
    }
}
foreach (['MAX_PENDING_REQUESTS = 2', 'renderForLifecycleChange', "addEventListener('pageshow'"] as $marker) {
    if (!str_contains($javascript, $marker)) {
        $errors[] = 'Client lifecycle contract marker is missing: ' . $marker . '.';
    }
}
foreach (['resolveCategoryMediaItems', 'CategoryItemLimit', 'readRequestConfigurationRevision'] as $marker) {
    if (!str_contains(readText($distribution . '/MediaCarousel/module.php', $errors), $marker)) {
        $errors[] = 'Dynamic category contract marker is missing: ' . $marker . '.';
    }
}

$distributionIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($distribution, FilesystemIterator::SKIP_DOTS)
);
foreach ($distributionIterator as $entry) {
    if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
        continue;
    }
    $contents = readText($entry->getPathname(), $errors);
    foreach (['/Users/', '\\Users\\', '192.168.'] as $privateMarker) {
        if (str_contains($contents, $privateMarker)) {
            $errors[] = 'Distribution contains a private path or address marker.';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "MediaCarousel distribution validation failed:\n");
    foreach (array_values(array_unique($errors)) as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "MediaCarousel distribution structure is valid.\n");

/** @param list<string> $expected @param list<string> $errors */
function validateExactEntries(string $directory, array $expected, array &$errors): void
{
    if (!is_dir($directory)) {
        $errors[] = 'Distribution directory is missing: ' . basename($directory) . '.';
        return;
    }
    $actual = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    sort($actual, SORT_STRING);
    sort($expected, SORT_STRING);
    if ($actual !== $expected) {
        $errors[] = 'Unexpected distribution entries below ' . basename($directory) . '.';
    }
}

/** @param list<string> $errors @return array<string, mixed> */
function readJsonObject(string $path, array &$errors): array
{
    try {
        $decoded = json_decode(readText($path, $errors), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            $errors[] = basename($path) . ' must contain a JSON object.';
            return [];
        }
        return $decoded;
    } catch (JsonException $exception) {
        $errors[] = 'Invalid JSON in ' . basename($path) . ': ' . $exception->getMessage();
        return [];
    }
}

/** @param list<string> $errors */
function readText(string $path, array &$errors): string
{
    $contents = is_file($path) ? file_get_contents($path) : false;
    if ($contents === false) {
        $errors[] = 'Required distribution file is unreadable: ' . basename($path) . '.';
        return '';
    }
    if (str_contains($contents, "\r")) {
        $errors[] = 'Distribution file is not LF-only: ' . basename($path) . '.';
    }
    return $contents;
}

/** @param array<string, mixed> $value @param list<string> $expected @param list<string> $errors */
function validateExactKeys(array $value, array $expected, string $name, array &$errors): void
{
    if (array_keys($value) !== $expected) {
        $errors[] = $name . ' fields differ from the frozen contract.';
    }
}

/** @param list<string> $errors */
function validateGuid(mixed $value, string $name, array &$errors): void
{
    if (!is_string($value) || preg_match('/^\{[A-F0-9]{8}(?:-[A-F0-9]{4}){3}-[A-F0-9]{12}\}$/D', $value) !== 1) {
        $errors[] = 'Invalid ' . $name . '.';
    }
}
