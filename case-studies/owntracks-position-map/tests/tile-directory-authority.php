<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use OwnTracksPositionMap\Prototype\OwnTracksTileDirectoryAuthority;

$temporaryRoot = ownTracksAuthorityTemporaryRoot();

try {
    $configuration = [
        'mode' => 'private-xyz-directory',
        'rootPath' => $temporaryRoot,
        'tileSetRevision' => 'synthetic-v1',
        'minimumZoom' => 0,
        'maximumZoom' => 18,
        'tileSizePixels' => 256,
    ];
    $normalized = OwnTracksTileDirectoryAuthority::normalize($configuration);
    assertSameValue(true, $normalized['enabled'], 'Authority was not enabled.');
    assertSameValue(false, $normalized['networkAccess'], 'Network access was enabled.');
    assertSameValue(true, $normalized['readOnly'], 'Authority is not read-only.');
    assertSameValue(
        ['mode' => 'none', 'enabled' => false, 'kind' => 'none'],
        OwnTracksTileDirectoryAuthority::normalize(['mode' => 'none']),
        'Disabled authority normalization differs.'
    );

    $validPng = ownTracksAuthorityPng(256, 256, "\x33\x66\x99");
    ownTracksAuthorityWrite($temporaryRoot, 3, 4, 2, $validPng);
    $authority = new OwnTracksTileDirectoryAuthority($configuration);
    assertSameValue(
        ['content' => $validPng],
        $authority->read(3, 4, 2),
        'Valid authority tile differs.'
    );
    assertSameValue(null, $authority->read(3, 4, 3), 'Missing tile was accepted.');

    ownTracksAuthorityWrite(
        $temporaryRoot,
        3,
        4,
        3,
        ownTracksAuthorityPng(128, 128, "\x33\x66\x99")
    );
    assertSameValue(
        null,
        $authority->read(3, 4, 3),
        'Wrong PNG dimensions were accepted.'
    );

    $corrupt = $validPng;
    $corrupt[40] = chr(ord($corrupt[40]) ^ 0x01);
    ownTracksAuthorityWrite($temporaryRoot, 3, 4, 4, $corrupt);
    assertSameValue(null, $authority->read(3, 4, 4), 'Corrupt CRC was accepted.');

    ownTracksAuthorityWrite(
        $temporaryRoot,
        3,
        4,
        5,
        $validPng . str_repeat('x', 512 * 1024)
    );
    assertSameValue(null, $authority->read(3, 4, 5), 'Oversized tile was accepted.');

    foreach ([[19, 0, 0], [3, 8, 0], [3, 0, -1]] as $invalidIndex) {
        $rejected = false;
        try {
            $authority->read($invalidIndex[0], $invalidIndex[1], $invalidIndex[2]);
        } catch (InvalidArgumentException) {
            $rejected = true;
        }
        assertTrue($rejected, 'Invalid XYZ index was accepted.');
    }

    $linkedTile = $temporaryRoot . '/3/4/6.png';
    if (@symlink($temporaryRoot . '/3/4/2.png', $linkedTile)) {
        assertSameValue(null, $authority->read(3, 4, 6), 'Linked tile was accepted.');
    }
    $linkedDirectoryTarget = $temporaryRoot . '/linked-directory-target';
    if (
        mkdir($linkedDirectoryTarget, 0700)
        && @symlink($linkedDirectoryTarget, $temporaryRoot . '/3/5')
    ) {
        assertSameValue(
            null,
            $authority->read(3, 5, 1),
            'Linked XYZ directory was accepted.'
        );
    }
    $linkedRoot = $temporaryRoot . '-link';
    if (@symlink($temporaryRoot, $linkedRoot)) {
        $rejected = false;
        try {
            new OwnTracksTileDirectoryAuthority(array_replace(
                $configuration,
                ['rootPath' => $linkedRoot]
            ));
        } catch (InvalidArgumentException) {
            $rejected = true;
        }
        assertTrue($rejected, 'Linked authority root was accepted.');
        unlink($linkedRoot);
    }

    foreach (
        [
            array_replace($configuration, ['rootPath' => 'relative/tiles']),
            array_replace($configuration, ['rootPath' => DIRECTORY_SEPARATOR]),
            array_replace($configuration, ['tileSetRevision' => '../private']),
            array_replace($configuration, ['maximumZoom' => 23]),
            array_replace($configuration, ['tileSizePixels' => 300]),
        ] as $invalidConfiguration
    ) {
        $rejected = false;
        try {
            OwnTracksTileDirectoryAuthority::normalize($invalidConfiguration);
        } catch (InvalidArgumentException) {
            $rejected = true;
        }
        assertTrue($rejected, 'Unsafe authority configuration was accepted.');
    }

    fwrite(STDOUT, "OwnTracks tile-directory authority tests passed.\n");
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'OwnTracks tile-directory authority tests failed: '
        . $exception->getMessage() . "\n"
    );
    exit(1);
} finally {
    ownTracksAuthorityRemoveTree($temporaryRoot);
}

function ownTracksAuthorityTemporaryRoot(): string
{
    $systemRoot = realpath(sys_get_temp_dir());
    if ($systemRoot === false) {
        throw new RuntimeException('System temporary root is unavailable.');
    }
    $path = rtrim($systemRoot, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'owntracks-authority-' . bin2hex(random_bytes(8));
    if (!mkdir($path, 0700)) {
        throw new RuntimeException('Authority temporary root cannot be created.');
    }

    return $path;
}

function ownTracksAuthorityPng(int $width, int $height, string $rgb): string
{
    $row = "\0" . str_repeat($rgb, $width);
    $raw = str_repeat($row, $height);
    $compressed = gzcompress($raw, 6);
    if ($compressed === false) {
        throw new RuntimeException('Synthetic PNG compression failed.');
    }
    $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1A\n"
        . ownTracksAuthorityChunk('IHDR', $header)
        . ownTracksAuthorityChunk('IDAT', $compressed)
        . ownTracksAuthorityChunk('IEND', '');
}

function ownTracksAuthorityChunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data
        . hash('crc32b', $type . $data, true);
}

function ownTracksAuthorityWrite(
    string $root,
    int $zoom,
    int $x,
    int $y,
    string $content
): void {
    $directory = $root . '/' . $zoom . '/' . $x;
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new RuntimeException('Synthetic tile directory cannot be created.');
    }
    if (file_put_contents($directory . '/' . $y . '.png', $content) === false) {
        throw new RuntimeException('Synthetic tile cannot be written.');
    }
}

function ownTracksAuthorityRemoveTree(string $root): void
{
    $temporaryRoot = realpath(sys_get_temp_dir());
    if (
        $temporaryRoot === false
        || !str_starts_with($root, rtrim($temporaryRoot, '/') . '/owntracks-authority-')
        || !is_dir($root)
        || is_link($root)
    ) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo) {
            continue;
        }
        if ($entry->isLink() || $entry->isFile()) {
            unlink($entry->getPathname());
        } elseif ($entry->isDir()) {
            rmdir($entry->getPathname());
        }
    }
    rmdir($root);
}
