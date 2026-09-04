# Viewport Generation And Historical Accuracy Correction

**Status:** Repository correction complete; synthetic responsive acceptance
passed; exact package activated separately in step 66

**Date:** 2026-09-02

## Physical Findings

The remaining blank tile strips were not a cache-capacity problem. Read-only
runtime evidence showed no exhausted provider budget, but repeated spatial
allowlist rejections. A tile request created for one accepted viewport could
arrive after a newer viewport had replaced the server-side authorization. The
gateway then checked an in-flight old request against the new rectangle and
rejected it. Zooming appeared to repair the map only because it created a new
set of requests whose timing happened to match the latest viewport.

The separate pre-August `0 rendered` result had a different cause. Position
archives were populated, while the corresponding accuracy archives contained
no historical samples. The strict line policy therefore treated every point as
accuracy-unknown and removed it from the path.

## Corrected Contracts

The case-study-local runtime now:

- binds every tile request to the exact accepted viewport generation through
  the protected same-origin gateway;
- retains at most three recent viewport generations for no longer than 60
  seconds, so bounded in-flight requests survive a following fit, pan or zoom;
- keeps the capability, spatial allowlist, provider request, concurrency and
  byte budgets unchanged;
- marks historical observations without accuracy evidence as unverified and
  permits them only for the selected Path presentation;
- starts a new line segment when verified and unverified evidence changes, and
  renders unverified segments with a restrained dashed style; and
- reports `accuracy unknown` without inventing a historical accuracy value.

Positions and ETA retain their stricter current-quality semantics. WGS84 and
geodesic processing remain entirely inside the OwnTracks adapter and do not
cross into the local Navimow coordinate model.

## Presentation And Verification

The non-interactive result count is centred in the upper band of the compact
selection frame. The native controls still start below the known 46-pixel host
touch boundary.

The complete OwnTracks test target, deterministic module-fileset check, PHPCS,
PHPStan and diff check pass. Internal-browser checks at iPhone, iPad and desktop
sizes loaded every synthetic tile and showed the unverified historical-path
status without console errors. The resulting 36-file package identity is
`15bb10a7f938753005e6ba63ea1c81c3f03a2e5af16e435c06a4aa7381948b4d`.

No public helper or general SAEF map abstraction was added. The generation
history and legacy-accuracy policy remain implementation-local until another
independent consumer demonstrates the same recurring need.
