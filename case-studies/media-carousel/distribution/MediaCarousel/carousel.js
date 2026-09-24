(function () {
    'use strict';

    const MAX_PENDING_REQUESTS = 2;
    const MAX_RECEIPT_PROBES = 6;

    const carousel = document.getElementById('carousel');
    const track = document.getElementById('track');
    const previousImage = document.getElementById('previousImage');
    const currentImage = document.getElementById('currentImage');
    const nextImage = document.getElementById('nextImage');
    const previousButton = document.getElementById('previous');
    const nextButton = document.getElementById('next');
    const title = document.getElementById('title');
    const position = document.getElementById('position');
    const loading = document.getElementById('loading');
    const message = document.getElementById('message');
    const toast = document.getElementById('toast');
    const fitButton = document.getElementById('fitToggle');

    const state = {
        instanceID: 0,
        configurationRevision: '',
        items: [],
        settings: null,
        currentIndex: 0,
        sources: new Map(),
        readyRevisions: new Map(),
        pending: new Map(),
        receivingBundle: false,
        receiptProbes: new Map(),
        failures: new Map(),
        stale: new Set(),
        mediaGenerations: new Map(),
        navigation: null,
        fitMode: null,
        prefetchOrder: [],
        autoTimer: null,
        toastTimer: null,
        pauseUntil: 0,
        busy: false,
        renderGeneration: 0,
        pointerID: null,
        pointerStartX: 0,
        pointerStartTime: 0,
        pointerDeltaX: 0,
        lifecycleRenderFrame: null
    };

    // Bounded, view-local evidence only: no IDs, titles, image bytes, history,
    // storage writes or extra requests. Readable through the tile DOM in QA.
    const diagnostics = {
        version: 4, bootstraps: 0, batches: 0, requested: 0, accepted: 0, timeouts: 0,
        mediaErrors: 0, revisionRejected: 0, requestRejected: 0,
        invalidations: 0, superseded: 0, imageReady: 0, imageFailed: 0,
        lastRoundTripMs: 0, maxRoundTripMs: 0, lastPreparationMs: 0,
        maxPreparationMs: 0, lastImageReadyMs: 0, maxImageReadyMs: 0,
        lastSourceCharacters: 0,
        receiptRequested: 0, receipts: 0, receiptRejected: 0,
        lateReceipts: 0, lateProbeResponses: 0, reorderedReceipts: 0,
        pairedResponses: 0, receiptDispatchFailures: 0,
        lastReceiptRoundTripMs: 0, maxReceiptRoundTripMs: 0,
        lastPairedReceiptMs: 0, lastPairedAfterReceiptMs: 0,
        lastPairedRoundTripMs: 0, lastPairedPreparationMs: 0,
        lastPairedReceiptDispatchMs: 0
    };

    function publishDiagnostics() {
        carousel.dataset.loadDiagnostics = JSON.stringify(Object.assign({}, diagnostics, {
            pending: state.pending.size, cached: state.sources.size,
            ready: state.readyRevisions.size, currentIndex: state.currentIndex,
            itemCount: state.items.length
        }));
    }

    function countDiagnostic(name) {
        diagnostics[name] = Math.min(1000000000, diagnostics[name] + 1);
        publishDiagnostics();
    }

    function timeDiagnostic(name, value) {
        if (!Number.isFinite(value) || value < 0) return;
        const bounded = Math.min(3600000, Math.round(value));
        diagnostics['last' + name + 'Ms'] = bounded;
        diagnostics['max' + name + 'Ms'] = Math.max(diagnostics['max' + name + 'Ms'], bounded);
    }

    function localize(text) {
        return typeof translate === 'function' ? translate(text) : text;
    }

    function parseMessage(data) {
        if (typeof data === 'string') {
            return JSON.parse(data);
        }

        return data;
    }

    function wrapIndex(index) {
        const count = state.items.length;
        if (count === 0) {
            return 0;
        }

        return ((index % count) + count) % count;
    }

    function isNeighbour(index) {
        if (state.items.length < 2) {
            return index === state.currentIndex;
        }

        return index === state.currentIndex
            || index === wrapIndex(state.currentIndex - 1)
            || index === wrapIndex(state.currentIndex + 1);
    }

    function storageKey() {
        return 'saef-media-carousel:' + state.instanceID;
    }

    function positionStorageKey() {
        return storageKey() + ':position';
    }

    function restorePosition() {
        try {
            const stored = JSON.parse(localStorage.getItem(positionStorageKey()) || 'null');
            if (!stored || stored.configurationRevision !== state.configurationRevision) {
                return;
            }
            if (Number.isInteger(stored.index) && stored.index >= 0 && stored.index < state.items.length) {
                state.currentIndex = stored.index;
            }
        } catch (error) {
            // Cross-view position persistence is an optimisation and never authoritative.
        }
    }

    function restoreSessionSource() {
        try {
            const stored = JSON.parse(sessionStorage.getItem(storageKey()) || 'null');
            if (!stored || stored.configurationRevision !== state.configurationRevision) {
                return;
            }
            if (
                stored.index === state.currentIndex
                && stored.source
                && typeof stored.source.source === 'string'
                && typeof stored.source.contentRevision === 'string'
            ) {
                state.sources.set(state.currentIndex, {
                    source: stored.source.source,
                    contentRevision: stored.source.contentRevision,
                    preview: stored.source.preview === true
                });
                state.stale.add(state.currentIndex);
            }
        } catch (error) {
            // Session persistence is an optimisation and never authoritative.
        }
    }

    function restoreClientState() {
        restorePosition();
        restoreSessionSource();
    }

    function storeClientState() {
        try {
            localStorage.setItem(positionStorageKey(), JSON.stringify({
                configurationRevision: state.configurationRevision,
                index: state.currentIndex
            }));
        } catch (error) {
            // Cross-view position persistence is an optimisation and never authoritative.
        }

        try {
            const source = state.sources.get(state.currentIndex);
            const persistableSource = source && source.source.length <= 1_500_000 ? source : null;
            sessionStorage.setItem(storageKey(), JSON.stringify({
                configurationRevision: state.configurationRevision,
                index: state.currentIndex,
                source: persistableSource
            }));
        } catch (error) {
            // Storage quotas must not affect presentation.
        }
    }

    function clearPending() {
        state.pending.forEach(function (entry) {
            clearTimeout(entry.timer);
        });
        state.pending.clear();
    }

    function resetClientCache() {
        clearPending();
        state.sources.clear();
        state.readyRevisions.clear();
        state.failures.clear();
        state.stale.clear();
        state.mediaGenerations.clear();
        state.navigation = null;
        state.fitMode = null;
        state.busy = false;
        state.prefetchOrder = [];
        state.currentIndex = 0;
        state.renderGeneration += 1;
    }

    function applySettings() {
        const settings = state.settings;
        if (!settings.showFitToggle || state.fitMode === null) {
            state.fitMode = settings.fitMode;
        }
        updateFitPresentation();
        document.documentElement.style.setProperty(
            '--transition-ms',
            settings.transitionMilliseconds + 'ms'
        );
        title.hidden = !settings.showTitles;
        position.hidden = !settings.showDots;
        previousButton.hidden = !settings.showArrows || state.items.length < 2;
        nextButton.hidden = !settings.showArrows || state.items.length < 2;
    }

    function updateFitPresentation() {
        document.documentElement.style.setProperty('--fit-mode', state.fitMode);
        fitButton.hidden = !state.settings.showFitToggle;
        const fit = state.fitMode === 'contain';
        const label = localize(fit ? 'Fill image area' : 'Show entire image');
        fitButton.setAttribute('aria-label', label);
        fitButton.setAttribute('title', label);
        fitButton.dataset.fit = fit ? 'contain' : 'cover';
    }

    function toggleFit() {
        if (!state.settings || !state.settings.showFitToggle || state.items.length === 0) {
            return;
        }
        state.fitMode = state.fitMode === 'contain' ? 'cover' : 'contain';
        updateFitPresentation();
        pauseAutomaticAdvance();
        scheduleAutoAdvance();
    }

    function applyBootstrap(payload) {
        countDiagnostic('bootstraps');
        const revisionChanged = state.configurationRevision !== payload.configurationRevision;
        if (revisionChanged) {
            resetClientCache();
        }

        state.instanceID = payload.instanceID;
        state.configurationRevision = payload.configurationRevision;
        state.items = Array.isArray(payload.items) ? payload.items : [];
        state.settings = payload.settings;

        if (state.items.length === 0) {
            showConfigurationMessage(localize('No valid images configured'));
            return;
        }

        if (revisionChanged) {
            restoreClientState();
        } else if (state.currentIndex >= state.items.length) {
            state.currentIndex = 0;
        }

        applySettings();
        message.hidden = true;
        loading.hidden = false;
        buildPrefetchOrder();

        if (payload.initialMedia) {
            receiveMedia(payload.initialMedia, false);
        }

        requestMedia(state.currentIndex);
        renderSlots().then(function () {
            pumpPrefetch();
        });
    }

    function showConfigurationMessage(text) {
        clearTimeout(state.autoTimer);
        clearPending();
        loading.hidden = true;
        title.hidden = true;
        position.hidden = true;
        previousButton.hidden = true;
        nextButton.hidden = true;
        fitButton.hidden = true;
        message.textContent = text;
        message.hidden = false;
    }

    function receiveReceipt(payload) {
        const probe = state.receiptProbes.get(payload.requestID);
        if (payload.configurationRevision !== state.configurationRevision
            || !probe || probe.index !== payload.index
            || probe.configurationRevision !== payload.configurationRevision
            || probe.receiptAt !== null) {
            countDiagnostic('receiptRejected');
            return;
        }
        probe.receiptAt = performance.now();
        const pending = state.pending.get(payload.index);
        if (!pending || pending.requestID !== payload.requestID) {
            countDiagnostic('lateReceipts');
        }
        if (probe.responseAt !== null) countDiagnostic('reorderedReceipts');
        timeDiagnostic('ReceiptRoundTrip', probe.receiptAt - probe.startedAt);
        // Do not clear/extend the timeout, free a slot or change visible state.
        countDiagnostic('receipts');
    }

    function recordProbeResponse(payload) {
        const probe = state.receiptProbes.get(payload.requestID);
        if (!probe || probe.index !== payload.index
            || probe.configurationRevision !== payload.configurationRevision
            || probe.responseAt !== null) return;
        probe.responseAt = performance.now();
        const pending = state.pending.get(payload.index);
        if (!pending || pending.requestID !== payload.requestID) {
            countDiagnostic('lateProbeResponses');
        }
        if (payload.receiptDispatchCompleted === false) countDiagnostic('receiptDispatchFailures');
        if (probe.receiptAt === null
            || !Number.isFinite(payload.preparationMilliseconds)
            || payload.preparationMilliseconds < 0
            || !Number.isFinite(payload.receiptDispatchMilliseconds)
            || payload.receiptDispatchMilliseconds < 0) return;
        const bounded = value => Math.min(3600000, Math.round(value));
        diagnostics.lastPairedReceiptMs = bounded(probe.receiptAt - probe.startedAt);
        diagnostics.lastPairedAfterReceiptMs = bounded(probe.responseAt - probe.receiptAt);
        diagnostics.lastPairedRoundTripMs = bounded(probe.responseAt - probe.startedAt);
        diagnostics.lastPairedPreparationMs = bounded(payload.preparationMilliseconds);
        diagnostics.lastPairedReceiptDispatchMs = bounded(payload.receiptDispatchMilliseconds);
        countDiagnostic('pairedResponses');
    }

    function receiveMedia(payload, shouldRender) {
        if (payload.configurationRevision !== state.configurationRevision) {
            countDiagnostic('revisionRejected');
            return;
        }
        if (!Number.isInteger(payload.index) || payload.index < 0 || payload.index >= state.items.length) {
            return;
        }
        if (typeof payload.source !== 'string' || !payload.source.startsWith('data:image/')) {
            return;
        }

        const isPreview = payload.preview === true;
        const existing = state.sources.get(payload.index);
        if (isPreview && existing && existing.preview === false) {
            return;
        }

        if (!isPreview) recordProbeResponse(payload);
        const pending = state.pending.get(payload.index);
        // Responses are broadcast to every tile. Only our current request may
        // replace an image; a late response must not overwrite newer content.
        if (!isPreview && (!pending || pending.requestID !== payload.requestID)) {
            countDiagnostic('requestRejected');
            return;
        }
        if (pending && !isPreview) {
            clearTimeout(pending.timer);
            state.pending.delete(payload.index);
            timeDiagnostic('RoundTrip', performance.now() - pending.startedAt);
            timeDiagnostic('Preparation', payload.preparationMilliseconds);
            if (pending.generation !== (state.mediaGenerations.get(payload.index) || 0)) {
                countDiagnostic('superseded');
                pumpPrefetch();
                return;
            }
        }

        state.sources.set(payload.index, {
            source: payload.source,
            contentRevision: payload.contentRevision,
            preview: isPreview
        });
        if (!isPreview) {
            diagnostics.lastSourceCharacters = Math.min(1000000000, payload.source.length);
            countDiagnostic('accepted');
        }
        state.readyRevisions.delete(payload.index);
        state.failures.delete(payload.index);
        if (!isPreview) {
            state.stale.delete(payload.index);
        }

        buildPrefetchOrder();
        if (shouldRender !== false) {
            if (isNeighbour(payload.index) && !state.busy) {
                renderSlots().then(resumeNavigation);
            }
            pumpPrefetch();
        }
    }

    function invalidateMedia(payload) {
        if (payload.configurationRevision !== state.configurationRevision) {
            return;
        }
        if (!Number.isInteger(payload.index) || payload.index < 0 || payload.index >= state.items.length) {
            return;
        }

        // Keep the last usable frame while refreshing it in the background.
        state.stale.add(payload.index);
        state.mediaGenerations.set(payload.index, (state.mediaGenerations.get(payload.index) || 0) + 1);
        countDiagnostic('invalidations');
        state.failures.delete(payload.index);
        buildPrefetchOrder();
        pumpPrefetch();
        updatePresentationMetadata();
    }

    function receiveMediaError(payload) {
        let failedIndex = null;
        state.pending.forEach(function (entry, index) {
            if (entry.requestID === payload.requestID) {
                failedIndex = index;
            }
        });
        if (failedIndex === null) {
            return;
        }

        const pending = state.pending.get(failedIndex);
        if (pending) {
            clearTimeout(pending.timer);
            state.pending.delete(failedIndex);
        }
        countDiagnostic('mediaErrors');
        handleRequestFailure(failedIndex);
    }

    function requestID(index) {
        return 'mc_' + index + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }

    function needsMedia(index) {
        const existing = state.sources.get(index);
        return state.settings && index >= 0 && index < state.items.length
            && (!existing || existing.preview === true || state.stale.has(index))
            && !state.pending.has(index)
            && (state.failures.get(index) || 0) <= state.settings.retryCount;
    }

    function requestMedia(index) {
        // One SDK action in flight per view. Its two independently correlated
        // responses retain per-image invalidation, timeout and retry protection.
        if (state.receivingBundle || state.pending.size > 0 || !needsMedia(index)) {
            return;
        }

        if (typeof requestAction !== 'function') {
            showConfigurationMessage(localize('Image unavailable'));
            return;
        }

        const indices = [index];
        for (const candidate of state.prefetchOrder) {
            if (indices.length >= MAX_PENDING_REQUESTS) break;
            if (candidate !== index && needsMedia(candidate)) indices.push(candidate);
        }
        const requests = indices.map(prepareMediaRequest);
        countDiagnostic('batches');
        requestAction('LoadMediaBundle', JSON.stringify(requests));
    }

    function prepareMediaRequest(index) {
        const id = requestID(index);
        const timeout = window.setTimeout(function () {
            const current = state.pending.get(index);
            if (!current || current.requestID !== id) {
                return;
            }
            state.pending.delete(index);
            countDiagnostic('timeouts');
            handleRequestFailure(index);
        }, state.settings.loadTimeoutSeconds * 1000);

        const diagnosticReceipt = diagnostics.receiptRequested < MAX_RECEIPT_PROBES;
        state.pending.set(index, {
            requestID: id, timer: timeout,
            startedAt: performance.now(),
            generation: state.mediaGenerations.get(index) || 0
        });
        if (diagnosticReceipt) {
            state.receiptProbes.set(id, {
                index: index, configurationRevision: state.configurationRevision,
                startedAt: performance.now(), receiptAt: null, responseAt: null
            });
            countDiagnostic('receiptRequested');
        }
        countDiagnostic('requested');
        const request = {
            index: index,
            requestID: id,
            configurationRevision: state.configurationRevision
        };
        if (diagnosticReceipt) request.diagnosticReceipt = true;
        return request;
    }

    function handleRequestFailure(index) {
        const failures = (state.failures.get(index) || 0) + 1;
        state.failures.set(index, failures);

        if (failures <= state.settings.retryCount) {
            const revision = state.configurationRevision;
            window.setTimeout(function () {
                if (revision === state.configurationRevision) {
                    buildPrefetchOrder();
                    pumpPrefetch();
                }
            }, 300 * failures);
        } else {
            if (state.navigation && state.navigation.index === index) {
                state.navigation = null;
                scheduleAutoAdvance();
            }
            if (index === state.currentIndex || isNeighbour(index)) {
                showToast(localize('Image unavailable'));
            }
            pumpPrefetch();
        }
    }

    function buildPrefetchOrder() {
        const order = [];
        const add = function (index) {
            const wrapped = wrapIndex(index);
            if (!order.includes(wrapped)) {
                order.push(wrapped);
            }
        };

        if (state.navigation) {
            add(state.navigation.index);
        }
        add(state.currentIndex);
        if (state.items.length > 1) {
            add(state.currentIndex + 1);
            add(state.currentIndex - 1);
        }
        for (let offset = 2; offset < state.items.length; offset += 1) {
            add(state.currentIndex + offset);
        }

        state.prefetchOrder = order;
    }

    function pumpPrefetch() {
        if (state.receivingBundle || !state.settings || state.pending.size > 0) {
            return;
        }

        while (state.pending.size === 0) {
            const nextIndex = state.prefetchOrder.find(function (index) {
                const failures = state.failures.get(index) || 0;
                const source = state.sources.get(index);
                return (!source || source.preview === true || state.stale.has(index))
                    && !state.pending.has(index)
                    && failures <= state.settings.retryCount;
            });

            if (nextIndex === undefined) {
                break;
            }
            const previousCount = state.pending.size;
            requestMedia(nextIndex);
            if (state.pending.size <= previousCount) {
                break;
            }
        }
    }

    function renderForLifecycleChange() {
        if (state.lifecycleRenderFrame !== null) {
            return;
        }

        state.lifecycleRenderFrame = window.requestAnimationFrame(function () {
            state.lifecycleRenderFrame = null;
            centerTrack(false);
            if (!state.settings || state.items.length === 0 || document.hidden) {
                return;
            }
            renderSlots().then(function () {
                pumpPrefetch();
                scheduleAutoAdvance();
            });
        });
    }

    function waitForLoadedImage(index) {
        const entry = state.sources.get(index);
        if (!entry) {
            requestMedia(index);
            return Promise.resolve(false);
        }
        if (state.readyRevisions.get(index) === entry.contentRevision) {
            return Promise.resolve(true);
        }

        return new Promise(function (resolve) {
            const probe = new Image();
            const startedAt = performance.now();
            let settled = false;
            const finish = function (ready) {
                if (settled) {
                    return;
                }
                settled = true;
                clearTimeout(timer);
                if (ready) {
                    state.readyRevisions.set(index, entry.contentRevision);
                }
                timeDiagnostic('ImageReady', performance.now() - startedAt);
                countDiagnostic(ready ? 'imageReady' : 'imageFailed');
                resolve(ready);
            };
            const timer = window.setTimeout(function () {
                finish(false);
            }, state.settings.loadTimeoutSeconds * 1000);

            probe.onload = function () {
                finish(probe.naturalWidth > 0);
            };
            probe.onerror = function () {
                finish(false);
            };
            probe.src = entry.source;
            if (probe.complete && probe.naturalWidth > 0) {
                finish(true);
            }
        });
    }

    async function renderSlots() {
        if (!state.settings || state.items.length === 0) {
            return;
        }

        const generation = ++state.renderGeneration;
        const slotDefinitions = [
            {element: currentImage, index: state.currentIndex},
            {element: nextImage, index: wrapIndex(state.currentIndex + 1)},
            {element: previousImage, index: wrapIndex(state.currentIndex - 1)}
        ];

        for (const slot of slotDefinitions) {
            const ready = await waitForLoadedImage(slot.index);
            if (generation !== state.renderGeneration) {
                return;
            }

            const source = state.sources.get(slot.index);
            if (!ready || !source) {
                if (slot.element !== currentImage || currentImage.hidden) {
                    slot.element.hidden = true;
                }
                continue;
            }

            slot.element.src = source.source;
            slot.element.alt = state.items[slot.index].title || '';
            slot.element.dataset.index = String(slot.index);
            slot.element.dataset.revision = source.contentRevision;
            slot.element.hidden = false;
        }

        const currentSource = state.sources.get(state.currentIndex);
        const currentReady = currentSource
            && state.readyRevisions.get(state.currentIndex) === currentSource.contentRevision;
        if (currentReady) {
            loading.hidden = true;
            message.hidden = true;
            updatePresentationMetadata();
            storeClientState();
            if (currentSource.preview === true) {
                clearTimeout(state.autoTimer);
            } else {
                scheduleAutoAdvance();
            }
        }

        centerTrack(false);
        publishDiagnostics();
    }

    function updatePresentationMetadata() {
        const current = state.items[state.currentIndex];
        title.textContent = current ? current.title : '';
        if (state.stale.has(state.currentIndex)) {
            title.textContent += ' · ' + localize('Updating image');
        }
        title.hidden = !state.settings.showTitles || !title.textContent;

        position.replaceChildren();
        if (state.settings.showDots) {
            state.items.forEach(function (_, index) {
                const dot = document.createElement('span');
                dot.className = index === state.currentIndex ? 'dot current' : 'dot';
                position.appendChild(dot);
            });
        }
        position.hidden = !state.settings.showDots;
    }

    function trackWidth() {
        return Math.max(1, carousel.clientWidth);
    }

    function setTrackPosition(offset, animate) {
        track.style.transition = animate
            ? 'transform var(--transition-ms) cubic-bezier(0.22, 0.61, 0.36, 1)'
            : 'none';
        track.style.transform = 'translate3d(' + (-trackWidth() + offset) + 'px, 0, 0)';
    }

    function centerTrack(animate) {
        setTrackPosition(0, animate);
    }

    function waitForTransition() {
        return new Promise(function (resolve) {
            let settled = false;
            const finish = function () {
                if (settled) {
                    return;
                }
                settled = true;
                track.removeEventListener('transitionend', finish);
                resolve();
            };
            track.addEventListener('transitionend', finish, {once: true});
            window.setTimeout(finish, state.settings.transitionMilliseconds + 120);
        });
    }

    async function move(direction, manual, fromDrag) {
        if (state.busy) {
            return;
        }
        if (state.items.length < 2) {
            centerTrack(true);
            return;
        }

        state.busy = true;
        const revision = state.configurationRevision;
        const targetIndex = wrapIndex(state.currentIndex + direction);
        const ready = await waitForLoadedImage(targetIndex);
        if (revision !== state.configurationRevision) {
            return;
        }
        if (!ready) {
            state.busy = false;
            centerTrack(true);
            if (state.sources.has(targetIndex)) {
                // A received but undecodable source will not emit another media
                // response. Do not leave a navigation intent waiting forever.
                state.navigation = null;
                showToast(localize('Image unavailable'));
                if (manual) {
                    pauseAutomaticAdvance();
                }
                scheduleAutoAdvance();
                return;
            }
            if (manual) {
                state.navigation = {index: targetIndex, direction: direction};
                state.failures.delete(targetIndex);
                buildPrefetchOrder();
                pumpPrefetch();
                showToast(localize('Loading image'));
                pauseAutomaticAdvance();
            } else {
                scheduleAutoAdvance(1000);
            }
            return;
        }

        state.navigation = null;
        toast.hidden = true;
        clearTimeout(state.autoTimer);
        if (!fromDrag) {
            centerTrack(false);
        }

        window.requestAnimationFrame(function () {
            setTrackPosition(direction > 0 ? -trackWidth() : trackWidth(), true);
        });
        await waitForTransition();
        if (revision !== state.configurationRevision) {
            return;
        }

        state.currentIndex = targetIndex;
        state.pointerDeltaX = 0;
        if (manual) {
            pauseAutomaticAdvance();
        }

        track.style.transition = 'none';
        await renderSlots();
        centerTrack(false);
        state.busy = false;
        buildPrefetchOrder();
        pumpPrefetch();
        scheduleAutoAdvance();
    }

    function resumeNavigation() {
        if (state.navigation && !state.busy && state.sources.has(state.navigation.index)) {
            const direction = state.navigation.direction;
            state.navigation = null;
            move(direction, true, false);
        }
    }

    function pauseAutomaticAdvance() {
        state.pauseUntil = Date.now() + state.settings.pauseAfterInteractionSeconds * 1000;
        clearTimeout(state.autoTimer);
    }

    function scheduleAutoAdvance(delayOverride) {
        clearTimeout(state.autoTimer);
        if (
            !state.settings
            || !state.settings.autoLoop
            || state.items.length < 2
            || document.hidden
            || state.busy
            || state.navigation
        ) {
            return;
        }

        const normalDelay = state.settings.loopSeconds * 1000;
        const pauseDelay = Math.max(0, state.pauseUntil - Date.now());
        const delay = Math.max(delayOverride || normalDelay, pauseDelay);
        state.autoTimer = window.setTimeout(function () {
            move(1, false, false);
        }, delay);
    }

    function showToast(text) {
        clearTimeout(state.toastTimer);
        toast.textContent = text;
        toast.hidden = false;
        state.toastTimer = window.setTimeout(function () {
            toast.hidden = true;
        }, 1800);
    }

    function pointerDown(event) {
        if (event.target.closest && event.target.closest('button')) {
            return;
        }
        if (state.busy || state.items.length < 2 || (event.button !== undefined && event.button !== 0)) {
            return;
        }

        state.pointerID = event.pointerId;
        state.pointerStartX = event.clientX;
        state.pointerStartTime = performance.now();
        state.pointerDeltaX = 0;
        clearTimeout(state.autoTimer);
        carousel.setPointerCapture(event.pointerId);
        track.style.transition = 'none';
    }

    function pointerMove(event) {
        if (state.pointerID !== event.pointerId) {
            return;
        }

        const limit = trackWidth() * 0.92;
        state.pointerDeltaX = Math.max(
            -limit,
            Math.min(limit, event.clientX - state.pointerStartX)
        );
        setTrackPosition(state.pointerDeltaX, false);
    }

    function pointerUp(event) {
        if (state.pointerID !== event.pointerId) {
            return;
        }

        const elapsed = Math.max(1, performance.now() - state.pointerStartTime);
        const velocity = Math.abs(state.pointerDeltaX) / elapsed;
        const thresholdReached = Math.abs(state.pointerDeltaX) >= trackWidth() * 0.2;
        const velocityReached = velocity >= 0.35 && Math.abs(state.pointerDeltaX) >= 24;
        const direction = state.pointerDeltaX < 0 ? 1 : -1;

        state.pointerID = null;
        if (thresholdReached || velocityReached) {
            move(direction, true, true);
        } else {
            centerTrack(true);
            pauseAutomaticAdvance();
            scheduleAutoAdvance();
        }
    }

    function pointerCancel(event) {
        if (state.pointerID !== event.pointerId) {
            return;
        }
        state.pointerID = null;
        state.pointerDeltaX = 0;
        centerTrack(true);
        scheduleAutoAdvance();
    }

    window.handleMessage = function (data) {
        let payload;
        try {
            payload = parseMessage(data);
        } catch (error) {
            showConfigurationMessage(localize('No valid images configured'));
            return;
        }

        switch (payload.action) {
            case 'mediaBundle': {
                if (state.receivingBundle || !Array.isArray(payload.messages)
                    || payload.messages.length < 1 || payload.messages.length > 2
                    || payload.messages.some(entry => !entry || typeof entry !== 'object'
                        || !['media', 'mediaStarted', 'mediaError', 'bootstrap'].includes(entry.action))) return;
                state.receivingBundle = true;
                let render = false;
                try {
                    for (const entry of payload.messages) {
                        if (entry.action === 'media') {
                            receiveMedia(entry, false);
                            render = true;
                        } else {
                            window.handleMessage(entry);
                        }
                    }
                } finally {
                    state.receivingBundle = false;
                }
                if (render && !state.busy) renderSlots().then(resumeNavigation);
                pumpPrefetch();
                break;
            }
            case 'bootstrap':
                applyBootstrap(payload);
                break;
            case 'media':
                receiveMedia(payload);
                break;
            case 'mediaStarted':
                receiveReceipt(payload);
                break;
            case 'invalidate':
                invalidateMedia(payload);
                break;
            case 'mediaError':
                receiveMediaError(payload);
                break;
            case 'configurationError':
                showConfigurationMessage(payload.message || localize('No valid images configured'));
                break;
        }
    };

    previousButton.setAttribute('aria-label', localize('Previous image'));
    nextButton.setAttribute('aria-label', localize('Next image'));
    loading.setAttribute('aria-label', localize('Loading image'));

    previousButton.addEventListener('click', function () {
        move(-1, true, false);
    });
    nextButton.addEventListener('click', function () {
        move(1, true, false);
    });
    fitButton.addEventListener('click', toggleFit);
    carousel.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            move(-1, true, false);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            move(1, true, false);
        }
    });
    carousel.addEventListener('pointerdown', pointerDown);
    carousel.addEventListener('pointermove', pointerMove);
    carousel.addEventListener('pointerup', pointerUp);
    carousel.addEventListener('pointercancel', pointerCancel);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(state.autoTimer);
        } else {
            renderForLifecycleChange();
        }
    });

    window.addEventListener('pageshow', renderForLifecycleChange);
    window.addEventListener('focus', renderForLifecycleChange);

    const resizeObserver = new ResizeObserver(function () {
        renderForLifecycleChange();
    });
    resizeObserver.observe(carousel);
}());
