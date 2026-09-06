(function () {
    'use strict';

    const root = document.querySelector('[data-owntracks-map]');
    if (!root) {
        return;
    }

    const canvas = root.querySelector('[data-map-canvas]');
    const context = canvas.getContext('2d');
    const sourceSelect = root.querySelector('[data-source-select]');
    const daySelect = root.querySelector('[data-day-select]');
    const modeSelect = root.querySelector('[data-mode-select]');
    const etaOutput = root.querySelector('[data-eta]');
    const pointOutput = root.querySelector('[data-point-detail]');
    const statusOutput = root.querySelector('[data-status]');
    const pointers = new Map();
    const state = {
        sources: [],
        generation: 0,
        result: null,
        target: null,
        eta: null,
        centerX: 0,
        centerY: 0,
        scale: 1,
        longitudeAnchor: -Math.PI,
        width: 0,
        height: 0,
        pixelRatio: 1,
        hitPoints: [],
        pointerStart: null,
        pinchDistance: null,
        lastRenderMilliseconds: 0,
        lastAction: 'bootstrap',
    };

    function cssColor(name) {
        const style = getComputedStyle(canvas);
        const properties = {
            '--ot-text': 'color',
            '--ot-grid': 'borderTopColor',
            '--ot-grid-strong': 'borderRightColor',
            '--ot-track': 'borderBottomColor',
            '--ot-point': 'borderLeftColor',
            '--ot-target': 'outlineColor',
        };
        return style[properties[name]];
    }

    function mercatorY(latitude) {
        const limited = Math.max(-85.05112878, Math.min(85.05112878, latitude));
        const radians = limited * Math.PI / 180;
        return Math.log(Math.tan(Math.PI / 4 + radians / 2));
    }

    function latitudeFromMercator(value) {
        return (2 * Math.atan(Math.exp(value)) - Math.PI / 2) * 180 / Math.PI;
    }

    function unwrapLongitude(longitude) {
        let radians = longitude * Math.PI / 180;
        while (radians < state.longitudeAnchor) {
            radians += Math.PI * 2;
        }
        while (radians >= state.longitudeAnchor + Math.PI * 2) {
            radians -= Math.PI * 2;
        }
        return radians;
    }

    function coordinateToScreen(latitude, longitude) {
        return {
            x: state.width / 2 + (unwrapLongitude(longitude) - state.centerX) * state.scale,
            y: state.height / 2 - (mercatorY(latitude) - state.centerY) * state.scale,
        };
    }

    function screenToWorld(x, y) {
        return {
            x: state.centerX + (x - state.width / 2) / state.scale,
            y: state.centerY - (y - state.height / 2) / state.scale,
        };
    }

    function fitAll() {
        if (!state.result || !state.result.fitBounds) {
            return;
        }
        const bounds = state.result.fitBounds;
        const west = bounds.west * Math.PI / 180;
        let east = bounds.east * Math.PI / 180;
        if (bounds.crossesAntimeridian || east < west) {
            east += Math.PI * 2;
        }
        state.longitudeAnchor = west;
        const south = mercatorY(bounds.south);
        const north = mercatorY(bounds.north);
        const extentWidth = Math.max(east - west, 0.00001);
        const extentHeight = Math.max(north - south, 0.00001);
        const horizontalPadding = Math.min(80, state.width * 0.16);
        const topPadding = Math.min(140, state.height * 0.34);
        const bottomPadding = Math.min(78, state.height * 0.24);
        const usableWidth = Math.max(80, state.width - horizontalPadding * 2);
        const usableHeight = Math.max(80, state.height - topPadding - bottomPadding);
        state.scale = Math.max(
            40,
            Math.min(4000000, Math.min(usableWidth / extentWidth, usableHeight / extentHeight))
        );
        state.centerX = (west + east) / 2;
        state.centerY = (south + north) / 2
            + (topPadding - bottomPadding) / (2 * state.scale);
        state.lastAction = 'fit-all';
        draw();
    }

    function zoomAt(factor, x, y) {
        const before = screenToWorld(x, y);
        state.scale = Math.max(40, Math.min(4000000, state.scale * factor));
        const after = screenToWorld(x, y);
        state.centerX += before.x - after.x;
        state.centerY += before.y - after.y;
        state.lastAction = 'zoom';
        draw();
    }

    function gridStepDegrees() {
        const desiredRadians = 95 / state.scale;
        const desiredDegrees = desiredRadians * 180 / Math.PI;
        const steps = [
            0.0001, 0.0002, 0.0005, 0.001, 0.002, 0.005,
            0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10, 20, 45, 90,
        ];
        return steps.find(function (step) {
            return step >= desiredDegrees;
        }) || 90;
    }

    function drawGrid() {
        const step = gridStepDegrees();
        const topLeft = screenToWorld(0, 0);
        const bottomRight = screenToWorld(state.width, state.height);
        const westDegrees = topLeft.x * 180 / Math.PI;
        const eastDegrees = bottomRight.x * 180 / Math.PI;
        const northDegrees = latitudeFromMercator(topLeft.y);
        const southDegrees = latitudeFromMercator(bottomRight.y);
        context.lineWidth = 1;
        context.font = '11px system-ui, sans-serif';
        context.fillStyle = cssColor('--ot-text');

        for (
            let longitude = Math.floor(westDegrees / step) * step;
            longitude <= eastDegrees + step;
            longitude += step
        ) {
            const x = state.width / 2
                + (longitude * Math.PI / 180 - state.centerX) * state.scale;
            context.strokeStyle = Math.abs(longitude % (step * 5)) < step / 20
                ? cssColor('--ot-grid-strong')
                : cssColor('--ot-grid');
            context.beginPath();
            context.moveTo(x, 0);
            context.lineTo(x, state.height);
            context.stroke();
            context.fillText(longitude.toFixed(step < 0.01 ? 3 : 2) + '°', x + 3, state.height - 6);
        }
        for (
            let latitude = Math.floor(southDegrees / step) * step;
            latitude <= northDegrees + step;
            latitude += step
        ) {
            const y = state.height / 2
                - (mercatorY(latitude) - state.centerY) * state.scale;
            context.strokeStyle = Math.abs(latitude % (step * 5)) < step / 20
                ? cssColor('--ot-grid-strong')
                : cssColor('--ot-grid');
            context.beginPath();
            context.moveTo(0, y);
            context.lineTo(state.width, y);
            context.stroke();
            context.fillText(latitude.toFixed(step < 0.01 ? 3 : 2) + '°', 4, y - 4);
        }
    }

    function timestampLabel(timestamp) {
        return new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        }).format(new Date(timestamp * 1000));
    }

    function drawTrack() {
        state.hitPoints = [];
        if (!state.result || !state.result.render) {
            return;
        }
        const mode = modeSelect.value;
        const points = state.result.render.points;
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

        if (mode !== 'timestamp-points') {
            context.strokeStyle = cssColor('--ot-track');
            context.lineWidth = 3;
            context.lineJoin = 'round';
            context.lineCap = 'round';
            grouped.forEach(function (segment) {
                if (segment.length < 2) {
                    return;
                }
                context.beginPath();
                segment.forEach(function (point, index) {
                    const screen = coordinateToScreen(
                        point.latitudeDegrees,
                        point.longitudeDegrees
                    );
                    if (index === 0) {
                        context.moveTo(screen.x, screen.y);
                    } else {
                        context.lineTo(screen.x, screen.y);
                    }
                });
                context.stroke();
            });
        }

        const showAllPoints = mode !== 'segmented-line';
        const visiblePoints = showAllPoints
            ? points
            : points.slice(-1);
        visiblePoints.forEach(function (point) {
            const screen = coordinateToScreen(
                point.latitudeDegrees,
                point.longitudeDegrees
            );
            state.hitPoints.push({screen: screen, point: point});
            context.fillStyle = cssColor('--ot-point');
            context.beginPath();
            context.arc(screen.x, screen.y, 5, 0, Math.PI * 2);
            context.fill();
            if (mode !== 'segmented-line' && points.length <= 60) {
                context.font = '11px system-ui, sans-serif';
                context.fillStyle = cssColor('--ot-text');
                context.fillText(timestampLabel(point.observedAt), screen.x + 7, screen.y - 7);
            }
        });

        if (state.target) {
            const target = coordinateToScreen(
                state.target.latitude,
                state.target.longitude
            );
            context.strokeStyle = cssColor('--ot-target');
            context.lineWidth = 3;
            context.beginPath();
            context.arc(target.x, target.y, 9, 0, Math.PI * 2);
            context.moveTo(target.x - 13, target.y);
            context.lineTo(target.x + 13, target.y);
            context.moveTo(target.x, target.y - 13);
            context.lineTo(target.x, target.y + 13);
            context.stroke();
        }
    }

    function draw() {
        const startedAt = performance.now();
        context.clearRect(0, 0, state.width, state.height);
        drawGrid();
        drawTrack();
        state.lastRenderMilliseconds = performance.now() - startedAt;
        document.documentElement.dataset.rendererReady = state.result ? 'true' : 'false';
        document.documentElement.dataset.rendererGeneration = String(state.generation);
        document.documentElement.dataset.rendererPointCount = String(state.hitPoints.length);
    }

    function resize() {
        const bounds = root.getBoundingClientRect();
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        state.width = Math.max(1, Math.round(bounds.width));
        state.height = Math.max(1, Math.round(bounds.height));
        state.pixelRatio = ratio;
        canvas.width = Math.round(state.width * ratio);
        canvas.height = Math.round(state.height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        if (state.result) {
            fitAll();
        } else {
            draw();
        }
    }

    function renderEta() {
        if (!state.eta || state.eta.status === 'unavailable') {
            etaOutput.textContent = 'ETA unavailable';
            return;
        }
        if (state.eta.status === 'stale') {
            etaOutput.textContent = 'ETA stale';
            return;
        }
        if (state.eta.status === 'reached') {
            etaOutput.textContent = 'Destination reached';
            return;
        }
        const minutes = Math.max(1, Math.round(state.eta.etaSeconds / 60));
        etaOutput.textContent = state.eta.routeAware
            ? 'Route ETA ' + minutes + ' min'
            : 'Diagnostic ETA ≈ ' + minutes + ' min · no routing';
    }

    function requestTrack() {
        state.generation += 1;
        statusOutput.textContent = 'Loading synthetic day…';
        state.lastAction = 'selection-request';
        const payload = {
            requestGeneration: state.generation,
            sourceKey: sourceSelect.value,
            selectedDate: daySelect.value,
        };
        if (typeof window.requestAction === 'function') {
            window.requestAction('SelectTrack', JSON.stringify(payload));
        }
    }

    window.handleOwnTracksMapMessage = function (message) {
        const payload = typeof message === 'string' ? JSON.parse(message) : message;
        if (payload.action === 'bootstrap') {
            state.sources = payload.sources;
            sourceSelect.replaceChildren();
            payload.sources.forEach(function (source) {
                const option = document.createElement('option');
                option.value = source.sourceKey;
                option.textContent = source.label;
                sourceSelect.appendChild(option);
            });
            sourceSelect.value = payload.selectedSourceKey;
            daySelect.min = payload.minimumDate;
            daySelect.max = payload.maximumDate;
            daySelect.value = payload.selectedDate;
            requestTrack();
            return;
        }
        if (payload.action !== 'trackResult') {
            return;
        }
        if (payload.requestGeneration !== state.generation) {
            state.lastAction = 'stale-result-discarded';
            return;
        }
        state.result = payload.result;
        state.target = payload.target;
        state.eta = payload.eta;
        statusOutput.textContent = payload.result.statistics.validObservations
            + ' valid · ' + payload.result.statistics.renderedPoints + ' rendered';
        renderEta();
        fitAll();
    };
    window.handleMessage = window.handleOwnTracksMapMessage;

    function nearestPoint(x, y) {
        let nearest = null;
        let distance = 22;
        state.hitPoints.forEach(function (candidate) {
            const delta = Math.hypot(candidate.screen.x - x, candidate.screen.y - y);
            if (delta < distance) {
                distance = delta;
                nearest = candidate.point;
            }
        });
        return nearest;
    }

    function pointerDown(event) {
        if (event.button !== undefined && event.button !== 0) {
            return;
        }
        canvas.setPointerCapture(event.pointerId);
        pointers.set(event.pointerId, {x: event.clientX, y: event.clientY});
        state.pointerStart = {x: event.clientX, y: event.clientY, moved: false};
        if (pointers.size === 2) {
            const values = Array.from(pointers.values());
            state.pinchDistance = Math.hypot(
                values[1].x - values[0].x,
                values[1].y - values[0].y
            );
        }
    }

    function pointerMove(event) {
        const previous = pointers.get(event.pointerId);
        if (!previous) {
            return;
        }
        pointers.set(event.pointerId, {x: event.clientX, y: event.clientY});
        if (pointers.size === 2) {
            const values = Array.from(pointers.values());
            const distance = Math.hypot(
                values[1].x - values[0].x,
                values[1].y - values[0].y
            );
            const center = {
                x: (values[0].x + values[1].x) / 2
                    - root.getBoundingClientRect().left,
                y: (values[0].y + values[1].y) / 2
                    - root.getBoundingClientRect().top,
            };
            if (state.pinchDistance && state.pinchDistance > 0) {
                zoomAt(distance / state.pinchDistance, center.x, center.y);
            }
            state.pinchDistance = distance;
            return;
        }
        const deltaX = event.clientX - previous.x;
        const deltaY = event.clientY - previous.y;
        if (state.pointerStart && Math.hypot(
            event.clientX - state.pointerStart.x,
            event.clientY - state.pointerStart.y
        ) > 4) {
            state.pointerStart.moved = true;
        }
        state.centerX -= deltaX / state.scale;
        state.centerY += deltaY / state.scale;
        state.lastAction = 'pan';
        draw();
    }

    function pointerUp(event) {
        const local = root.getBoundingClientRect();
        if (state.pointerStart && !state.pointerStart.moved && pointers.size === 1) {
            const point = nearestPoint(
                event.clientX - local.left,
                event.clientY - local.top
            );
            pointOutput.textContent = point
                ? timestampLabel(point.observedAt)
                    + ' · accuracy '
                    + (point.horizontalAccuracyMeters === null
                        ? 'unknown'
                        : Math.round(point.horizontalAccuracyMeters) + ' m')
                : '';
        }
        pointers.delete(event.pointerId);
        state.pointerStart = null;
        if (pointers.size < 2) {
            state.pinchDistance = null;
        }
    }

    canvas.addEventListener('pointerdown', pointerDown);
    canvas.addEventListener('pointermove', pointerMove);
    canvas.addEventListener('pointerup', pointerUp);
    canvas.addEventListener('pointercancel', pointerUp);
    canvas.addEventListener('wheel', function (event) {
        event.preventDefault();
        const bounds = root.getBoundingClientRect();
        zoomAt(
            Math.exp(-event.deltaY * 0.0015),
            event.clientX - bounds.left,
            event.clientY - bounds.top
        );
    }, {passive: false});
    canvas.addEventListener('keydown', function (event) {
        const pan = 48 / state.scale;
        if (event.key === 'ArrowLeft') {
            state.centerX -= pan;
        } else if (event.key === 'ArrowRight') {
            state.centerX += pan;
        } else if (event.key === 'ArrowUp') {
            state.centerY += pan;
        } else if (event.key === 'ArrowDown') {
            state.centerY -= pan;
        } else if (event.key === '+' || event.key === '=') {
            zoomAt(1.35, state.width / 2, state.height / 2);
            event.preventDefault();
            return;
        } else if (event.key === '-') {
            zoomAt(1 / 1.35, state.width / 2, state.height / 2);
            event.preventDefault();
            return;
        } else if (event.key === 'Home') {
            fitAll();
            event.preventDefault();
            return;
        } else {
            return;
        }
        event.preventDefault();
        state.lastAction = 'keyboard-pan';
        draw();
    });
    root.querySelector('[data-zoom-in]').addEventListener('click', function () {
        zoomAt(1.35, state.width / 2, state.height / 2);
    });
    root.querySelector('[data-zoom-out]').addEventListener('click', function () {
        zoomAt(1 / 1.35, state.width / 2, state.height / 2);
    });
    root.querySelector('[data-fit-all]').addEventListener('click', fitAll);
    sourceSelect.addEventListener('change', requestTrack);
    daySelect.addEventListener('change', requestTrack);
    modeSelect.addEventListener('change', function () {
        state.lastAction = 'mode-change';
        draw();
    });

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(root);
    window.addEventListener('pageshow', resize);
    window.addEventListener('focus', draw);

    window.__ownTracksRendererDiagnostics = {
        snapshot: function () {
            return {
                generation: state.generation,
                sourceKey: sourceSelect.value,
                selectedDate: daySelect.value,
                mode: modeSelect.value,
                scale: state.scale,
                pointCount: state.hitPoints.length,
                fitObservationCount: state.result
                    ? state.result.statistics.fitObservationCount
                    : 0,
                renderedPointCount: state.result
                    ? state.result.statistics.renderedPoints
                    : 0,
                renderMilliseconds: state.lastRenderMilliseconds,
                lastAction: state.lastAction,
                etaText: etaOutput.textContent,
                width: state.width,
                height: state.height,
            };
        },
    };
}());
