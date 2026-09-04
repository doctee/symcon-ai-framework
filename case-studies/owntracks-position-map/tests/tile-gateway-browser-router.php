<?php

declare(strict_types=1);

require_once __DIR__ . '/../candidate/OwnTracksTileDirectoryAuthority.php';

use OwnTracksPositionMap\Prototype\OwnTracksTileDirectoryAuthority;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($path === '/tile-gateway-browser-fixture.php') {
    require __DIR__ . '/tile-gateway-browser-fixture.php';
    return true;
}
if (
    !is_string($path)
    || preg_match(
        '#^/hook/owntracks-position-map/([0-9]{1,2})/'
        . '([0-9]{1,10})/([0-9]{1,10})\.png$#D',
        $path,
        $match
    ) !== 1
) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
$capability = $_SERVER['HTTP_X_SAEF_TILE_CAPABILITY'] ?? '';
$viewportGeneration = $_SERVER['HTTP_X_SAEF_TILE_VIEWPORT'] ?? '';
$rejectedViewportGeneration = getenv(
    'OWNTRACKS_BROWSER_REJECT_VIEWPORT_GENERATION'
);
$delayMilliseconds = getenv('OWNTRACKS_BROWSER_TILE_DELAY_MILLISECONDS');
if (
    !in_array($method, ['GET', 'HEAD'], true)
    || $capability !== 'eyJzeW50aGV0aWMiOnRydWV9.c3ludGhldGlj'
    || !is_string($viewportGeneration)
    || preg_match('/^[1-9][0-9]{0,9}$/D', $viewportGeneration) !== 1
) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}
if (
    is_string($delayMilliseconds)
    && preg_match('/^(?:[1-9][0-9]{0,3}|10000)$/D', $delayMilliseconds) === 1
) {
    usleep((int) $delayMilliseconds * 1000);
}
if (
    is_string($rejectedViewportGeneration)
    && $rejectedViewportGeneration !== ''
    && hash_equals($rejectedViewportGeneration, $viewportGeneration)
) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}
$zoom = (int) $match[1];
$x = (int) $match[2];
$y = (int) $match[3];
$side = 2 ** $zoom;
if ($zoom > 18 || $x >= $side || $y >= $side) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}

$root = getenv('OWNTRACKS_BROWSER_AUTHORITY_ROOT');
if (!is_string($root) || !is_dir($root) || is_link($root)) {
    http_response_code(500);
    exit('Fixture authority unavailable');
}
$tileDirectory = $root . '/' . $zoom . '/' . $x;
$tilePath = $tileDirectory . '/' . $y . '.png';
if (!is_file($tilePath)) {
    if (
        !is_dir($tileDirectory)
        && !@mkdir($tileDirectory, 0700, true)
        && !is_dir($tileDirectory)
    ) {
        http_response_code(500);
        exit('Fixture authority unavailable');
    }
    $image = imagecreatetruecolor(256, 256);
    if ($image === false) {
        http_response_code(500);
        exit('Fixture unavailable');
    }
    $background = imagecolorallocate($image, 226, 231, 219);
    $minor = imagecolorallocate($image, 188, 199, 183);
    $major = imagecolorallocate($image, 126, 153, 139);
    $road = imagecolorallocate($image, 248, 246, 232);
    $roadEdge = imagecolorallocate($image, 190, 167, 130);
    $text = imagecolorallocate($image, 50, 65, 62);
    imagefilledrectangle($image, 0, 0, 255, 255, $background);
    for ($position = 0; $position <= 256; $position += 32) {
        imageline($image, $position, 0, $position, 255, $minor);
        imageline($image, 0, $position, 255, $position, $minor);
    }
    imageline($image, 0, 0, 255, 255, $major);
    imageline($image, 0, 255, 255, 0, $major);
    imagesetthickness($image, 12);
    imageline($image, -20, 190, 276, 82, $roadEdge);
    imagesetthickness($image, 8);
    imageline($image, -20, 190, 276, 82, $road);
    imagesetthickness($image, 1);
    imagestring($image, 5, 12, 12, 'SYNTHETIC', $text);
    imagestring($image, 3, 12, 34, $zoom . '/' . $x . '/' . $y, $text);
    $temporaryPath = $tilePath . '.tmp-' . bin2hex(random_bytes(8));
    if (!imagepng($image, $temporaryPath, 6)) {
        http_response_code(500);
        exit('Fixture unavailable');
    }
    @chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, $tilePath) && !is_file($tilePath)) {
        @unlink($temporaryPath);
        http_response_code(500);
        exit('Fixture authority unavailable');
    }
    @unlink($temporaryPath);
}
$authority = new OwnTracksTileDirectoryAuthority([
    'mode' => 'private-xyz-directory',
    'rootPath' => $root,
    'tileSetRevision' => 'synthetic-browser-v1',
    'minimumZoom' => 0,
    'maximumZoom' => 18,
    'tileSizePixels' => 256,
]);
$tile = $authority->read($zoom, $x, $y);
$png = $tile['content'] ?? null;
if (!is_string($png)) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}
header('Cache-Control: private, max-age=300');
header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Vary: X-SAEF-Tile-Capability, X-SAEF-Tile-Viewport');
header('X-Content-Type-Options: nosniff');
if ($method === 'GET') {
    echo $png;
}
return true;
