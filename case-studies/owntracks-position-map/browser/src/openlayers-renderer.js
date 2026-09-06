import Feature from 'ol/Feature.js';
import {defaults as defaultInteractions} from 'ol/interaction/defaults.js';
import Graticule from 'ol/layer/Graticule.js';
import TileLayer from 'ol/layer/Tile.js';
import VectorLayer from 'ol/layer/Vector.js';
import OlMap from 'ol/Map.js';
import LineString from 'ol/geom/LineString.js';
import Point from 'ol/geom/Point.js';
import ImageTileSource from 'ol/source/ImageTile.js';
import VectorSource from 'ol/source/Vector.js';
import {
    Circle as CircleStyle,
    Fill,
    Stroke,
    Style,
    Text,
} from 'ol/style.js';
import View from 'ol/View.js';
import {fromLonLat, transformExtent} from 'ol/proj.js';
import {createXYZ} from 'ol/tilegrid.js';
import 'ol/ol.css';

(function () {
    'use strict';

    const root = document.querySelector('[data-owntracks-openlayers-map]');
    if (!root) {
        return;
    }

    const mapSurface = root.querySelector('[data-map-surface]');
    const sourceSelect = root.querySelector('[data-source-select]');
    const daySelect = root.querySelector('[data-day-select]');
    const modeSelect = root.querySelector('[data-mode-select]');
    const etaOutput = root.querySelector('[data-eta]');
    const tooltipOutput = root.querySelector('[data-point-tooltip]');
    const attributionOutput = root.querySelector('[data-attribution]');
    const statusOutput = root.querySelector('[data-status]');
    const lineSource = new VectorSource({wrapX: false});
    const leaderSource = new VectorSource({wrapX: false});
    const arrowSource = new VectorSource({wrapX: false});
    const pointSource = new VectorSource({wrapX: false});
    const labelSource = new VectorSource({wrapX: false});
    const targetSource = new VectorSource({wrapX: false});
    const clientSessionKey = 'client-' + Array.from(
        crypto.getRandomValues(new Uint32Array(4)),
        function (value) {
            return value.toString(36).padStart(7, '0');
        }
    ).join('');
    const tileCapabilityHeader = 'X-SAEF-Tile-Capability';
    const tileViewportHeader = 'X-SAEF-Tile-Viewport';
    const tileRetryDelaysMilliseconds = [3000, 60000];
    const tileRetryDrainPollMilliseconds = 250;
    const tileManualRearmCooldownMilliseconds = 3000;
    let baseLayer = null;
    const state = {
        generation: 0,
        result: null,
        target: null,
        eta: null,
        etaEntries: [],
        tooltipFeature: null,
        tooltipPinned: false,
        tooltipTimer: null,
        requestNoticeTimer: null,
        requestStartedAt: null,
        lastRequestDurationMilliseconds: 0,
        slowRequestCount: 0,
        lastAction: 'bootstrap',
        lastProjectionMilliseconds: 0,
        pendingBasemap: null,
        tileGrid: null,
        tileAccess: null,
        tileCapability: null,
        tileCapabilityExpiresAt: 0,
        tileCapabilityTimer: null,
        tileCapabilityGeneration: 0,
        tileViewportGeneration: 0,
        tileViewportAcceptedGeneration: 0,
        tileViewportReady: false,
        tileViewportTimer: null,
        tileViewportFingerprint: null,
        tileEpoch: 0,
        tileQueue: [],
        activeTileRequests: new Map(),
        tileRequestsStarted: 0,
        tileRequestsSucceeded: 0,
        tileRequestsFailed: 0,
        tileMissingCount: 0,
        tileViewportFailureCount: 0,
        tileFailureCounts: {
            network: 0,
            httpClient: 0,
            httpServer: 0,
            httpOther: 0,
            contentType: 0,
            payload: 0,
            decode: 0,
        },
        tileRetryTimer: null,
        tileRetryCount: 0,
        tileRetryDrainWaitCount: 0,
        tileRetryViewportGeneration: 0,
        tileManualRearmCount: 0,
        tileManualRearmViewportGeneration: 0,
        tileManualRearmLastAt: 0,
        tileBasemapConfigureTimer: null,
        tileBasemapDrainWaitCount: 0,
        tileCapabilityRefreshCount: 0,
        tileObjectUrlsCreated: 0,
        tileObjectUrlsRevoked: 0,
        tileMaximumObservedConcurrency: 0,
        labelCandidates: [],
        browserSources: [],
        pathSourceKey: null,
        pathSelectedDate: null,
        todayDate: null,
        selectedOverviewSourceKey: null,
        overviewDisplacements: new Map(),
    };

    function surfaceColors() {
        const style = getComputedStyle(mapSurface);
        return {
            text: style.color,
            grid: style.borderTopColor,
            gridStrong: style.borderRightColor,
            track: style.borderBottomColor,
            point: style.borderLeftColor,
            target: style.outlineColor,
            halo: getComputedStyle(root).backgroundColor,
        };
    }

    const graticule = new Graticule({
        showLabels: true,
        wrapX: false,
        targetSize: 110,
        strokeStyle: new Stroke({color: 'rgba(127,127,127,0.28)', width: 1}),
    });
    const lineLayer = new VectorLayer({source: lineSource, updateWhileInteracting: false});
    const leaderLayer = new VectorLayer({
        source: leaderSource,
        updateWhileInteracting: false,
    });
    const arrowLayer = new VectorLayer({source: arrowSource, updateWhileInteracting: false});
    const pointLayer = new VectorLayer({source: pointSource, updateWhileInteracting: false});
    const labelLayer = new VectorLayer({
        source: labelSource,
        declutter: true,
        updateWhileInteracting: false,
    });
    const targetLayer = new VectorLayer({source: targetSource, updateWhileInteracting: false});
    const view = new View({
        projection: 'EPSG:3857',
        center: fromLonLat([0, 0]),
        zoom: 2,
        constrainResolution: false,
        enableRotation: false,
    });
    const map = new OlMap({
        target: mapSurface,
        layers: [
            graticule,
            lineLayer,
            leaderLayer,
            pointLayer,
            arrowLayer,
            labelLayer,
            targetLayer,
        ],
        controls: [],
        interactions: defaultInteractions({
            altShiftDragRotate: false,
            pinchRotate: false,
        }),
        view,
        keyboardEventTarget: mapSurface,
    });
    document.documentElement.dataset.openlayersRotationEnabled = 'false';
    document.documentElement.dataset.openlayersRotation = String(
        view.getRotation()
    );

    function sameOriginXyzTemplate(value) {
        return typeof value === 'string'
            && value.length <= 256
            && value.startsWith('/')
            && !value.startsWith('//')
            && !value.includes('..')
            && !value.includes('\\')
            && !value.includes('?')
            && !value.includes('#')
            && (value.match(/\{z\}/g) || []).length === 1
            && (value.match(/\{x\}/g) || []).length === 1
            && (value.match(/\{y\}/g) || []).length === 1;
    }

    function renderAttribution(configuration) {
        attributionOutput.replaceChildren();
        if (!configuration.enabled) {
            attributionOutput.textContent = configuration.attributionText;
            return;
        }
        const link = document.createElement('a');
        link.textContent = configuration.attributionText;
        link.href = configuration.attributionUrl;
        link.target = '_blank';
        link.rel = 'noreferrer noopener';
        attributionOutput.appendChild(link);
    }

    function clearTileCapabilityTimer() {
        if (state.tileCapabilityTimer !== null) {
            window.clearTimeout(state.tileCapabilityTimer);
            state.tileCapabilityTimer = null;
        }
    }

    function clearTileRetryTimer() {
        if (state.tileRetryTimer !== null) {
            window.clearTimeout(state.tileRetryTimer);
            state.tileRetryTimer = null;
        }
    }

    function clearTileBasemapConfigureTimer() {
        if (state.tileBasemapConfigureTimer !== null) {
            window.clearTimeout(state.tileBasemapConfigureTimer);
            state.tileBasemapConfigureTimer = null;
        }
    }

    function tileFailure(failureClass, message) {
        const error = new Error(message);
        error.tileFailureClass = failureClass;
        return error;
    }

    function recordTileFailure(failureClass) {
        const normalized = Object.prototype.hasOwnProperty.call(
            state.tileFailureCounts,
            failureClass
        )
            ? failureClass
            : 'network';
        state.tileFailureCounts[normalized] += 1;
        document.documentElement.dataset.openlayersTileFailureCounts =
            JSON.stringify(state.tileFailureCounts);
    }

    function resetTileRetry() {
        clearTileRetryTimer();
        state.tileRetryCount = 0;
        state.tileRetryDrainWaitCount = 0;
        state.tileRetryViewportGeneration = state.tileViewportGeneration;
        document.documentElement.dataset.openlayersTileRetryCount = '0';
        document.documentElement.dataset.openlayersTileRetryDrainWaitCount = '0';
        document.documentElement.dataset.openlayersTileRetryState = 'idle';
    }

    function scheduleTileRetry(delayMilliseconds = null) {
        if (
            state.tileRetryTimer !== null
            || state.tileRetryCount >= tileRetryDelaysMilliseconds.length
            || baseLayer === null
            || !state.tileViewportReady
        ) {
            return;
        }
        const retryDelay = delayMilliseconds === null
            ? tileRetryDelaysMilliseconds[state.tileRetryCount]
            : delayMilliseconds;
        const generation = state.generation;
        const viewportGeneration = state.tileViewportGeneration;
        state.tileRetryViewportGeneration = viewportGeneration;
        document.documentElement.dataset.openlayersTileRetryState = 'scheduled';
        state.tileRetryTimer = window.setTimeout(function () {
            state.tileRetryTimer = null;
            if (
                generation !== state.generation
                || viewportGeneration !== state.tileViewportGeneration
                || viewportGeneration !== state.tileRetryViewportGeneration
                || baseLayer === null
                || !state.tileViewportReady
            ) {
                document.documentElement.dataset.openlayersTileRetryState =
                    'cancelled';
                return;
            }
            if (
                state.tileQueue.length > 0
                || state.activeTileRequests.size > 0
            ) {
                state.tileRetryDrainWaitCount += 1;
                document.documentElement.dataset
                    .openlayersTileRetryDrainWaitCount =
                    String(state.tileRetryDrainWaitCount);
                document.documentElement.dataset.openlayersTileRetryState =
                    'waiting-for-queue';
                scheduleTileRetry(tileRetryDrainPollMilliseconds);
                return;
            }
            state.tileRetryCount += 1;
            document.documentElement.dataset.openlayersTileRetryCount =
                String(state.tileRetryCount);
            document.documentElement.dataset.openlayersTileRetryState =
                'requesting-viewport';
            requestTileViewport({
                force: true,
                preserveRetryCount: true,
            });
        }, retryDelay);
    }

    function cancelTileRequests() {
        state.tileEpoch += 1;
        const abortError = new Error('Tile request aborted.');
        abortError.name = 'AbortError';
        state.tileQueue.forEach(function (request) {
            request.reject(abortError);
        });
        state.tileQueue = [];
        state.activeTileRequests.forEach(function (request) {
            request.controller.abort();
        });
        state.activeTileRequests.clear();
    }

    function disableProtectedBasemap(reason) {
        clearTileBasemapConfigureTimer();
        cancelTileRequests();
        clearTileCapabilityTimer();
        state.tileCapability = null;
        state.tileCapabilityExpiresAt = 0;
        if (baseLayer) {
            map.removeLayer(baseLayer);
            baseLayer = null;
        }
        renderAttribution({
            enabled: false,
            attributionText: 'OpenLayers · map tiles unavailable',
        });
        document.documentElement.dataset.openlayersProviderMode = 'none';
        document.documentElement.dataset.openlayersTileLayerCount = '0';
        document.documentElement.dataset.openlayersTileAuthState = reason;
        document.documentElement.dataset.openlayersLayerCount = String(
            map.getLayers().getLength()
        );
    }

    function pumpTileQueue() {
        const access = state.tileAccess;
        if (!access || state.tileCapability === null) {
            return;
        }
        while (
            state.tileQueue.length > 0
            && state.activeTileRequests.size < access.maximumConcurrentRequests
        ) {
            const queued = state.tileQueue.shift();
            if (!queued || queued.epoch !== state.tileEpoch) {
                continue;
            }
            const controller = new AbortController();
            const requestId = queued.requestId;
            const abortFromSource = function () {
                controller.abort();
            };
            if (queued.signal) {
                if (queued.signal.aborted) {
                    queued.reject(new DOMException('Aborted', 'AbortError'));
                    continue;
                }
                queued.signal.addEventListener('abort', abortFromSource, {
                    once: true,
                });
            }
            state.activeTileRequests.set(requestId, {
                controller,
                abortFromSource,
                signal: queued.signal,
            });
            state.tileMaximumObservedConcurrency = Math.max(
                state.tileMaximumObservedConcurrency,
                state.activeTileRequests.size
            );
            document.documentElement.dataset.openlayersTileMaximumConcurrency =
                String(state.tileMaximumObservedConcurrency);
            state.tileRequestsStarted += 1;
            document.documentElement.dataset.openlayersTileRequestsStarted =
                String(state.tileRequestsStarted);
            fetch(queued.src, {
                method: 'GET',
                headers: {
                    [tileCapabilityHeader]: state.tileCapability,
                    [tileViewportHeader]: String(queued.viewportGeneration),
                },
                credentials: 'same-origin',
                cache: 'no-cache',
                redirect: 'error',
                referrerPolicy: 'no-referrer',
                signal: controller.signal,
            }).then(function (response) {
                if (!response.ok || response.status !== 200) {
                    const failureClass = response.status >= 400
                        && response.status < 500
                        ? 'httpClient'
                        : response.status >= 500 && response.status < 600
                            ? 'httpServer'
                            : 'httpOther';
                    throw tileFailure(failureClass, 'Tile response rejected.');
                }
                if (response.headers.get('content-type') !== 'image/png') {
                    throw tileFailure(
                        'contentType',
                        'Tile content type rejected.'
                    );
                }
                return response.blob();
            }).then(function (blob) {
                if (
                    queued.epoch !== state.tileEpoch
                    || blob.size <= 0
                    || blob.size > 512 * 1024
                ) {
                    throw tileFailure('payload', 'Tile payload rejected.');
                }
                return new Promise(function (resolve, reject) {
                    const objectUrl = URL.createObjectURL(blob);
                    state.tileObjectUrlsCreated += 1;
                    const image = new Image();
                    image.addEventListener('load', function () {
                        document.documentElement.dataset.openlayersTileImagesLoaded =
                            String(state.tileObjectUrlsCreated);
                        window.setTimeout(function () {
                            URL.revokeObjectURL(objectUrl);
                            state.tileObjectUrlsRevoked += 1;
                            document.documentElement.dataset.openlayersTileObjectUrlBalance =
                                String(
                                    state.tileObjectUrlsCreated
                                    - state.tileObjectUrlsRevoked
                                );
                        }, 5000);
                        resolve(image);
                    }, {once: true});
                    image.addEventListener('error', function () {
                        URL.revokeObjectURL(objectUrl);
                        state.tileObjectUrlsRevoked += 1;
                        reject(tileFailure('decode', 'Tile image rejected.'));
                    }, {once: true});
                    image.src = objectUrl;
                    document.documentElement.dataset.openlayersTileObjectUrlBalance =
                        String(
                            state.tileObjectUrlsCreated
                            - state.tileObjectUrlsRevoked
                        );
                });
            }).then(function (image) {
                state.tileRequestsSucceeded += 1;
                document.documentElement.dataset.openlayersTileRequestsSucceeded =
                    String(state.tileRequestsSucceeded);
                queued.resolve(image);
            }).catch(function (error) {
                if (error.name === 'AbortError') {
                    queued.reject(error);
                    return;
                }
                state.tileRequestsFailed += 1;
                document.documentElement.dataset.openlayersTileRequestsFailed =
                    String(state.tileRequestsFailed);
                state.tileMissingCount += 1;
                document.documentElement.dataset.openlayersTileMissingCount =
                    String(state.tileMissingCount);
                if (
                    queued.viewportGeneration
                    === state.tileViewportAcceptedGeneration
                ) {
                    state.tileViewportFailureCount += 1;
                    document.documentElement.dataset
                        .openlayersTileViewportFailureCount =
                        String(state.tileViewportFailureCount);
                }
                recordTileFailure(error.tileFailureClass || 'network');
                scheduleTileRetry();
                queued.reject(error);
            }).finally(function () {
                const active = state.activeTileRequests.get(requestId);
                if (active && active.signal) {
                    active.signal.removeEventListener(
                        'abort',
                        active.abortFromSource
                    );
                }
                state.activeTileRequests.delete(requestId);
                pumpTileQueue();
            });
        }
    }

    function loadProtectedTile(zoom, x, y, options) {
        return new Promise(function (resolve, reject) {
            const previousMinimum = Number(
                document.documentElement.dataset.openlayersTileRequestMinimumZoom
            );
            const previousMaximum = Number(
                document.documentElement.dataset.openlayersTileRequestMaximumZoom
            );
            document.documentElement.dataset.openlayersTileRequestMinimumZoom =
                String(Number.isInteger(previousMinimum)
                    ? Math.min(previousMinimum, zoom)
                    : zoom);
            document.documentElement.dataset.openlayersTileRequestMaximumZoom =
                String(Number.isInteger(previousMaximum)
                    ? Math.max(previousMaximum, zoom)
                    : zoom);
            if (
                state.tileCapability === null
                || Date.now() >= state.tileCapabilityExpiresAt * 1000
            ) {
                disableProtectedBasemap('expired');
                reject(new Error('Tile capability unavailable.'));
                return;
            }
            const template = state.pendingBasemap.urlTemplate;
            const src = template
                .replace('{z}', String(zoom))
                .replace('{x}', String(x))
                .replace('{y}', String(y));
            const requestId = state.tileEpoch + ':' + state.tileRequestsStarted
                + ':' + state.tileQueue.length;
            const viewportGeneration = state.tileViewportAcceptedGeneration;
            if (!Number.isInteger(viewportGeneration) || viewportGeneration <= 0) {
                reject(new Error('Tile viewport capability unavailable.'));
                return;
            }
            state.tileQueue.push({
                epoch: state.tileEpoch,
                requestId,
                src,
                viewportGeneration,
                signal: options.signal,
                resolve,
                reject,
            });
            pumpTileQueue();
        });
    }

    function tileAccessContract(configuration) {
        if (
            !configuration
            || configuration.mode !== 'symcon-webhook'
            || configuration.authenticationMode
                !== 'ephemeral-header-capability'
            || configuration.headerName !== tileCapabilityHeader
            || configuration.hookPathPrefix
                !== '/hook/owntracks-position-map'
            || !Number.isInteger(configuration.tokenTtlSeconds)
            || configuration.tokenTtlSeconds < 60
            || configuration.tokenTtlSeconds > 900
            || !Number.isInteger(configuration.refreshBeforeExpirySeconds)
            || configuration.refreshBeforeExpirySeconds < 15
            || configuration.refreshBeforeExpirySeconds
                >= configuration.tokenTtlSeconds
            || !Number.isInteger(configuration.maximumConcurrentRequests)
            || configuration.maximumConcurrentRequests < 1
            || configuration.maximumConcurrentRequests > 16
        ) {
            throw new Error('Tile access contract is invalid.');
        }
        return configuration;
    }

    function requestTileCapability(isRefresh) {
        state.tileCapabilityGeneration += 1;
        document.documentElement.dataset.openlayersTileAuthState = 'requesting';
        if (typeof requestAction !== 'function') {
            disableProtectedBasemap('unavailable');
            return;
        }
        try {
            requestAction('RequestTileCapability', JSON.stringify({
                requestGeneration: state.tileCapabilityGeneration,
                clientSessionKey,
            }));
            if (isRefresh) {
                state.tileCapabilityRefreshCount += 1;
                document.documentElement.dataset.openlayersTileCapabilityRefreshCount =
                    String(state.tileCapabilityRefreshCount);
            }
        } catch (error) {
            disableProtectedBasemap('failed');
        }
    }

    function scheduleTileCapabilityRefresh() {
        clearTileCapabilityTimer();
        const refreshAt = state.tileCapabilityExpiresAt * 1000
            - state.tileAccess.refreshBeforeExpirySeconds * 1000;
        const delay = refreshAt - Date.now();
        if (delay <= 0) {
            disableProtectedBasemap('expired');
            return;
        }
        state.tileCapabilityTimer = window.setTimeout(function () {
            state.tileCapabilityTimer = null;
            requestTileCapability(true);
        }, delay);
    }

    function configureBasemap(configuration, tileAccess) {
        cancelTileRequests();
        if (baseLayer) {
            map.removeLayer(baseLayer);
            baseLayer = null;
        }
        if (!configuration || configuration.mode === 'none') {
            state.tileGrid = null;
            graticule.setVisible(true);
            const disabled = configuration || {
                enabled: false,
                attributionText: 'OpenLayers · no map tiles',
            };
            renderAttribution(disabled);
            document.documentElement.dataset.openlayersProviderMode = 'none';
            document.documentElement.dataset.openlayersTileLayerCount = '0';
            document.documentElement.dataset.openlayersTileAuthState = 'disabled';
            document.documentElement.dataset.openlayersLayerCount = String(
                map.getLayers().getLength()
            );
            return;
        }
        if (
            configuration.mode !== 'same-origin-xyz'
            || configuration.enabled !== true
            || !sameOriginXyzTemplate(configuration.urlTemplate)
            || typeof configuration.attributionText !== 'string'
            || typeof configuration.attributionUrl !== 'string'
            || !Number.isInteger(configuration.maximumZoom)
            || configuration.maximumZoom < 1
            || configuration.maximumZoom > 22
            || configuration.attributionText.length === 0
            || configuration.attributionText.length > 160
            || /[<>\u0000-\u001f\u007f]/.test(configuration.attributionText)
        ) {
            throw new Error('Basemap provider contract is invalid.');
        }
        state.tileAccess = tileAccessContract(tileAccess);
        state.tileGrid = createXYZ({
            maxZoom: configuration.maximumZoom,
        });
        if (state.tileCapability === null) {
            state.pendingBasemap = configuration;
            requestTileCapability(false);
            return;
        }
        if (!state.tileViewportReady) {
            state.pendingBasemap = configuration;
            document.documentElement.dataset.openlayersTileAuthState =
                'ready-pending-viewport';
            return;
        }
        const source = new ImageTileSource({
            loader: loadProtectedTile,
            maxZoom: configuration.maximumZoom,
            tileGrid: state.tileGrid,
            wrapX: false,
            attributions: configuration.attributionText,
            transition: 0,
        });
        graticule.setVisible(false);
        baseLayer = new TileLayer({
            source,
            preload: 0,
            useInterimTilesOnError: true,
        });
        map.getLayers().insertAt(0, baseLayer);
        renderAttribution(configuration);
        document.documentElement.dataset.openlayersProviderMode =
            'same-origin-xyz';
        document.documentElement.dataset.openlayersTileLayerCount = '1';
        document.documentElement.dataset.openlayersTileAuthState = 'ready';
        document.documentElement.dataset.openlayersLayerCount = String(
            map.getLayers().getLength()
        );
    }

    function configureBasemapWhenIdle() {
        clearTileBasemapConfigureTimer();
        if (
            baseLayer
            && (state.tileQueue.length > 0 || state.activeTileRequests.size > 0)
        ) {
            state.tileBasemapDrainWaitCount += 1;
            document.documentElement.dataset.openlayersTileBasemapDrainWaitCount =
                String(state.tileBasemapDrainWaitCount);
            document.documentElement.dataset.openlayersTileAuthState =
                'waiting-for-basemap-drain';
            state.tileBasemapConfigureTimer = window.setTimeout(function () {
                state.tileBasemapConfigureTimer = null;
                configureBasemapWhenIdle();
            }, 100);
            return;
        }
        configureBasemap(state.pendingBasemap, state.tileAccess);
    }

    function timestampLabel(timestamp) {
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(new Date(timestamp * 1000));
    }

    function observationLabel(point) {
        const time = timestampLabel(point.observedAt);
        if (
            typeof point.observedDate !== 'string'
            || point.observedDate === state.todayDate
            || !/^\d{4}-\d{2}-\d{2}$/.test(point.observedDate)
        ) {
            return time;
        }
        const date = new Intl.DateTimeFormat(undefined, {
            day: '2-digit',
            month: '2-digit',
            timeZone: 'UTC',
        }).format(new Date(point.observedDate + 'T00:00:00Z'));
        return date + ' · ' + time;
    }

    function unwrappedLongitude(longitude) {
        if (
            state.result
            && state.result.fitBounds
            && state.result.fitBounds.crossesAntimeridian
            && longitude < state.result.fitBounds.west
        ) {
            return longitude + 360;
        }
        return longitude;
    }

    function projectedCoordinate(point) {
        return fromLonLat([
            unwrappedLongitude(point.longitudeDegrees),
            point.latitudeDegrees,
        ]);
    }

    function clearTooltipTimer() {
        if (state.tooltipTimer !== null) {
            window.clearTimeout(state.tooltipTimer);
            state.tooltipTimer = null;
        }
    }

    function hidePointTooltip() {
        clearTooltipTimer();
        state.tooltipFeature = null;
        state.tooltipPinned = false;
        tooltipOutput.hidden = true;
        tooltipOutput.textContent = '';
        tooltipOutput.style.left = '0px';
        tooltipOutput.style.top = '0px';
        document.documentElement.dataset.openlayersTooltipState = 'hidden';
    }

    function pointTooltipText(feature) {
        const accuracy = feature.get('horizontalAccuracyMeters');
        const sourceLabel = feature.get('sourceLabel');
        return (sourceLabel ? sourceLabel + ' · ' : '')
            + observationLabel({
                observedAt: feature.get('observedAt'),
                observedDate: feature.get('observedDate'),
            })
            + ' · accuracy '
            + (accuracy === null ? 'unknown' : Math.round(accuracy) + ' m');
    }

    function positionPointTooltip(feature) {
        const geometry = feature.getGeometry();
        const pixel = geometry
            ? map.getPixelFromCoordinate(geometry.getCoordinates())
            : null;
        if (!pixel) {
            hidePointTooltip();
            return;
        }
        const rootRect = root.getBoundingClientRect();
        const surfaceRect = mapSurface.getBoundingClientRect();
        const anchorX = surfaceRect.left - rootRect.left + pixel[0];
        const anchorY = surfaceRect.top - rootRect.top + pixel[1];
        const margin = 8;
        const offset = 12;
        const width = tooltipOutput.offsetWidth;
        const height = tooltipOutput.offsetHeight;
        let left = anchorX + offset;
        if (left + width > root.clientWidth - margin) {
            left = anchorX - width - offset;
        }
        left = Math.max(
            margin,
            Math.min(left, root.clientWidth - width - margin)
        );
        let top = anchorY - height - offset;
        if (top < margin) {
            top = anchorY + offset;
        }
        top = Math.max(
            margin,
            Math.min(top, root.clientHeight - height - margin)
        );
        tooltipOutput.style.left = Math.round(left) + 'px';
        tooltipOutput.style.top = Math.round(top) + 'px';
    }

    function showPointTooltip(feature, pinned) {
        clearTooltipTimer();
        state.tooltipFeature = feature;
        state.tooltipPinned = pinned;
        tooltipOutput.textContent = pointTooltipText(feature);
        tooltipOutput.hidden = false;
        document.documentElement.dataset.openlayersTooltipState = pinned
            ? 'pinned'
            : 'hover';
        positionPointTooltip(feature);
        if (pinned) {
            state.tooltipTimer = window.setTimeout(hidePointTooltip, 4000);
        }
    }

    function pointFeatureAtPixel(pixel) {
        return map.forEachFeatureAtPixel(pixel, function (candidate) {
            return candidate.get('observedAt') ? candidate : null;
        }, {hitTolerance: 10});
    }

    function applyStyles() {
        const colors = surfaceColors();
        lineLayer.setStyle(function (feature) {
            const unverified = feature.get('lineConfidence') === 'unverified';
            return new Style({
                stroke: new Stroke({
                    color: colors.track,
                    width: unverified ? 2 : 3,
                    lineDash: unverified ? [6, 5] : undefined,
                }),
            });
        });
        leaderLayer.setStyle(new Style({
            stroke: new Stroke({
                color: colors.track,
                width: 2,
            }),
        }));
        arrowLayer.setStyle(function (feature) {
            return new Style({
                text: new Text({
                    text: '➤',
                    font: '700 16px system-ui, sans-serif',
                    rotation: -feature.get('direction'),
                    rotateWithView: true,
                    fill: new Fill({color: colors.track}),
                    stroke: new Stroke({color: colors.halo, width: 2}),
                }),
                zIndex: 4,
            });
        });
        pointLayer.setStyle(function (feature) {
            return new Style({
                image: new CircleStyle({
                    radius: 6,
                    fill: new Fill({color: colors.point}),
                    stroke: new Stroke({color: colors.halo, width: 2}),
                    displacement: feature.get('markerDisplacement') || [0, 0],
                }),
                zIndex: 5,
            });
        });
        labelLayer.setStyle(function (feature) {
            return new Style({
                text: new Text({
                    text: feature.get('labelText'),
                    offsetX: feature.get('labelOffsetX') || 7,
                    offsetY: feature.get('labelOffsetY') || -8,
                    textAlign: feature.get('labelTextAlign') || 'left',
                    fill: new Fill({color: colors.text}),
                    stroke: new Stroke({color: colors.halo, width: 3}),
                }),
            });
        });
        targetLayer.setStyle(new Style({
            image: new CircleStyle({
                radius: 10,
                fill: new Fill({color: 'rgba(0,0,0,0)'}),
                stroke: new Stroke({color: colors.target, width: 3}),
            }),
        }));
    }

    function rebuildOverviewDisplacements() {
        leaderSource.clear(true);
        state.overviewDisplacements = new Map();
        const features = pointSource.getFeatures();
        features.forEach(function (feature) {
            feature.set('markerDisplacement', [0, 0]);
        });
        if (modeSelect.value !== 'current-overview' || features.length < 2) {
            document.documentElement.dataset.openlayersDisplacedPointCount = '0';
            return;
        }
        const remaining = features.slice();
        const groups = [];
        while (remaining.length > 0) {
            const group = [remaining.shift()];
            let expanded = true;
            while (expanded) {
                expanded = false;
                for (let index = remaining.length - 1; index >= 0; index -= 1) {
                    const candidatePixel = map.getPixelFromCoordinate(
                        remaining[index].getGeometry().getCoordinates()
                    );
                    const close = group.some(function (member) {
                        const memberPixel = map.getPixelFromCoordinate(
                            member.getGeometry().getCoordinates()
                        );
                        return candidatePixel && memberPixel
                            && Math.hypot(
                                candidatePixel[0] - memberPixel[0],
                                candidatePixel[1] - memberPixel[1]
                            ) < 24;
                    });
                    if (close) {
                        group.push(remaining.splice(index, 1)[0]);
                        expanded = true;
                    }
                }
            }
            groups.push(group);
        }
        let displaced = 0;
        groups.forEach(function (group) {
            if (group.length < 2) {
                return;
            }
            group.sort(function (left, right) {
                return String(left.get('sourceLabel')).localeCompare(
                    String(right.get('sourceLabel'))
                );
            });
            group.forEach(function (feature, index) {
                const angle = -Math.PI / 2 + index * 2 * Math.PI / group.length;
                const marker = [
                    Math.round(Math.cos(angle) * 9),
                    Math.round(Math.sin(angle) * 9),
                ];
                const label = [
                    16,
                    Math.round((index - (group.length - 1) / 2) * 24),
                ];
                feature.set('markerDisplacement', marker);
                state.overviewDisplacements.set(feature.get('sourceKey'), {
                    marker,
                    label,
                });
                const coordinate = feature.getGeometry().getCoordinates();
                const pixel = map.getPixelFromCoordinate(coordinate);
                if (pixel) {
                    const displacedCoordinate = map.getCoordinateFromPixel([
                        pixel[0] + marker[0],
                        pixel[1] - marker[1],
                    ]);
                    leaderSource.addFeature(new Feature(new LineString([
                        coordinate,
                        displacedCoordinate,
                    ])));
                }
                displaced += 1;
            });
        });
        document.documentElement.dataset.openlayersDisplacedPointCount =
            String(displaced);
        pointLayer.changed();
    }

    function rebuildTimestampLabels() {
        labelSource.clear(true);
        const occupied = [];
        const candidates = state.labelCandidates.slice().reverse();
        candidates.forEach(function (point) {
            const coordinate = projectedCoordinate(point);
            const pixel = map.getPixelFromCoordinate(coordinate);
            if (!pixel) {
                return;
            }
            const displacement = state.overviewDisplacements.get(point.sourceKey);
            const labelText = point.sourceLabel
                ? point.sourceLabel + ' · ' + observationLabel(point)
                : observationLabel(point);
            const estimatedWidth = Math.min(
                220,
                Math.max(80, Math.ceil(labelText.length * 6.2))
            );
            const labelOffset = displacement
                ? displacement.label.slice()
                : [7, -8];
            let textAlign = 'left';
            if (
                pixel[0] + labelOffset[0] + estimatedWidth
                    > mapSurface.clientWidth - 8
            ) {
                labelOffset[0] = -8;
                textAlign = 'right';
            }
            const box = {
                left: textAlign === 'right'
                    ? pixel[0] + labelOffset[0] - estimatedWidth
                    : pixel[0] + labelOffset[0],
                right: textAlign === 'right'
                    ? pixel[0] + labelOffset[0]
                    : pixel[0] + labelOffset[0] + estimatedWidth,
                top: pixel[1] + labelOffset[1] - 14,
                bottom: pixel[1] + labelOffset[1] + 8,
            };
            if (
                box.right < 0
                || box.left > mapSurface.clientWidth
                || box.bottom < 0
                || box.top > mapSurface.clientHeight
                || occupied.some(function (other) {
                    return box.left < other.right
                        && box.right > other.left
                        && box.top < other.bottom
                        && box.bottom > other.top;
                })
            ) {
                return;
            }
            occupied.push(box);
            const feature = new Feature(new Point(coordinate));
            feature.set('labelText', labelText);
            feature.set('labelOffsetX', labelOffset[0]);
            feature.set('labelOffsetY', labelOffset[1]);
            feature.set('labelTextAlign', textAlign);
            labelSource.addFeature(feature);
        });
        document.documentElement.dataset.openlayersLabelCount = String(
            labelSource.getFeatures().length
        );
    }

    function rebuildFeatures() {
        const startedAt = performance.now();
        hidePointTooltip();
        lineSource.clear(true);
        leaderSource.clear(true);
        arrowSource.clear(true);
        pointSource.clear(true);
        labelSource.clear(true);
        targetSource.clear(true);
        state.labelCandidates = [];
        if (!state.result) {
            return;
        }
        const points = state.result.render.points;
        const mode = modeSelect.value;
        const grouped = new Map();
        points.forEach(function (point) {
            if (point.segmentIndex === null) {
                return;
            }
            if (!grouped.has(point.segmentIndex)) {
                grouped.set(point.segmentIndex, []);
            }
            grouped.get(point.segmentIndex).push(point);
        });
        if (mode === 'path') {
            grouped.forEach(function (segment) {
                const coordinates = segment.map(projectedCoordinate);
                if (coordinates.length >= 2) {
                    const line = new Feature(new LineString(coordinates));
                    line.set(
                        'lineConfidence',
                        segment[0].lineConfidence || 'verified'
                    );
                    lineSource.addFeature(line);
                    const maximumArrows = 12;
                    const stride = Math.max(
                        1,
                        Math.ceil((coordinates.length - 1) / maximumArrows)
                    );
                    for (let start = 0; start < coordinates.length - 1;
                        start += stride) {
                        const end = Math.min(
                            coordinates.length - 1,
                            start + stride
                        );
                        const arrowStart = Math.min(
                            end - 1,
                            start + Math.floor((end - start) / 2)
                        );
                        const previous = coordinates[arrowStart];
                        const current = coordinates[arrowStart + 1];
                        const direction = Math.atan2(
                            current[1] - previous[1],
                            current[0] - previous[0]
                        );
                        const midpoint = [
                            (previous[0] + current[0]) / 2,
                            (previous[1] + current[1]) / 2,
                        ];
                        const arrow = new Feature(new Point(midpoint));
                        arrow.set('direction', direction);
                        arrowSource.addFeature(arrow);
                    }
                }
            });
        }
        const visiblePoints = points;
        const labelBudget = mapSurface.clientWidth <= 560 ? 8 : 18;
        const labelStride = visiblePoints.length <= labelBudget
            ? 1
            : Math.ceil((visiblePoints.length - 1) / (labelBudget - 1));
        visiblePoints.forEach(function (point, index) {
            const showTimestampLabel = index % labelStride === 0
                || index === visiblePoints.length - 1;
            if (showTimestampLabel) {
                state.labelCandidates.push(point);
            }
            const feature = new Feature(new Point(projectedCoordinate(point)));
            feature.setProperties({
                observedAt: point.observedAt,
                observedDate: point.observedDate || null,
                horizontalAccuracyMeters: point.horizontalAccuracyMeters,
                sourceLabel: point.sourceLabel || null,
                sourceKey: point.sourceKey || null,
            });
            pointSource.addFeature(feature);
        });
        if (state.target) {
            const longitude = unwrappedLongitude(state.target.longitude);
            targetSource.addFeature(new Feature(new Point(fromLonLat([
                longitude,
                state.target.latitude,
            ]))));
        }
        applyStyles();
        rebuildOverviewDisplacements();
        rebuildTimestampLabels();
        state.lastProjectionMilliseconds = performance.now() - startedAt;
        document.documentElement.dataset.openlayersReady = 'true';
        document.documentElement.dataset.openlayersGeneration = String(state.generation);
        document.documentElement.dataset.openlayersPointCount = String(
            pointSource.getFeatures().length
        );
        document.documentElement.dataset.openlayersLineCount = String(
            lineSource.getFeatures().length
        );
        document.documentElement.dataset.openlayersArrowCount = String(
            arrowSource.getFeatures().length
        );
        document.documentElement.dataset.openlayersProjectionMilliseconds =
            state.lastProjectionMilliseconds.toFixed(3);
    }

    function fitPadding() {
        const surfaceRect = mapSurface.getBoundingClientRect();
        const controlsRect = root.querySelector('.ot-map__controls')
            .getBoundingClientRect();
        const navigationRect = root.querySelector('.ot-map__navigation')
            .getBoundingClientRect();
        const statusRect = statusOutput.getBoundingClientRect();
        const top = Math.ceil(Math.max(
            controlsRect.bottom,
            navigationRect.bottom,
            statusRect.bottom
        ) - surfaceRect.top + 12);
        const right = Math.ceil(surfaceRect.right - navigationRect.left + 12);
        const bottom = etaOutput.hidden
            ? 24
            : Math.ceil(surfaceRect.bottom - etaOutput.getBoundingClientRect().top + 12);
        return [top, right, bottom, 24];
    }

    function updateFitOcclusionDiagnostics() {
        const surfaceRect = mapSurface.getBoundingClientRect();
        const overlays = [
            root.querySelector('.ot-map__controls').getBoundingClientRect(),
            root.querySelector('.ot-map__navigation').getBoundingClientRect(),
            statusOutput.getBoundingClientRect(),
        ];
        let occluded = 0;
        pointSource.getFeatures().forEach(function (feature) {
            const pixel = map.getPixelFromCoordinate(
                feature.getGeometry().getCoordinates()
            );
            if (!pixel) {
                return;
            }
            const displacement = feature.get('markerDisplacement') || [0, 0];
            const x = surfaceRect.left + pixel[0] + displacement[0];
            const y = surfaceRect.top + pixel[1] - displacement[1];
            if (overlays.some(function (overlay) {
                return x >= overlay.left - 8
                    && x <= overlay.right + 8
                    && y >= overlay.top - 8
                    && y <= overlay.bottom + 8;
            })) {
                occluded += 1;
            }
        });
        document.documentElement.dataset.openlayersFitOverlayOcclusionCount =
            String(occluded);
    }

    function fitAll(options = {}) {
        if (!state.result || !state.result.fitBounds) {
            return;
        }
        const bounds = state.result.fitBounds;
        const east = bounds.crossesAntimeridian && bounds.east < bounds.west
            ? bounds.east + 360
            : bounds.east;
        const extent = transformExtent(
            [bounds.west, bounds.south, east, bounds.north],
            'EPSG:4326',
            'EPSG:3857',
            8
        );
        if (modeSelect.value === 'current-overview') {
            const minimumOverviewSpanMeters = 100;
            const centerX = (extent[0] + extent[2]) / 2;
            const centerY = (extent[1] + extent[3]) / 2;
            const halfWidth = Math.max(
                minimumOverviewSpanMeters / 2,
                (extent[2] - extent[0]) / 2
            );
            const halfHeight = Math.max(
                minimumOverviewSpanMeters / 2,
                (extent[3] - extent[1]) / 2
            );
            extent[0] = centerX - halfWidth;
            extent[1] = centerY - halfHeight;
            extent[2] = centerX + halfWidth;
            extent[3] = centerY + halfHeight;
            document.documentElement.dataset.openlayersMinimumOverviewFitMeters =
                String(minimumOverviewSpanMeters);
        } else {
            delete document.documentElement.dataset
                .openlayersMinimumOverviewFitMeters;
        }
        view.fit(extent, {
            size: map.getSize(),
            padding: fitPadding(),
            duration: 0,
            maxZoom: 18,
        });
        rebuildOverviewDisplacements();
        rebuildTimestampLabels();
        updateFitOcclusionDiagnostics();
        state.lastAction = 'fit-all';
        scheduleTileViewport({
            rearmMissingTiles: options.rearmMissingTiles === true,
        });
    }

    function clearTileViewportTimer() {
        if (state.tileViewportTimer !== null) {
            window.clearTimeout(state.tileViewportTimer);
            state.tileViewportTimer = null;
        }
    }

    function requestTileViewport(options = {}) {
        clearTileViewportTimer();
        if (
            !state.result
            || !state.pendingBasemap
            || state.pendingBasemap.enabled !== true
            || typeof requestAction !== 'function'
            || !map.getSize()
        ) {
            return;
        }
        if (state.tileGrid === null || !Number.isFinite(view.getResolution())) {
            return;
        }
        const zoom = state.tileGrid.getZForResolution(view.getResolution());
        document.documentElement.dataset.openlayersTileViewportZoom =
            String(zoom);
        const extent = transformExtent(
            view.calculateExtent(map.getSize()),
            'EPSG:3857',
            'EPSG:4326',
            8
        );
        const bounds = {
            west: Math.max(-180, Math.min(180, extent[0])),
            south: Math.max(-85.05112878, Math.min(85.05112878, extent[1])),
            east: Math.max(-180, Math.min(180, extent[2])),
            north: Math.max(-85.05112878, Math.min(85.05112878, extent[3])),
            crossesAntimeridian: false,
        };
        const fingerprint = state.generation + ':' + zoom + ':'
            + [bounds.west, bounds.south, bounds.east, bounds.north]
                .map(function (value) { return value.toFixed(5); })
                .join(':');
        const sameViewport = fingerprint === state.tileViewportFingerprint;
        let manualRearm = false;
        if (options.rearmMissingTiles === true && sameViewport) {
            const now = Date.now();
            if (
                state.tileViewportReady
                && state.tileViewportFailureCount > 0
                && state.tileManualRearmViewportGeneration
                    !== state.tileViewportGeneration
                && now - state.tileManualRearmLastAt
                    >= tileManualRearmCooldownMilliseconds
            ) {
                manualRearm = true;
                state.tileManualRearmViewportGeneration =
                    state.tileViewportGeneration;
                state.tileManualRearmLastAt = now;
                state.tileManualRearmCount += 1;
                document.documentElement.dataset.openlayersTileManualRearmCount =
                    String(state.tileManualRearmCount);
            } else {
                return;
            }
        }
        if (
            options.force !== true
            && !manualRearm
            && sameViewport
        ) {
            return;
        }
        state.tileViewportFingerprint = fingerprint;
        state.tileViewportGeneration += 1;
        state.tileViewportReady = false;
        if (options.preserveRetryCount === true || manualRearm) {
            clearTileRetryTimer();
            state.tileRetryViewportGeneration = state.tileViewportGeneration;
        } else {
            resetTileRetry();
        }
        document.documentElement.dataset.openlayersTileAuthState =
            'requesting-viewport';
        if (manualRearm) {
            state.lastAction = 'fit-all-tile-rearm';
        }
        try {
            requestAction('RequestTileViewport', JSON.stringify({
                requestGeneration: state.generation,
                viewportGeneration: state.tileViewportGeneration,
                clientSessionKey,
                zoom,
                bounds,
            }));
        } catch (error) {
            document.documentElement.dataset.openlayersTileAuthState =
                'viewport-failed';
        }
    }

    function scheduleTileViewport(options = {}) {
        clearTileViewportTimer();
        state.tileViewportTimer = window.setTimeout(function () {
            state.tileViewportTimer = null;
            requestTileViewport(options);
        }, 120);
    }

    function releaseCommittedPickerFocus(control) {
        window.requestAnimationFrame(function () {
            if (document.activeElement !== control) {
                return;
            }
            let pickerOpen = false;
            try {
                pickerOpen = control.matches(':open');
            } catch (error) {
                pickerOpen = false;
            }
            if (
                pickerOpen
                || window.matchMedia(
                    '(hover: none) and (pointer: coarse)'
                ).matches
            ) {
                control.blur();
            }
        });
    }

    function handleSelectionChange(event) {
        releaseCommittedPickerFocus(event.currentTarget);
        requestSelection();
    }

    function renderEta() {
        const overview = modeSelect.value === 'current-overview';
        etaOutput.hidden = !overview;
        if (!overview) {
            etaOutput.textContent = '';
            return;
        }
        if (state.selectedOverviewSourceKey === null) {
            etaOutput.textContent = 'Tap a position for ETA';
            return;
        }
        const available = state.etaEntries.filter(function (entry) {
            return entry.status === 'available' || entry.status === 'reached';
        });
        if (available.length === 0) {
            const selected = state.browserSources.find(function (source) {
                return source.sourceKey === state.selectedOverviewSourceKey;
            });
            const unavailable = state.etaEntries.find(function (entry) {
                return entry.sourceKey === state.selectedOverviewSourceKey;
            });
            const reason = unavailable ? unavailable.reason : null;
            const detail = {
                'position-stale': 'position too old',
                'current-position-stale': 'position too old',
                'position-unavailable': 'position unavailable',
                'outside-eta-radius': 'outside ETA range',
                'targets-unavailable': 'destinations unavailable',
                'source-evidence-unavailable': 'movement data unavailable',
                'speed-evidence-insufficient': 'speed data insufficient',
                'target-confidence-insufficient': 'destination unclear',
                'eta-out-of-range': 'ETA outside range',
            }[reason] || null;
            etaOutput.textContent = (selected ? selected.label + ': ' : '')
                + 'ETA unavailable'
                + (detail ? ' · ' + detail : '');
            return;
        }
        etaOutput.textContent = available.map(function (entry) {
            if (entry.status === 'reached') {
                return entry.sourceLabel + ': destination reached';
            }
            const minutes = Math.max(1, Math.round(entry.etaSeconds / 60));
            return entry.sourceLabel + ': ETA ≈ ' + minutes + ' min';
        }).join(' · ');
    }

    function rebuildSourceOptions(overview) {
        sourceSelect.replaceChildren();
        if (overview) {
            const option = document.createElement('option');
            option.value = 'current-overview';
            option.textContent = 'All';
            sourceSelect.appendChild(option);
            sourceSelect.value = option.value;
            return;
        }
        state.browserSources.forEach(function (source) {
            const option = document.createElement('option');
            option.value = source.sourceKey;
            option.textContent = source.label;
            sourceSelect.appendChild(option);
        });
        const configured = state.browserSources.some(function (source) {
            return source.sourceKey === state.pathSourceKey;
        });
        sourceSelect.value = configured
            ? state.pathSourceKey
            : state.browserSources[0].sourceKey;
        state.pathSourceKey = sourceSelect.value;
    }

    function applyModeControls() {
        const overview = modeSelect.value === 'current-overview';
        if (overview) {
            if (!sourceSelect.disabled && sourceSelect.value) {
                state.pathSourceKey = sourceSelect.value;
            }
            if (!daySelect.disabled && daySelect.value) {
                state.pathSelectedDate = daySelect.value;
            }
            rebuildSourceOptions(true);
            daySelect.value = state.todayDate;
        } else {
            rebuildSourceOptions(false);
            daySelect.value = state.pathSelectedDate || state.todayDate;
        }
        sourceSelect.disabled = overview;
        daySelect.disabled = overview;
        renderEta();
    }

    function requestSelection() {
        state.generation += 1;
        state.tileViewportReady = false;
        state.tileViewportFingerprint = null;
        resetTileRetry();
        clearTileViewportTimer();
        cancelTileRequests();
        if (baseLayer) {
            map.removeLayer(baseLayer);
            baseLayer = null;
        }
        if (state.requestNoticeTimer !== null) {
            window.clearTimeout(state.requestNoticeTimer);
            state.requestNoticeTimer = null;
        }
        state.requestStartedAt = performance.now();
        const overview = modeSelect.value === 'current-overview';
        if (!overview && !sourceSelect.disabled) {
            state.pathSourceKey = sourceSelect.value;
            state.pathSelectedDate = daySelect.value;
        }
        applyModeControls();
        if (!overview) {
            state.pathSourceKey = sourceSelect.value;
            state.pathSelectedDate = daySelect.value;
        }
        statusOutput.textContent = overview
            ? 'Loading positions…'
            : 'Loading day…';
        state.lastAction = 'selection-request';
        if (typeof requestAction !== 'function') {
            state.lastRequestDurationMilliseconds =
                performance.now() - state.requestStartedAt;
            state.requestStartedAt = null;
            statusOutput.textContent = 'Live data unavailable';
            state.lastAction = 'action-bridge-unavailable';
            return;
        }
        const requestedGeneration = state.generation;
        try {
            requestAction('SelectTrack', JSON.stringify({
                requestGeneration: state.generation,
                clientSessionKey: clientSessionKey,
                sourceKey: overview ? 'current-overview' : sourceSelect.value,
                selectedDate: overview ? state.todayDate : daySelect.value,
                viewMode: modeSelect.value,
                etaSourceKey: overview
                    ? state.selectedOverviewSourceKey
                    : null,
            }));
            state.requestNoticeTimer = window.setTimeout(function () {
                if (state.generation !== requestedGeneration) {
                    return;
                }
                state.requestNoticeTimer = null;
                state.slowRequestCount += 1;
                statusOutput.textContent = overview
                    ? 'Still loading positions…'
                    : 'Still loading selected day…';
                state.lastAction = 'selection-still-loading';
            }, 20000);
        } catch (error) {
            state.lastRequestDurationMilliseconds =
                performance.now() - state.requestStartedAt;
            state.requestStartedAt = null;
            statusOutput.textContent = 'Live data unavailable';
            state.lastAction = 'action-bridge-failed';
        }
    }

    function restoreSelectedOverviewTooltip() {
        if (state.selectedOverviewSourceKey === null) {
            return;
        }
        const feature = pointSource.getFeatures().find(function (candidate) {
            return candidate.get('sourceKey')
                === state.selectedOverviewSourceKey;
        });
        if (feature) {
            showPointTooltip(feature, true);
        }
    }

    window.handleOwnTracksOpenLayersMessage = function (message) {
        const payload = typeof message === 'string' ? JSON.parse(message) : message;
        if (payload.action === 'bootstrap') {
            state.pendingBasemap = payload.basemap;
            configureBasemap(payload.basemap, payload.tileAccess || null);
            state.browserSources = payload.sources.slice();
            state.pathSourceKey = payload.selectedSourceKey;
            state.pathSelectedDate = payload.selectedDate;
            state.todayDate = payload.maximumDate;
            daySelect.min = payload.minimumDate;
            daySelect.max = payload.maximumDate;
            applyModeControls();
            requestSelection();
            return;
        }
        if (payload.action === 'tileCapability') {
            if (
                payload.requestGeneration !== state.tileCapabilityGeneration
                || typeof payload.token !== 'string'
                || payload.token.length === 0
                || payload.token.length > 1024
                || !/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/.test(payload.token)
                || !Number.isInteger(payload.expiresAt)
                || payload.expiresAt * 1000 <= Date.now()
                || payload.expiresAt * 1000
                    > Date.now() + state.tileAccess.tokenTtlSeconds * 1000 + 5000
            ) {
                disableProtectedBasemap('rejected');
                return;
            }
            state.tileCapability = payload.token;
            state.tileCapabilityExpiresAt = payload.expiresAt;
            scheduleTileCapabilityRefresh();
            if (state.result === null || !state.tileViewportReady) {
                document.documentElement.dataset.openlayersTileAuthState =
                    state.result === null
                        ? 'ready-pending-fit'
                        : 'ready-pending-viewport';
                return;
            }
            configureBasemapWhenIdle();
            return;
        }
        if (payload.action === 'tileCapabilityError') {
            disableProtectedBasemap('failed');
            return;
        }
        if (payload.action === 'tileViewport') {
            if (
                payload.requestGeneration !== state.generation
                || payload.viewportGeneration !== state.tileViewportGeneration
            ) {
                return;
            }
            state.tileViewportAcceptedGeneration = payload.viewportGeneration;
            state.tileViewportReady = true;
            state.tileViewportFailureCount = 0;
            document.documentElement.dataset
                .openlayersTileViewportFailureCount = '0';
            if (state.tileRetryCount > 0) {
                document.documentElement.dataset.openlayersTileRetryState =
                    'viewport-refreshed';
            }
            configureBasemapWhenIdle();
            return;
        }
        if (
            payload.action === 'tileViewportError'
            && payload.requestGeneration === state.generation
            && payload.viewportGeneration === state.tileViewportGeneration
        ) {
            state.tileViewportReady = false;
            document.documentElement.dataset.openlayersTileAuthState =
                'viewport-rejected';
            return;
        }
        if (payload.action === 'configurationError') {
            if (state.requestNoticeTimer !== null) {
                window.clearTimeout(state.requestNoticeTimer);
                state.requestNoticeTimer = null;
            }
            state.lastRequestDurationMilliseconds = state.requestStartedAt === null
                ? 0
                : performance.now() - state.requestStartedAt;
            state.requestStartedAt = null;
            statusOutput.textContent = 'Map configuration unavailable';
            state.lastAction = 'configuration-error';
            return;
        }
        if (
            payload.action === 'trackError'
            && (payload.requestGeneration === null
                || payload.requestGeneration === state.generation)
        ) {
            if (state.requestNoticeTimer !== null) {
                window.clearTimeout(state.requestNoticeTimer);
                state.requestNoticeTimer = null;
            }
            state.lastRequestDurationMilliseconds = state.requestStartedAt === null
                ? 0
                : performance.now() - state.requestStartedAt;
            state.requestStartedAt = null;
            statusOutput.textContent = 'Selected day unavailable';
            state.lastAction = 'selection-error';
            return;
        }
        if (
            payload.action !== 'trackResult'
            || payload.requestGeneration !== state.generation
        ) {
            if (payload.action === 'trackResult') {
                state.lastAction = 'stale-result-discarded';
            }
            return;
        }
        if (state.requestNoticeTimer !== null) {
            window.clearTimeout(state.requestNoticeTimer);
            state.requestNoticeTimer = null;
        }
        state.lastRequestDurationMilliseconds = state.requestStartedAt === null
            ? 0
            : performance.now() - state.requestStartedAt;
        state.requestStartedAt = null;
        state.result = payload.result;
        state.target = payload.target;
        state.eta = payload.eta;
        state.etaEntries = Array.isArray(payload.etaEntries)
            ? payload.etaEntries
            : [];
        statusOutput.textContent = payload.viewMode === 'current-overview'
            ? payload.result.statistics.renderedPoints + ' positions'
            : payload.result.statistics.validObservations
                + ' valid · ' + payload.result.statistics.renderedPoints
                + ' rendered'
                + (payload.result.statistics.renderedUnverifiedPoints > 0
                    ? ' · accuracy unknown'
                    : '');
        renderEta();
        const etaRefresh = payload.viewMode === 'current-overview'
            && state.selectedOverviewSourceKey !== null;
        rebuildFeatures();
        if (etaRefresh) {
            restoreSelectedOverviewTooltip();
        } else {
            fitAll();
        }
        scheduleTileViewport();
    };
    window.handleMessage = window.handleOwnTracksOpenLayersMessage;

    map.on('pointermove', function (event) {
        if (
            state.tooltipPinned
            || event.dragging
            || !window.matchMedia('(hover: hover) and (pointer: fine)').matches
        ) {
            return;
        }
        const feature = pointFeatureAtPixel(event.pixel);
        if (!feature) {
            hidePointTooltip();
            return;
        }
        showPointTooltip(feature, false);
    });
    map.on('singleclick', function (event) {
        const feature = pointFeatureAtPixel(event.pixel);
        if (!feature) {
            hidePointTooltip();
            return;
        }
        showPointTooltip(feature, true);
        if (modeSelect.value === 'current-overview') {
            state.selectedOverviewSourceKey = feature.get('sourceKey');
            requestSelection();
        }
    });
    map.on('movestart', function () {
        hidePointTooltip();
        state.lastAction = 'pan-or-zoom';
    });
    map.on('moveend', function () {
        rebuildOverviewDisplacements();
        rebuildTimestampLabels();
        updateFitOcclusionDiagnostics();
        document.documentElement.dataset.openlayersRotation = String(
            view.getRotation()
        );
        scheduleTileViewport();
    });
    map.getViewport().addEventListener('pointerleave', function () {
        if (!state.tooltipPinned) {
            hidePointTooltip();
        }
    });
    mapSurface.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            hidePointTooltip();
        }
    });

    root.querySelector('[data-zoom-in]').addEventListener('click', function () {
        view.setZoom((view.getZoom() || 0) + 1);
        state.lastAction = 'zoom';
    });
    root.querySelector('[data-zoom-out]').addEventListener('click', function () {
        view.setZoom((view.getZoom() || 0) - 1);
        state.lastAction = 'zoom';
    });
    root.querySelector('[data-fit-all]').addEventListener('click', function () {
        fitAll({rearmMissingTiles: true});
    });
    sourceSelect.addEventListener('change', handleSelectionChange);
    daySelect.addEventListener('change', handleSelectionChange);
    modeSelect.addEventListener('change', function (event) {
        releaseCommittedPickerFocus(event.currentTarget);
        state.selectedOverviewSourceKey = null;
        requestSelection();
    });

    const resizeObserver = new ResizeObserver(function () {
        map.updateSize();
        rebuildFeatures();
        fitAll();
    });
    resizeObserver.observe(root);

    window.__ownTracksOpenLayersDiagnostics = {
        snapshot: function () {
            return {
                generation: state.generation,
                sourceKey: sourceSelect.value,
                selectedDate: daySelect.value,
                mode: modeSelect.value,
                zoom: view.getZoom(),
                resolution: view.getResolution(),
                rotation: view.getRotation(),
                pointFeatureCount: pointSource.getFeatures().length,
                timestampLabelCount: Number(
                    document.documentElement.dataset.openlayersLabelCount || 0
                ),
                tooltipState:
                    document.documentElement.dataset.openlayersTooltipState,
                lineFeatureCount: lineSource.getFeatures().length,
                directionArrowCount: arrowSource.getFeatures().length,
                timestampLabelCandidateCount: labelSource.getFeatures().length,
                fitObservationCount: state.result
                    ? state.result.statistics.fitObservationCount
                    : 0,
                renderedPointCount: state.result
                    ? state.result.statistics.renderedPoints
                    : 0,
                projectionMilliseconds: state.lastProjectionMilliseconds,
                lastRequestDurationMilliseconds:
                    state.lastRequestDurationMilliseconds,
                slowRequestCount: state.slowRequestCount,
                lastAction: state.lastAction,
                etaText: etaOutput.textContent,
                etaVisible: !etaOutput.hidden,
                width: mapSurface.clientWidth,
                height: mapSurface.clientHeight,
                layerCount: map.getLayers().getLength(),
                providerMode:
                    document.documentElement.dataset.openlayersProviderMode,
                tileLayerCount: baseLayer ? 1 : 0,
                tileAuthState:
                    document.documentElement.dataset.openlayersTileAuthState,
                tileQueueLength: state.tileQueue.length,
                activeTileRequestCount: state.activeTileRequests.size,
                tileRequestsStarted: state.tileRequestsStarted,
                tileRequestsSucceeded: state.tileRequestsSucceeded,
                tileRequestsFailed: state.tileRequestsFailed,
                tileMissingCount: state.tileMissingCount,
                tileViewportFailureCount: state.tileViewportFailureCount,
                tileFailureCounts: Object.assign({}, state.tileFailureCounts),
                tileRetryCount: state.tileRetryCount,
                tileRetryDrainWaitCount: state.tileRetryDrainWaitCount,
                tileBasemapDrainWaitCount: state.tileBasemapDrainWaitCount,
                tileRetryState:
                    document.documentElement.dataset.openlayersTileRetryState,
                tileManualRearmCount: state.tileManualRearmCount,
                tileCapabilityRefreshCount: state.tileCapabilityRefreshCount,
                tileViewportGeneration: state.tileViewportGeneration,
                tileViewportAcceptedGeneration:
                    state.tileViewportAcceptedGeneration,
                tileViewportReady: state.tileViewportReady,
                tileObjectUrlBalance:
                    state.tileObjectUrlsCreated - state.tileObjectUrlsRevoked,
                tileMaximumObservedConcurrency:
                    state.tileMaximumObservedConcurrency,
                displacedPointCount: Number(
                    document.documentElement.dataset
                        .openlayersDisplacedPointCount || 0
                ),
                fitOverlayOcclusionCount: Number(
                    document.documentElement.dataset
                        .openlayersFitOverlayOcclusionCount || 0
                ),
                selectedOverviewSourceKey: state.selectedOverviewSourceKey,
                sourceDisabled: sourceSelect.disabled,
                dayDisabled: daySelect.disabled,
            };
        },
    };
}());
