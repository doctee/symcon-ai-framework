# 380 Local Map State Colors And Zone Statistics Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Offline implementation and private visual verification passed;
publication and live rollout remain separately controlled

**Date:** 2026-08-28

## 1. Goal

Explain every station color in the map legend, color the mower marker by its
current REST-authoritative state and expose the already implemented bounded
zone-statistics projection through stable optional Symcon variables.

## 2. Presentation Contract

The station legend now explains:

- green: docked;
- orange: returning to the station;
- gray: away from the station;
- teal: unknown or stale REST state.

The mower marker uses the fresh REST `VehicleState` projection:

- green: Running or Mapping;
- yellow: Idle, Paused or Self-Checking;
- orange: Docking;
- red: Lifted or Error;
- gray: Offline;
- teal: unknown, stale or unsupported state.

MQTT supplies only the marker position and retained route. It never decides the
displayed operational state and remains receive-only.

The productive mower marker is shown only for fresh MQTT position evidence
while the REST state is not Docked. With disabled or stale MQTT, historical
routes remain visible but no old endpoint is presented as the current mower
position. A docked mower is represented by the green station marker.

## 3. Statistics Contract

`EnableZoneStatistics` is disabled by default. When enabled with a valid
accepted map package, the Device registers two global state variables and four
variables for every explicitly bound zone:

- latest pass-progress candidate;
- retained observed-area delta;
- last observation timestamp;
- evidence quality.

Zone variable Idents contain the stable positive manufacturer zone ID. Local
labels affect only display names. Disabling the feature leaves every created
variable intact and only marks the global state as disabled. The module does
not alter Archive Control settings.

Fresh statistics are `Available`; retained values shown without fresh MQTT
evidence are `Stale`. Missing evidence is `No Data`. Invalid contracts fail
closed without replacing prior values.

## 4. Geometry Boundary

Statistics carry the same accepted geometry key as the map. Stored statistics
are discarded from projection when that key no longer matches, for example
after app-side boundary or excluded-area changes followed by accepting a new
private package.

`PassProgress` is manufacturer task evidence. `ObservedArea` is a retained
task-area delta. Neither value is labelled geometric mowing coverage. Exact
coverage remains blocked on mower-width calibration, overlap treatment and a
proven zone denominator.

## 5. Verification

Offline checks prove:

- all seven mower presentation states and four station states;
- dedicated legend classes and unchanged productive layer counts;
- correct fresh, stale and disabled statistics transitions;
- stable variable definitions after repeated ApplyChanges and disable;
- no variable for an unbound zone;
- candidate/distribution renderer equality except namespace;
- successful PHPCS, PHPStan and complete Navimow offline checks.

A private coordinate-bearing Dark-Skin render confirms that the expanded
two-column legend remains in the free lower-right area without covering the
mapped working zones. Private geometry and labels remain outside SAEF.

## 6. Architecture Decisions

### AD-NAV-380-01: REST owns map-state colors

**Decision:** Derive station and mower colors only from fresh REST state.

**Reason:** Position cadence must not silently promote MQTT inference into the
public operational-state authority.

### AD-NAV-380-02: Reuse the existing statistics reducer

**Decision:** Project `ZoneStatisticsReducer` output instead of creating a new
statistics helper or storage model.

**Reason:** The reducer already enforces bounded pass evidence, explicit source
semantics and denominator separation.

### AD-NAV-380-03: Preserve variable and Archive identity

**Decision:** Register statistics additively and never delete them on disable.

**Reason:** Visualization links and user-configured Archive logging are
installation-owned persistent state.

## 7. Remaining Gates

| Gate | Status |
| --- | --- |
| implementation | passed offline |
| private Dark-Skin visual review | passed |
| complete Navimow check | passed |
| SAEF branch publication | open |
| standalone publication | open |
| metadata conformance | open |
| Symcon disabled-MQTT update | open |
| statistics activation | open |
| MQTT activation | not implied |
| mower command | not performed |

The next operation is a hash-bound SAEF and standalone publication followed by
one disabled-MQTT Symcon update. Statistics may then be enabled without storing
MQTT credentials; they remain `No Data` or `Stale` until a separately approved
receive-only observation supplies fresh evidence.
