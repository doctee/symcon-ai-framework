(function () {
    'use strict';

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

    const state = {
        instanceID: 0,
        configurationRevision: '',
        items: [],
        settings: null,
        currentIndex: 0,
        sources: new Map(),
        readyRevisions: new Map(),
        pending: new Map(),
        failures: new Map(),
        prefetchOrder: [],
        autoTimer: null,
        toastTimer: null,
        pauseUntil: 0,
        busy: false,
        renderGeneration: 0,
        pointerID: null,
        pointerStartX: 0,
        pointerStartTime: 0,
        pointerDeltaX: 0
    };

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

    function restoreSession() {
        try {
            const stored = JSON.parse(sessionStorage.getItem(storageKey()) || 'null');
            if (!stored || stored.configurationRevision !== state.configurationRevision) {
                return;
            }
            if (Number.isInteger(stored.index) && stored.index >= 0 && stored.index < state.items.length) {
                state.currentIndex = stored.index;
            }
            if (
                stored.source
                && typeof stored.source.source === 'string'
                && typeof stored.source.contentRevision === 'string'
            ) {
                state.sources.set(state.currentIndex, stored.source);
            }
        } catch (error) {
            // Session persistence is an optimisation and never authoritative.
        }
    }

    function storeSession() {
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
        state.prefetchOrder = [];
        state.currentIndex = 0;
        state.renderGeneration += 1;
    }

    function applySettings() {
        const settings = state.settings;
        document.documentElement.style.setProperty('--fit-mode', settings.fitMode);
        document.documentElement.style.setProperty(
            '--transition-ms',
            settings.transitionMilliseconds + 'ms'
        );
        title.hidden = !settings.showTitles;
        position.hidden = !settings.showDots;
        previousButton.hidden = !settings.showArrows || state.items.length < 2;
        nextButton.hidden = !settings.showArrows || state.items.length < 2;
    }

    function applyBootstrap(payload) {
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
            restoreSession();
        } else if (state.currentIndex >= state.items.length) {
            state.currentIndex = 0;
        }

        applySettings();
        message.hidden = true;
        loading.hidden = false;
        buildPrefetchOrder();

        if (payload.initialMedia) {
            receiveMedia(payload.initialMedia);
        } else {
            requestMedia(state.currentIndex);
        }

        renderSlots();
        pumpPrefetch();
    }

    function showConfigurationMessage(text) {
        clearTimeout(state.autoTimer);
        clearPending();
        loading.hidden = true;
        title.hidden = true;
        position.hidden = true;
        previousButton.hidden = true;
        nextButton.hidden = true;
        message.textContent = text;
        message.hidden = false;
    }

    function receiveMedia(payload) {
        if (payload.configurationRevision !== state.configurationRevision) {
            return;
        }
        if (!Number.isInteger(payload.index) || payload.index < 0 || payload.index >= state.items.length) {
            return;
        }
        if (typeof payload.source !== 'string' || !payload.source.startsWith('data:image/')) {
            return;
        }

        const pending = state.pending.get(payload.index);
        if (pending) {
            clearTimeout(pending.timer);
            state.pending.delete(payload.index);
        }

        state.sources.set(payload.index, {
            source: payload.source,
            contentRevision: payload.contentRevision
        });
        state.readyRevisions.delete(payload.index);
        state.failures.delete(payload.index);

        if (isNeighbour(payload.index)) {
            renderSlots();
        }

        buildPrefetchOrder();
        pumpPrefetch();
    }

    function invalidateMedia(payload) {
        if (payload.configurationRevision !== state.configurationRevision) {
            return;
        }
        if (!Number.isInteger(payload.index) || payload.index < 0 || payload.index >= state.items.length) {
            return;
        }

        state.sources.delete(payload.index);
        state.readyRevisions.delete(payload.index);
        state.failures.delete(payload.index);
        requestMedia(payload.index);
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
        handleRequestFailure(failedIndex);
    }

    function requestID(index) {
        return 'mc_' + index + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }

    function requestMedia(index) {
        if (
            !state.settings
            || state.sources.has(index)
            || state.pending.has(index)
            || index < 0
            || index >= state.items.length
        ) {
            return;
        }

        if (typeof requestAction !== 'function') {
            showConfigurationMessage(localize('Image unavailable'));
            return;
        }

        const id = requestID(index);
        const timeout = window.setTimeout(function () {
            const current = state.pending.get(index);
            if (!current || current.requestID !== id) {
                return;
            }
            state.pending.delete(index);
            handleRequestFailure(index);
        }, state.settings.loadTimeoutSeconds * 1000);

        state.pending.set(index, {requestID: id, timer: timeout});
        requestAction('LoadMedia', JSON.stringify({index: index, requestID: id}));
    }

    function handleRequestFailure(index) {
        const failures = (state.failures.get(index) || 0) + 1;
        state.failures.set(index, failures);

        if (failures <= state.settings.retryCount) {
            window.setTimeout(function () {
                requestMedia(index);
            }, 300 * failures);
        } else {
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
        if (!state.settings || state.pending.size >= 2) {
            return;
        }

        const nextIndex = state.prefetchOrder.find(function (index) {
            const failures = state.failures.get(index) || 0;
            return !state.sources.has(index)
                && !state.pending.has(index)
                && failures <= state.settings.retryCount;
        });

        if (nextIndex !== undefined) {
            requestMedia(nextIndex);
            if (state.pending.size < 2) {
                window.setTimeout(pumpPrefetch, 120);
            }
        }
    }

    function waitForDecodedImage(index) {
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
                resolve(ready);
            };
            const decode = function () {
                if (typeof probe.decode === 'function') {
                    probe.decode().then(function () {
                        finish(true);
                    }).catch(function () {
                        finish(false);
                    });
                } else {
                    finish(probe.naturalWidth > 0);
                }
            };
            const timer = window.setTimeout(function () {
                finish(false);
            }, state.settings.loadTimeoutSeconds * 1000);

            probe.onload = decode;
            probe.onerror = function () {
                finish(false);
            };
            probe.src = entry.source;
            if (probe.complete && probe.naturalWidth > 0) {
                decode();
            }
        });
    }

    async function renderSlots() {
        if (!state.settings || state.items.length === 0) {
            return;
        }

        const generation = ++state.renderGeneration;
        const slotDefinitions = [
            {element: previousImage, index: wrapIndex(state.currentIndex - 1)},
            {element: currentImage, index: state.currentIndex},
            {element: nextImage, index: wrapIndex(state.currentIndex + 1)}
        ];

        const prepared = await Promise.all(slotDefinitions.map(async function (slot) {
            const ready = await waitForDecodedImage(slot.index);
            return {slot: slot, ready: ready};
        }));
        if (generation !== state.renderGeneration) {
            return;
        }

        prepared.forEach(function (result) {
            const source = state.sources.get(result.slot.index);
            if (!result.ready || !source) {
                if (result.slot.element !== currentImage || currentImage.hidden) {
                    result.slot.element.hidden = true;
                }
                return;
            }

            result.slot.element.src = source.source;
            result.slot.element.alt = state.items[result.slot.index].title || '';
            result.slot.element.dataset.index = String(result.slot.index);
            result.slot.element.dataset.revision = source.contentRevision;
            result.slot.element.hidden = false;
        });

        const currentReady = state.readyRevisions.get(state.currentIndex)
            === (state.sources.get(state.currentIndex) || {}).contentRevision;
        if (currentReady) {
            loading.hidden = true;
            message.hidden = true;
            updatePresentationMetadata();
            storeSession();
            scheduleAutoAdvance();
        }

        centerTrack(false);
    }

    function updatePresentationMetadata() {
        const current = state.items[state.currentIndex];
        title.textContent = current ? current.title : '';
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
        const targetIndex = wrapIndex(state.currentIndex + direction);
        const ready = await waitForDecodedImage(targetIndex);
        if (!ready) {
            state.busy = false;
            centerTrack(true);
            requestMedia(targetIndex);
            if (manual) {
                showToast(localize('Loading image'));
                pauseAutomaticAdvance();
            } else {
                scheduleAutoAdvance(1000);
            }
            return;
        }

        clearTimeout(state.autoTimer);
        if (!fromDrag) {
            centerTrack(false);
        }

        window.requestAnimationFrame(function () {
            setTrackPosition(direction > 0 ? -trackWidth() : trackWidth(), true);
        });
        await waitForTransition();

        state.currentIndex = targetIndex;
        state.pointerDeltaX = 0;
        if (manual) {
            pauseAutomaticAdvance();
        }

        track.style.transition = 'none';
        await renderSlots();
        centerTrack(false);
        state.busy = false;
        scheduleAutoAdvance();
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
            case 'bootstrap':
                applyBootstrap(payload);
                break;
            case 'media':
                receiveMedia(payload);
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
            scheduleAutoAdvance();
        }
    });

    const resizeObserver = new ResizeObserver(function () {
        centerTrack(false);
    });
    resizeObserver.observe(carousel);
}());
