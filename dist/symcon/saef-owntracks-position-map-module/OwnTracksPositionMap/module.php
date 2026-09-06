<?php

declare(strict_types=1);

use OwnTracksPositionMap\Prototype\OwnTracksDayWindow;
use OwnTracksPositionMap\Prototype\OwnTracksEtaProjector;
use OwnTracksPositionMap\Prototype\OwnTracksMotionAwareTargetResolver;
use OwnTracksPositionMap\Prototype\OwnTracksOsmTileProviderPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksPinnedHttpsTileTransport;
use OwnTracksPositionMap\Prototype\OwnTracksProviderPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksProviderTileCache;
use OwnTracksPositionMap\Prototype\OwnTracksProviderTileRuntime;
use OwnTracksPositionMap\Prototype\OwnTracksSymconArchiveAdapter;
use OwnTracksPositionMap\Prototype\OwnTracksTileAccessPolicy;
use OwnTracksPositionMap\Prototype\OwnTracksTileDeadline;
use OwnTracksPositionMap\Prototype\OwnTracksTileDirectoryAuthority;
use OwnTracksPositionMap\Prototype\OwnTracksTileFileCache;
use OwnTracksPositionMap\Prototype\OwnTracksTileMissStateStore;
use OwnTracksPositionMap\Prototype\OwnTracksTileRequestBudget;
use OwnTracksPositionMap\Prototype\OwnTracksTileSelectionAllowlist;
use OwnTracksPositionMap\Prototype\OwnTracksTileWebhookAdapter;
use OwnTracksPositionMap\Prototype\OwnTracksTrackCore;
use OwnTracksPositionMap\Prototype\OwnTracksWgs84;

$ownTracksCoreDirectory = is_dir(__DIR__ . '/libs/OwnTracks')
    ? __DIR__ . '/libs/OwnTracks'
    : dirname(__DIR__, 2);
require_once $ownTracksCoreDirectory . '/OwnTracksWgs84.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileDeadline.php';
require_once $ownTracksCoreDirectory . '/OwnTracksDayWindow.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTrackCore.php';
require_once $ownTracksCoreDirectory . '/OwnTracksEtaProjector.php';
require_once $ownTracksCoreDirectory . '/OwnTracksMotionAwareTargetResolver.php';
require_once $ownTracksCoreDirectory . '/OwnTracksSymconArchiveAdapter.php';
require_once $ownTracksCoreDirectory . '/OwnTracksProviderPolicy.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileAccessPolicy.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileCapability.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileDirectoryAuthority.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileFileCache.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileGateway.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileRequestBudget.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileWebhookAdapter.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileSelectionAllowlist.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileMissResolver.php';
require_once $ownTracksCoreDirectory . '/OwnTracksOsmTileProviderPolicy.php';
require_once $ownTracksCoreDirectory . '/OwnTracksPinnedHttpsTileTransport.php';
require_once $ownTracksCoreDirectory . '/OwnTracksProviderTileCache.php';
require_once $ownTracksCoreDirectory . '/OwnTracksTileMissStateStore.php';
require_once $ownTracksCoreDirectory . '/OwnTracksProviderTileRuntime.php';
unset($ownTracksCoreDirectory);

/**
 * HTML-SDK runtime candidate with a default-disabled protected tile boundary.
 * The native Strict hook is registered once during Create(). The default
 * configuration keeps its handler inert and enables neither a tile provider
 * nor OSRM.
 */
class OwnTracksPositionMap extends IPSModuleStrict
{
    private const VISUALIZATION_TYPE_HTML = 1;
    private const STATUS_INVALID_CONFIGURATION = 200;
    private const MAX_REQUEST_BYTES = 2048;
    private const MAX_CLIENT_SESSIONS = 16;
    private const CLIENT_SESSION_TTL_SECONDS = 60 * 60;
    private const CLIENT_KEY_PATTERN = '/^[a-z0-9-]{12,80}$/D';
    private const SOURCE_KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/D';
    private const LOCATION_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/D';
    private const EXTERNAL_SOURCE_KEY = 'saef-external-path';
    private const LOCATION_MODULE_ID =
        '{3B6B9CB0-8D95-4358-874A-13FF1A8BECD1}';
    private const MAXIMUM_LOCATION_DESCRIPTOR_BYTES = 2048;
    private const TILE_HOOK_ADDRESS = 'owntracks-position-map';
    private const TILE_AUDIENCE_PREFIX = 'owntracks-position-map-instance-';
    private const TILE_PROVIDER_REVISION = 'osm-standard-raster-policy-v1';
    private const TILE_VIEWPORT_HEADER = 'X-SAEF-Tile-Viewport';
    private const TILE_VIEWPORT_GRACE_SECONDS = 60;
    private const MAXIMUM_RETAINED_TILE_VIEWPORTS = 3;
    private const MAXIMUM_SOURCE_CLOCK_LEAD_SECONDS = 5;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Sources', '[]');
        $this->RegisterPropertyInteger('ExternalAnchorID', 0);
        $this->RegisterPropertyString('ExternalAnchorPositionIdent', 'position');
        $this->RegisterPropertyString('SelectedTimeZone', 'UTC');
        $this->RegisterPropertyInteger('HistoryDays', 730);
        $this->RegisterPropertyInteger('MaxArchiveRecords', 2500);
        $this->RegisterPropertyInteger('MaxRenderedPoints', 500);
        $this->RegisterPropertyInteger('MaximumGapSeconds', 60 * 60);
        $this->RegisterPropertyInteger('MaximumReceptionDelaySeconds', 15 * 60);
        $this->RegisterPropertyInteger('MaximumAccuracyAgeSeconds', 30 * 60);
        $this->RegisterPropertyFloat('MaximumAccuracyMeters', 100.0);
        $this->RegisterPropertyFloat('MaximumStepDistanceMeters', 50000.0);
        $this->RegisterPropertyString('EtaTargetLocations', '[]');
        $this->RegisterPropertyBoolean('AllowGeodesicEta', true);
        $this->RegisterPropertyInteger('MaximumActivityAgeSeconds', 30 * 60);
        $this->RegisterPropertyString(
            'ProviderConfiguration',
            '{"basemap":{"mode":"none"},"routing":{"mode":"none",'
            . '"allowGeodesicFallback":true},"tileAccess":{"mode":"none"},'
            . '"tileAuthority":{"mode":"none"},'
            . '"tileFallback":{"mode":"none"}}'
        );
        $this->RegisterAttributeString('ActiveRequests', '{}');
        $this->RegisterAttributeString('RegisteredReferences', '[]');
        $this->RegisterAttributeString('TileCapabilitySecret', '');

        // Follow the native IPSModuleStrict lifecycle demonstrated by
        // SymconTest: registration belongs to Create(), while the handler
        // enforces whether the endpoint is operational.
        $this->RegisterHook(self::TILE_HOOK_ADDRESS);

        $visualizationType = defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN')
            ? (int) constant('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN')
            : self::VISUALIZATION_TYPE_HTML;
        $this->SetVisualizationType($visualizationType);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        try {
            $this->clearRegisteredReferences();
            $sources = $this->sources();
            $provider = $this->providerConfiguration();
            $tileAccess = $this->tileAccessConfiguration();
            $tileAuthority = $this->tileAuthorityConfiguration();
            $tileFallback = $this->tileFallbackConfiguration();
            $this->validateTileBoundary(
                $provider,
                $tileAccess,
                $tileAuthority,
                $tileFallback
            );
            if (($tileAuthority['enabled'] ?? false) === true) {
                new OwnTracksTileDirectoryAuthority($tileAuthority);
            }
            if (($tileFallback['enabled'] ?? false) === true) {
                new OwnTracksPinnedHttpsTileTransport(
                    $tileFallback['transportConfiguration']
                );
            }
            $targetLocations = $this->targetLocationsConfiguration();
            $this->validateRuntimeBounds();
            $references = array_map(
                static fn (array $source): int => $source['sourceRootId'],
                $sources
            );
            foreach ($targetLocations as $targetLocation) {
                $references[] = $targetLocation['locationInstanceId'];
            }
            $anchorID = $this->ReadPropertyInteger('ExternalAnchorID');
            if ($anchorID > 0) {
                if (!IPS_InstanceExists($anchorID)) {
                    throw new RuntimeException(
                        'Configured external anchor is missing.'
                    );
                }
                $references[] = $anchorID;
                $references[] = $this->externalAnchorVariableID();
            }
            $this->registerReferences($references);
            $this->SetStatus(IS_ACTIVE);
            $this->UpdateVisualizationValue(
                $this->encodeMessage($this->bootstrapMessage())
            );
        } catch (Throwable $exception) {
            $this->SetStatus(self::STATUS_INVALID_CONFIGURATION);
            $this->SendDebug(
                'OwnTracks map configuration rejected',
                $exception->getMessage(),
                0
            );
            $this->UpdateVisualizationValue(
                $this->encodeMessage([
                    'action' => 'configurationError',
                    'message' => 'Map configuration unavailable',
                ])
            );
        }
    }

    public function RequestAction(string $ident, mixed $value): void
    {
        if ($ident === 'RequestTileCapability') {
            $this->requestTileCapability($value);
            return;
        }
        if ($ident === 'RequestTileViewport') {
            $this->requestTileViewport($value);
            return;
        }
        if ($ident !== 'SelectTrack') {
            throw new InvalidArgumentException(
                'Unsupported action: ' . $ident
            );
        }

        $generation = null;
        try {
            if (!is_string($value) || strlen($value) > self::MAX_REQUEST_BYTES) {
                throw new InvalidArgumentException('Track request is invalid.');
            }
            $request = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($request) || array_is_list($request)) {
                throw new InvalidArgumentException('Track request is invalid.');
            }
            $generation = $this->positiveInteger(
                $request['requestGeneration'] ?? null,
                'Request generation'
            );
            $clientKey = $this->clientKey($request['clientSessionKey'] ?? null);
            $sourceKey = $this->sourceKey($request['sourceKey'] ?? null);
            $viewMode = $request['viewMode'] ?? 'path';
            if (!in_array($viewMode, ['current-overview', 'path'], true)) {
                throw new InvalidArgumentException('View mode is invalid.');
            }
            $selectedDate = $request['selectedDate'] ?? null;
            if (!is_string($selectedDate)) {
                throw new InvalidArgumentException('Selected date is invalid.');
            }

            $sources = $this->sources();
            if ($viewMode === 'current-overview') {
                if ($sourceKey !== 'current-overview') {
                    throw new InvalidArgumentException(
                        'Overview source is invalid.'
                    );
                }
                $etaSourceKey = $request['etaSourceKey'] ?? null;
                if ($etaSourceKey !== null) {
                    $etaSourceKey = $this->sourceKey($etaSourceKey);
                    $this->sourceByKey($sources, $etaSourceKey);
                }
                $this->activateRequest($clientKey, $generation);
                $result = $this->currentOverviewResult($sources, $generation);
                $etaEntries = $this->currentOverviewEtaEntries(
                    $sources,
                    $result['render']['points'],
                    $generation,
                    $clientKey,
                    $etaSourceKey
                );
                if ($this->activeGeneration($clientKey) !== $generation) {
                    return;
                }
                $this->recordTileSelection(
                    $clientKey,
                    $generation,
                    $result['fitBounds'] ?? null
                );
                $this->UpdateVisualizationValue(
                    $this->encodeMessage([
                        'action' => 'trackResult',
                        'viewMode' => $viewMode,
                        'requestGeneration' => $generation,
                        'result' => $result,
                        'target' => null,
                        'targetResolution' => null,
                        'etaEntries' => $etaEntries,
                        'eta' => [
                            'status' => 'unavailable',
                            'strategy' => 'none',
                            'reason' => 'current-overview',
                            'routeAware' => false,
                        ],
                    ])
                );
                return;
            }
            $externalSelected = $sourceKey === self::EXTERNAL_SOURCE_KEY;
            $source = $externalSelected
                ? null
                : $this->sourceByKey($sources, $sourceKey);
            if (
                $externalSelected
                && $this->ReadPropertyInteger('ExternalAnchorID') <= 0
            ) {
                throw new InvalidArgumentException(
                    'Selected source is not configured.'
                );
            }
            $window = OwnTracksDayWindow::fromLocalDate(
                $selectedDate,
                $this->ReadPropertyString('SelectedTimeZone')
            );
            $this->assertSelectableWindow($window);
            $this->activateRequest($clientKey, $generation);
            $query = [
                'requestGeneration' => $generation,
                'sourceKey' => $sourceKey,
                'from' => $window['from'],
                'to' => $window['to'],
                'renderMode' => 'line-with-sampled-timestamps',
                'maxArchiveRecords' =>
                    $this->ReadPropertyInteger('MaxArchiveRecords'),
                'maxRenderedPoints' =>
                    $this->ReadPropertyInteger('MaxRenderedPoints'),
                'maximumGapSeconds' =>
                    $this->ReadPropertyInteger('MaximumGapSeconds'),
                'maximumReceptionDelaySeconds' =>
                    $this->ReadPropertyInteger('MaximumReceptionDelaySeconds'),
                'maximumSourceClockLeadSeconds' =>
                    self::MAXIMUM_SOURCE_CLOCK_LEAD_SECONDS,
                'maximumAccuracyAgeSeconds' =>
                    $this->ReadPropertyInteger('MaximumAccuracyAgeSeconds'),
                'maximumAccuracyMeters' =>
                    $this->ReadPropertyFloat('MaximumAccuracyMeters'),
                'maximumStepDistanceMeters' =>
                    $this->ReadPropertyFloat('MaximumStepDistanceMeters'),
                'excludePoorAccuracyFromLine' => true,
                'allowUnknownAccuracyForLine' => true,
                'includeActivityEvidence' => false,
            ];
            if ($externalSelected) {
                $result = $this->externalPathResult($query);
            } else {
                $adapterResult = OwnTracksSymconArchiveAdapter::loadSelected(
                    ['sources' => $this->adapterSources($sources)],
                    $source['selectorValue'],
                    $query,
                    fn (): int => $this->activeGeneration($clientKey)
                );
                if (($adapterResult['outcome'] ?? null) !== 'ready') {
                    return;
                }
                $result = $adapterResult['result'] ?? null;
            }
            if (!is_array($result)) {
                throw new RuntimeException('Track result is invalid.');
            }
            if ($this->activeGeneration($clientKey) !== $generation) {
                return;
            }
            $this->recordTileSelection(
                $clientKey,
                $generation,
                $result['fitBounds'] ?? null
            );
            $this->UpdateVisualizationValue(
                $this->encodeMessage([
                    'action' => 'trackResult',
                    'viewMode' => $viewMode,
                    'requestGeneration' => $generation,
                    'result' => $result,
                    'target' => null,
                    'targetResolution' => null,
                    'etaEntries' => [],
                    'eta' => [
                        'status' => 'unavailable',
                        'strategy' => 'none',
                        'reason' => 'path-mode',
                        'routeAware' => false,
                    ],
                ])
            );
        } catch (Throwable $exception) {
            $this->SendDebug(
                'OwnTracks track request rejected',
                $exception->getMessage(),
                0
            );
            $this->UpdateVisualizationValue(
                $this->encodeMessage([
                    'action' => 'trackError',
                    'requestGeneration' => $generation,
                    'message' => 'Selected track unavailable',
                ])
            );
        }
    }

    protected function ProcessHookData(): void
    {
        $deadline = new OwnTracksTileDeadline();
        try {
            $policy = $this->tileAccessConfiguration();
            if (($policy['enabled'] ?? false) !== true) {
                $this->emitTileResponse($this->disabledTileResponse());
                return;
            }
            $provider = $this->providerConfiguration();
            $tileAuthority = $this->tileAuthorityConfiguration();
            $tileFallback = $this->tileFallbackConfiguration();
            $this->validateTileBoundary(
                $provider,
                $policy,
                $tileAuthority,
                $tileFallback
            );
            $authority = new OwnTracksTileDirectoryAuthority($tileAuthority);
            $capabilityHeader = $_SERVER['HTTP_X_SAEF_TILE_CAPABILITY'] ?? null;
            $viewportHeader = $_SERVER['HTTP_X_SAEF_TILE_VIEWPORT'] ?? null;
            $headerLines = is_string($capabilityHeader)
                ? [[
                    'name' => 'X-SAEF-Tile-Capability',
                    'value' => $capabilityHeader,
                ]]
                : [];
            if (is_string($viewportHeader)) {
                $headerLines[] = [
                    'name' => self::TILE_VIEWPORT_HEADER,
                    'value' => $viewportHeader,
                ];
            }
            $bodyPresent = $this->requestBodyPresent();
            $providerEnabled = ($tileFallback['enabled'] ?? false) === true;
            $tileReader = fn (int $zoom, int $x, int $y): ?array =>
                $this->readStaticTile($authority, $zoom, $x, $y);
            if ($providerEnabled) {
                $tileReader = function (
                    int $zoom,
                    int $x,
                    int $y
                ) use (
                    $authority,
                    $tileFallback,
                    $capabilityHeader,
                    $viewportHeader,
                    $deadline
                ): ?array {
                    if (
                        !is_string($capabilityHeader)
                        || !is_string($viewportHeader)
                        || preg_match(
                            '/^[1-9][0-9]{0,9}$/D',
                            $viewportHeader
                        ) !== 1
                    ) {
                        return null;
                    }
                    $viewportGeneration = (int) $viewportHeader;
                    if ($viewportGeneration > 2_147_483_647) {
                        return null;
                    }
                    $claims = \OwnTracksPositionMap\Prototype\OwnTracksTileCapability::verify(
                        $capabilityHeader,
                        $this->tileCapabilitySecret(),
                        $this->tileAudience(),
                        $this->currentTimestamp()
                    );
                    $selection = $this->tileSelectionForClient(
                        $claims['clientSessionKey'],
                        $viewportGeneration
                    );
                    if (
                        $selection === null
                        || !is_array($selection['viewport'] ?? null)
                        || !is_string($selection['selectionKey'] ?? null)
                        || !is_string(
                            $selection['viewport']['selectionKey'] ?? null
                        )
                    ) {
                        return $this->readStaticTile(
                            $authority,
                            $zoom,
                            $x,
                            $y
                        );
                    }
                    $allowlist = OwnTracksTileSelectionAllowlist::fromFitBounds(
                        $selection['viewport']['bounds'],
                        $selection['viewport']['minimumZoom'],
                        $selection['viewport']['maximumZoom'],
                        $tileFallback['viewportRingTiles'],
                        $tileFallback['maximumTilesPerSelection']
                    );
                    if (
                        !$this->tileWithinSelectionEnvelope(
                            $selection['viewport']['bounds'],
                            $zoom,
                            $x,
                            $y,
                            $tileFallback['viewportRingTiles']
                        )
                    ) {
                        return $this->readStaticTile(
                            $authority,
                            $zoom,
                            $x,
                            $y
                        );
                    }
                    $transport = new OwnTracksPinnedHttpsTileTransport(
                        $tileFallback['transportConfiguration']
                    );
                    $runtime = new OwnTracksProviderTileRuntime(
                        $allowlist,
                        $tileFallback['resolverConfiguration'],
                        OwnTracksProviderTileCache::forSymconInstance(
                            $this->InstanceID,
                            self::TILE_PROVIDER_REVISION,
                            $deadline
                        ),
                        OwnTracksTileMissStateStore::forSymconInstance(
                            $this->InstanceID,
                            $deadline
                        ),
                        $this->providerRequestBudget($deadline),
                        $selection['viewport']['selectionKey'],
                        hash(
                            'sha256',
                            'provider-budget' . "\0" . $this->InstanceID
                        ),
                        $deadline
                    );

                    return $runtime->read(
                        $zoom,
                        $x,
                        $y,
                        fn (int $tileZoom, int $tileX, int $tileY): ?array =>
                            $this->readStaticTileForProviderFallback(
                                $authority,
                                $tileZoom,
                                $tileX,
                                $tileY
                            ),
                        fn (
                            string $url,
                            array $options,
                            array $conditionalHeaders,
                            int $now
                        ): array => $this->fetchProviderTile(
                            $transport,
                            $url,
                            $options,
                            $conditionalHeaders,
                            $now
                        ),
                        $this->currentTimestamp(),
                        $tileFallback['maximumRequestsPerMinute'],
                        $tileFallback['maximumConcurrentRequests']
                    );
                };
            }
            $response = OwnTracksTileWebhookAdapter::handle(
                $_SERVER,
                $headerLines,
                $bodyPresent,
                $policy,
                $this->tileCapabilitySecret(),
                $this->tileAudience(),
                $provider['basemap']['maximumZoom'],
                $tileAuthority['tileSetRevision'],
                $providerEnabled
                    ? null
                    : OwnTracksTileFileCache::forSymconInstance(
                        $this->InstanceID,
                        $deadline
                    ),
                OwnTracksTileRequestBudget::forSymconInstance($this->InstanceID, $deadline),
                $this->currentTimestamp(),
                $tileReader
            );
            if ($providerEnabled && $response['status'] === 200) {
                $response['headers']['Vary'] =
                    'X-SAEF-Tile-Capability, ' . self::TILE_VIEWPORT_HEADER;
            }
            $this->emitTileResponse($response);
        } catch (Throwable $exception) {
            $this->SendDebug(
                'OwnTracks tile request unavailable',
                $exception->getMessage(),
                0
            );
            $this->emitTileResponse([
                'status' => 503,
                'headers' => [
                    'Cache-Control' => 'no-store',
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'Retry-After' => '60',
                    'X-Content-Type-Options' => 'nosniff',
                ],
                'body' => 'Request unavailable',
                'classification' => 'runtime-unavailable',
            ]);
        }
    }

    /** @return array{content: string}|null */
    private function readStaticTile(
        OwnTracksTileDirectoryAuthority $authority,
        int $zoom,
        int $x,
        int $y
    ): ?array {
        try {
            return $authority->read($zoom, $x, $y);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return array{content: string}|null */
    private function readStaticTileForProviderFallback(
        OwnTracksTileDirectoryAuthority $authority,
        int $zoom,
        int $x,
        int $y
    ): ?array {
        $tile = $this->readStaticTile($authority, $zoom, $x, $y);
        if ($tile === null || !self::isUniformRasterPlaceholder($tile['content'])) {
            return $tile;
        }

        return null;
    }

    private static function isUniformRasterPlaceholder(string $content): bool
    {
        if (strlen($content) > 8192 || !str_starts_with($content, "\x89PNG\r\n\x1A\n")) {
            return false;
        }
        $offset = 8;
        $length = strlen($content);
        $width = null;
        $height = null;
        $bytesPerPixel = null;
        $compressed = '';
        while ($offset + 12 <= $length) {
            $decodedLength = unpack('Nlength', substr($content, $offset, 4));
            $chunkLength = $decodedLength['length'] ?? null;
            if (!is_int($chunkLength) || $offset + 12 + $chunkLength > $length) {
                return false;
            }
            $type = substr($content, $offset + 4, 4);
            $data = substr($content, $offset + 8, $chunkLength);
            if ($type === 'IHDR') {
                if ($chunkLength !== 13) {
                    return false;
                }
                $header = unpack(
                    'Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace',
                    $data
                );
                $width = $header['width'] ?? null;
                $height = $header['height'] ?? null;
                $bytesPerPixel = match ($header['colorType'] ?? null) {
                    0, 3 => 1,
                    2 => 3,
                    4 => 2,
                    6 => 4,
                    default => null,
                };
                if (
                    ($header['bitDepth'] ?? null) !== 8
                    || ($header['compression'] ?? null) !== 0
                    || ($header['filter'] ?? null) !== 0
                    || ($header['interlace'] ?? null) !== 0
                    || !is_int($width) || !is_int($height)
                    || !is_int($bytesPerPixel)
                ) {
                    return false;
                }
            } elseif ($type === 'IDAT') {
                $compressed .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
            $offset += 12 + $chunkLength;
        }
        if ($width === null || $height === null || $bytesPerPixel === null || $compressed === '') {
            return false;
        }
        $rowBytes = $width * $bytesPerPixel;
        $expectedBytes = $height * ($rowBytes + 1);
        if ($expectedBytes <= 0 || $expectedBytes > 2 * 1024 * 1024) {
            return false;
        }
        $decoded = @zlib_decode($compressed, $expectedBytes + 1);
        if (!is_string($decoded) || strlen($decoded) !== $expectedBytes) {
            return false;
        }
        $cursor = 0;
        $previous = array_fill(0, $rowBytes, 0);
        $firstPixel = null;
        for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
            $filter = ord($decoded[$cursor++]);
            if ($filter > 4) {
                return false;
            }
            $row = [];
            for ($column = 0; $column < $rowBytes; $column++) {
                $raw = ord($decoded[$cursor++]);
                $left = $column >= $bytesPerPixel
                    ? $row[$column - $bytesPerPixel]
                    : 0;
                $up = $previous[$column];
                $upLeft = $column >= $bytesPerPixel
                    ? $previous[$column - $bytesPerPixel]
                    : 0;
                $predictor = match ($filter) {
                    0 => 0,
                    1 => $left,
                    2 => $up,
                    3 => intdiv($left + $up, 2),
                    4 => self::paethPredictor($left, $up, $upLeft),
                };
                $row[$column] = ($raw + $predictor) & 0xff;
            }
            for ($pixelOffset = 0; $pixelOffset < $rowBytes; $pixelOffset += $bytesPerPixel) {
                $pixel = array_slice($row, $pixelOffset, $bytesPerPixel);
                if ($firstPixel === null) {
                    $firstPixel = $pixel;
                } elseif ($pixel !== $firstPixel) {
                    return false;
                }
            }
            $previous = $row;
        }

        return $firstPixel !== null;
    }

    private static function paethPredictor(int $left, int $up, int $upLeft): int
    {
        $estimate = $left + $up - $upLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upLeftDistance = abs($estimate - $upLeft);
        if ($leftDistance <= $upDistance && $leftDistance <= $upLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upLeftDistance) {
            return $up;
        }

        return $upLeft;
    }

    public function GetVisualizationTile(): string
    {
        $assetDirectory = is_dir(__DIR__ . '/assets')
            ? __DIR__ . '/assets'
            : dirname(__DIR__, 2);
        $markup = file_get_contents(
            $assetDirectory . (
                str_ends_with($assetDirectory, '/assets')
                    ? '/openlayers-renderer.html'
                    : '/renderer/openlayers-renderer.html'
            )
        );
        $style = file_get_contents(
            $assetDirectory . (
                str_ends_with($assetDirectory, '/assets')
                    ? '/renderer.css'
                    : '/renderer/renderer.css'
            )
        );
        $openLayersStyle = file_get_contents(
            $assetDirectory . (
                str_ends_with($assetDirectory, '/assets')
                    ? '/openlayers-renderer.bundle.css'
                    : '/openlayers/openlayers-renderer.bundle.css'
            )
        );
        $script = file_get_contents(
            $assetDirectory . (
                str_ends_with($assetDirectory, '/assets')
                    ? '/openlayers-renderer.bundle.js'
                    : '/openlayers/openlayers-renderer.bundle.js'
            )
        );
        if (
            $markup === false
            || $style === false
            || $openLayersStyle === false
            || $script === false
        ) {
            throw new RuntimeException('Map frontend files are unavailable.');
        }

        try {
            $bootstrap = $this->bootstrapMessage();
        } catch (Throwable $exception) {
            $this->SendDebug(
                'OwnTracks map bootstrap rejected',
                $exception->getMessage(),
                0
            );
            $bootstrap = [
                'action' => 'configurationError',
                'message' => 'Map configuration unavailable',
            ];
        }
        $bootstrapJson = json_encode(
            $bootstrap,
            JSON_THROW_ON_ERROR
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta http-equiv="Content-Security-Policy" content="default-src '
            . '&apos;none&apos;; style-src &apos;unsafe-inline&apos;; script-src '
            . '&apos;unsafe-inline&apos;; ' . $this->tileContentSecurityPolicy() . '">'
            . '<style>' . $style . $openLayersStyle . '</style></head><body>'
            . $markup . '<script>' . $script . '</script><script>'
            . 'window.handleOwnTracksOpenLayersMessage(' . $bootstrapJson . ');'
            . '</script></body></html>';
    }

    /** @return array<string, mixed> */
    private function bootstrapMessage(): array
    {
        $sources = $this->sources();
        $provider = $this->providerConfiguration();
        $tileAccess = $this->tileAccessConfiguration();
        $now = $this->currentTimestamp();
        $timeZone = new DateTimeZone(
            $this->ReadPropertyString('SelectedTimeZone')
        );
        $today = (new DateTimeImmutable('@' . $now))
            ->setTimezone($timeZone)
            ->format('Y-m-d');
        $minimum = (new DateTimeImmutable($today, $timeZone))
            ->modify('-' . $this->ReadPropertyInteger('HistoryDays') . ' days')
            ->format('Y-m-d');

        $browserSources = array_map(
            static fn (array $source): array => [
                'sourceKey' => $source['sourceKey'],
                'label' => $source['label'],
            ],
            $sources
        );
        return [
            'action' => 'bootstrap',
            'sources' => $browserSources,
            'selectedSourceKey' => $sources[0]['sourceKey'],
            'minimumDate' => $minimum,
            'maximumDate' => $today,
            'selectedDate' => $today,
            'basemap' => $provider['basemap'],
            'tileAccess' => $tileAccess,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, mixed>
     */
    private function currentOverviewResult(
        array $sources,
        int $generation
    ): array {
        $points = [];
        $coordinates = [];
        foreach ($sources as $source) {
            try {
                $point = $this->currentOverviewPoint($source);
            } catch (Throwable) {
                continue;
            }
            $points[] = $point;
            $coordinates[] = [
                'latitude' => $point['latitudeDegrees'],
                'longitude' => $point['longitudeDegrees'],
            ];
        }
        $bounds = OwnTracksWgs84::bounds($coordinates);
        $observed = array_column($points, 'observedAt');
        $from = $observed === [] ? null : min($observed);
        $to = $observed === [] ? null : max($observed);

        return [
            'requestGeneration' => $generation,
            'sourceKey' => 'current-overview',
            'coordinateReference' => 'EPSG:4326',
            'query' => [
                'from' => $from,
                'to' => $to,
                'renderMode' => 'current-overview',
                'maxArchiveRecords' => 0,
                'maxRenderedPoints' => count($sources),
            ],
            'historyWindow' => [
                'requestedFrom' => null,
                'requestedTo' => null,
                'returnedFrom' => $from,
                'returnedTo' => $to,
                'archiveLimitReached' => false,
            ],
            'fitBounds' => $bounds,
            'etaEvidence' => [],
            'render' => [
                'mode' => 'current-overview',
                'points' => $points,
                'segments' => [],
                'segmentCount' => 0,
                'removedByQuality' => 0,
                'removedByRenderBudget' => 0,
                'renderBudgetReached' => false,
                'allSegmentBoundariesRetained' => true,
            ],
            'statistics' => [
                'archiveRecordsRead' => 0,
                'validObservations' => count($points),
                'invalidRecords' => count($sources) - count($points),
                'outsideWindowRecords' => 0,
                'accuracyChangesRead' => 0,
                'renderedPoints' => count($points),
                'removedByQuality' => 0,
                'removedByRenderBudget' => 0,
                'segmentCount' => 0,
                'archiveLimitReached' => false,
                'renderBudgetReached' => false,
                'fitObservationCount' => $bounds['observationCount'] ?? 0,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param list<array<string, mixed>> $points
     * @return list<array<string, mixed>>
     */
    private function currentOverviewEtaEntries(
        array $sources,
        array $points,
        int $generation,
        string $clientKey,
        ?string $selectedSourceKey
    ): array {
        if ($selectedSourceKey === null) {
            return [];
        }
        $selectedSource = null;
        foreach ($sources as $source) {
            if (($source['sourceKey'] ?? null) === $selectedSourceKey) {
                $selectedSource = $source;
                break;
            }
        }
        if (!is_array($selectedSource)) {
            return [];
        }
        $unavailable = static fn (string $reason): array => [[
            'status' => 'unavailable',
            'strategy' => 'none',
            'reason' => $reason,
            'routeAware' => false,
            'sourceKey' => $selectedSource['sourceKey'],
            'sourceLabel' => $selectedSource['label'],
        ]];
        $targets = $this->targetLocationsConfiguration();
        if ($targets === []) {
            return $unavailable('targets-unavailable');
        }
        $pointBySource = [];
        foreach ($points as $point) {
            $key = $point['sourceKey'] ?? null;
            if (is_string($key)) {
                $pointBySource[$key] = $point;
            }
        }
        $evaluatedAt = $this->currentTimestamp();
        $entries = [];
        foreach ([$selectedSource] as $source) {
            if ($this->activeGeneration($clientKey) !== $generation) {
                return [];
            }
            $point = $pointBySource[$source['sourceKey']] ?? null;
            if (!is_array($point)) {
                return $unavailable('position-unavailable');
            }
            $observedAt = $point['observedAt'] ?? null;
            if (
                !is_int($observedAt)
                || $observedAt > $evaluatedAt
                || $evaluatedAt - $observedAt > 15 * 60
            ) {
                return $unavailable('position-stale');
            }
            $coordinate = [
                'latitude' => $point['latitudeDegrees'],
                'longitude' => $point['longitudeDegrees'],
            ];
            $withinRadius = false;
            foreach ($targets as $target) {
                if (
                    OwnTracksWgs84::distanceMeters(
                        $coordinate,
                        [
                            'latitude' => $target['latitude'],
                            'longitude' => $target['longitude'],
                        ]
                    ) < 100000.0
                ) {
                    $withinRadius = true;
                    break;
                }
            }
            if (!$withinRadius) {
                return $unavailable('outside-eta-radius');
            }
            try {
                $query = [
                    'requestGeneration' => $generation,
                    'sourceKey' => $source['sourceKey'],
                    'from' => max(1, $evaluatedAt - 30 * 60),
                    'to' => $evaluatedAt,
                    'renderMode' => 'line-with-sampled-timestamps',
                    'maxArchiveRecords' => min(
                        1000,
                        $this->ReadPropertyInteger('MaxArchiveRecords')
                    ),
                    'maxRenderedPoints' => min(
                        250,
                        $this->ReadPropertyInteger('MaxRenderedPoints')
                    ),
                    'maximumGapSeconds' =>
                        $this->ReadPropertyInteger('MaximumGapSeconds'),
                    'maximumReceptionDelaySeconds' =>
                        $this->ReadPropertyInteger(
                            'MaximumReceptionDelaySeconds'
                        ),
                    'maximumSourceClockLeadSeconds' =>
                        self::MAXIMUM_SOURCE_CLOCK_LEAD_SECONDS,
                    'maximumAccuracyAgeSeconds' =>
                        $this->ReadPropertyInteger(
                            'MaximumAccuracyAgeSeconds'
                        ),
                    'maximumAccuracyMeters' =>
                        $this->ReadPropertyFloat('MaximumAccuracyMeters'),
                    'maximumStepDistanceMeters' =>
                        $this->ReadPropertyFloat(
                            'MaximumStepDistanceMeters'
                        ),
                    'excludePoorAccuracyFromLine' => true,
                    'includeActivityEvidence' => true,
                ];
                $adapter = OwnTracksSymconArchiveAdapter::loadSelected(
                    ['sources' => $this->adapterSources($sources)],
                    $source['selectorValue'],
                    $query,
                    fn (): int => $this->activeGeneration($clientKey)
                );
                if (($adapter['outcome'] ?? null) !== 'ready') {
                    return $unavailable('source-evidence-unavailable');
                }
                $result = $adapter['result'] ?? null;
                if (!is_array($result)) {
                    return $unavailable('source-evidence-unavailable');
                }
                $resolution = OwnTracksMotionAwareTargetResolver::resolve(
                    is_array($result['etaEvidence'] ?? null)
                        ? $result['etaEvidence']
                        : [],
                    is_array($result['activityEvidence'] ?? null)
                        ? $result['activityEvidence']
                        : [],
                    $targets,
                    [
                        'evaluatedAt' => $evaluatedAt,
                        'lookbackSeconds' => 30 * 60,
                        'maximumPositionAgeSeconds' => 15 * 60,
                        'maximumActivityAgeSeconds' =>
                            $this->ReadPropertyInteger(
                                'MaximumActivityAgeSeconds'
                            ),
                        'minimumSegmentCount' => 2,
                    ]
                );
                $target = null;
                $closing = [];
                if (($resolution['status'] ?? null) === 'selected') {
                    $resolved = $resolution['target'] ?? null;
                    if (is_array($resolved)) {
                        $target = $resolved + ['routeEstimate' => null];
                        $closing = [
                            'closingSpeedMetersPerSecond' =>
                                $resolution[
                                    'closingSpeedMetersPerSecond'
                                ] ?? null,
                            'closingSpeedObservedAt' =>
                                $resolution['basisObservedAt'] ?? null,
                            'closingSpeedEvidenceCount' =>
                                $resolution['evidenceSegmentCount'] ?? null,
                        ];
                    }
                }
                $eta = OwnTracksEtaProjector::project(
                    is_array($result['etaEvidence'] ?? null)
                        ? $result['etaEvidence']
                        : [],
                    $target,
                    [
                        'evaluatedAt' => $evaluatedAt,
                        'maximumRouteAgeSeconds' => 15 * 60,
                        'maximumCurrentPositionAgeSeconds' => 15 * 60,
                        'lookbackSeconds' => 30 * 60,
                        'arrivalRadiusMeters' => 50.0,
                        'minimumSpeedMetersPerSecond' => 0.5,
                        'maximumSpeedMetersPerSecond' => 70.0,
                        'allowGeodesicFallback' =>
                            $this->ReadPropertyBoolean('AllowGeodesicEta'),
                    ] + $closing
                );
                $entries[] = $eta + [
                    'sourceKey' => $source['sourceKey'],
                    'sourceLabel' => $source['label'],
                ];
            } catch (Throwable) {
                $entries[] = [
                    'status' => 'unavailable',
                    'strategy' => 'none',
                    'reason' => 'source-evidence-unavailable',
                    'routeAware' => false,
                    'sourceKey' => $source['sourceKey'],
                    'sourceLabel' => $source['label'],
                ];
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function currentOverviewPoint(array $source): array
    {
        $positionID = $this->sourceVariableID(
            $source,
            'positionIdent',
            3
        );
        $raw = GetValue($positionID);
        if (!is_string($raw) || strlen($raw) > 65536) {
            throw new RuntimeException('Current position is invalid.');
        }
        $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Current position is invalid.');
        }
        $observedAt = $payload['tst'] ?? null;
        if (!is_int($observedAt) || $observedAt <= 0) {
            throw new RuntimeException('Current position timestamp is invalid.');
        }
        $coordinate = OwnTracksWgs84::coordinate(
            $payload['lat'] ?? null,
            $payload['lon'] ?? null
        );
        $observedDate = (new DateTimeImmutable('@' . $observedAt))
            ->setTimezone(new DateTimeZone(
                $this->ReadPropertyString('SelectedTimeZone')
            ))
            ->format('Y-m-d');
        $variable = IPS_GetVariable($positionID);
        $receivedAt = $variable['VariableUpdated'];
        if ($receivedAt <= 0) {
            throw new RuntimeException('Current position reception is invalid.');
        }
        $accuracy = null;
        $accuracyObservedAt = null;
        try {
            $accuracyID = $this->sourceVariableID(
                $source,
                'accuracyIdent',
                [1, 2]
            );
            $accuracyValue = GetValue($accuracyID);
            $accuracyVariable = IPS_GetVariable($accuracyID);
            $candidateObservedAt = $accuracyVariable['VariableUpdated'];
            if (
                (is_int($accuracyValue) || is_float($accuracyValue))
                && is_finite((float) $accuracyValue)
                && (float) $accuracyValue >= 0.0
                && abs($candidateObservedAt - $observedAt)
                    <= $this->ReadPropertyInteger(
                        'MaximumAccuracyAgeSeconds'
                    )
            ) {
                $accuracy = (float) $accuracyValue;
                $accuracyObservedAt = $candidateObservedAt;
            }
        } catch (Throwable) {
            $accuracy = null;
            $accuracyObservedAt = null;
        }
        $altitude = $payload['alt'] ?? null;
        if (
            $altitude !== null
            && (!is_int($altitude) && !is_float($altitude))
        ) {
            $altitude = null;
        }

        return [
            'observedAt' => $observedAt,
            'observedDate' => $observedDate,
            'receivedAt' => $receivedAt,
            'latitudeDegrees' => $coordinate['latitude'],
            'longitudeDegrees' => $coordinate['longitude'],
            'altitudeMeters' => $altitude === null ? null : (float) $altitude,
            'horizontalAccuracyMeters' => $accuracy,
            'accuracyObservedAt' => $accuracyObservedAt,
            'accuracyAttribution' => $accuracy === null ? 'unknown' : 'current',
            'qualityFlags' => [],
            'lineEligible' => false,
            'segmentIndex' => null,
            'sourceKey' => $source['sourceKey'],
            'sourceLabel' => $source['label'],
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @param int|list<int> $expectedTypes
     */
    private function sourceVariableID(
        array $source,
        string $identKey,
        int|array $expectedTypes
    ): int {
        $rootID = $source['sourceRootId'] ?? null;
        $ident = $source[$identKey] ?? null;
        if (
            !is_int($rootID)
            || $rootID <= 0
            || !IPS_InstanceExists($rootID)
            || !is_string($ident)
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $ident) !== 1
        ) {
            throw new RuntimeException('Source variable mapping is invalid.');
        }
        $variableID = @IPS_GetObjectIDByIdent($ident, $rootID);
        $allowedTypes = is_int($expectedTypes)
            ? [$expectedTypes]
            : $expectedTypes;
        if (
            $variableID === false
            || $variableID <= 0
            || !IPS_VariableExists($variableID)
            || !in_array(
                IPS_GetVariable($variableID)['VariableType'],
                $allowedTypes,
                true
            )
        ) {
            throw new RuntimeException('Source variable is missing.');
        }

        return $variableID;
    }

    /** @return array<string, mixed> */
    private function tileAccessConfiguration(): array
    {
        $decoded = json_decode(
            $this->ReadPropertyString('ProviderConfiguration'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException(
                'Tile access configuration is invalid.'
            );
        }

        $tileAccess = $decoded['tileAccess'] ?? ['mode' => 'none'];
        if (!is_array($tileAccess) || array_is_list($tileAccess)) {
            throw new InvalidArgumentException(
                'Tile access configuration is invalid.'
            );
        }

        return OwnTracksTileAccessPolicy::normalize($tileAccess);
    }

    /** @return array<string, mixed> */
    private function tileAuthorityConfiguration(): array
    {
        $decoded = json_decode(
            $this->ReadPropertyString('ProviderConfiguration'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException(
                'Tile authority configuration is invalid.'
            );
        }
        $authority = $decoded['tileAuthority'] ?? ['mode' => 'none'];
        if (!is_array($authority) || array_is_list($authority)) {
            throw new InvalidArgumentException(
                'Tile authority configuration is invalid.'
            );
        }

        return OwnTracksTileDirectoryAuthority::normalize($authority);
    }

    /** @return array<string, mixed> */
    private function tileFallbackConfiguration(): array
    {
        $decoded = json_decode(
            $this->ReadPropertyString('ProviderConfiguration'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException(
                'Tile fallback configuration is invalid.'
            );
        }
        $fallback = $decoded['tileFallback'] ?? ['mode' => 'none'];
        if (!is_array($fallback) || array_is_list($fallback)) {
            throw new InvalidArgumentException(
                'Tile fallback configuration is invalid.'
            );
        }
        if (($fallback['mode'] ?? null) === 'none') {
            return ['mode' => 'none', 'enabled' => false];
        }
        $policy = OwnTracksOsmTileProviderPolicy::normalize($fallback);
        $minimumZoom = $fallback['minimumZoom'] ?? null;
        $maximumZoom = $fallback['maximumZoom'] ?? null;
        $viewportRingTiles = $fallback['viewportRingTiles'] ?? null;
        $maximumTiles = $fallback['maximumTilesPerSelection'] ?? null;
        $maximumRequests = $fallback['maximumRequestsPerSelection'] ?? null;
        $maximumBytes = $fallback['maximumBytesPerSelection'] ?? null;
        $timeoutMilliseconds = $fallback['timeoutMilliseconds'] ?? null;
        $negativeTtlSeconds = $fallback['negativeTtlSeconds'] ?? null;
        if (
            !is_int($minimumZoom)
            || $minimumZoom < 0
            || !is_int($maximumZoom)
            || $maximumZoom < max(1, $minimumZoom)
            || $maximumZoom > $policy['maximumZoom']
            || !is_int($viewportRingTiles)
            || $viewportRingTiles < 0
            || $viewportRingTiles > 2
            || !is_int($maximumTiles)
            || $maximumTiles < 1
            || $maximumTiles > 512
            || !is_int($maximumRequests)
            || $maximumRequests < 1
            || $maximumRequests > $policy['maximumRequestsPerMinute']
            || !is_int($maximumBytes)
            || $maximumBytes < 512 * 1024
            || $maximumBytes > 16 * 1024 * 1024
            || !is_int($timeoutMilliseconds)
            || $timeoutMilliseconds < 250
            || $timeoutMilliseconds > 5000
            || !is_int($negativeTtlSeconds)
            || $negativeTtlSeconds < 10
            || $negativeTtlSeconds > 600
        ) {
            throw new InvalidArgumentException(
                'Tile fallback bounds are invalid.'
            );
        }

        return [
            'mode' => $policy['mode'],
            'enabled' => true,
            'minimumZoom' => $minimumZoom,
            'maximumZoom' => $maximumZoom,
            'viewportRingTiles' => $viewportRingTiles,
            'maximumTilesPerSelection' => $maximumTiles,
            'maximumRequestsPerMinute' =>
                $policy['maximumRequestsPerMinute'],
            'maximumConcurrentRequests' =>
                $policy['maximumConcurrentRequests'],
            'resolverConfiguration' => [
                'mode' => 'fixed-https-xyz',
                'origin' => $policy['origin'],
                'pathTemplate' => $policy['pathTemplate'],
                'maximumZoom' => $maximumZoom,
                'maximumRequestsPerSelection' => $maximumRequests,
                'maximumBytesPerSelection' => $maximumBytes,
                'timeoutMilliseconds' => $timeoutMilliseconds,
                'negativeTtlSeconds' => $negativeTtlSeconds,
            ],
            'transportConfiguration' => [
                'origin' => $policy['origin'],
                'pathTemplate' => $policy['pathTemplate'],
                'userAgent' => $policy['userAgent'],
                'refererOrigin' => $policy['refererOrigin'],
                'timeoutMilliseconds' => $timeoutMilliseconds,
                'maximumResponseBytes' => 512 * 1024,
                'fallbackCacheTtlSeconds' =>
                    $policy['fallbackCacheTtlSeconds'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $provider
     * @param array<string, mixed> $tileAccess
     * @param array<string, mixed> $tileAuthority
     * @param array<string, mixed> $tileFallback
     */
    private function validateTileBoundary(
        array $provider,
        array $tileAccess,
        array $tileAuthority,
        array $tileFallback
    ): void {
        $basemapEnabled = ($provider['basemap']['enabled'] ?? false) === true;
        $accessEnabled = ($tileAccess['enabled'] ?? false) === true;
        $authorityEnabled = ($tileAuthority['enabled'] ?? false) === true;
        if (
            $basemapEnabled !== $accessEnabled
            || $accessEnabled !== $authorityEnabled
        ) {
            throw new InvalidArgumentException(
                'Basemap, tile access and tile authority must share one state.'
            );
        }
        if (!$authorityEnabled) {
            if (($tileFallback['enabled'] ?? false) === true) {
                throw new InvalidArgumentException(
                    'Tile fallback requires the static tile authority.'
                );
            }
            return;
        }
        $basemapMaximumZoom = $provider['basemap']['maximumZoom'] ?? null;
        $authorityMaximumZoom = $tileAuthority['maximumZoom'] ?? null;
        if (
            ($provider['basemap']['urlTemplate'] ?? null)
                !== '/hook/owntracks-position-map/{z}/{x}/{y}.png'
            || !is_int($basemapMaximumZoom)
            || !is_int($authorityMaximumZoom)
            || $authorityMaximumZoom > $basemapMaximumZoom
        ) {
            throw new InvalidArgumentException(
                'Static tile authority exceeds the browser tile boundary.'
            );
        }
        if (($tileFallback['enabled'] ?? false) !== true) {
            if ($authorityMaximumZoom !== $basemapMaximumZoom) {
                throw new InvalidArgumentException(
                    'Static-only basemap and tile authority bounds differ.'
                );
            }
            return;
        }
        if (($tileFallback['maximumZoom'] ?? null) !== $basemapMaximumZoom) {
            throw new InvalidArgumentException(
                'Dynamic tile fallback and browser tile bounds differ.'
            );
        }
    }

    /**
     * The production boundary is overrideable only by the repository harness;
     * default-disabled configuration prevents this method from being reached.
     *
     * @param array<string, mixed> $options
     * @param array<string, string> $conditionalHeaders
     * @return array<string, mixed>
     */
    protected function fetchProviderTile(
        OwnTracksPinnedHttpsTileTransport $transport,
        string $url,
        array $options,
        array $conditionalHeaders,
        int $now
    ): array {
        return $transport->fetchWithSystemTransport(
            $url,
            $options,
            $conditionalHeaders,
            $now
        );
    }

    private function providerRequestBudget(
        ?OwnTracksTileDeadline $deadline = null
    ): OwnTracksTileRequestBudget {
        if ($this->InstanceID <= 0) {
            throw new RuntimeException('Provider request-budget owner is invalid.');
        }

        return new OwnTracksTileRequestBudget(
            rtrim(sys_get_temp_dir(), '/\\')
                . DIRECTORY_SEPARATOR
                . 'saef-owntracks-position-map-provider-budget'
                . DIRECTORY_SEPARATOR . 'instance-' . $this->InstanceID,
            $deadline
        );
    }

    private function requestTileCapability(mixed $value): void
    {
        $generation = null;
        try {
            if (!is_string($value) || strlen($value) > self::MAX_REQUEST_BYTES) {
                throw new InvalidArgumentException(
                    'Tile capability request is invalid.'
                );
            }
            $request = json_decode($value, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($request) || array_is_list($request)) {
                throw new InvalidArgumentException(
                    'Tile capability request is invalid.'
                );
            }
            $generation = $request['requestGeneration'] ?? null;
            $policy = $this->tileAccessConfiguration();
            if (($policy['enabled'] ?? false) !== true) {
                throw new InvalidArgumentException(
                    'Tile capability issuance is disabled.'
                );
            }
            $message = OwnTracksTileWebhookAdapter::issueCapability(
                $request,
                $policy,
                $this->tileCapabilitySecret(),
                $this->tileAudience(),
                $this->currentTimestamp(),
                OwnTracksTileRequestBudget::forSymconInstance($this->InstanceID)
            );
            $this->UpdateVisualizationValue($this->encodeMessage($message));
        } catch (Throwable $exception) {
            $this->SendDebug(
                'OwnTracks tile capability rejected',
                $exception->getMessage(),
                0
            );
            $this->UpdateVisualizationValue($this->encodeMessage([
                'action' => 'tileCapabilityError',
                'requestGeneration' => is_int($generation) ? $generation : null,
                'message' => 'Tile access unavailable',
            ]));
        }
    }

    private function tileCapabilitySecret(): string
    {
        $secret = $this->ReadAttributeString('TileCapabilitySecret');
        if ($secret !== '') {
            return $secret;
        }
        $secret = rtrim(
            strtr(base64_encode(random_bytes(32)), '+/', '-_'),
            '='
        );
        $this->WriteAttributeString('TileCapabilitySecret', $secret);

        return $secret;
    }

    private function tileAudience(): string
    {
        if ($this->InstanceID <= 0) {
            throw new RuntimeException('Tile audience owner is invalid.');
        }

        return self::TILE_AUDIENCE_PREFIX . $this->InstanceID;
    }

    private function tileContentSecurityPolicy(): string
    {
        $policy = $this->tileAccessConfiguration();
        if (($policy['enabled'] ?? false) === true) {
            return 'connect-src &apos;self&apos;; img-src data: blob:';
        }

        return 'connect-src &apos;none&apos;; img-src data:';
    }

    private function requestBodyPresent(): bool
    {
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
        if (is_string($contentLength) && $contentLength !== '0') {
            return true;
        }
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return false;
        }
        try {
            return fread($stream, 1) !== '';
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   classification: string
     * } $response
     */
    private function emitTileResponse(array $response): void
    {
        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $response['body'];
    }

    /**
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   classification: string
     * }
     */
    private function disabledTileResponse(): array
    {
        return [
            'status' => 404,
            'headers' => [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'text/plain; charset=utf-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => 'Not found',
            'classification' => 'runtime-disabled',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sources(): array
    {
        $decoded = json_decode(
            $this->ReadPropertyString('Sources'),
            true,
            32,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded) || count($decoded) !== 3) {
            throw new InvalidArgumentException(
                'Exactly three OwnTracks sources are required.'
            );
        }
        $normalized = [];
        $keys = [];
        $selectors = [];
        foreach ($decoded as $source) {
            if (!is_array($source)) {
                throw new InvalidArgumentException('Source mapping is invalid.');
            }
            $key = $this->sourceKey($source['sourceKey'] ?? null);
            $label = $source['label'] ?? null;
            $selector = $source['selectorValue'] ?? null;
            $rootID = $source['sourceRootId'] ?? null;
            $positionIdent = $source['positionIdent'] ?? null;
            $accuracyIdent = $source['accuracyIdent'] ?? null;
            $activityIdent = $source['activityIdent'] ?? 'motionactivities';
            if (
                !is_string($label)
                || $label === ''
                || strlen($label) > 80
                || preg_match('/[<>\x00-\x1F]/', $label) === 1
                || !is_int($selector)
                || !is_int($rootID)
                || $rootID <= 0
                || !IPS_InstanceExists($rootID)
                || !is_string($positionIdent)
                || !is_string($accuracyIdent)
                || !is_string($activityIdent)
                || $key === self::EXTERNAL_SOURCE_KEY
                || isset($keys[$key])
                || isset($selectors[$selector])
            ) {
                throw new InvalidArgumentException('Source mapping is invalid.');
            }
            $keys[$key] = true;
            $selectors[$selector] = true;
            $normalized[] = [
                'sourceKey' => $key,
                'label' => $label,
                'selectorValue' => $selector,
                'sourceRootId' => $rootID,
                'positionIdent' => $positionIdent,
                'accuracyIdent' => $accuracyIdent,
                'activityIdent' => $activityIdent,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, mixed>>
     */
    private function adapterSources(array $sources): array
    {
        return array_map(
            static fn (array $source): array => [
                'sourceKey' => $source['sourceKey'],
                'selectorValue' => $source['selectorValue'],
                'sourceRootId' => $source['sourceRootId'],
                'positionIdent' => $source['positionIdent'],
                'accuracyIdent' => $source['accuracyIdent'],
                'activityIdent' => $source['activityIdent'],
            ],
            $sources
        );
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, mixed>
     */
    private function sourceByKey(array $sources, string $sourceKey): array
    {
        foreach ($sources as $source) {
            if ($source['sourceKey'] === $sourceKey) {
                return $source;
            }
        }

        throw new InvalidArgumentException('Selected source is not configured.');
    }

    /** @return array<string, mixed> */
    private function providerConfiguration(): array
    {
        $decoded = json_decode(
            $this->ReadPropertyString('ProviderConfiguration'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Provider configuration is invalid.');
        }
        $provider = OwnTracksProviderPolicy::normalize($decoded);
        if (($provider['routing']['enabled'] ?? true) !== false) {
            throw new InvalidArgumentException(
                'This runtime gate permits no routing authority.'
            );
        }

        return $provider;
    }

    /** @return list<array<string, mixed>> */
    private function targetLocationsConfiguration(): array
    {
        $decoded = json_decode(
            $this->ReadPropertyString('EtaTargetLocations'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new InvalidArgumentException(
                'ETA target locations are invalid.'
            );
        }
        if ($decoded === []) {
            return [];
        }
        if (count($decoded) !== 2) {
            throw new InvalidArgumentException(
                'Exactly two ETA target locations are required.'
            );
        }
        $targets = [];
        $keys = [];
        $instanceIds = [];
        foreach ($decoded as $candidate) {
            if (!is_array($candidate)) {
                throw new InvalidArgumentException(
                    'ETA target location is invalid.'
                );
            }
            $instanceId = $candidate['locationInstanceId'] ?? null;
            if (
                !is_int($instanceId)
                || $instanceId <= 0
                || isset($instanceIds[$instanceId])
            ) {
                throw new InvalidArgumentException(
                    'ETA target location is invalid.'
                );
            }
            $location = $this->sharedLocationConfiguration($instanceId);
            $key = $location['key'];
            if (isset($keys[$key])) {
                throw new InvalidArgumentException(
                    'ETA target location key is duplicated.'
                );
            }
            $instanceIds[$instanceId] = true;
            $keys[$key] = true;
            $coordinate = OwnTracksWgs84::coordinate(
                $location['latitude'],
                $location['longitude']
            );
            $targets[] = [
                'targetKey' => $key,
                'latitude' => $coordinate['latitude'],
                'longitude' => $coordinate['longitude'],
                'locationInstanceId' => $instanceId,
            ];
        }

        return $targets;
    }

    /**
     * @return array{
     *   key: string,
     *   latitude: float,
     *   longitude: float,
     *   timezone: string,
     *   elevation: float|null
     * }
     */
    private function sharedLocationConfiguration(int $instanceId): array
    {
        if ($instanceId <= 0 || !IPS_InstanceExists($instanceId)) {
            throw new InvalidArgumentException(
                'Shared location instance does not exist.'
            );
        }
        $instance = IPS_GetInstance($instanceId);
        $moduleId = $instance['ModuleInfo']['ModuleID'] ?? null;
        if (!is_string($moduleId) || $moduleId !== self::LOCATION_MODULE_ID) {
            throw new InvalidArgumentException(
                'Shared location instance has an incompatible module type.'
            );
        }
        $descriptorJson = SAEFLOCATION_GetDescriptor($instanceId);
        if (strlen($descriptorJson) > self::MAXIMUM_LOCATION_DESCRIPTOR_BYTES) {
            throw new InvalidArgumentException(
                'Shared location descriptor is too large.'
            );
        }
        $descriptor = json_decode(
            $descriptorJson,
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        $location = is_array($descriptor)
            ? ($descriptor['location'] ?? null)
            : null;
        $key = is_array($location) ? ($location['key'] ?? null) : null;
        $timezone = is_array($location)
            ? ($location['timezone'] ?? null)
            : null;
        $elevation = is_array($location)
            ? ($location['elevation'] ?? null)
            : null;
        if (
            !is_array($descriptor)
            || ($descriptor['success'] ?? null) !== true
            || !is_array($location)
            || !is_string($key)
            || preg_match(self::LOCATION_KEY_PATTERN, $key) !== 1
            || !is_string($timezone)
            || $timezone === ''
            || ($elevation !== null
                && !is_int($elevation)
                && !is_float($elevation))
        ) {
            throw new InvalidArgumentException(
                'Shared location descriptor is unavailable.'
            );
        }
        $coordinate = OwnTracksWgs84::coordinate(
            $location['latitude'] ?? null,
            $location['longitude'] ?? null
        );
        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new InvalidArgumentException(
                'Shared location timezone is invalid.'
            );
        }
        if ($elevation !== null && !is_finite((float) $elevation)) {
            throw new InvalidArgumentException(
                'Shared location elevation is invalid.'
            );
        }

        return [
            'key' => $key,
            'latitude' => $coordinate['latitude'],
            'longitude' => $coordinate['longitude'],
            'timezone' => $timezone,
            'elevation' => $elevation === null ? null : (float) $elevation,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function externalPathResult(array $query): array
    {
        $query['renderMode'] = 'timestamp-points';
        $query['excludePoorAccuracyFromLine'] = false;
        $variableID = $this->externalAnchorVariableID();
        $variable = IPS_GetVariable($variableID);
        $observedAt = $variable['VariableUpdated'];
        if ($observedAt <= 0) {
            throw new RuntimeException('External path timestamp is invalid.');
        }
        $coordinate = $this->externalAnchor();
        if ($coordinate === null) {
            throw new RuntimeException('External path position is missing.');
        }
        $record = [
            'TimeStamp' => $observedAt,
            'Value' => json_encode(
                [
                    'tst' => $observedAt,
                    'lat' => $coordinate['latitude'],
                    'lon' => $coordinate['longitude'],
                ],
                JSON_THROW_ON_ERROR
            ),
        ];

        return OwnTracksTrackCore::project([$record], [], $query);
    }

    /** @return array{latitude: float, longitude: float}|null */
    private function externalAnchor(): ?array
    {
        if ($this->ReadPropertyInteger('ExternalAnchorID') <= 0) {
            return null;
        }
        $value = GetValue($this->externalAnchorVariableID());
        if (!is_string($value) || strlen($value) > 4096) {
            throw new RuntimeException('External anchor value is invalid.');
        }
        $decoded = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('External anchor value is invalid.');
        }

        return OwnTracksWgs84::coordinate(
            $decoded['lat'] ?? null,
            $decoded['lon'] ?? null
        );
    }

    private function externalAnchorVariableID(): int
    {
        $anchorID = $this->ReadPropertyInteger('ExternalAnchorID');
        $ident = $this->ReadPropertyString('ExternalAnchorPositionIdent');
        if (
            $anchorID <= 0
            || !IPS_InstanceExists($anchorID)
            || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $ident) !== 1
        ) {
            throw new RuntimeException('External anchor configuration is invalid.');
        }
        $variableID = @IPS_GetObjectIDByIdent($ident, $anchorID);
        if (
            $variableID === false
            || $variableID <= 0
            || !IPS_VariableExists($variableID)
            || IPS_GetVariable($variableID)['VariableType'] !== 3
        ) {
            throw new RuntimeException('External anchor position is missing.');
        }

        return $variableID;
    }

    /** @param list<int> $references */
    private function registerReferences(array $references): void
    {
        $references = array_values(array_unique($references));
        sort($references, SORT_NUMERIC);
        foreach ($references as $referenceID) {
            if ($referenceID <= 0) {
                throw new RuntimeException('Reference configuration is invalid.');
            }
            $this->RegisterReference($referenceID);
        }
        $this->WriteAttributeString(
            'RegisteredReferences',
            json_encode($references, JSON_THROW_ON_ERROR)
        );
    }

    private function clearRegisteredReferences(): void
    {
        $references = json_decode(
            $this->ReadAttributeString('RegisteredReferences'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );
        if (!is_array($references) || !array_is_list($references)) {
            throw new RuntimeException('Registered reference state is invalid.');
        }
        foreach ($references as $referenceID) {
            if (!is_int($referenceID) || $referenceID <= 0) {
                throw new RuntimeException('Registered reference state is invalid.');
            }
            $this->UnregisterReference($referenceID);
        }
        $this->WriteAttributeString('RegisteredReferences', '[]');
    }

    /** @param array<string, mixed> $window */
    private function assertSelectableWindow(array $window): void
    {
        $now = $this->currentTimestamp();
        $maximum = OwnTracksDayWindow::fromLocalDate(
            (new DateTimeImmutable('@' . $now))
                ->setTimezone(new DateTimeZone(
                    $this->ReadPropertyString('SelectedTimeZone')
                ))
                ->format('Y-m-d'),
            $this->ReadPropertyString('SelectedTimeZone')
        );
        $minimumTimestamp = $maximum['from']
            - $this->ReadPropertyInteger('HistoryDays') * 24 * 60 * 60
            - 2 * 60 * 60;
        if (
            !is_int($window['from'] ?? null)
            || $window['from'] < $minimumTimestamp
            || $window['from'] > $maximum['from']
        ) {
            throw new InvalidArgumentException(
                'Selected date is outside the configured history window.'
            );
        }
    }

    private function validateRuntimeBounds(): void
    {
        $integerBounds = [
            ['HistoryDays', 1, 3660],
            ['MaxArchiveRecords', 1, 10000],
            ['MaxRenderedPoints', 1, 5000],
            ['MaximumGapSeconds', 1, 24 * 60 * 60],
            ['MaximumReceptionDelaySeconds', 0, 24 * 60 * 60],
            ['MaximumAccuracyAgeSeconds', 1, 24 * 60 * 60],
            ['MaximumActivityAgeSeconds', 1, 24 * 60 * 60],
        ];
        foreach ($integerBounds as [$property, $minimum, $maximum]) {
            $value = $this->ReadPropertyInteger($property);
            if ($value < $minimum || $value > $maximum) {
                throw new InvalidArgumentException(
                    $property . ' is outside the supported range.'
                );
            }
        }
        foreach (
            [
                'MaximumAccuracyMeters' => 100000.0,
                'MaximumStepDistanceMeters' => 10000000.0,
            ] as $property => $maximum
        ) {
            $value = $this->ReadPropertyFloat($property);
            if (!is_finite($value) || $value <= 0.0 || $value > $maximum) {
                throw new InvalidArgumentException(
                    $property . ' is outside the supported range.'
                );
            }
        }
        OwnTracksDayWindow::fromLocalDate(
            '2000-01-01',
            $this->ReadPropertyString('SelectedTimeZone')
        );
    }

    private function activateRequest(string $clientKey, int $generation): void
    {
        $now = $this->currentTimestamp();
        $requests = $this->activeRequests();
        $previousGeneration = $requests[$clientKey]['generation'] ?? null;
        if (is_int($previousGeneration) && $generation <= $previousGeneration) {
            throw new InvalidArgumentException(
                'Request generation is not monotonic.'
            );
        }
        foreach ($requests as $key => $request) {
            if (($request['seenAt'] ?? 0) < $now - self::CLIENT_SESSION_TTL_SECONDS) {
                unset($requests[$key]);
            }
        }
        $requests[$clientKey] = [
            'generation' => $generation,
            'seenAt' => $now,
        ];
        uasort(
            $requests,
            static fn (array $left, array $right): int =>
                ($right['seenAt'] ?? 0) <=> ($left['seenAt'] ?? 0)
        );
        $requests = array_slice(
            $requests,
            0,
            self::MAX_CLIENT_SESSIONS,
            true
        );
        $this->WriteAttributeString(
            'ActiveRequests',
            json_encode($requests, JSON_THROW_ON_ERROR)
        );
    }

    private function recordTileSelection(
        string $clientKey,
        int $generation,
        mixed $fitBounds
    ): void {
        $fallback = $this->tileFallbackConfiguration();
        if (($fallback['enabled'] ?? false) !== true) {
            return;
        }
        $requests = $this->activeRequests();
        $request = $requests[$clientKey] ?? null;
        if (
            !is_array($request)
            || ($request['generation'] ?? null) !== $generation
            || !is_array($fitBounds)
        ) {
            return;
        }
        try {
            $west = $this->finiteTileBound($fitBounds['west'] ?? null);
            $south = $this->finiteTileBound($fitBounds['south'] ?? null);
            $east = $this->finiteTileBound($fitBounds['east'] ?? null);
            $north = $this->finiteTileBound($fitBounds['north'] ?? null);
            if (
                $west < -180.0 || $west > 180.0
                || $east < -180.0 || $east > 180.0
                || $south < -85.05112878 || $south > 85.05112878
                || $north < -85.05112878 || $north > 85.05112878
                || $south > $north
            ) {
                throw new InvalidArgumentException('Tile selection bounds are invalid.');
            }
            $bounds = [
                'west' => $west,
                'south' => $south,
                'east' => $east,
                'north' => $north,
                'crossesAntimeridian' =>
                    ($fitBounds['crossesAntimeridian'] ?? false) === true,
            ];
            $request['tileSelection'] = [
                'bounds' => $bounds,
                'selectionKey' => hash(
                    'sha256',
                    $clientKey . "\0" . $generation . "\0"
                        . json_encode($bounds, JSON_THROW_ON_ERROR)
                ),
            ];
        } catch (Throwable) {
            unset($request['tileSelection']);
            $this->SendDebug(
                'OwnTracks tile selection unavailable',
                'Selection bounds are invalid.',
                0
            );
        }
        $requests[$clientKey] = $request;
        $this->WriteAttributeString(
            'ActiveRequests',
            json_encode($requests, JSON_THROW_ON_ERROR)
        );
    }

    /** @return array<string, mixed>|null */
    private function tileSelectionForClient(
        string $clientKey,
        int $viewportGeneration
    ): ?array {
        $clientKey = $this->clientKey($clientKey);
        $request = $this->activeRequests()[$clientKey] ?? null;
        $selection = is_array($request)
            ? ($request['tileSelection'] ?? null)
            : null;
        $seenAt = is_array($request) ? ($request['seenAt'] ?? null) : null;
        if (
            !is_array($selection)
            || !is_int($seenAt)
            || $seenAt < $this->currentTimestamp()
                - self::CLIENT_SESSION_TTL_SECONDS
            || $viewportGeneration <= 0
        ) {
            return null;
        }

        $viewports = $selection['viewports'] ?? null;
        $viewport = is_array($viewports)
            ? ($viewports[(string) $viewportGeneration] ?? null)
            : null;
        if (
            !is_array($viewport)
            || ($viewport['viewportGeneration'] ?? null)
                !== $viewportGeneration
            || !is_int($viewport['acceptedAt'] ?? null)
            || $viewport['acceptedAt'] < $this->currentTimestamp()
                - self::TILE_VIEWPORT_GRACE_SECONDS
        ) {
            return null;
        }
        $selection['viewport'] = $viewport;

        return $selection;
    }

    private function requestTileViewport(mixed $value): void
    {
        $generation = null;
        $viewportGeneration = null;
        try {
            if (!is_string($value) || strlen($value) > self::MAX_REQUEST_BYTES) {
                throw new InvalidArgumentException('Tile viewport request is invalid.');
            }
            $requestValue = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($requestValue) || array_is_list($requestValue)) {
                throw new InvalidArgumentException('Tile viewport request is invalid.');
            }
            $generation = $this->positiveInteger(
                $requestValue['requestGeneration'] ?? null,
                'Request generation'
            );
            $viewportGeneration = $this->positiveInteger(
                $requestValue['viewportGeneration'] ?? null,
                'Viewport generation'
            );
            $clientKey = $this->clientKey(
                $requestValue['clientSessionKey'] ?? null
            );
            $zoom = $requestValue['zoom'] ?? null;
            $boundsValue = $requestValue['bounds'] ?? null;
            if (!is_int($zoom) || !is_array($boundsValue)) {
                throw new InvalidArgumentException('Tile viewport request is invalid.');
            }
            $fallback = $this->tileFallbackConfiguration();
            if (($fallback['enabled'] ?? false) !== true) {
                throw new InvalidArgumentException('Tile viewport is unavailable.');
            }
            $requests = $this->activeRequests();
            $active = $requests[$clientKey] ?? null;
            $selection = is_array($active)
                ? ($active['tileSelection'] ?? null)
                : null;
            if (
                !is_array($active)
                || ($active['generation'] ?? null) !== $generation
                || !is_array($selection)
                || !is_array($selection['bounds'] ?? null)
                || $viewportGeneration
                    <= (int) ($active['viewportGeneration'] ?? 0)
            ) {
                throw new InvalidArgumentException('Tile viewport is stale.');
            }
            $minimumZoom = max(
                $fallback['minimumZoom'],
                $zoom - 1
            );
            $maximumZoom = min(
                $fallback['maximumZoom'],
                $zoom
            );
            $allowlist = OwnTracksTileSelectionAllowlist::fromFitBounds(
                $boundsValue,
                $minimumZoom,
                $maximumZoom,
                $fallback['viewportRingTiles'],
                $fallback['maximumTilesPerSelection']
            );
            if (!$this->tileBoundsIntersect($selection['bounds'], $boundsValue)) {
                throw new InvalidArgumentException('Tile viewport is outside selection.');
            }
            $viewport = [
                'bounds' => [
                    'west' => $this->finiteTileBound($boundsValue['west'] ?? null),
                    'south' => $this->finiteTileBound($boundsValue['south'] ?? null),
                    'east' => $this->finiteTileBound($boundsValue['east'] ?? null),
                    'north' => $this->finiteTileBound($boundsValue['north'] ?? null),
                    'crossesAntimeridian' =>
                        ($boundsValue['crossesAntimeridian'] ?? false) === true,
                ],
                'minimumZoom' => $minimumZoom,
                'maximumZoom' => $maximumZoom,
                'tileCount' => $allowlist->tileCount(),
                'viewportGeneration' => $viewportGeneration,
                'acceptedAt' => $this->currentTimestamp(),
                'selectionKey' => hash(
                    'sha256',
                    $selection['selectionKey'] . "\0" . $viewportGeneration
                        . "\0" . $zoom . "\0"
                        . json_encode($boundsValue, JSON_THROW_ON_ERROR)
                ),
            ];
            $retained = $active['tileSelection']['viewports'] ?? [];
            if (!is_array($retained)) {
                $retained = [];
            }
            foreach ($retained as $key => $candidate) {
                if (
                    !is_array($candidate)
                    || !is_int($candidate['acceptedAt'] ?? null)
                    || $candidate['acceptedAt'] < $this->currentTimestamp()
                        - self::TILE_VIEWPORT_GRACE_SECONDS
                ) {
                    unset($retained[$key]);
                }
            }
            $retained[(string) $viewportGeneration] = $viewport;
            uksort(
                $retained,
                static fn (string $left, string $right): int =>
                    (int) $right <=> (int) $left
            );
            $retained = array_slice(
                $retained,
                0,
                self::MAXIMUM_RETAINED_TILE_VIEWPORTS,
                true
            );
            $active['viewportGeneration'] = $viewportGeneration;
            $active['tileSelection']['viewport'] = $viewport;
            $active['tileSelection']['viewports'] = $retained;
            $requests[$clientKey] = $active;
            $this->WriteAttributeString(
                'ActiveRequests',
                json_encode($requests, JSON_THROW_ON_ERROR)
            );
            $this->UpdateVisualizationValue($this->encodeMessage([
                'action' => 'tileViewport',
                'requestGeneration' => $generation,
                'viewportGeneration' => $viewportGeneration,
            ]));
        } catch (Throwable $exception) {
            $this->SendDebug(
                'OwnTracks tile viewport rejected',
                $exception->getMessage(),
                0
            );
            $this->UpdateVisualizationValue($this->encodeMessage([
                'action' => 'tileViewportError',
                'requestGeneration' => $generation,
                'viewportGeneration' => $viewportGeneration,
            ]));
        }
    }

    private function finiteTileBound(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Tile selection bound is invalid.');
        }
        $bound = (float) $value;
        if (!is_finite($bound)) {
            throw new InvalidArgumentException('Tile selection bound is invalid.');
        }

        return $bound;
    }

    /** @param array<string, mixed> $selection @param array<string, mixed> $viewport */
    private function tileBoundsIntersect(array $selection, array $viewport): bool
    {
        $selectionSouth = $this->finiteTileBound($selection['south'] ?? null);
        $selectionNorth = $this->finiteTileBound($selection['north'] ?? null);
        $viewportSouth = $this->finiteTileBound($viewport['south'] ?? null);
        $viewportNorth = $this->finiteTileBound($viewport['north'] ?? null);
        if ($viewportNorth < $selectionSouth || $viewportSouth > $selectionNorth) {
            return false;
        }
        foreach ($this->longitudeIntervals($selection) as $selectionInterval) {
            foreach ($this->longitudeIntervals($viewport) as $viewportInterval) {
                if (
                    $viewportInterval[1] >= $selectionInterval[0]
                    && $viewportInterval[0] <= $selectionInterval[1]
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string, mixed> $bounds @return list<array{0: float, 1: float}> */
    private function longitudeIntervals(array $bounds): array
    {
        $west = $this->finiteTileBound($bounds['west'] ?? null);
        $east = $this->finiteTileBound($bounds['east'] ?? null);
        $crosses = ($bounds['crossesAntimeridian'] ?? false) === true
            || $east < $west;

        return $crosses
            ? [[$west, 180.0], [-180.0, $east]]
            : [[$west, $east]];
    }

    /** @param array<string, mixed> $bounds */
    private function tileWithinSelectionEnvelope(
        array $bounds,
        int $zoom,
        int $x,
        int $y,
        int $ringTiles
    ): bool {
        if ($zoom < 0 || $zoom > 22 || $x < 0 || $y < 0) {
            return false;
        }
        $side = 2 ** $zoom;
        if ($x >= $side || $y >= $side) {
            return false;
        }
        $south = $this->finiteTileBound($bounds['south'] ?? null);
        $north = $this->finiteTileBound($bounds['north'] ?? null);
        $minimumY = max(0, $this->tileLatitudeToY($north, $zoom) - $ringTiles);
        $maximumY = min(
            $side - 1,
            $this->tileLatitudeToY($south, $zoom) + $ringTiles
        );
        if ($y < $minimumY || $y > $maximumY) {
            return false;
        }
        foreach ($this->longitudeIntervals($bounds) as [$west, $east]) {
            $minimumX = max(0, $this->tileLongitudeToX($west, $zoom) - $ringTiles);
            $maximumX = min(
                $side - 1,
                $this->tileLongitudeToX($east, $zoom) + $ringTiles
            );
            if ($x >= $minimumX && $x <= $maximumX) {
                return true;
            }
        }

        return false;
    }

    private function tileLongitudeToX(float $longitude, int $zoom): int
    {
        $side = 2 ** $zoom;

        return min(
            $side - 1,
            max(0, (int) floor(($longitude + 180.0) / 360.0 * $side))
        );
    }

    private function tileLatitudeToY(float $latitude, int $zoom): int
    {
        $side = 2 ** $zoom;
        $latitude = max(-85.05112878, min(85.05112878, $latitude));
        $normalized = (1.0 - asinh(tan(deg2rad($latitude))) / M_PI) / 2.0;

        return min($side - 1, max(0, (int) floor($normalized * $side)));
    }

    private function activeGeneration(string $clientKey): int
    {
        $requests = $this->activeRequests();
        $generation = $requests[$clientKey]['generation'] ?? null;
        if (!is_int($generation) || $generation <= 0) {
            throw new RuntimeException('Active request generation is missing.');
        }

        return $generation;
    }

    /** @return array<string, array<string, mixed>> */
    private function activeRequests(): array
    {
        $decoded = json_decode(
            $this->ReadAttributeString('ActiveRequests'),
            true,
            16,
            JSON_THROW_ON_ERROR
        );

        return is_array($decoded) ? $decoded : [];
    }

    private function clientKey(mixed $value): string
    {
        if (
            !is_string($value)
            || preg_match(self::CLIENT_KEY_PATTERN, $value) !== 1
        ) {
            throw new InvalidArgumentException('Client session key is invalid.');
        }

        return $value;
    }

    private function sourceKey(mixed $value): string
    {
        if (
            !is_string($value)
            || preg_match(self::SOURCE_KEY_PATTERN, $value) !== 1
        ) {
            throw new InvalidArgumentException('Source key is invalid.');
        }

        return $value;
    }

    private function positiveInteger(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $message */
    private function encodeMessage(array $message): string
    {
        return json_encode(
            $message,
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );
    }

    protected function currentTimestamp(): int
    {
        return time();
    }
}
