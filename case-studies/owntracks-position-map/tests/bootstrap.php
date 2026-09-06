<?php

declare(strict_types=1);

$ownTracksTestCoreDirectory = defined('OWNTRACKS_TEST_CORE_DIRECTORY')
    ? constant('OWNTRACKS_TEST_CORE_DIRECTORY')
    : __DIR__ . '/../candidate';
if (
    !is_string($ownTracksTestCoreDirectory)
    || !is_dir($ownTracksTestCoreDirectory)
) {
    throw new RuntimeException('OwnTracks test core directory is missing.');
}
require_once $ownTracksTestCoreDirectory . '/OwnTracksWgs84.php';
require_once $ownTracksTestCoreDirectory . '/OwnTracksTileDeadline.php';
require_once $ownTracksTestCoreDirectory . '/OwnTracksDayWindow.php';
require_once $ownTracksTestCoreDirectory . '/OwnTracksTrackCore.php';
require_once $ownTracksTestCoreDirectory . '/OwnTracksEtaProjector.php';
$ownTracksTargetResolverFile = $ownTracksTestCoreDirectory
    . '/OwnTracksMotionAwareTargetResolver.php';
if (is_file($ownTracksTargetResolverFile)) {
    require_once $ownTracksTargetResolverFile;
}
require_once $ownTracksTestCoreDirectory . '/OwnTracksSymconArchiveAdapter.php';
require_once $ownTracksTestCoreDirectory . '/OwnTracksProviderPolicy.php';
$ownTracksTileCapabilityFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileCapability.php';
$ownTracksTileAccessPolicyFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileAccessPolicy.php';
if (is_file($ownTracksTileAccessPolicyFile)) {
    require_once $ownTracksTileAccessPolicyFile;
}
if (is_file($ownTracksTileCapabilityFile)) {
    require_once $ownTracksTileCapabilityFile;
}
$ownTracksTileGatewayFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileGateway.php';
$ownTracksTileRequestBudgetFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileRequestBudget.php';
$ownTracksTileWebhookAdapterFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileWebhookAdapter.php';
$ownTracksTileFileCacheFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileFileCache.php';
$ownTracksTileDirectoryAuthorityFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileDirectoryAuthority.php';
$ownTracksTileSelectionAllowlistFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileSelectionAllowlist.php';
$ownTracksTileMissResolverFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileMissResolver.php';
$ownTracksOsmTileProviderPolicyFile = $ownTracksTestCoreDirectory
    . '/OwnTracksOsmTileProviderPolicy.php';
$ownTracksPinnedHttpsTileTransportFile = $ownTracksTestCoreDirectory
    . '/OwnTracksPinnedHttpsTileTransport.php';
$ownTracksProviderTileCacheFile = $ownTracksTestCoreDirectory
    . '/OwnTracksProviderTileCache.php';
$ownTracksTileMissStateStoreFile = $ownTracksTestCoreDirectory
    . '/OwnTracksTileMissStateStore.php';
$ownTracksProviderTileRuntimeFile = $ownTracksTestCoreDirectory
    . '/OwnTracksProviderTileRuntime.php';
if (is_file($ownTracksTileSelectionAllowlistFile)) {
    require_once $ownTracksTileSelectionAllowlistFile;
}
if (is_file($ownTracksTileMissResolverFile)) {
    require_once $ownTracksTileMissResolverFile;
}
if (is_file($ownTracksOsmTileProviderPolicyFile)) {
    require_once $ownTracksOsmTileProviderPolicyFile;
}
if (is_file($ownTracksPinnedHttpsTileTransportFile)) {
    require_once $ownTracksPinnedHttpsTileTransportFile;
}
if (is_file($ownTracksProviderTileCacheFile)) {
    require_once $ownTracksProviderTileCacheFile;
}
if (is_file($ownTracksTileMissStateStoreFile)) {
    require_once $ownTracksTileMissStateStoreFile;
}
if (is_file($ownTracksTileDirectoryAuthorityFile)) {
    require_once $ownTracksTileDirectoryAuthorityFile;
}
if (is_file($ownTracksTileFileCacheFile)) {
    require_once $ownTracksTileFileCacheFile;
}
if (is_file($ownTracksTileRequestBudgetFile)) {
    require_once $ownTracksTileRequestBudgetFile;
}
if (is_file($ownTracksTileGatewayFile)) {
    require_once $ownTracksTileGatewayFile;
}
if (is_file($ownTracksTileWebhookAdapterFile)) {
    require_once $ownTracksTileWebhookAdapterFile;
}
if (is_file($ownTracksProviderTileRuntimeFile)) {
    require_once $ownTracksProviderTileRuntimeFile;
}
unset($ownTracksTestCoreDirectory);
unset($ownTracksTargetResolverFile);
unset($ownTracksTileAccessPolicyFile);
unset($ownTracksTileCapabilityFile);
unset($ownTracksTileDirectoryAuthorityFile);
unset($ownTracksTileSelectionAllowlistFile);
unset($ownTracksTileMissResolverFile);
unset($ownTracksOsmTileProviderPolicyFile);
unset($ownTracksPinnedHttpsTileTransportFile);
unset($ownTracksProviderTileCacheFile);
unset($ownTracksTileMissStateStoreFile);
unset($ownTracksProviderTileRuntimeFile);
unset($ownTracksTileFileCacheFile);
unset($ownTracksTileGatewayFile);
unset($ownTracksTileRequestBudgetFile);
unset($ownTracksTileWebhookAdapterFile);

/** @param mixed $actual */
function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, mixed> */
function syntheticFixture(): array
{
    $path = __DIR__ . '/../fixtures/track-day-synthetic.json';
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Synthetic fixture cannot be read.');
    }
    $fixture = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($fixture)) {
        throw new RuntimeException('Synthetic fixture has invalid shape.');
    }

    return $fixture;
}
