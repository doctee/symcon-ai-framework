# 88 MQTT Partial Payload Parser Design and Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline fixture-backed parser complete; productive integration blocked
**Date:** 2026-07-27
**Scope:** Parse and accumulate the first sanitized MQTT location fixtures

## 1. Purpose

This step turns the receive-only evidence from
`87-mqtt-wss-private-capture-report.md` into an executable offline contract.

It implements:

- exact per-device topic validation;
- bounded JSON parsing;
- fixture-backed parsing of partial `location` arrays;
- explicit absent, null and unknown-field handling;
- timestamp-ordered accumulation;
- regression checks for accepted and rejected inputs.

It does not:

- add MQTT transport to the installable Symcon distribution;
- connect to Navimow or any broker;
- publish MQTT messages;
- map numeric MQTT states to `NAVIMOW.VehicleState`;
- change existing variables, actions, profiles or archive logging;
- expose mower coordinates as productive variables.

## 2. Evidence Boundary

The parser contract is based only on the two promoted location fixtures:

```text
fixtures/mqtt/location-pose-partial.json
fixtures/mqtt/location-type-3-partial.json
```

Both payloads have:

- a JSON array root;
- one non-empty object;
- an integer millisecond `time`;
- an integer `type`.

Only the larger payload additionally contains pose fields and integer
`vehicleState`.

No fixture exists yet for:

- `state`;
- `event`;
- `attributes`;
- active mowing or docking transitions;
- battery or online state over MQTT.

Those channel contracts therefore fail closed.

## 3. Candidate Components

The offline candidate consists of:

```text
candidate/MqttPayloadException.php
candidate/MqttPayloadParser.php
candidate/MqttPartialStateAccumulator.php
tests/mqtt-parser.php
```

The `candidate/` location is deliberate. These classes are analysis artifacts,
not part of `distribution/` and not loaded by the productive module.

## 4. Topic Contract

The parser accepts only one of these exact topics for the configured device:

```text
/downlink/vehicle/{deviceId}/realtimeDate/state
/downlink/vehicle/{deviceId}/realtimeDate/event
/downlink/vehicle/{deviceId}/realtimeDate/attributes
/downlink/vehicle/{deviceId}/realtimeDate/location
```

The device ID must be non-empty and must not contain:

```text
/ # +
```

Exact string comparison prevents:

- wildcard expansion;
- cross-device acceptance;
- prefix or suffix confusion;
- accidental acceptance of a new topic.

Topic acceptance alone does not approve a payload contract. Only `location`
currently has fixture-backed parsing.

## 5. Resource Bounds

The parser enforces:

| Boundary | Limit |
| --- | ---: |
| payload size | 1 MiB |
| JSON depth | 32 |
| location entries per payload | 64 |
| fields per entry | 64 |

The payload must be valid UTF-8 JSON. The location root must be a non-empty
array, and every entry must be a non-empty JSON object.

These bounds are parser-defense limits, not claims about vendor maxima.

## 6. Field Contract

Fixture-backed integer fields:

```text
time
type
vehicleState
```

Fixture-backed numeric fields:

```text
postureTheta
postureX
postureY
mowingPercentage
```

Numeric pose values may be JSON numbers or finite numeric strings because the
capture showed `postureTheta` as a string. They are normalized to finite
floating-point values.

Every location entry requires integer `time`. A timestamp-less patch cannot be
ordered safely and is rejected.

`vehicleState` remains an unmodified integer. The parser makes no semantic
mapping from that integer to REST state names.

## 7. Partial Patch Contract

Each array entry becomes a patch with:

```text
fields
presentFields
nullFields
unknownFields
sourceTimestamp
```

The distinctions are intentional:

- a present valid value is eligible to update accumulated state;
- an absent field produces no update;
- an explicit null is reported but does not clear known state;
- an unknown field name is reported but its value is not retained;
- no missing field is invented.

This prevents the smaller observed message from clearing pose or state values
obtained from the preceding larger message.

## 8. Accumulation and Ordering

`MqttPartialStateAccumulator` holds only the latest accepted fixture-backed
fields and the latest source timestamp.

Rules:

1. The first valid patch is accepted.
2. A newer patch updates only fields that it actually carries.
3. A patch older than the last accepted timestamp is ignored atomically.
4. Reapplying the same patch is idempotent.
5. Explicit null fields do not clear state.
6. Unknown field values never enter accumulated state.

Equal timestamps remain admissible because future evidence may show multiple
complementary partial objects for one source instant. A later evidence step
must revisit this choice if equal-timestamp conflicts are observed.

The accumulator is process-local and has no persistence contract. It is not a
replacement for REST state, Symcon variables or runtime diagnostics.

## 9. State Authority

The productive authority remains unchanged:

| Data | Current authority |
| --- | --- |
| vehicle state | REST |
| online state | REST |
| battery level | REST |
| status timestamp | REST |
| MQTT numeric state | evidence only |
| MQTT coordinates | private evidence only |

The candidate parser may preserve synthetic pose fields for regression testing.
That does not approve public coordinate fixtures beyond the existing sanitized
set, productive storage, archive logging or user-facing variables.

## 10. Regression Coverage

The dedicated offline test covers:

- both promoted location fixtures;
- numeric-string normalization;
- raw integer preservation of `vehicleState`;
- partial update accumulation;
- absent-field retention;
- explicit-null retention;
- unknown-field value exclusion;
- out-of-order rejection;
- duplicate idempotence;
- cross-device topic rejection;
- wildcard topic rejection;
- unverified-channel rejection;
- wrong root and empty array rejection;
- type mismatch rejection;
- timestamp-less patch rejection;
- invalid UTF-8 JSON rejection;
- oversized payload rejection.

No network, OAuth, MQTT broker, mower or Symcon installation is used by these
tests.

## 11. Architecture Decisions

### AD-NAV-313: Keep the parser outside the productive distribution

**Decision:** Implement the fixture-backed contract under `candidate/`.

**Rationale:** Transport and partial shape are proven, but active semantics and
Symcon topology are not.

**Consequence:** Offline behavior can mature without silently changing the
private-pilot module.

### AD-NAV-314: Accumulate partial patches instead of replacing snapshots

**Decision:** Update only valid fields present in each accepted entry.

**Rationale:** Consecutive captured location messages had different field
sets.

**Consequence:** Omission cannot erase a previously known value.

### AD-NAV-315: Treat explicit null as non-destructive evidence

**Decision:** Report explicit null fields and ignore them for accumulation.

**Rationale:** No fixture proves that null means a vendor-requested state
deletion.

**Consequence:** A future null-clearing rule requires independent evidence and
a new architecture decision.

### AD-NAV-316: Require source time and reject older patches

**Decision:** Require integer `time` and atomically ignore older patches.

**Rationale:** WSS delivery, reconnects and mixed partial messages must not
regress known state.

**Consequence:** Timestamp-less entries fail closed.

### AD-NAV-317: Keep numeric MQTT vehicle state unmapped

**Decision:** Preserve `vehicleState` as an integer evidence field only.

**Rationale:** One docked observation cannot define the numeric state domain.

**Consequence:** MQTT cannot update `NAVIMOW.VehicleState`.

### AD-NAV-318: Reject unverified channel payloads

**Decision:** Recognize exact channel topics but parse only fixture-backed
`location`.

**Rationale:** Subscription acknowledgement does not prove payload shape.

**Consequence:** `state`, `event` and `attributes` require sanitized fixtures
before parser support.

## 12. Validation

The required gates are:

```bash
php case-studies/navimow/tests/mqtt-parser.php
vendor/bin/phpcs \
  case-studies/navimow/candidate/MqttPayloadException.php \
  case-studies/navimow/candidate/MqttPayloadParser.php \
  case-studies/navimow/candidate/MqttPartialStateAccumulator.php \
  case-studies/navimow/tests/mqtt-parser.php
make check
git diff --check -- case-studies/navimow
```

## 13. Decision

**Offline partial location parser: COMPLETE.**

**Offline timestamp-aware accumulator: COMPLETE.**

**Active MQTT semantics: BLOCKED pending comparison evidence.**

**Productive MQTT integration: NO-GO.**

**Existing Symcon variables and archive logging: UNCHANGED.**

## 14. Recommended Next Step

Create `89-mqtt-active-rest-comparison-capture-procedure.md`.

The next run should remain receive-only and use a scheduled or official-app
start. It should collect bounded, contemporaneous:

- exact-topic MQTT messages;
- read-only REST status snapshots;
- operator-observed phase markers for Docked, Running, Docking and Docked.

It must send no MQTT publish and no mower command. The purpose is to establish
numeric state semantics, event timing, channel activity and REST/MQTT
reconciliation before any Symcon shadow transport is designed.
