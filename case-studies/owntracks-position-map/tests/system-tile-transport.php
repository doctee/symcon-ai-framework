<?php

declare(strict_types=1);

namespace OwnTracksPositionMap\Prototype;

require_once __DIR__ . '/bootstrap.php';

/** No network: exercise the production transport through namespaced cURL doubles. */
final class SystemTileTransportFixture
{
    public static string $mode = 'ok';
    public static int $requests = 0;
    /** @var array<int, mixed> */
    public static array $options = [];
}

function curl_version(): array
{
    return ['features' => SystemTileTransportFixture::$mode === 'no-async' ? 0 : CURL_VERSION_ASYNCHDNS];
}

function curl_init(string $url): object
{
    return (object) ['url' => $url];
}

function curl_setopt_array(object $handle, array $options): bool
{
    SystemTileTransportFixture::$options = $options;
    return true;
}

function curl_exec(object $handle): bool
{
    $options = SystemTileTransportFixture::$options;
    $mode = SystemTileTransportFixture::$mode;
    $peer = match ($mode) {
        'private' => '127.0.0.1', 'private-v6' => '::1',
        'shared' => '100.64.0.1', 'benchmark' => '198.18.0.1',
        'documentation' => '192.0.2.1', 'multicast' => '224.0.0.1', 'multicast-v6' => 'ff02::1',
        default => '1.1.1.1',
    };
    $port = $mode === 'wrong-port' ? 8443 : 443;
    // PHP invokes this callback with handle, primary/local address and both ports.
    if ($options[CURLOPT_PREREQFUNCTION]($handle, $peer, '127.0.0.1', $port, 50000) !== CURL_PREREQFUNC_OK) {
        return false;
    }
    SystemTileTransportFixture::$requests++;
    $headers = ["HTTP/2 200\r\n", "Content-Type: image/png\r\n", "Cache-Control: max-age=3600\r\n"];
    if ($mode === 'large-headers') {
        $headers[] = 'X-Overflow: ' . str_repeat('x', 17000) . "\r\n";
    }
    foreach ($headers as $header) {
        if ($options[CURLOPT_HEADERFUNCTION]($handle, $header) !== strlen($header)) {
            return false;
        }
    }
    $body = $mode === 'large-body' ? str_repeat('x', 512 * 1024 + 1) : "\x89PNG\r\n\x1a\nsynthetic";
    return $options[CURLOPT_WRITEFUNCTION]($handle, $body) === strlen($body);
}

function curl_errno(object $handle): int
{
    return 42;
}

function curl_getinfo(object $handle, int $option): mixed
{
    return match ($option) {
        CURLINFO_RESPONSE_CODE => 200,
        CURLINFO_PRIMARY_IP => SystemTileTransportFixture::$mode === 'peer-changed' ? '8.8.8.8' : '1.1.1.1',
        CURLINFO_EFFECTIVE_URL => $handle->url,
        CURLINFO_TOTAL_TIME => SystemTileTransportFixture::$mode === 'late' ? 2.0 : 0.001,
        default => null,
    };
}

if (
    !defined('CURLOPT_PREREQFUNCTION')
    || !defined('CURL_PREREQFUNC_ABORT')
    || !defined('CURL_PREREQFUNC_OK')
) {
    // Legacy PHP may serve offline tiles, but cannot activate this native path.
    assertTrue(!OwnTracksPinnedHttpsTileTransport::systemTransportSupported(), 'Unsupported runtime accepted.');
    echo "System tile transport correctly unavailable on legacy PHP.\n";
    exit(0);
}
$transport = new OwnTracksPinnedHttpsTileTransport([
    'origin' => 'https://tile.openstreetmap.org',
    'pathTemplate' => '/{z}/{x}/{y}.png',
    'userAgent' => 'SAEF synthetic security regression',
    'refererOrigin' => 'https://connect.symcon.de/',
    'timeoutMilliseconds' => 1500,
    'maximumResponseBytes' => 512 * 1024,
    'fallbackCacheTtlSeconds' => 604800,
]);
$request = ['timeoutMilliseconds' => 1500, 'maximumResponseBytes' => 512 * 1024,
    'followRedirects' => false, 'requirePublicPeerAddress' => true];
$url = 'https://tile.openstreetmap.org/0/0/0.png';
$response = $transport->fetchWithSystemTransport($url, $request, [], 1000);
assertSameValue(200, $response['status'], 'Native plan rejected synthetic public peer.');
$options = SystemTileTransportFixture::$options;
assertSameValue('', $options[CURLOPT_PROXY], 'Environment proxy must be disabled.');
assertSameValue('*', $options[CURLOPT_NOPROXY], 'Proxy bypass missing.');
assertSameValue(false, $options[CURLOPT_FOLLOWLOCATION], 'Redirect enabled.');
assertSameValue(1500, $options[CURLOPT_TIMEOUT_MS], 'Total deadline missing.');
assertSameValue(true, $options[CURLOPT_SSL_VERIFYPEER], 'TLS peer validation disabled.');
assertSameValue(2, $options[CURLOPT_SSL_VERIFYHOST], 'TLS host validation disabled.');
assertSameValue(true, $options[CURLOPT_FRESH_CONNECT], 'Connection reuse allowed.');
assertSameValue(true, $options[CURLOPT_FORBID_REUSE], 'Connection retained.');
assertTrue(!isset($options[CURLOPT_RESOLVE]), 'Native async DNS unexpectedly bypassed.');

$preHttpRejects = ['no-async', 'private', 'private-v6', 'shared', 'benchmark', 'documentation',
    'multicast', 'multicast-v6', 'wrong-port'];
foreach (array_merge($preHttpRejects, ['peer-changed', 'large-headers', 'large-body', 'late']) as $mode) {
    SystemTileTransportFixture::$mode = $mode;
    $before = SystemTileTransportFixture::$requests;
    $rejected = false;
    try {
        $transport->fetchWithSystemTransport($url, $request, [], 1000);
    } catch (\RuntimeException) {
        $rejected = true;
    }
    assertTrue($rejected, 'Unsafe native response accepted: ' . $mode);
    if (in_array($mode, $preHttpRejects, true)) {
        assertSameValue($before, SystemTileTransportFixture::$requests, 'HTTP admitted before peer check.');
    }
}
echo "System tile transport security tests passed (no network).\n";
