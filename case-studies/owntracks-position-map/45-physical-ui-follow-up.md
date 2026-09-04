# Physical UI Follow-up

**Status:** Repository implementation, automated checks, responsive
internal-browser acceptance and exact-package live activation complete;
physical acceptance pending

**Date:** 2026-09-01

## Physical Feedback

Physical use of the active package exposed seven presentation and interaction
issues:

1. direction markers looked detached from the path and used the point color;
2. close overview positions were displaced too far and their leaders were hard
   to see;
3. fit-all needed an explicit proof that no current point sits below the
   selection or navigation overlays;
4. the selection panel remained too tall and its field geometry was unsettled;
5. one zoom operation temporarily showed missing tiles;
6. overview ETA had no clear source ownership; and
7. a current position from another day showed only its time.

## Repository Correction

Path arrows are now compact arrow glyphs in the exact path color. Their
positions are selected between adjacent observations inside each bounded
interval, and the arrow layer sits above the point layer. They therefore remain
part of the line instead of appearing as separate orange marker triangles.

Close current positions use a nine-pixel radial displacement instead of
fourteen pixels. A solid two-pixel line-color leader connects every displaced
marker to its true projected WGS84 coordinate. Labels retain enough independent
vertical spacing for all three names to remain visible. This is presentation
only; fitting, tooltips, distance and ETA continue to use the unmodified
geodetic coordinate.

Fit padding is now derived from the measured selection and navigation overlay
rectangles. A diagnostic counts marker/overlay intersections after fitting and
after each move. Labels choose left or right alignment at the viewport edge so
the third overview label is not clipped.

The selection overlay is reduced to a 300-pixel desktop/tablet width and a
bounded 304-pixel compact width. Its field columns follow their content, field
height is 26 pixels in the synthetic desktop viewport, panel padding and label
gap are reduced, and the compact layout reserves the right-side host-control
band. The navigation control is reduced independently while keeping `Fit all`
on one line.

## Source-selected ETA

The overview performs no archive read before a marker is selected. Tapping a
current marker sends only that configured source key back through the existing
bounded selection action. The runtime validates the key against the three
configured sources and performs at most one 30-minute ETA evidence read.

The panel initially says `Tap a position for ETA`. After selection it reports
either the source-labelled ETA or a source-labelled unavailable state. Stale
positions, missing targets and the strict 100-kilometre boundary remain
fail-closed. `Path` still performs no activity or ETA read.

## Date And Tile Behavior

Every overview point carries its local observation date derived with the
configured IANA time zone. If that date differs from the current overview day,
the persistent label and tooltip show date plus time; same-day points retain
the shorter time-only form.

The existing protected immutable tile authority is unchanged. The browser tile
layer now preloads one adjacent zoom level and retains interim tiles on an
individual tile error. This reduces a blank flash while zooming and does not
add dynamic provider access, a new authority or a retry storm.

## Verification

The complete OwnTracks test suite, performance checks, module fileset check,
PHPCS and PHPStan pass. Responsive internal-browser checks at 1280 x 720,
1024 x 768 and 390 x 844 confirmed:

- zero document overflow;
- three visible overview labels, including two close positions;
- date plus time for previous-day overview positions;
- zero marker intersections with selection or navigation overlays;
- compact non-overlapping controls and a single-line `Fit all` action;
- source-specific ETA behavior after a marker tap;
- one path line with twelve visible line-colored direction arrows; and
- zero tile failures across a two-step zoom-in/zoom-out round trip.

The protected synthetic tile run loaded 74 tiles with a maximum concurrency of
four, retained a ready authenticated layer throughout the zoom sequence and
released every temporary object URL afterward. No provider was contacted.

The resulting exact 29-file package identity is
`8561019a0b3946e638f88cb5369a119da7702e839df5e84dea7ac5bafa207230`.

## Remaining Gates

The exact package is active with an independently verified rollback boundary as
recorded in `46-physical-ui-live-activation.md`. Physical
Safari/iPad/iPhone acceptance, dynamic provider access, a new tile revision,
commit, publication and retained-artifact cleanup each require their own gate.
