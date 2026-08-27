# 365 Local Map Runtime Integration Readiness Design

**Case study:** Navimow native IP-Symcon module

**Status:** Runtime integration designed; implementation, publication and live
activation remain closed

**Date:** 2026-08-27

## 1. Goal And Boundary

This step turns the verified offline map scene, revision-bounded track store
and SVG renderer into a proposed IP-Symcon ownership and lifecycle design.

It does not:

- change the productive Navimow distribution;
- create, delete or update a Symcon object;
- enable MQTT or retrieve MQTT credentials;
- call the private map endpoint;
- accept a changed map revision automatically;
- publish, push or deploy any artifact;
- infer geometric mowing coverage.

REST remains authoritative for public mower state. MQTT remains a receive-only
position and task-evidence source.

## 2. Reuse Before Extend

The runtime candidate must compose the existing components:

- `MqttPositionDiagnostic` supplies bounded accepted location samples;
- `MqttPathSegmenter` establishes path discontinuities;
- `MqttTaskObservationLedger` supplies retained task evidence;
- `MapGeometryReducer` validates a private map projection;
- `LocalMapSceneProjector` joins compatible geometry, path and statistics;
- `RevisionBoundedTrackStore` owns bounded revision-aware path retention;
- `LocalMapSvgRenderer` produces active-content-free presentation;
- SAEF `ConfigurationHash` supplies deterministic geometry fingerprints;
- SAEF Registry, Statistics and ErrorRingBuffer remain the preferred runtime
  metadata building blocks.

No new public SAEF helper is justified. The first integration remains inside
the Navimow implementation until the separate OwnTracks case study proves a
stable shared presentation contract.

## 3. Runtime Ownership

### 3.1 Account instance

The Account instance continues to own cloud authentication, REST polling,
receive-only MQTT transport and the ephemeral diagnostic source streams. It
must not own a combined multi-device map or installation-specific zone names.

It may answer one bounded internal child request containing only validated
position and task evidence for the matching mower. Existing public state
variables and the REST/MQTT authority boundary remain unchanged.

### 3.2 Device instance

The Device instance is the proposed owner of one mower's accepted private map
revision, retained revision-bound paths, rendered map output and presentation
settings. This keeps the map next to the existing stable mower variables and
avoids changing their Idents, profiles, archive assignments or action state.

The first implementation must be additive. Existing variables including
`VehicleState`, `Online`, `BatteryLevel` and command evidence remain untouched.

### 3.3 Private map import

Undocumented private app-cloud access does not become a periodic runtime
dependency. A sanitized reduced projection is imported explicitly through an
installation-private workflow. Authentication material and raw app payloads
must never be persisted by the Device instance.

## 4. Proposed Configuration Contract

All new properties default to a disabled or empty state:

| Property | Type | Default | Purpose |
| --- | --- | --- | --- |
| `EnableLocalMap` | Boolean | `false` | Master gate for projection and rendering. |
| `AcceptedMapProjection` | String | empty | Private reduced geometry projection, bounded and validated. |
| `AcceptedGeometryKey` | String | empty | Explicitly accepted configuration fingerprint. |
| `HiddenZoneSequences` | String | `[1]` | JSON list of presentation-only hidden zone labels; the current private convention hides Zone 4's label while retaining its polygon. |
| `TrackRetentionHours` | Integer | `72` | Additional time boundary inside the candidate's hard count and byte limits. |
| `MapRefreshInterval` | Integer | `60` | Render cadence in seconds while fresh movement evidence exists. |
| `MapIdleRefreshInterval` | Integer | `300` | Render cadence while docked, stale or inactive. |

The property names are a design candidate, not an approved public contract.
The map projection is installation-private and must be bounded before it enters
the module configuration. A future import UI should show a structure summary
and geometry fingerprint, never raw credentials or private protocol details.

## 5. Persistent State Contract

The Device instance may own these bounded attributes:

| Attribute | Content | Recovery rule |
| --- | --- | --- |
| `LocalMapRevisionRegistry` | Current accepted and candidate revision metadata. | Invalid state fails closed to map unavailable. |
| `LocalMapTrackState` | Serialized `RevisionBoundedTrackStore` state. | Restore through the candidate validator; reject the complete value on corruption. |
| `LocalMapStatisticsState` | Revision-bound pass summaries only. | Never migrate to another revision implicitly. |
| `LocalMapRenderMetadata` | Last render time, source freshness and configuration hash. | Rebuildable; safe to reset. |
| `LocalMapErrorHistory` | Bounded diagnostic history. | Use ErrorRingBuffer semantics. |

Track retention remains subject to all candidate hard limits: four revisions,
64 segments, 2048 points and 512 KiB serialized state. Time retention may
remove older complete segments but may not expand those limits.

No unbounded coordinate JSON is registered as a public variable or written to
the Symcon archive.

## 6. Update And Rendering Pipeline

The runtime flow is timer-coalesced and bounded:

1. Account validates receive-only MQTT position and task evidence.
2. Device requests one bounded evidence projection at its configured render
   cadence through the existing parent interface.
3. Account returns evidence only when its private device hash matches the
   requested configured mower; ambiguity or mismatch fails closed.
4. Device rejects stale, regressing, oversized or revision-incompatible input.
5. Path segmentation and revision-bound retention run under one instance
   semaphore.
6. Scene projection uses only the accepted geometry revision.
7. Renderer output replaces the owned map variable atomically after complete
   validation.

During movement, a render interval of 60 seconds is the initial conservative
default. Docked or inactive operation uses 300 seconds. These intervals affect
presentation only and do not change REST polling or MQTT lifecycle policy.

## 7. Station State And Presentation

The station marker receives an explicit presentation state derived from a
fresh REST-authoritative `VehicleState` value:

| REST state | Renderer state | Presentation |
| --- | --- | --- |
| `Docked` | `docked` | green station |
| `Docking` | `docking` | amber station |
| any other fresh known state | `undocked` | slate station |
| stale, offline or unknown | `unknown` | petrol station |

MQTT location state codes must not set the station state. They may only support
diagnostics. A stale REST state must therefore render `unknown`, not preserve a
possibly misleading last color.

Zone-label visibility is presentation-only. Hiding the current Zone 4 label
does not remove its polygon, change task attribution, alter geometry hashing or
change zone statistics.

## 8. Map Revision Workflow

Changes made in the official app to boundaries, excluded areas or station pose
produce a different geometry key. The productive workflow must be:

    accepted revision
      -> explicit private refresh/import
      -> validated candidate revision
      -> coordinate-free difference summary
      -> explicit operator acceptance
      -> atomic revision switch

Automatic app-cloud polling and automatic revision acceptance remain blocked.
Old paths remain attached to their original revision. They may be retained for
historical export but are not overlaid on the new map without a separately
validated migration.

Changing only hidden labels or other rendering preferences does not create a
geometry revision.

## 9. Symcon Presentation Surface

The first surface should be one Device-owned string variable with the
`~HTMLBox` profile and a stable Ident such as `LocalMap`. It contains only the
validated SVG output and is hidden when `EnableLocalMap=false`.

The implementation must not delete this variable when local-map display is
disabled. Preserving the object keeps visualization links and any future
archive or permissions configuration stable. Disabled state should clear or
replace the value with a bounded inactive representation and hide the variable.

The initial view is intentionally local-coordinate only. External map tiles,
geocoding and geographic transformation require separate privacy and provider
decisions.

## 10. Failure And Recovery Behavior

| Condition | Required behavior |
| --- | --- |
| MQTT disabled or unavailable | Keep the last bounded historical path; mark current position stale; REST variables continue normally. |
| REST state stale | Render station as `unknown`. |
| Invalid map projection | Reject it and keep local map disabled; do not replace the accepted revision. |
| Candidate revision differs | Expose only a coordinate-free change summary; do not mix paths or statistics. |
| Retained-state corruption | Discard the corrupt retained map state, record a bounded error and rebuild from future evidence. |
| Renderer failure or oversized SVG | Keep the previous valid SVG, record the failure and retry only after new input or the bounded timer. |
| Module update or restart | Restore validated retained state, reconcile timers idempotently and render only after kernel readiness. |

Map failures must not set the Account or Device connection status to an error
while REST remains healthy. The local map is an optional presentation feature,
not the module's state authority.

## 11. Rollback Contract

Rollback is configuration-first:

1. set `EnableLocalMap=false`;
2. stop map timers and evidence requests;
3. hide but do not delete the stable map variable;
4. retain bounded revision and track state until a separately authorized
   cleanup decision;
5. leave REST polling, archived mower variables and command handling unchanged.

Credential cleanup remains owned by the established MQTT lifecycle and pilot
closure machinery. Disabling the map must not create or retain MQTT
credentials.

## 12. Architecture Decisions

### AD-NAV-365-01: Device-owned map state

**Decision:** One mower Device instance owns its map, retained paths and
presentation.

**Reason:** Geometry and visualization are mower-specific while Account remains
the shared transport owner.

### AD-NAV-365-02: Explicit private import and acceptance

**Decision:** Keep map refresh and revision acceptance operator-driven.

**Reason:** The map source is undocumented, mutable and privacy-sensitive.

### AD-NAV-365-03: Preserve existing variable identities

**Decision:** Add one optional stable map variable without replacing or
recreating existing mower variables.

**Reason:** Existing archive logging and visualization bindings must survive
future module updates.

### AD-NAV-365-04: Coalesce presentation writes

**Decision:** Request and render on bounded cadence instead of reacting to
every MQTT message.

**Reason:** High-frequency input must not cause avoidable Symcon writes or UI
churn.

### AD-NAV-365-05: REST controls station color

**Decision:** Map fresh REST state to an explicit renderer state and render
stale state as unknown.

**Reason:** MQTT is supporting receive-only evidence and does not become a
second public state authority.

### AD-NAV-365-06: Defer provider-neutral extraction

**Decision:** Keep the renderer Navimow-local for the first runtime candidate.

**Reason:** OwnTracks uses WGS84 and must first prove the shared contract in a
separate case study.

## 13. Readiness Decision And Next Step

The runtime architecture is sufficiently bounded for an offline implementation
plan. Productive integration is not yet approved.

The next SAEF step should be
`366-local-map-runtime-integration-implementation-plan.md`. It should freeze:

- the exact distribution files and candidate files to change;
- the parent-to-child evidence message schema;
- property, attribute, variable and timer lifecycle tests;
- the no-recreation test for all existing Device variables;
- restart, disable and rollback test cases;
- synthetic map-revision and stale-REST fixtures;
- publication, disabled rollout and separately gated live-test boundaries.

No map runtime code should be added before that plan passes its offline review.
