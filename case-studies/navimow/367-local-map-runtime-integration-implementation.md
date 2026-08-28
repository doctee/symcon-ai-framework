# 367 Local Map Runtime Integration Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Default-disabled runtime candidate implemented and verified
offline; commit, publication and live rollout remain closed

**Date:** 2026-08-27

## 1. Scope And Safety Result

This step implements the step-366 runtime candidate inside the isolated
Navimow worktree. It performs no network, authentication, Symcon, MQTT or mower
operation and creates no public commit or publication.

The implementation is additive and default-disabled:

- `EnableLocalMap=false`;
- no map timer runs without a valid explicitly accepted private map package;
- the Account read path does not activate MQTT, refresh a token or call REST;
- existing Device variables, archive identities and command paths are unchanged;
- map failures remain local to the optional presentation feature;
- geometric mowing coverage remains not implemented.

## 2. Import Contract Correction

The implementation review closed one gap in the earlier plan. A geometry
projection alone cannot map privacy-safe MQTT task keys to zones. The accepted
private import therefore uses one bounded format-version-1 package containing:

    geometry
    bindings
    frameCorrelationApproved = true

`AcceptedGeometryKey` must equal the canonical ConfigurationHash of the
geometry. Hidden zone-label sequences remain presentation configuration and do
not change this geometry key.

Raw private map payloads, authentication data and private protocol details are
not accepted by the Device instance.

## 3. Account Evidence Projection

The existing parent interface now accepts the read-only internal function
`GetLocalMapEvidence` with a configured `DeviceId`.

The Account:

- remains the owner of receive-only MQTT diagnostics;
- compares the requested mower's SHA-256 key using `hash_equals()`;
- fails closed for empty, mismatched or conflicting-device evidence;
- returns bounded validated position and task projections only;
- distinguishes disabled, inactive, unavailable, ambiguous, stale and error
  states;
- caps the encoded result at 256 KiB;
- returns no device ID, MQTT topic, credential, token or raw payload.

The initial focused test found and corrected a PHP array-union defect that had
kept prepared `null` projection fields. The final implementation uses explicit
replacement and the regression test proves non-null bounded evidence.

## 4. Device Runtime

The Device instance now owns:

- one stable `LocalMap` string variable with `~HTMLBox` and position 100;
- one `LocalMapRefresh` timer;
- accepted revision, retained track, zone statistics, render metadata and a
  bounded error history as internal attributes;
- disabled-by-default map properties and bounded active and idle intervals.

Every refresh is protected by a Device-owned semaphore and performs no retry.
It requests one bounded Account projection, applies the established path,
statistics, scene, retention and renderer components, then writes validated
state and SVG only after the complete pipeline succeeds.

Active presentation cadence defaults to 60 seconds. Docked, stale or inactive
operation defaults to 300 seconds. These timers do not alter REST polling or
MQTT lifecycle timing.

## 5. Station And Label Presentation

Station presentation is derived only from fresh REST-owned Device variables:

| REST state | Renderer state |
| --- | --- |
| Docked | `docked` |
| Docking | `docking` |
| another fresh known state | `undocked` |
| offline, stale or unsupported | `unknown` |

When MQTT is disabled, inactive, unavailable or ambiguous, the module can
still render the accepted geometry and retained path with the current REST
station state. It does not claim new position evidence in that path.

The configured hidden sequence keeps the current Zone 4 label out of the view
while retaining its polygon, attribution and statistics semantics.

The Device exposes a validated `dark` or `light` map theme. `dark` is the
default and uses a charcoal background, light path and labels, restrained
excluded areas and distinct multi-hue zone fills suitable for the Symcon Dark
Skin.

## 6. Retention And Restart

`RevisionBoundedTrackStore` now additionally provides:

- deterministic time pruning before an explicit cutoff;
- a renderer projection for one exact accepted geometry revision.

The existing hard limits remain four revisions, 64 segments, 2048 points and
512 KiB. The configured 1-to-720-hour retention window can only reduce these
limits.

Restart tests prove validated state restoration. Feature disable stops the
timer, hides and clears the presentation value, and retains bounded internal
history without changing Account MQTT state. Corrupt retained state fails
locally and leaves REST variables intact.

## 7. Reuse Before Extend

The runtime promotes the six established Navimow candidates into the
standalone distribution namespace. No additional public helper is introduced.

Configuration hashing reuses the canonical SAEF helper. The generic fileset
maps:

    helpers/diagnostics/ConfigurationHash.php
      -> libs/SAEF/helpers/diagnostics/ConfigurationHash.php

There is no second canonical helper copy under the case-study distribution.
This follows the existing Open-Meteo packaging pattern.

## 8. Tests And Verification

Six new focused executable contracts cover:

- Account evidence status, device match and ambiguity;
- Device property, variable, timer and kernel-start lifecycle;
- dark-default, explicit-light and invalid-theme presentation behavior;
- end-to-end synthetic rendering and retained paths;
- existing variable type, profile and position stability;
- restart, disable, corruption and rollback behavior;
- manifest and publication inventory completeness.

The existing retained-track test also covers time pruning and renderer
projection. The complete Navimow offline suite passes, including all existing
REST, command, MQTT, parser, diagnostics, pilot, recovery, geometry, renderer
and distribution tests, PHPCS and PHPStan.

The generic publication check passes without mutation:

    filesetSha256
    2d36cb88a552d6b6b7568673d1ba1b32cf4407fe9e894dcdfb5db3d35e06844c

    publicationSha256
    79f3c71e407c84736155b5c9afedaed377cb68293f3463088958215128e794bb

Focused productive hashes:

    NavimowAccount/module.php
    19e4e500ac4e660dd3cb16ce621cdc894e8524e66db6b84b40703393be8981ce

    NavimowDevice/module.php
    2966da403a57d8d39c2c78875dae480f4194ddc24ea8322491187eea86fe31ae

    LocalMapSceneProjector.php
    8fd9f9974ab2d52344cf7f41e71e48a0ffe7a5d73c30910c82a3ab3e079a542d

    LocalMapSvgRenderer.php
    2dcb883935a502a61ddc0bd796af63932955694af9b62adbd3f5ed7b4f929993

    RevisionBoundedTrackStore.php
    c02ff045fc79ae09860d2edbc5eb8d939440d4845f3a1a2b05a9c117115efa9f

The official browser-based Symcon Module Validator was not run because no
standalone commit has been published. It remains part of the separate metadata
and publication gate and is not claimed by this offline result.

## 9. Architecture Decisions

### AD-NAV-367-01: Pull bounded evidence on presentation cadence

**Decision:** Device requests one read-only projection instead of processing
every Account MQTT message.

**Reason:** This coalesces writes and preserves Account transport ownership.

### AD-NAV-367-02: Import geometry and bindings atomically

**Decision:** Accept one revision package containing both contracts.

**Reason:** A geometry-only import cannot safely correlate task keys to zones.

### AD-NAV-367-03: Render retained state without active MQTT

**Decision:** Permit geometry, historical path and REST station refresh while
MQTT supplies no new evidence.

**Reason:** Presentation availability and station correctness must not falsely
imply current MQTT reception.

### AD-NAV-367-04: Keep one canonical ConfigurationHash source

**Decision:** Package the existing helper directly through the generic fileset.

**Reason:** Distribution assembly must not create a competing helper owner.

### AD-NAV-367-05: Preserve all existing Device variables

**Decision:** Add one stable optional variable and never delete it on disable.

**Reason:** User-managed archive logging and visualization references must
survive module updates.

### AD-NAV-367-06: Configure theme explicitly

**Decision:** Default the new map feature to `dark`, retain an explicit
`light` alternative and reject unknown values.

**Reason:** An embedded SVG cannot reliably inherit the surrounding Symcon
skin, while a configuration contract remains deterministic and testable.

## 10. Gate Decision And Next Step

The offline implementation gate passes. These gates remain closed:

| Gate | Status |
| --- | --- |
| local commit | closed |
| SAEF branch push and pull request | closed |
| standalone publication | closed |
| official Module Validator | closed |
| Symcon disabled update | closed |
| private map import | closed |
| MQTT or local-map live activation | closed |
| visualization placement | closed |

The next SAEF step should be
`368-local-map-runtime-candidate-review-and-publication-readiness.md`. It should
review the complete delta, freeze the exact candidate and rollback baseline,
and decide whether a local commit and SAEF publication candidate may be formed.
