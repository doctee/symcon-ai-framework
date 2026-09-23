'use strict';

// Exercise the production closure without exporting a test API to live clients.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function fixture() {
    const elements = new Map();
    const requests = [];
    const timers = new Map();
    let nextTimer = 0;
    let clock = 0;
    function element(id) {
        if (!elements.has(id)) {
            elements.set(id, {
                hidden: true, dataset: {}, style: {setProperty() {}}, clientWidth: 320,
                setAttribute() {}, addEventListener() {}, removeEventListener() {},
                replaceChildren() {}, appendChild() {}, textContent: ''
            });
        }
        return elements.get(id);
    }
    const storage = {getItem() { return null; }, setItem() {}};
    const context = {
        document: {
            getElementById: element, documentElement: element('root'), hidden: false,
            createElement: () => element('dot'), addEventListener() {}
        },
        localStorage: storage, sessionStorage: storage,
        setTimeout(fn, delay) { const id = ++nextTimer; timers.set(id, {fn, delay}); return id; },
        clearTimeout(id) { timers.delete(id); },
        requestAnimationFrame(fn) { fn(); return 1; }, addEventListener() {},
        requestAction(action, json) { requests.push(JSON.parse(json)); },
        Image: class { constructor() { this.complete = true; this.naturalWidth = 100; } },
        ResizeObserver: class { observe() {} }, performance: {now: () => clock}
    };
    context.window = context;
    const script = fs.readFileSync(path.join(__dirname, '../distribution/MediaCarousel/carousel.js'), 'utf8');
    vm.runInNewContext(script.replace('}());',
        'window.testAPI = {state, applyBootstrap, applySettings, toggleFit, receiveMedia, receiveReceipt, receiveMediaError, requestMedia, invalidateMedia, move, pumpPrefetch, renderSlots, pointerDown}; }());'), context);
    const api = context.testAPI;
    api.applyBootstrap({instanceID: 1, configurationRevision: 'one',
        items: [0, 1, 2, 3].map(index => ({mediaID: index + 1, title: String(index)})),
        settings: {fitMode: 'cover', autoLoop: false, showTitles: true, showArrows: true,
            showDots: true, loadTimeoutSeconds: 10, retryCount: 1, transitionMilliseconds: 0,
            pauseAfterInteractionSeconds: 1}});
    function respond(request, revision = 'image-' + request.index) {
        api.receiveMedia({...request, source: 'data:image/jpeg;base64,YQ==',
            contentRevision: revision, preview: false});
    }
    return {api, requests, timers, respond, element, advance(ms) { clock += ms; }};
}

async function flush() {
    for (let n = 0; n < 20; n += 1) await Promise.resolve();
}

test('refresh keeps a usable image and rejects a response superseded by another invalidation', async () => {
    const f = fixture();
    await flush();
    f.respond(f.requests.find(r => r.index === 0));
    await flush();
    // Free the second prefetch slot before refreshing.
    for (const request of [...f.requests]) f.respond(request);
    await flush();
    f.api.state.pending.forEach(entry => f.timers.delete(entry.timer));
    f.api.state.pending.clear();
    f.api.invalidateMedia({index: 0, configurationRevision: 'one'});
    assert.equal(f.api.state.sources.get(0).contentRevision, 'image-0');
    assert.equal(f.api.state.stale.has(0), true);
    const older = f.requests.findLast(r => r.index === 0);
    assert.equal(older.index, 0);
    f.api.invalidateMedia({index: 0, configurationRevision: 'one'});
    f.respond(older, 'superseded');
    assert.equal(f.api.state.sources.get(0).contentRevision, 'image-0');
    const fresh = f.requests.findLast(r => r.index === 0);
    assert.notEqual(fresh.requestID, older.requestID);
    f.respond(fresh, 'fresh');
    assert.equal(f.api.state.sources.get(0).contentRevision, 'fresh');
    assert.equal(f.api.state.stale.has(0), false);
});

test('manual navigation resumes when the requested image arrives, without a second click', async () => {
    const f = fixture();
    await flush();
    await f.api.move(1, true, false);
    assert.equal(f.api.state.navigation.index, 1);
    const target = f.requests.find(r => r.index === 1);
    assert.ok(target);
    f.respond(target);
    await flush();
    // Complete only the CSS transition fallback, not network/retry timers.
    for (const {fn, delay} of [...f.timers.values()]) if (delay === 120) fn();
    await flush();
    assert.equal(f.api.state.currentIndex, 1);
    assert.equal(f.api.state.navigation, null);
    assert.ok(f.api.state.pending.size <= 2);
});

test('foreign and timed-out responses cannot replace this client frame', async () => {
    const f = fixture();
    await flush();
    const request = f.requests[0];
    f.respond({...request, requestID: 'other-client'}, 'foreign');
    assert.equal(f.api.state.sources.has(0), false);
    assert.equal(f.api.state.pending.get(0).requestID, request.requestID);
    f.respond(request, 'own');
    f.respond(request, 'late');
    assert.equal(f.api.state.sources.get(0).contentRevision, 'own');
});

test('configuration changes cancel queued navigation and refresh state', async () => {
    const f = fixture();
    await flush();
    await f.api.move(1, true, false);
    f.api.state.stale.add(0);
    f.api.applyBootstrap({instanceID: 1, configurationRevision: 'two', items: [], settings: {}});
    assert.equal(f.api.state.navigation, null);
    assert.equal(f.api.state.stale.size, 0);
    assert.equal(f.api.state.pending.size, 0);
});

test('arrow pointerdown does not capture the pointer intended for a click', () => {
    const f = fixture();
    assert.doesNotThrow(() => f.api.pointerDown({target: {closest: () => ({})}}));
    assert.equal(f.api.state.pointerID, null);
});

test('render requests cannot bypass the exhausted retry budget', async () => {
    const f = fixture();
    await flush();
    const first = f.requests.find(r => r.index === 0);
    f.api.receiveMediaError({requestID: first.requestID});
    for (const {fn, delay} of [...f.timers.values()]) if (delay === 300) fn();
    const second = f.requests.findLast(r => r.index === 0);
    assert.notEqual(first.requestID, second.requestID);
    f.api.receiveMediaError({requestID: second.requestID});
    const count = f.requests.filter(r => r.index === 0).length;
    f.api.requestMedia(0);
    await f.api.renderSlots();
    assert.equal(f.requests.filter(r => r.index === 0).length, count);
    assert.equal(count, 2);
});

test('prefetch starts current and next first and never exceeds two outstanding requests', async () => {
    const f = fixture();
    await flush();
    assert.deepEqual(f.requests.map(r => r.index), [0, 1]);
    const served = new Set();
    for (let n = 0; n < 8; n += 1) {
        assert.ok(f.api.state.pending.size <= 2);
        const request = f.requests.find(r => !served.has(r.requestID));
        if (!request) break;
        served.add(request.requestID);
        f.respond(request);
        await flush();
    }
    assert.equal(f.api.state.sources.size, 4);
    assert.equal(f.api.state.pending.size, 0);
});

test('fit toggle is opt-in and changes presentation without requesting another image or changing index', async () => {
    const f = fixture();
    await flush();
    assert.equal(f.element('fitToggle').hidden, true);
    f.api.toggleFit();
    assert.equal(f.api.state.fitMode, 'cover');
    f.api.state.settings.showFitToggle = true;
    f.api.applySettings();
    const requests = f.requests.length;
    f.api.toggleFit();
    assert.equal(f.element('fitToggle').hidden, false);
    assert.equal(f.element('fitToggle').dataset.fit, 'contain');
    assert.equal(f.api.state.currentIndex, 0);
    assert.equal(f.requests.length, requests);
    f.api.applySettings();
    assert.equal(f.api.state.fitMode, 'contain');
    f.api.toggleFit();
    assert.equal(f.api.state.fitMode, 'cover');
    f.api.state.settings.showFitToggle = false;
    f.api.applySettings();
    assert.equal(f.element('fitToggle').hidden, true);
});

test('view diagnostics separate response and preparation timing without disclosing content or adding requests', async () => {
    const f = fixture();
    await flush();
    assert.equal(f.requests.length, 2);
    const request = f.requests[0];
    f.advance(1200);
    f.api.receiveMedia({...request, source: 'data:image/jpeg;base64,cHJpdmF0ZQ==',
        contentRevision: 'private-revision', preparationMilliseconds: 37, preview: false});
    await flush();
    const text = f.element('carousel').dataset.loadDiagnostics;
    const d = JSON.parse(text);
    assert.equal(d.accepted, 1);
    assert.equal(d.lastRoundTripMs, 1200);
    assert.equal(d.lastPreparationMs, 37);
    assert.ok(d.imageReady >= 1);
    assert.equal(d.lastImageReadyMs, 0);
    assert.equal(d.pending, f.api.state.pending.size);
    assert.ok(text.length < 1200);
    for (const forbidden of ['private', 'base64', request.requestID, 'mediaID', 'instanceID', 'title']) {
        assert.equal(text.includes(forbidden), false);
    }
    // One accepted image frees precisely one normal prefetch slot.
    assert.equal(f.requests.length, 3);
});

test('view diagnostics distinguish rejected, timed out and failed requests', async () => {
    const f = fixture();
    await flush();
    const request = f.requests[0];
    f.respond({...request, configurationRevision: 'obsolete'});
    f.respond({...request, requestID: 'foreign'});
    f.timers.get(f.api.state.pending.get(request.index).timer).fn();
    f.respond(request);
    f.api.receiveMediaError({requestID: f.requests[1].requestID});
    const d = JSON.parse(f.element('carousel').dataset.loadDiagnostics);
    assert.equal(d.revisionRejected, 1);
    assert.equal(d.requestRejected, 2);
    assert.equal(d.timeouts, 1);
    assert.equal(d.mediaErrors, 1);
    assert.equal(d.accepted, 0);
});

test('receipt pairs use one request and do not change timeout or pending slots', async () => {
    const f = fixture();
    await flush();
    const request = f.requests[0];
    assert.equal(request.diagnosticReceipt, true);
    const timer = f.api.state.pending.get(request.index).timer;
    f.advance(100);
    f.api.receiveReceipt(request);
    assert.equal(f.api.state.pending.get(request.index).timer, timer);
    assert.equal(f.api.state.pending.size, 2);
    assert.equal(f.requests.length, 2);
    f.advance(600);
    f.api.receiveMedia({...request, source: 'data:image/jpeg;base64,YQ==',
        contentRevision: 'one', preview: false, preparationMilliseconds: 200,
        receiptDispatchMilliseconds: 2, receiptDispatchCompleted: true});
    const d = JSON.parse(f.element('carousel').dataset.loadDiagnostics);
    assert.equal(d.receipts, 1);
    assert.equal(d.pairedResponses, 1);
    assert.equal(d.lastPairedReceiptMs, 100);
    assert.equal(d.lastPairedAfterReceiptMs, 600);
    assert.equal(d.lastPairedRoundTripMs, 700);
    assert.equal(d.lastPairedPreparationMs, 200);
    assert.equal(d.lastPairedReceiptDispatchMs, 2);
});

test('foreign duplicate obsolete and late receipts are inert', async () => {
    const f = fixture();
    await flush();
    const request = f.requests[0];
    f.api.receiveReceipt({...request, requestID: 'foreign'});
    f.api.receiveReceipt({...request, configurationRevision: 'old'});
    f.advance(10);
    f.api.receiveReceipt(request);
    f.advance(20);
    f.api.receiveReceipt(request);
    assert.equal(f.api.state.pending.get(request.index).receiptAt, 10);
    f.timers.get(f.api.state.pending.get(request.index).timer).fn();
    f.api.receiveReceipt(request);
    const d = JSON.parse(f.element('carousel').dataset.loadDiagnostics);
    assert.equal(d.receipts, 1);
    assert.equal(d.receiptRejected, 4);
    assert.equal(d.timeouts, 1);
});

test('receipt probes stop after six normal requests even across bootstrap changes', async () => {
    const f = fixture();
    await flush();
    for (let n = 0; n < 12; n += 1) {
        const request = f.requests.find(r => f.api.state.pending.get(r.index)?.requestID === r.requestID);
        f.respond(request);
        await flush();
        f.api.invalidateMedia({index: request.index, configurationRevision: 'one'});
    }
    const budget = f.requests.filter(r => r.diagnosticReceipt === true).length;
    assert.equal(budget, 6);
    assert.ok(f.requests.length > 6);
    f.api.applyBootstrap({instanceID: 1, configurationRevision: 'two',
        items: [{mediaID: 1, title: 'one'}], settings: {...f.api.state.settings}});
    await flush();
    assert.equal(f.requests.filter(r => r.diagnosticReceipt === true).length, 6);
});

test('missing receipt does not block media or fabricate a paired timing', async () => {
    const f = fixture();
    await flush();
    f.api.receiveMedia({...f.requests[0], source: 'data:image/jpeg;base64,YQ==',
        contentRevision: 'one', preview: false, preparationMilliseconds: 20,
        receiptDispatchMilliseconds: 1, receiptDispatchCompleted: false});
    const d = JSON.parse(f.element('carousel').dataset.loadDiagnostics);
    assert.equal(d.accepted, 1);
    assert.equal(d.pairedResponses, 0);
    assert.equal(d.receiptDispatchFailures, 1);
});
