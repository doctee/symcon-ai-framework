<?php

declare(strict_types=1);

const MQTT_SPIKE_PROBE_DIRECTORY = 'NavimowMqttReceiveProbe';

$caseStudy = dirname(__DIR__);
$distribution = $caseStudy . '/distribution';
$package = __DIR__ . '/symcon-mqtt-spike-library';
$probe = $package . '/' . MQTT_SPIKE_PROBE_DIRECTORY;
$standaloneMainManifest = $package . '/standalone-main-files.sha256';
$probeManifest = $package . '/probe-files.sha256';

$operation = $argv[1] ?? '--check';
if ($operation === '--write-manifests') {
    writeManifest(
        $probeManifest,
        manifestContents($probe)
    );
    fwrite(STDOUT, "Navimow MQTT spike manifests written.\n");
    exit(0);
}

assertManifestCurrent(
    $probeManifest,
    manifestContents($probe),
    'probe'
);

if ($operation === '--check') {
    assertManifestSyntax($standaloneMainManifest);
    fwrite(
        STDOUT,
        "Navimow MQTT spike publication manifests are current.\n"
    );
    exit(0);
} elseif ($operation === '--capture-main' && isset($argv[2])) {
    $target = publicationTarget($argv[2]);
    $targetManifest = manifestContents(
        $target,
        ['.git', MQTT_SPIKE_PROBE_DIRECTORY]
    );
    writeManifest($standaloneMainManifest, $targetManifest);
    assertManifestSyntax($standaloneMainManifest);
    $differenceCount = manifestDifferenceCount(
        manifestContents($distribution),
        $targetManifest
    );
    fwrite(
        STDOUT,
        sprintf(
            "Standalone main manifest captured; SAEF distribution drift files: %d.\n",
            $differenceCount
        )
    );
    exit(0);
} elseif ($operation !== '--stage' || !isset($argv[2])) {
    fwrite(
        STDERR,
        "Usage: php prepare-mqtt-spike-publication.php "
        . "--check|--write-manifests|--capture-main TARGET|--stage TARGET\n"
    );
    exit(1);
}

$target = publicationTarget($argv[2]);
$expectedMainManifest = readManifest($standaloneMainManifest);
assertTargetMatchesManifest($target, $expectedMainManifest);

$targetProbe = $target . '/' . MQTT_SPIKE_PROBE_DIRECTORY;
if (file_exists($targetProbe)) {
    throw new RuntimeException(
        'Probe directory already exists in publication target.'
    );
}

copyDirectory($probe, $targetProbe);
assertTargetMatchesManifest($target, $expectedMainManifest);

$targetProbeManifest = manifestContents($targetProbe);
$expectedProbeManifest = manifestContents($probe);
if (!hash_equals($expectedProbeManifest, $targetProbeManifest)) {
    throw new RuntimeException(
        'Staged probe does not match the reviewed probe package.'
    );
}

fwrite(
    STDOUT,
    "Navimow MQTT probe staged; productive files remain byte-identical.\n"
);

function manifestContents(string $root, array $excludedRoots = []): string
{
    if (!is_dir($root)) {
        throw new RuntimeException('Manifest root is missing.');
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if ($file->getFilename() === '.DS_Store') {
            continue;
        }

        $path = $file->getPathname();
        $relative = substr($path, strlen($root) + 1);
        $firstSegment = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];
        if (in_array($firstSegment, $excludedRoots, true)) {
            continue;
        }
        $files[$relative] = hash_file('sha256', $path);
    }

    ksort($files);
    $lines = [];
    foreach ($files as $relative => $hash) {
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to hash publication file.');
        }
        $lines[] = $hash . '  ' . str_replace('\\', '/', $relative);
    }

    return implode("\n", $lines) . "\n";
}

function writeManifest(string $path, string $contents): void
{
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write publication manifest.');
    }
}

function assertManifestCurrent(
    string $path,
    string $actual,
    string $label
): void {
    $expected = @file_get_contents($path);
    if (!is_string($expected) || !hash_equals($expected, $actual)) {
        throw new RuntimeException(
            sprintf(
                '%s publication manifest is missing or stale.',
                ucfirst($label)
            )
        );
    }
}

function assertManifestSyntax(string $path): void
{
    $contents = readManifest($path);
    foreach (array_filter(explode("\n", trim($contents))) as $line) {
        if (
            preg_match('/^[a-f0-9]{64}  [A-Za-z0-9_.\\/-]+$/D', $line) !== 1
        ) {
            throw new RuntimeException(
                'Standalone main publication manifest is malformed.'
            );
        }
    }
}

function readManifest(string $path): string
{
    $contents = @file_get_contents($path);
    if (!is_string($contents) || trim($contents) === '') {
        throw new RuntimeException(
            'Standalone main publication manifest is missing.'
        );
    }

    return $contents;
}

function publicationTarget(string $argument): string
{
    $target = realpath($argument);
    if ($target === false || !is_dir($target . '/.git')) {
        throw new RuntimeException(
            'Publication target must be a Git working tree root.'
        );
    }

    return $target;
}

function assertTargetMatchesManifest(
    string $target,
    string $expectedManifest
): void {
    $actualManifest = manifestContents(
        $target,
        ['.git', MQTT_SPIKE_PROBE_DIRECTORY]
    );
    if (!hash_equals($expectedManifest, $actualManifest)) {
        throw new RuntimeException(
            'Publication target differs from captured standalone main.'
        );
    }
}

function manifestDifferenceCount(string $left, string $right): int
{
    $leftFiles = manifestMap($left);
    $rightFiles = manifestMap($right);
    $paths = array_unique(
        array_merge(array_keys($leftFiles), array_keys($rightFiles))
    );
    $differences = 0;
    foreach ($paths as $path) {
        if (($leftFiles[$path] ?? null) !== ($rightFiles[$path] ?? null)) {
            $differences++;
        }
    }

    return $differences;
}

function manifestMap(string $manifest): array
{
    $files = [];
    foreach (array_filter(explode("\n", trim($manifest))) as $line) {
        if (
            preg_match('/^([a-f0-9]{64})  (.+)$/D', $line, $matches) !== 1
        ) {
            throw new RuntimeException('Publication manifest is malformed.');
        }
        $files[$matches[2]] = $matches[1];
    }

    return $files;
}

function copyDirectory(string $source, string $target): void
{
    if (!mkdir($target, 0755, false)) {
        throw new RuntimeException('Unable to create target probe directory.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $source,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }

        $relative = substr(
            $entry->getPathname(),
            strlen($source) + 1
        );
        if (
            $entry->getFilename() === '.DS_Store'
            || str_ends_with($relative, '.sha256')
        ) {
            continue;
        }

        $destination = $target . '/' . $relative;
        if ($entry->isDir()) {
            if (!mkdir($destination, 0755, true) && !is_dir($destination)) {
                throw new RuntimeException(
                    'Unable to create staged probe directory.'
                );
            }
            continue;
        }

        if (!copy($entry->getPathname(), $destination)) {
            throw new RuntimeException('Unable to stage probe file.');
        }
    }
}
