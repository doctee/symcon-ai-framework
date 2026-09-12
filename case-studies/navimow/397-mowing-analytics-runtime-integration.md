# SAEF Step 397: Mowing Analytics Runtime Integration

**Case study:** Navimow native IP-Symcon module

**Status:** Default-disabled productive candidate implemented; publication and
live gates remain closed

**Date:** 2026-09-12

## 1. Purpose

This step integrates the pure reducer into the existing Device-owned Local Map
refresh without changing REST authority, MQTT direction or command behavior.

## 2. Configuration

The following properties are additive:

| Property | Default | Meaning |
|---|---:|---|
| `EnableMowingAnalytics` | `false` | Master gate for retained analytics |
| `StatisticsTimeZone` | `UTC` | Day, week and month boundary |
| `MetersPerLocalUnit` | `1.0` | Explicit coordinate scale |
| `CuttingWidthMeters` | `0.0` | Positive width enables area estimates |
| `CoverageCellSizeMeters` | `0.1` | Diagnostic raster resolution |
| `MowingRecencyWarningDays` | `7` | Due threshold |
| `MowingRecencyCriticalDays` | `14` | Overdue threshold |
| `StatisticsSubareas` | `[]` | Optional revision-bound polygons |

Defaults create no geometric coverage claim. A productive installation must
set its local time zone and verified physical calibration explicitly.

For the X450 used by this case study, the manufacturer specifies a 430 mm
cutting width. Its installation-specific value is therefore
`CuttingWidthMeters = 0.43`. The generic module default remains `0.0` because
other Navimow models must not inherit an unverified physical dimension. The
coordinate scale still requires an independent installation-specific check.

Manufacturer reference:
<https://de.navimow.com/products/navimow-x4-robot-lawn-mower>

## 3. Runtime Flow

After a fresh accepted Local Map refresh, the Device:

1. restores the bounded analytics state;
2. reduces the accepted scene;
3. serializes the validated state into a Device attribute;
4. projects the current geometry revision;
5. updates stable variables when values are available; and
6. sends the same projection to the HTML SDK tile.

Stale map rendering retains the stored projection but marks analytics stale.
Invalid configuration marks the status invalid and preserves the prior state.
Disabling analytics stops updates without deleting variables or history.

## 4. Stable Variable Contract

Global variables:

```text
MowingAnalyticsStatus
MowingAnalyticsUpdatedAt
LastRunDistance
LastRunDuration
LastRunEstimatedArea
LastRunAreaPerformance
```

For each public bound zone ID, stable variables are registered as:

```text
Zone<ID>CoverageEstimate
Zone<ID>EstimatedAreaToday
Zone<ID>EstimatedAreaWeek
Zone<ID>EstimatedAreaMonth
Zone<ID>LastMowedAt
Zone<ID>MowingRecency
Zone<ID>LatestRunDistance
Zone<ID>LatestRunDuration
```

The types and profiles are fixed. Existing variables are neither renamed nor
recreated. Optional subareas remain in the bounded analytics projection; they
are not yet materialized as dynamic public variables because their stable
identifier and Archive lifecycle require a separate contract.

## 5. Archive Contract

Productive module code contains no Archive Control call. It does not enable,
disable or alter logging or aggregation for existing or new variables.

The live installation already has 25 operator-selected Navimow variables under
standard Archive logging. Their Idents and logging settings must survive every
future rollout. After the new variables exist and pass identity checks, a
separate installation-owned one-shot gate may add all analytics variables to
standard logging. The map HTML and serialized attributes are not Archive
targets.

## 6. State And Command Boundaries

- REST remains authoritative for `VehicleState`, online state and commands.
- MQTT remains receive-only and supplies only diagnostic evidence.
- No Start, Stop or MQTT command is added.
- Pause, Resume and Dock behavior is unchanged.
- No OAuth, credential or Core lifecycle is changed.

## 7. Architecture Decisions

### AD-NAV-397-01: Own analytics in the Device instance

The Device already owns map geometry, accepted path projection and public zone
variables. Keeping analytics there avoids a second state owner.

### AD-NAV-397-02: Preserve variables when disabled

Deleting variables would detach Archive history. Disable changes status and
stops updates but preserves definitions and values.

### AD-NAV-397-03: Keep Archive mutation out of module lifecycle

Logging volume and retention are installation policy. A module update must not
silently change them.

## 8. Gate Result

| Gate | Result |
|---|---|
| Productive runtime integration | PASS offline |
| Existing variable stability | PASS synthetic |
| Existing Archive preservation by design | PASS |
| New-variable live Archive activation | CLOSED |
| Publication and Symcon update | CLOSED |
