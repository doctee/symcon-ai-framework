# 299 Combined MQTT Position Accounting Stabilization Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Local candidate implemented and focused offline tests passed;
publication and live gates closed

**Date:** 2026-08-09

## 1. Implemented Scope

The local candidate changes:

- `distribution/NavimowAccount/module.php`;
- `tests/mqtt-pilot-checkpoints.php`;
- `fixtures/mqtt/position-accounting-segments.json`;
- `fixtures/mqtt/README.md`.

No standalone repository, Symcon installation or remote branch was changed.

## 2. Runtime Behavior

The Account now:

1. rolls current position-segment counters into the active pilot registry
   before every ephemeral-state clear;
2. resets the aggregate when a new pilot session starts;
3. exposes only coordinate-free pilot totals;
4. records monotonic totals, segment sequence and reset count in checkpoints;
5. records cumulative episode and rotation sequences in checkpoints;
6. continues to remove every coordinate and MQTT credential on disconnect.

The existing `MqttPositionDiagnostic` remains the bounded current-segment
projection. REST remains the only public device-state authority.

## 3. Compatibility

The pilot registry keeps format version 2. The new `positionAccounting` member
is additive and defaults to an empty counter set when reading an older registry.
Historical checkpoints gain zero defaults for the additive fields.

No property, public variable, profile, action, module GUID, Archive contract or
MQTT subscription changes.

## 4. Offline Evidence

The focused MQTT suite proves:

- one position segment is accumulated;
- `ApplyChanges()` still clears its coordinate projection;
- a second segment continues from the prior aggregate;
- `1 + 2` received samples becomes pilot total `3`;
- segment sequence becomes `2` and reset count becomes `1`;
- old checkpoint and registry projections migrate without private-field leaks;
- pilot summary remains below 16 KiB;
- Account ingestion, shadow reconciliation and transport lifecycle remain
  unchanged.

The synthetic fixture generalizes the observed `801 -> 11` raw reset into a
monotonic total of `812` without storing a coordinate.

## 5. Architecture Decisions

### AD-NAV-1258: Roll up before clearing

The last exact segment counters are available immediately before cleanup. This
boundary avoids per-position-message writes to a second registry.

### AD-NAV-1259: Keep retained samples segment-local

Retained samples are a bounded storage gauge, not a cumulative reception
counter. Summing them would misrepresent downsampling and eviction.

### AD-NAV-1260: Preserve additive registry compatibility

Missing accounting state maps to zero for the current session. No destructive
migration or new persistent attribute is required.

## 6. Remaining Gates

| Gate | Status |
|---|---|
| focused functional tests | PASS |
| PHPCS for changed productive/test files | PASS |
| full PHPStan | PASS |
| complete repository check | PASS |
| private harness position-accounting adaptation | PASS |
| malformed-registry cleanup regression | PASS |
| dedicated deadline-closure mode | DEFERRED, FAIL-CLOSED |
| commit | NOT PERFORMED |
| push/PR | NOT PERFORMED |
| standalone publication | NOT PERFORMED |
| Symcon update | NOT PERFORMED |
| MQTT activation | NOT PERFORMED |

## 7. Next Step

Freeze the verified local fileset and prepare separate publication,
disabled-update and future pilot gates. A dedicated deadline-closure mode
remains a separate improvement after the new checkpoint schema exists live.
