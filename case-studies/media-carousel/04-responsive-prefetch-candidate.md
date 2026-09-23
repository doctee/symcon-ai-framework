# Responsive navigation and prefetch candidate

Status: local candidate; not published or deployed.

The client previously requested the previous image before the next image while
rendering slots, even though the prefetch order specified next-first. It also
discarded a usable source immediately on a media update and forgot a manual
navigation intent when the target had not yet arrived. These paths can create
avoidable loading waits independently of actual server/network latency.

The candidate uses current/next/previous rendering order, fills both bounded
request slots without a 120 ms scheduling delay, retains stale frames during
refresh and resumes one queued manual navigation when its target becomes ready.
It keeps the two-request ceiling and three rendered image slots. Request IDs
and media generations reject foreign, late and superseded responses. Sequence
revision changes still clear the cache and queued navigation; this change does
not attempt to optimize rolling archive sequence replacement.

Arrow dimensions respond to the embedded viewport. Pointer events on the arrow
buttons do not also start a swipe/pointer capture. No server-side timer, camera
action, media write or new runtime storage is introduced.

## Verification

Run `node --test case-studies/media-carousel/tests/client-state.test.cjs`
(Node 22) and the existing PHP/fileset checks. The Node suite executes the actual
production closure with deterministic DOM, image and timer doubles. This is not
a physical Safari/iPhone rendering or performance acceptance test. Test small
tiles, delayed images, repeated updates and swipe/arrow interaction in the app
before declaring performance accepted.

## Optional local fit control

The accepted alternative is an optional `ShowFitToggle` icon button, disabled
by default. Its small 28-pixel visual has a 44-pixel touch target. Accessible
action labels distinguish showing the entire image from filling the image area.
The selection stays local to the current HTML view and survives image navigation;
a new view starts with the configured `FitMode`. It does not change server
configuration, persist image data or trigger an image request.

Automatic normal-cover/maximized-contain detection is not implemented.
The reviewed HTML-SDK documentation and the official VisualizationTypeTest,
HTMLVisuTest and HTMLVisuTestViewportOverride examples do not provide a verified
maximized-state callback or flag. Visualization type 2 enables native fullscreen
but does not itself distinguish the two rendering contexts inside the HTML.

A fixed viewport-size heuristic would also classify large ordinary tiles as
maximized, and the browser Fullscreen API is not equivalent to a native app
dialog. Neither is used as an undocumented replacement. The explicit control
works in compact and maximized views without assuming a host-context signal.

References:

- https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/html-sdk/
- https://github.com/symcon/SymconTest/tree/master/VisualizationTypeTest

Publication, package activation and installation-specific configuration changes
have not occurred in this candidate. Retained legacy objects are untouched.
