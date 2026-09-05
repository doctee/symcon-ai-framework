# OwnTracks Position Map Case Study

This case study defines the first SAEF design step for a provider-neutral
position map backed by three existing OwnTracks data instances. It also
preserves the existing selection-variable-driven historical path mode while
keeping the legacy external-path anchor only as rollback-compatible runtime
configuration, not as a browser source.

The target presentation is a borderless map tile with direct pan and zoom,
one explicit fit-all action for every valid observation of the selected source
and day, and a bounded ETA presentation for the next explicitly resolved
target.

The workstream is deliberately limited to requirements, a case-study-local
data contract, offline core and read-only Symcon adapter candidates, an
architecture comparison and a privacy-safe read-only live inventory. It does
not modify the OwnTracks instances, Archive Control, the existing map or any
visualization. It also does not introduce a general SAEF map API.

## Status

Security follow-up: [review 77](77-final-security-review.md),
[correction 78](78-security-correction-and-supplemental-review.md), targeted
[ACL hardening 79](79-windows-acl-hardening.md) and the final
[live activation 80](80-security-correction-live-activation.md) supersede the
earlier security-postflight wording. The corrected package is active after a
fresh negative-request matrix, one explicitly disclosed non-position-derived
provider request and an independent package/configuration/ACL postflight. This
is scoped evidence for the configured runtime, not an unconditional host or
provider security clearance.

The version-8 continuation records the read-only retained-worktree and UI
reconciliation in [Gate 81](81-channel-v8-pilot-inventory.md) and the
repository-only OwnTracks target adapter in
[Gate 82](82-channel-v8-deployment-adapter.md). Its separately authorized
[Windows qualification](83-channel-v8-windows-adapter-qualification.md) proves
PowerShell 5.1 transaction and integrated Symcon-PHP lock interoperability.
The separate miss-state adoption is defined in
[Gate 85](85-miss-state-format-adoption.md) and its protected synthetic Windows
qualification passed in
[Gate 86](86-miss-state-adoption-windows-qualification.md). Its corrected,
non-mutating [live preflight](87-miss-state-live-preflight.md) also passed, and
the separately authorized lossless
[live adoption](88-miss-state-live-adoption.md) completed with retained private
rollback evidence. The subsequent read-only
[target readiness preflight](89-target-allowlist-readiness-preflight.md) passed
without changing the empty target allowlist. No target installation, channel
mutation or live module operation is authorized by these documents.

**Read-only inventory, synthetic offline core, fixture- and live-verified
read-only archive adapter, provider decision, diagnostic renderer, pinned
OpenLayers candidate, parallel runtime/visualization design, repository-only
HTML-SDK runtime candidate, deterministic module packaging, additive
provider-free live activation, the SymconTest lifecycle package reload,
protected private-basemap activation and the source/presentation live
correction complete. The host-control and Symcon-theme follow-up is now
synthetically accepted, live-activated and physically accepted on Safari/Mac
and iPad. The three-current-position overview and direction-arrow refinement is
repository-accepted and live-activated with only the three private labels
changed. Physical iPad acceptance passed and produced a repository-only
follow-up for overlapping points, overview-only ETA, compact mode-first
controls, stronger arrows and partial regional tile coverage. That exact
29-file follow-up package is now live with an independent structural and
security postflight. Physical feedback produced a further repository-only
correction for path-colored arrows, smaller connected point displacement,
overlay-safe fit, compact controls, selected-source ETA, dated stale positions
and zoom tile continuity. Its exact 29-file package is now active with unchanged
configuration and live contracts, an independently verified rollback boundary
and no failed or staging residue. Physical feedback then exposed a Safari date-
field overhang, tight label inset, tooltip loss during ETA refresh and an
opaque stale-position rejection. Their bounded repository correction is
responsive-browser accepted and its exact package is now active with an
independently verified rollback boundary. The selection-bound OSM-on-miss
runtime has now passed its one-tile transport preflight and a guarded live
activation. Static tiles remain authoritative, Connect capability protection
is unchanged, the previous package and complete configuration are retained for
rollback, and physical provider-fallback acceptance remains pending.**

The separately authorized activation owns one new module instance and two new
links. The motion-aware two-target resolver is integrated with two existing
provider-neutral `SharedLocation` instances and verified by a bounded current-
day request. A repository-only protected tile-gateway core, authenticated
OpenLayers loader and bounded provider-neutral read-through cache are
synthetically verified. The cache now has a locked file-backed runtime adapter
that remains unused while gateway activation is disabled. A default-disabled
WebHook transport adapter now composes capability issuance, strict request
normalization, persistent request budgets and the gateway. Its stable native
Strict hook is now registered once during `Create()` following SymconTest; the
disabled handler performs no secret or tile work. The corrected lifecycle
package is now active and its disabled hook passed uniform local `404`,
`no-store` and `nosniff` checks. A temporary synthetic Connect spike verified
custom-header forwarding and
the effective Symcon request canonicalization, then removed every owned hook and
script. The private pilot now uses the Connect-protected private regional
basemap while routing remains disabled. A private pre-provisioned XYZ-directory
authority is integrated and verified without provider network access. The real
tileset source, regional zoom-14 boundary, storage ceilings and private
provisioning/rollback workflow are decided. The capacity preflight fixed a
privacy-safe two-target 100-kilometre coverage union and replaced the failed
single-extract assumption with a verified clipped four-extract source strategy.
The separately authorized private build, staged browser acceptance and bounded
Windows staging are complete: the exact raster inventory is inside every hard
limit, every PNG passed the directory authority, and the real basemap passed
desktop, iPad and iPhone-sized checks with no tile failures after deferring
layer activation until the first fit. The byte-verified archive was extracted
through an explicit path-safe stream boundary, independently rehashed and
atomically staged; the temporary transfer endpoint and capability were then
removed. The repository resolver also enforces the strict under-100-kilometre
ETA boundary with synthetic boundary coverage. The live correction now orders
the three OwnTracks sources `LT`, `CT`, `MT`, uses `LT` by default, permits line
segments across gaps of at most one hour and historically exposed the current
external path as an explicit fourth choice. Its package, configuration, provider,
logging, hook, link and rollback postflight passed. The internal Connect
browser remained at the Symcon loading screen. The subsequent physical
Safari/Mac test confirmed map interaction but exposed a pointer-active host
layer over the selection controls and missing Symcon-theme adaptation. The
repository follow-up now reserves the complete host band, restores native
control gesture policy, equalizes all three fields and consumes Symcon card,
content and accent colors. Its exact package is active with the preceding
package retained for rollback and an independent structural postflight passed.
Physical Safari/Mac and iPad acceptance passed. The next repository candidate
shows one current timestamped point for each of the three configured sources by
default, keeps `Path` source-local, removes the external anchor from browser
selection and adds bounded direction arrows. Its exact package is now active,
the private label-only configuration delta passed an independent postflight,
and the previous package plus complete previous configuration remain available
for rollback. Physical acceptance, routing, publication, commit and
retained-artifact cleanup remain closed.

Subsequent iPad evidence identified a cross-component authorization race: the
renderer's only failed-tile retry occurred after the server's viewport
generation had expired. The repository correction now renews that viewport
once before rebuilding the tile source and verifies that its retry delay stays
inside the server grace window. Live activation is recorded separately.

## Documents

| Document | Purpose |
| --- | --- |
| `01-requirements.md` | Defines scope, behavior, privacy, quality and acceptance requirements. |
| `02-data-contract.md` | Defines the OwnTracks WGS84 adapter contract candidate without promoting it to a public SAEF API. |
| `03-architecture-comparison.md` | Compares ownership and renderer options and recommends the smallest reversible next step. |
| `04-read-only-live-inventory.md` | Records the sanitized structure, archive cadence, gaps, accuracy, current map and rollback boundary. |
| `05-offline-core-implementation.md` | Records the fixture-backed WGS84, day-bounds, path-budget and ETA candidate. |
| `06-offline-renderer-comparison.md` | Compares renderer boundaries and records the executable no-tile diagnostic candidate. |
| `07-openlayers-offline-pilot.md` | Records the pinned local OpenLayers bundle, licenses, no-tile adapter and responsive browser evidence. |
| `08-symcon-archive-adapter-candidate.md` | Records the repository-only bounded Archive Control and selector-generation adapter candidate. |
| `09-provider-decision.md` | Selects same-origin internal tiles, optional internal OSRM and the provider-free fallback. |
| `10-parallel-runtime-visualization-design.md` | Defines additive HTML-SDK ownership, Connect-safe tile access, performance and exact rollback gates. |
| `11-repository-runtime-implementation.md` | Records the repository-only HTML-SDK candidate, external anchor contract and synthetic verification boundary. |
| `12-deterministic-module-packaging.md` | Records the self-contained module fileset, installability evidence, ownership and live rollback preconditions. |
| `13-parallel-no-tile-live-activation.md` | Records the sanitized additive activation, postflight and remaining ETA/visual gates. |
| `14-ios-action-bridge-correction.md` | Records the iPhone loading diagnosis and repository-only HTML-SDK bridge correction. |
| `15-mobile-presentation-correction.md` | Records the compact iPhone controls, collision-free maximize control and exact live rollback. |
| `16-private-eta-target-resolver-design.md` | Defines the next-target ownership decision without reusing the external path anchor or adding routing authority. |
| `17-touch-pan-and-rotation-correction.md` | Records the OpenLayers/iOS gesture conflict, repository correction and synthetic mobile evidence. |
| `18-motion-aware-target-resolver.md` | Records the read-only motion/speed evidence and synthetic bounded target resolver candidate. |
| `19-shared-location-eta-integration.md` | Records bounded activity reads, SharedLocation reuse and motion-aware diagnostic ETA packaging. |
| `20-shared-location-eta-live-activation.md` | Records the controlled private activation, rollback evidence and bounded current-day postflight. |
| `21-protected-tile-gateway-candidate.md` | Records the case-study-local capability gateway, authenticated loader and synthetic desktop/touch evidence. |
| `22-bounded-tile-cache-candidate.md` | Records the authenticated read-through cache, revision boundary, fixed memory/TTL limits and eviction evidence. |
| `23-tile-cache-runtime-adapter.md` | Records the private file-backed cache persistence, concurrency, corruption recovery and disabled-default boundary. |
| `24-webhook-adapter-security-review.md` | Records strict request translation, persistent rate/concurrency state and the repository security matrix. |
| `25-synthetic-connect-webhook-acceptance.md` | Records custom-header forwarding, Symcon request canonicalization, fail-closed corrections and verified cleanup. |
| `26-default-disabled-webhook-runtime-integration.md` | Wires the reviewed adapter into the pilot runtime while keeping hook, secret, provider and tile authority disabled by default. |
| `27-private-xyz-directory-tile-authority.md` | Selects and implements a strict read-only pre-provisioned XYZ directory authority without provider or network access. |
| `28-real-tileset-provisioning-plan.md` | Selects the private OSM-derived source pipeline and fixes regional coverage, zoom, capacity, provenance and rollback gates. |
| `29-read-only-real-tileset-preflight.md` | Records the sanitized ETA radius, verified source coverage and pinned container provenance without a PBF or container pull. |
| `30-eta-radius-implementation.md` | Records the case-study-local strict under-100-kilometre eligibility implementation, package regeneration and synthetic boundary evidence. |
| `31-private-real-tileset-build-and-browser-acceptance.md` | Records the sanitized bounded build, authority validation, real-basemap browser evidence and remaining transfer/activation/cleanup gates. |
| `32-private-windows-tileset-staging.md` | Records the bounded LAN transfer, path-safe extraction, independent content postflight and remaining live-activation gate. |
| `33-symcontest-strict-hook-lifecycle.md` | Records the SymconTest reference, restart-safe Strict hook lifecycle correction and repository verification boundary. |
| `34-symcontest-package-live-reload-rollback.md` | Records the verified candidate transfer, automatic live-package rollback and corrected structural acceptance contract. |
| `35-symcontest-package-live-activation.md` | Records the successful lifecycle package reload, structural provider-free postflight and uniform disabled native-hook evidence. |
| `36-protected-basemap-live-activation.md` | Records protected private-basemap activation, security postflight and desktop/iPad/iPhone-sized browser acceptance. |
| `37-source-fit-label-and-safari-correction.md` | Records the repository-only correction for source-local fit, external-position isolation, line continuity, decluttered timestamps and Safari control handling. |
| `38-source-presentation-live-correction.md` | Records the gated package activation, LT/CT/MT ordering, one-hour line gap, preserved external path, independent postflight and pending physical Safari acceptance. |
| `39-host-controls-and-theme-correction.md` | Records the host-layer diagnosis, corrected native control boundary, equal field geometry, Symcon theme variables and synthetic responsive acceptance. |
| `40-host-controls-and-theme-live-activation.md` | Records the exact package-only activation, immutable rollback boundary, unchanged live contracts and completed Safari/Mac and iPad acceptance. |
| `41-current-overview-and-direction-arrows.md` | Records the archive-free three-source current overview, source-local Path mode, compact controls, bounded direction arrows and responsive acceptance. |
| `42-current-overview-live-activation.md` | Records the exact-package activation, private label-only delta, independent structural postflight and retained rollback boundary. |
| `43-overview-controls-eta-and-tile-coverage.md` | Records the iPad follow-up for point fan-out, mode-first controls, overview-only bounded ETA, visible arrows, resilient partial coverage and a gated dynamic-tile design. |
| `44-overview-controls-live-activation.md` | Records the exact package-only activation, unchanged live contracts, independent security postflight and retained rollback boundary. |
| `45-physical-ui-follow-up.md` | Records the physical UI feedback, source-selected ETA, line-colored arrows, smaller marker fan-out, overlay-safe fit, compact controls and zoom tile continuity. |
| `46-physical-ui-live-activation.md` | Records the exact package-only activation, unchanged source/logging and security contracts, independent postflight and retained rollback boundary. |
| `47-control-tooltip-eta-follow-up.md` | Records the Safari field-bound correction, label inset, tooltip retention across ETA refresh and explanatory stale-position result. |
| `48-control-tooltip-eta-live-activation.md` | Records the guarded activation, automatic first rollback, verified retry, unchanged live contracts and independent postflight. |
| `49-slow-load-and-bounded-tile-miss-candidate.md` | Records the Safari date containment, truthful slow-load state and synthetic spatially allowlisted on-miss fallback without provider or live authority. |
| `50-osm-on-miss-provider-and-transport.md` | Selects OSM Standard only for interactive on-miss use and records the pinned HTTPS transport, policy constraints, privacy disclosure and still-closed cache/runtime gates. |
| `51-provider-aware-tile-cache.md` | Records the bounded origin-aware cache, conditional revalidation, fail-closed stale handling and still-closed network/runtime gates. |
| `52-disabled-provider-runtime-integration.md` | Records the packaged, selection-bound on-miss runtime, disabled-default network boundary, cross-session cache isolation and synthetic acceptance. |
| `53-one-tile-network-preflight.md` | Records the explicitly authorized synthetic OSM request, pinned transport evidence, PHP 8.5 compatibility correction and still-disabled live boundary. |
| `54-provider-fallback-live-activation.md` | Records the guarded 36-file activation, first automatic rollback, corrected reload retry, tile-fallback-only configuration delta and independent security postflight. |
| `55-viewport-tile-authorization-and-fit-correction.md` | Records the repository-only status-safe fit and bounded current-viewport authorization that preserves the stable selection budget across pan and zoom. |
| `56-viewport-tile-live-activation.md` | Records the exact 36-file activation, byte-identical live contracts, independent security postflight and pending physical viewport/tile acceptance. |
| `57-fit-viewport-authorization-correction.md` | Records the repository-only Fit/Resize authorization ordering correction and synthetic desktop/iPad generation evidence. |
| `58-fit-viewport-live-activation.md` | Records the exact package activation, unchanged live contracts, retained cache and rollback evidence, and pending physical Fit-all acceptance. |
| `59-complete-viewport-tile-loading.md` | Diagnoses rectangular tile gaps and records viewport-scoped authorization, stable provider budgets, visible-tile priority and synthetic desktop/iPad acceptance. |
| `60-complete-viewport-live-activation.md` | Records the exact package and per-viewport budget activation, unchanged protected contracts, retained rollback evidence and pending physical acceptance. |
| `61-gateway-budget-fit-and-overlay-follow-up.md` | Corrects denied-provider accounting, adds one bounded delayed tile retry and records the 100-metre Positions fit plus compact edge-aligned overlays. |
| `62-bounded-retry-live-activation.md` | Records the exact package-only activation, unchanged gateway/provider/security contracts, retained cache and rollback evidence, and pending physical retry acceptance. |
| `63-host-touch-boundary-and-status-placement.md` | Separates the six-pixel visual frame from the 46-pixel host touch boundary and moves the non-interactive position count into the upper band. |
| `64-host-touch-boundary-live-activation.md` | Records the exact package-only activation, unchanged live contracts, retained cache and rollback evidence, and pending physical iPhone acceptance. |
| `65-viewport-generation-and-historical-accuracy-correction.md` | Binds tile requests to recent accepted viewport generations, restores explicitly unverified pre-accuracy paths and centres the compact status band. |
| `66-viewport-generation-live-activation.md` | Records the exact package-only activation, unchanged security and source contracts, retained rollback evidence and deployed historical-path postflight. |
| `67-bounded-source-clock-skew.md` | Defines the case-study-local five-second OwnTracks source-clock tolerance, explicit quality flag and strict six-second rejection boundary. |
| `68-source-clock-skew-live-activation.md` | Records the exact package-only activation, unchanged live contracts, retained rollback evidence and corrected 200-to-199 historical-path projection. |
| `69-viewport-retry-authorization-correction.md` | Diagnoses the expired-generation retry and renews one bounded viewport before rebuilding the protected tile source. |
| `70-viewport-retry-live-activation.md` | Records the exact package activation, unchanged protected contracts, retained rollback and pending physical provider-tile acceptance. |
| `71-tile-grid-zoom-alignment-correction.md` | Diagnoses the fitted-view zoom mismatch and makes viewport authorization use the exact OpenLayers XYZ tile-grid resolution selected by the protected source. |
| `72-tile-grid-zoom-alignment-live-activation.md` | Records the exact package activation, automatic first rollback, interface-discovered reload retry and independent unchanged-configuration postflight. |
| `73-drain-aware-tile-retry-correction.md` | Prevents one failed tile from cancelling slower active and queued requests, then proves complete bounded recovery with a delayed synthetic browser fixture. |
| `74-complete-provider-tile-live-correction.md` | Defers every protected basemap rebuild until tile work drains, gives provider/cache tiles priority over the immutable static fallback, and records complete live historical-fit acceptance. |
| `75-high-zoom-and-detail-revision.md` | Separates the static and dynamic zoom ceilings, adds one bounded final retry and records the targeted local z15-18 detail build. |
| `76-high-zoom-live-activation.md` | Records the bounded extension transfer, immutable revision activation, semantic configuration retry, independent security postflight and pending provider-reaching browser acceptance. |
| `77-final-security-review.md` | Records three confirmed security findings and the limits of the read-only audit. |
| `78-security-correction-and-supplemental-review.md` | Records the isolated correction, concurrency/corruption/deadline regressions, preserved-budget rollback and newly identified ACL/toolchain gates. |
| `79-windows-acl-hardening.md` | Records the separately authorized protected-DACL hardening of OwnTracks-owned runtime, static-tile and active-package roots. |
| `80-security-correction-live-activation.md` | Records the exact security-correction activation, one-request transport acceptance, independent postflight, cleanup and state-aware rollback boundary. |
| `81-channel-v8-pilot-inventory.md` | Reconciles all retained OwnTracks worktrees, handovers and historical Safari/UI findings with current `origin/main`. |
| `82-channel-v8-deployment-adapter.md` | Defines and implements the target-bound ownership, quiescence, state, reload, health, rollback and retention contract for channel version 8. |
| `83-channel-v8-windows-adapter-qualification.md` | Records the sanitized Windows PowerShell 5.1 transaction and integrated Symcon-PHP lock interoperability result while keeping installation and live module gates closed. |
| `84-target-allowlist-preflight.md` | Corrects the mutation-only ACL classifier, records the non-mutating diagnostic boundary and prepares the private target-bound initializer preflight. |
| `85-miss-state-format-adoption.md` | Defines the separate lossless format-1-to-format-2 adoption with shared quiescence, hash-pinned two-phase authorization, byte-exact rollback and independent retention. |
| `86-miss-state-adoption-windows-qualification.md` | Records the protected Windows PowerShell 5.1 preflight, lossless adoption, lock-contention recovery and byte-exact automatic rollback qualification. |
| `87-miss-state-live-preflight.md` | Records the corrected read-only live format-1-to-format-2 preflight, PHP-empty-map diagnosis and negative-mutation postflight. |
| `88-miss-state-live-adoption.md` | Records the separately authorized lossless live format-1-to-format-2 adoption, independent postflight and retained rollback evidence. |
| `89-target-allowlist-readiness-preflight.md` | Records the repeated read-only target-bound initializer preflight after format-2 adoption and the removal of the adapter-readiness blocker without installing a target. |

## Architectural Boundary

This case study follows Navimow steps 351 and 352:

- presentation may eventually consume a small provider-neutral track contract;
- OwnTracks remains responsible for WGS84 input and geodesic processing;
- Navimow remains responsible for its uncalibrated local coordinate frame and
  Euclidean local-distance strategy;
- no coordinate, threshold, zone or calibration assumption crosses between
  those adapters; and
- extraction of shared SAEF behavior waits until two implementations prove a
  stable recurring need.

## Closed Gates

Separate explicit authorization is required before any of the following:

- creating a live script, variable, media object or visualization link;
- changing logging, aggregation or archive data;
- changing an OwnTracks, hook, external-data or map instance;
- reading or changing visualization configuration through another live
  channel;
- replacing, hiding or reconfiguring the existing map; or
- publishing a module, helper, renderer or reusable contract.

The diagnostic Canvas candidate has no basemap or external library. The pinned
OpenLayers candidate now accepts an optional same-origin XYZ contract, but the
fixture keeps that contract disabled and contacts no provider. Neither
candidate owns an IP-Symcon object.
