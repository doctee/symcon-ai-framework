<?php

declare(strict_types=1);

$projectRoot = str_replace('\\', '/', dirname(__DIR__, 3));
$builder = $projectRoot . '/tools/build-symcon-module-fileset.php';
$manifest = $projectRoot . '/deployments/symcon/open-meteo-module.fileset.json';
$relativeRoot = 'dist/symcon/saef-open-meteo-module';
$temporaryRoots = [];

try {
    $temporaryRoots = [
        openMeteoFilesetTemporaryRoot('open-meteo-fileset-a-'),
        openMeteoFilesetTemporaryRoot('open-meteo-fileset-b-'),
    ];
    foreach ($temporaryRoots as $temporaryRoot) {
        runOpenMeteoFilesetCommand([
            PHP_BINARY,
            $builder,
            '--output-root=' . $temporaryRoot,
            $manifest,
        ]);
    }

    $first = openMeteoFilesetHashes($temporaryRoots[0] . '/' . $relativeRoot);
    $second = openMeteoFilesetHashes($temporaryRoots[1] . '/' . $relativeRoot);
    $tracked = openMeteoFilesetHashes($projectRoot . '/' . $relativeRoot);
    openMeteoFilesetSame($first, $second, 'Independent module fileset builds differ.');
    openMeteoFilesetSame($first, $tracked, 'Tracked Open-Meteo module fileset is stale.');

    $sourceMap = json_decode(
        (string) file_get_contents(
            $temporaryRoots[0] . '/' . $relativeRoot . '/fileset.sources.json'
        ),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    openMeteoFilesetSame(29, count($sourceMap['files'] ?? []), 'Fileset source count differs.');
    foreach ($sourceMap['files'] ?? [] as $file) {
        if (!is_array($file)) {
            throw new RuntimeException('Fileset source map entry is invalid.');
        }
        $source = $file['source'] ?? null;
        $target = $file['target'] ?? null;
        $expectedHash = $file['sha256'] ?? null;
        if (!is_string($source) || !is_string($target) || !is_string($expectedHash)) {
            throw new RuntimeException('Fileset source map fields are invalid.');
        }
        openMeteoFilesetSame(
            hash_file('sha256', $projectRoot . '/' . $source),
            $expectedHash,
            'Canonical source hash differs.'
        );
        openMeteoFilesetSame(
            $expectedHash,
            hash_file('sha256', $temporaryRoots[0] . '/' . $relativeRoot . '/' . $target),
            'Generated target is not byte-exact.'
        );
    }

    foreach (
        [
        'library.json',
        'OpenMeteoWeather/module.php',
        'OpenMeteoSolarForecast/module.php',
        'libs/OpenMeteo/Profiles.php',
        'libs/OpenMeteo/WeatherForecastProjector.php',
        'libs/SAEF/helpers/object/EnsureProfile.php',
        'libs/SAEF/helpers/diagnostics/ConfigurationHash.php',
        ] as $required
    ) {
        if (!isset($first[$required])) {
            throw new RuntimeException('Required module fileset target is missing.');
        }
    }

    foreach (array_keys($first) as $relativePath) {
        $contents = (string) file_get_contents(
            $temporaryRoots[0] . '/' . $relativeRoot . '/' . $relativePath
        );
        if (str_contains($contents, '/Users/') || str_contains($contents, 'ObjectID')) {
            throw new RuntimeException('Generated module fileset contains private deployment data.');
        }
    }

    runOpenMeteoFilesetCommand([
        PHP_BINARY,
        $builder,
        '--check',
        $manifest,
    ]);

    $additionalTarget = $temporaryRoots[0] . '/' . $relativeRoot . '/stale.php';
    if (file_put_contents($additionalTarget, "<?php\n") === false) {
        throw new RuntimeException('Cannot create additional fileset regression target.');
    }
    $additionalCheck = executeOpenMeteoFilesetCommand([
        PHP_BINARY,
        $builder,
        '--check',
        '--output-root=' . $temporaryRoots[0],
        $manifest,
    ]);
    openMeteoFilesetSame(1, $additionalCheck['status'], 'Fileset check accepted an additional target.');
    if (!str_contains($additionalCheck['stderr'], 'additional targets')) {
        throw new RuntimeException('Additional fileset target failure is not classified.');
    }
    if (!unlink($additionalTarget)) {
        throw new RuntimeException('Cannot remove additional fileset regression target.');
    }

    fwrite(STDOUT, "module-fileset: ok\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'module-fileset: failed: ' . $exception->getMessage() . "\n");
    exit(1);
} finally {
    foreach ($temporaryRoots as $temporaryRoot) {
        removeOpenMeteoFilesetTree($temporaryRoot);
    }
}

/** @param list<string> $command */
function runOpenMeteoFilesetCommand(array $command): void
{
    $result = executeOpenMeteoFilesetCommand($command);
    if ($result['status'] !== 0) {
        throw new RuntimeException(
            'Module fileset subprocess failed: ' . trim($result['stderr'] . $result['stdout'])
        );
    }
}

/**
 * @param list<string> $command
 *
 * @return array{status: int, stdout: string, stderr: string}
 */
function executeOpenMeteoFilesetCommand(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start Open-Meteo fileset builder.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'status' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

function openMeteoFilesetTemporaryRoot(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Cannot create Open-Meteo fileset temporary root.');
    }

    return $path;
}

/** @return array<string, string> */
function openMeteoFilesetHashes(string $root): array
{
    if (!is_dir($root)) {
        throw new RuntimeException('Open-Meteo fileset root is missing.');
    }
    $hashes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo && $item->isFile()) {
            $relative = str_replace(
                '\\',
                '/',
                substr($item->getPathname(), strlen($root) + 1)
            );
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) {
                throw new RuntimeException('Cannot hash Open-Meteo fileset target.');
            }
            $hashes[$relative] = $hash;
        }
    }
    ksort($hashes, SORT_STRING);

    return $hashes;
}

function openMeteoFilesetSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

function removeOpenMeteoFilesetTree(string $root): void
{
    $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($root, $temporaryRoot . 'open-meteo-fileset-') || !is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if (!$item instanceof SplFileInfo) {
            continue;
        }
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($root);
}
