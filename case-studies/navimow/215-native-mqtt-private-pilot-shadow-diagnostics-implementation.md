# 215 Native MQTT Private Pilot Shadow Diagnostics Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation and regression complete; publication,
Symcon update and live use remain closed
**Date:** 2026-07-29
**Scope:** Implement the bounded manual MQTT-shadow inspection contract from
step 214

## 1. Purpose

Step 214 selected the existing Account diagnostic API instead of temporary
Symcon variables for private-pilot visibility.

This step implements:

1. a version-2 `GetMqttDiagnostics()` contract;
2. one identity-free semantic shadow observation;
3. fail-closed empty, malformed and multi-device behavior;
4. a private side-by-side MQTT-hint and REST-authoritative projection;
5. bounded harness retention of that context;
6. synthetic fixtures and regression coverage;
7. complete Navimow offline validation.

No module was published. No Symcon installation, MQTT connection, OAuth state,
service or mower was accessed or changed.

## 2. Productive Delta

Exactly one productive module file changed:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

`GetMqttDiagnostics()` now reports:

```text
formatVersion: 2
shadow.trackedDeviceCount
shadow.pendingReconciliationCount
shadow.observation
```

The observation contains:

```text
status
authority
lastSourceTimestamp
lastReceivedAt
ageSeconds
fields.vehicleState
fields.batteryLevel
fields.mowingPercentage
fields.locationType
fields.locationVehicleStateCode
```

The change does not alter:

- MQTT parser acceptance;
- topic subscriptions;
- Receiver forwarding;
- MQTT credential handling;
- lifecycle or reconnect behavior;
- REST reconciliation;
- device variables;
- command behavior;
- forms, metadata or topology;
- Archive Control logging.

## 3. Observation State Machine

The projection returns one of four states:

| State | Condition | Semantic values |
|---|---|---|
| `unavailable` | valid shadow container with no tracked device | all `null` |
| `available` | exactly one structurally valid tracked shadow | bounded allowlist |
| `ambiguous` | more than one tracked device | all `null` |
| `invalid` | malformed container, key, state or timestamp | all `null` |

The authority field is always:

```text
mqtt-hint
```

It prevents a consumer from presenting the projection as confirmed public
device state.

## 4. Single-Device Boundary

The implementation exposes semantic values only when exactly one device shadow
is tracked.

The internal device key must match the existing 64-character lowercase
SHA-256 shape. The key is used for structural validation only and is never
returned.

For two or more devices:

```text
status: ambiguous
timestamps: null
fields: null
```

No newest-device or first-device heuristic is used. This prevents accidental
association with the wrong REST instance.

## 5. Value Projection

The implementation uses a fixed output object. Unknown retained fields cannot
create output keys.

Validation:

| Field | Projection rule |
|---|---|
| `vehicleState` | integer `0..11`, otherwise `null` |
| `batteryLevel` | finite integer or float `0..100`, otherwise `null` |
| `mowingPercentage` | finite integer or float `0..100`, otherwise `null` |
| `locationType` | non-negative bounded integer, otherwise `null` |
| `locationVehicleStateCode` | non-negative bounded integer, otherwise `null` |
| `lastSourceTimestamp` | positive integer or `null` |
| `lastReceivedAt` | positive integer |

A malformed supported field is omitted semantically by returning `null`.
Malformed container, state or timestamp structure changes the entire
observation to `invalid`.

The age calculation is:

```text
max(0, currentTimestamp - lastReceivedAt)
```

A future synthetic receipt timestamp therefore cannot produce a negative age.

## 6. Privacy Result

The productive diagnostic result exposes no:

- device ID or hashed device key;
- serial number;
- topic or subscription;
- raw MQTT payload;
- credential or Authorization value;
- endpoint or host;
- ObjectID;
- coordinate, route, map or geometry.

The existing parser behavior remains unchanged: geometry is validated but not
retained.

The exact active diagnostic fixture remains synthetic and contains no private
installation data.

## 7. Read-Only Result

The diagnostic method:

- reads existing attributes and configuration;
- derives age at call time;
- writes no attribute;
- writes no variable;
- starts no timer;
- touches no MQTT/WebSocket property;
- performs no REST request.

Tests compare the complete persistent Account state before and after diagnostic
reads.

## 8. Private Probe Delta

The private pilot probe advances from snapshot format version 1 to version 2.

It adds:

```text
mqttHint
restAuthoritative
comparison
```

### MQTT hint

The private projection contains only the bounded Account observation:

- availability;
- symbolic and numeric vehicle state;
- battery;
- mowing progress;
- location classification codes;
- source, receipt and age timestamps.

The symbolic state is derived from the frozen `NAVIMOW.VehicleState` mapping.

### REST authority

The existing Device variables supply:

- numeric and symbolic vehicle state;
- battery level;
- last status-update timestamp.

The section is explicitly labeled:

```text
authority: rest
```

### Comparison

The existing Account statistics supply:

- last comparison result;
- last comparison timestamp.

No new comparison, REST request or reconciliation is triggered by the probe.

## 9. Probe Gate

The private probe accepts:

```text
unavailable
available
```

It rejects the overall checkpoint contract for:

```text
ambiguous
invalid
```

`unavailable` is valid immediately after activation and before the first
accepted MQTT message. During a known mowing transition it remains an
operational finding for the observer and harness evidence.

The probe remains free of:

- Symcon object creation or deletion;
- property or configuration mutation;
- `RequestAction()` or `SetValue()`;
- MQTT Connect or Disconnect;
- MQTT publish;
- mower commands.

## 10. Harness Retention

Each already bounded private snapshot entry now retains:

```text
mqttHint
restAuthoritative
comparison
```

The existing maximum snapshot count and atomic state-file rules remain
unchanged. No new unbounded history was introduced.

The context is observational. It does not alter:

- minimum pilot duration;
- 72-hour deadline;
- required mowing-cycle count;
- required credential rotation;
- stop conditions;
- mandatory cleanup.

## 11. Regression Coverage

A dedicated offline test covers:

| Case | Result |
|---|---|
| disabled empty shadow | version 2, `unavailable`, read-only |
| enabled empty shadow | `unavailable` |
| one complete device shadow | exact available projection |
| partial and invalid semantic fields | invalid fields become `null` |
| unsupported retained field | not projected |
| future receipt timestamp | age clamped to zero |
| two tracked shadows | `ambiguous`, no identity or values |
| malformed JSON root | `invalid` |
| malformed device state | `invalid` |
| ApplyChanges cleanup | zero counts and `unavailable` |
| identity scan | no raw or hashed device key |

Existing tests additionally cover:

- accumulator restart reset;
- parser geometry discard;
- Account variable non-mutation;
- bounded aggregate diagnostics;
- REST reconciliation comparison;
- lifecycle cleanup;
- Receiver privacy and boundedness.

## 12. Changed Artifacts

Productive and public case-study artifacts:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/fixtures/mqtt/bounded-diagnostics-shadow-active.json
case-studies/navimow/fixtures/mqtt/README.md
case-studies/navimow/tests/mqtt-shadow-diagnostics.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
case-studies/navimow/tools/check-mqtt-shadow.sh
case-studies/navimow/README.md
case-studies/navimow/215-native-mqtt-private-pilot-shadow-diagnostics-implementation.md
```

Private artifacts:

```text
private/navimow-capture/native-mqtt-private-pilot/PilotHarness.php
private/navimow-capture/native-mqtt-private-pilot/offline-test.php
private/navimow-capture/native-mqtt-private-pilot/symcon-readonly-probe.php
private/navimow-capture/native-mqtt-private-pilot/README.md
```

Private artifacts remain outside the standalone module publication.

Frozen SHA-256 evidence:

```text
39fbc2183b0d5a119e2a4ba5cfdfcc81373b8a2f0b5be517a8c8cddb0cbbc069  distribution/NavimowAccount/module.php
1500dfa49e90c26d1b574d9b4abf27eedc7f611ffe0b7efaa160a0a5b5657e66  bounded-diagnostics-shadow-active.json
ef243b8e7d9b19f5efd48646cdf97143d7d275877c599b42aefd070673962a54  mqtt-shadow-diagnostics.php
49b2de3f4ad6ed8d7101a8063d49223ced10eae4937077eb7bf0404943d7d9a5  private/PilotHarness.php
eef39df14197d179764d1231a3378751bf4476e902fbeefc09cc33d130de8d92  private/offline-test.php
35b1158038a049ad37700c46a033117362434d7925efa9b938a78ac741568e6b  private/symcon-readonly-probe.php
```

## 13. Validation

Targeted diagnostic regression:

```text
php case-studies/navimow/tests/mqtt-shadow-diagnostics.php
```

Result:

```text
PASS
Navimow MQTT shadow diagnostic checks passed.
```

Private harness validation:

```text
sh private/navimow-capture/native-mqtt-private-pilot/validate.sh
```

Result:

```text
PASS
syntax, synthetic-clock behavior, mutation scan and privacy scan
```

Complete Navimow gate:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
PASS
fixtures
REST client and authentication
MQTT envelope and parser
Receiver and Account ingestion
shadow diagnostics and reconciliation
transport lifecycle
distribution validation
PHPCS
PHPStan
```

Additional coding-standard check for the new test and private artifacts:

```text
vendor/bin/phpcs \
  case-studies/navimow/tests/mqtt-shadow-diagnostics.php \
  private/navimow-capture/native-mqtt-private-pilot/PilotHarness.php \
  private/navimow-capture/native-mqtt-private-pilot/offline-test.php \
  private/navimow-capture/native-mqtt-private-pilot/symcon-readonly-probe.php
```

Result:

```text
PASS
```

## 14. Architecture Decisions

### AD-NAV-773: Project a fixed schema

**Decision:** Return every allowed semantic key with either a validated value or
`null`.

**Reason:** Consumers receive a stable bounded contract, while retained unknown
fields cannot expand the public result.

### AD-NAV-774: Validate the internal anonymous key without exposing it

**Decision:** Require the established SHA-256 key shape for an available
single-device observation.

**Reason:** Structural corruption fails closed without turning the key into a
public pseudonymous identifier.

### AD-NAV-775: Treat multiple shadows as ambiguity

**Decision:** Return no semantic sample when more than one device is tracked.

**Reason:** Correct REST/MQTT association is more important than showing the
newest available hint.

### AD-NAV-776: Keep malformed fields local

**Decision:** Invalid allowlisted scalar fields become `null`; malformed state
containers and timestamps invalidate the observation.

**Reason:** A bad optional value should not reveal raw state, while broken
temporal structure makes the complete sample unreliable.

### AD-NAV-777: Version both productive and private projections

**Decision:** Advance Account diagnostics and private snapshots to version 2.

**Reason:** The new semantic fields are intentional contract changes and should
not be consumed silently as version 1.

### AD-NAV-778: Retain side-by-side evidence only in the bounded harness

**Decision:** Store MQTT hint, REST authority and comparison context in existing
bounded private snapshots.

**Reason:** Manual review becomes possible without Symcon variables, Archive
Control changes or a second persistent module store.

## 15. Gate Decision

| Gate | Decision |
|---|---|
| design implementation | PASS |
| fixed version-2 projection | PASS |
| REST authority | PRESERVED |
| identity and geometry exclusion | PASS |
| productive diagnostic read-only behavior | PASS |
| private probe mutation scan | PASS |
| private harness regression | PASS |
| complete Navimow offline gate | PASS |
| standalone publication | CLOSED |
| Symcon update | CLOSED |
| live diagnostic use | CLOSED |
| MQTT activation | CLOSED |
| current live MQTT state | DISABLED AND CREDENTIAL-FREE |

## 16. Next Step

Proceed with:

```text
216-native-mqtt-private-pilot-shadow-diagnostics-publication-plan.md
```

That step should:

1. freeze the exact productive standalone delta;
2. prove private harness files cannot enter the publication;
3. run the official/fallback metadata validation path;
4. define publication and remote verification;
5. define the disabled Symcon update and version-2 read-only check;
6. keep pilot initialization, persistence acceptance and activation as later
   explicit gates.
