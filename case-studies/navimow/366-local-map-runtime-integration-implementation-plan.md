# 366 Local Map Runtime Integration Implementation Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Offline implementation plan complete; implementation, publication
and live rollout remain closed

**Date:** 2026-08-27

## 1. Objective

Prepare one additive, default-disabled runtime candidate that can persist and
render a revision-bound local Navimow map inside the Device instance without
changing existing REST variables, archives, commands or MQTT authority.

This plan authorizes no code change by itself. It freezes the intended files,
contracts, test order and rollback boundary for the next separately reviewed
offline implementation step.

## 2. Preconditions

Implementation may start only from the verified step-364 candidates and the
step-365 architecture. The following contracts remain fixed:

- REST is authoritative for public mower and station state;
- MQTT is receive-only and optional;
- map geometry is explicitly imported and accepted, never polled automatically;
- app-driven geometry changes create a new candidate revision;
- existing Device variable Idents and archive identities are preserved;
- path storage is bounded and installation-private;
- geometric mowing coverage remains unavailable.

## 3. Exact Productive Fileset

The first implementation candidate may change only these existing distribution
files:

1. `case-studies/navimow/distribution/NavimowAccount/module.php`
2. `case-studies/navimow/distribution/NavimowDevice/module.php`
3. `case-studies/navimow/distribution/NavimowDevice/form.json`
4. `case-studies/navimow/distribution/NavimowDevice/locale.json`

It may add these Navimow-owned runtime classes under
`case-studies/navimow/distribution/libs/Navimow/`:

1. `MapGeometryReducer.php`
2. `MqttPathSegmenter.php`
3. `ZoneStatisticsReducer.php`
4. `LocalMapSceneProjector.php`
5. `RevisionBoundedTrackStore.php`
6. `LocalMapSvgRenderer.php`

The implementation must package the existing helper source unchanged as:

    helpers/diagnostics/ConfigurationHash.php
      -> distribution/libs/SAEF/helpers/diagnostics/ConfigurationHash.php

The Navimow fileset and publication inventories must be extended only after
the offline runtime candidate passes. Copying the canonical helper into the
standalone package is distribution assembly, not a second implementation.

No module GUID, library GUID, data-interface GUID or existing profile is
changed.

## 4. Candidate Promotion Rules

The six case-study candidates are promoted by changing their namespace from
`Navimow\Prototype` to `Navimow` and adapting only integration-specific error
types where necessary. Their validation limits and behavior remain unchanged.

Any source change beyond namespace, imports and proven integration needs must
be documented with a focused regression test. The promoted runtime files must
not import from `case-studies/navimow/candidate/`.

`LocalMapSceneProjector` must require the packaged canonical ConfigurationHash
helper through the Device module loader. It must not embed a local hash
implementation.

## 5. Parent Request Contract

Extend the existing data interface schema version 1 with one function:

    Function: GetLocalMapEvidence
    DeviceId: configured mower identifier

The Account response is bounded and internal:

    status: ok | disabled | inactive | unavailable | ambiguous | stale | error
    authority:
      state: rest-authoritative
      path: mqtt-inference
      task: mqtt-inference
    observedAt: positive Unix timestamp
    position: validated MqttPositionDiagnostic projection or null
    task: validated MqttTaskObservationLedger projection or null

The Account must:

- reuse `validateDeviceId()`;
- hash the requested DeviceId and compare it with the retained diagnostic
  device key using `hash_equals()`;
- reject conflicting-device state and mismatches;
- return no raw device ID, topic, token, credential or unparsed payload;
- cap encoded output at 256 KiB;
- avoid any REST request, token refresh, MQTT mutation or lifecycle transition;
- return disabled or inactive state without activating transport.

The method is a read-only projection. It does not become a public module action
or a REST-state source.

## 6. Device Lifecycle Contract

### 6.1 New properties

Register the step-365 candidate properties with fail-safe defaults. Numeric
values must be normalized to bounded ranges during use:

- `EnableLocalMap=false`;
- `AcceptedMapProjection=''`;
- `AcceptedGeometryKey=''`;
- `HiddenZoneSequences='[1]'`;
- `TrackRetentionHours=72`, allowed 1 to 720;
- `MapRefreshInterval=60`, allowed 15 to 900;
- `MapIdleRefreshInterval=300`, allowed 60 to 3600.

The private projection input contains one format-version-1 package with
`geometry`, `bindings` and `frameCorrelationApproved=true`. Geometry alone is
insufficient because task evidence could not be assigned to private zones.

The form exposes the master checkbox, bounded intervals, hidden-zone sequence
list, explicit color theme and a multiline private package input. It shows the
computed revision summary and never echoes raw geometry in validation errors.

### 6.2 New attributes

Register the five step-365 attributes with empty valid defaults. State reads
must validate byte limits before JSON decoding. Writes occur only after a
complete candidate validates and serializes successfully.

### 6.3 Stable variable

Register exactly one additive string variable:

    Ident: LocalMap
    Name: Local Map
    Profile: ~HTMLBox
    Position: 100

Register it on every `ApplyChanges()` invocation. `EnableLocalMap` controls
visibility and timer operation, not object creation or deletion. Existing
variables retain their Idents, types, profiles and positions.

### 6.4 Timer and semaphore

Register one timer:

    Ident: LocalMapRefresh
    Callback: NAVDV_RefreshLocalMap($_IPS["TARGET"])

The callback uses one Device-owned semaphore and has no retry loop. A failure
records bounded diagnostics and waits for the next normal interval. Docked or
inactive REST state selects the idle interval; a fresh non-docked state selects
the active interval.

Disabling the feature sets the timer to zero, hides `LocalMap`, clears its
presentation value and retains validated bounded history pending a separate
cleanup action.

## 7. Runtime Reduction Sequence

One successful refresh performs this fixed sequence:

1. validate enabled state, DeviceId and accepted map configuration;
2. recompute and verify the accepted geometry key;
3. request `GetLocalMapEvidence` from the parent;
4. validate response status, authority, byte size and timestamps;
5. obtain retained task passes and reduce zone statistics;
6. segment retained position samples using the existing bounded path policy;
7. build an accepted-revision scene;
8. ingest that scene into `RevisionBoundedTrackStore`;
9. project only the accepted current revision;
10. map fresh REST `VehicleState` to station presentation state;
11. render SVG with hidden-zone sequences and the validated explicit
    `dark` or `light` theme;
12. atomically persist validated retained state, metadata and SVG;
13. schedule the next active or idle interval.

If any step fails, later steps do not run. The previous valid SVG and retained
state remain intact.

## 8. Station-State Mapping Tests

Focused tests must prove:

| Input | Expected renderer state |
| --- | --- |
| fresh `VehicleState=Docked` | `docked` |
| fresh `VehicleState=Docking` | `docking` |
| fresh other known active or idle state | `undocked` |
| `Online=false` | `unknown` |
| `LastStatusUpdate` older than the REST stale boundary | `unknown` |

Theme tests must additionally prove that `dark` is the default, `light` is an
explicit alternative and every other value fails configuration validation.
| unsupported value | `unknown` |

MQTT `vehicleStateCode` variations must not alter this result.

## 9. Required Test Files

Add focused tests under `case-studies/navimow/tests/`:

1. `local-map-evidence-contract.php`
2. `local-map-device-lifecycle.php`
3. `local-map-runtime-reducer.php`
4. `local-map-variable-stability.php`
5. `local-map-restart-and-disable.php`
6. `local-map-distribution-fileset.php`

Extend the existing Symcon harness only where the module uses an already
supported IP-Symcon API. Do not create a parallel lifecycle harness.

Public fixtures contain synthetic geometry, positions, task keys and times.
They cover accepted, changed, stale, malformed, oversized, mismatched-device
and ambiguous-device cases.

## 10. Variable And Archive Stability Gate

The variable-stability test snapshots the complete pre-change Device variable
contract and proves after repeated `ApplyChanges()` calls:

- all existing Idents still exist exactly once;
- type and profile are unchanged;
- no existing variable is deleted or recreated;
- `LocalMap` exists exactly once whether enabled or disabled;
- feature disable changes only visibility, timer and owned presentation state;
- no existing variable action or archive contract changes.

Live archive verification remains a later read-only rollout check. The offline
test must not hardcode installation ObjectIDs.

## 11. Restart, Disable And Rollback Tests

The restart test reconstructs the module from serialized attributes and proves:

- corrupt state fails closed without affecting REST variables;
- accepted state restores without revision mixing;
- a candidate geometry does not become accepted;
- the timer starts only after valid configuration and kernel readiness;
- stale REST state renders an unknown station;
- repeated `ApplyChanges()` is idempotent.

The rollback test disables the feature and proves zero map timer activity, no
parent evidence request, hidden stable `LocalMap`, retained bounded state and
unchanged Account MQTT lifecycle state.

## 12. Verification Order

The offline implementation step must run, in order:

1. JSON validation for changed forms, locale and manifests;
2. PHP syntax checks for every changed or added PHP file;
3. the six focused map-runtime tests;
4. all existing local-map candidate tests;
5. all existing Navimow REST, command, MQTT and lifecycle tests;
6. distribution build and complete inventory equality;
7. module metadata and official validator checks;
8. PHPCS and PHPStan with the canonical lock-identical Composer toolset;
9. privacy scan for local paths, ObjectIDs, labels, coordinates, credentials
   and private protocol values;
10. clean-worktree and diff checks.

Failure at any step blocks publication readiness.

## 13. Gates After Offline Implementation

The following remain separate:

| Gate | Scope |
| --- | --- |
| P1 | Review and merge the SAEF candidate through a clean branch and PR. |
| P2 | Publish the exact manifest-bound standalone fileset through the generic publisher and verify metadata. |
| S1 | Update Symcon with `EnableLocalMap=false`; verify existing variable ObjectIDs, archives, REST and commands read-only. |
| L1 | Import and stage one private accepted map while MQTT remains disabled; verify no credentials and no map timer activity. |
| L2 | Enable one bounded receive-only map observation with explicit credential acceptance and mandatory cleanup. |
| V1 | Add the map variable to the Symcon visualization and verify desktop plus mobile rendering. |

No one gate implies another. Publication does not authorize Symcon update;
disabled update does not authorize private geometry import or MQTT activation.

## 14. Implementation Decision

The plan is ready for an offline-only implementation step:

`367-local-map-runtime-integration-implementation.md`.

That step may implement and test the frozen fileset locally. It must stop before
commit, publication, Symcon update, private map import or live MQTT activation
unless those boundaries receive separate approval.
