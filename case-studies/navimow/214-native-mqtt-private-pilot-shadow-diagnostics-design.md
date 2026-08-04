# 214 Native MQTT Private Pilot Shadow Diagnostics Design

**Case study:** Navimow native IP-Symcon module
**Status:** Design complete; implementation, publication and live use remain
closed
**Date:** 2026-07-29
**Scope:** Define a bounded manual MQTT-shadow inspection contract before the
48-72-hour private pilot

## 1. Purpose

Step 213 implemented the private pilot observation harness. Its read-only
projection can prove lifecycle, transport, counter, OAuth, REST and archived
vehicle-state evidence, but the productive Account diagnostics currently expose
only these shadow aggregates:

```text
trackedDeviceCount
pendingReconciliationCount
```

The accepted internal MQTT shadow already retains selected semantic hints. A
manual observer therefore cannot yet answer:

1. which semantic MQTT hint was most recently accepted;
2. when it was received;
3. how old it is;
4. whether it agrees with the REST-authoritative public state;
5. whether MQTT supplied mowing progress or location classification data.

This step designs that missing visibility before the inactive pilot preflight.
It does not change the productive module, private harness, Symcon object tree,
archive configuration or live system.

## 2. Current Data Flow

The current receive-only path is:

```text
native MQTT Receiver
  -> bounded envelope and payload parsing
  -> semantic partial-state accumulator
  -> internal Account shadow attribute
  -> bounded reconciliation queue
  -> REST status read
  -> public Navimow Device variables
```

The public device variables remain REST-owned. MQTT is an early hint and
reconciliation trigger, not a second authoritative writer.

The internal accumulator accepts only:

| Field | Meaning | Current persistence |
|---|---|---|
| `vehicleState` | normalized vehicle-state code | internal shadow only |
| `batteryLevel` | reported battery percentage | internal shadow only |
| `mowingPercentage` | reported mowing progress | internal shadow only |
| `locationType` | numeric location classification | internal shadow only |
| `locationVehicleStateCode` | numeric state code from location payload | internal shadow only |
| `lastSourceTimestamp` | source timestamp of the newest accepted patch | internal shadow only |
| `lastReceivedAt` | local receipt timestamp | internal shadow only |

Device keys are SHA-256 derivatives used only inside the Account attribute.
They are not part of the proposed diagnostic output.

## 3. Authority Contract

The diagnostic extension must preserve this authority order:

```text
public state and automation decisions: REST
early observation and reconciliation hint: MQTT
manual pilot interpretation: REST value plus labeled MQTT hint
```

The MQTT sample must always be labeled as:

```text
diagnostic hint; not authoritative
```

No automation, command verification or public device variable may consume the
new diagnostic projection. A disagreement is evidence for analysis, not a
reason to overwrite the REST result.

## 4. Chosen Visibility Surface

The first pilot will use an extension of the existing read-only
`GetMqttDiagnostics()` result.

No temporary Symcon variables will be created.

Reasons:

- temporary variables mutate the live object tree;
- ownership and cleanup would need a separate lifecycle;
- Archive Control could accidentally retain private operational data;
- MQTT hint variables could be mistaken for authoritative device variables;
- a public variable contract should not be introduced solely for one pilot;
- the existing diagnostic method is already bounded and read-only.

Permanent optional MQTT diagnostic variables remain a possible later feature.
They require a separate variable-and-action contract, explicit visibility
defaults and logging disabled by default.

## 5. Diagnostic Schema

The implementation shall advance the diagnostics payload from
`formatVersion = 1` to `formatVersion = 2`.

The existing `shadow` counters remain. A new bounded observation is added:

```json
{
  "formatVersion": 2,
  "shadow": {
    "trackedDeviceCount": 1,
    "pendingReconciliationCount": 0,
    "observation": {
      "status": "available",
      "authority": "mqtt-hint",
      "lastSourceTimestamp": 0,
      "lastReceivedAt": 0,
      "ageSeconds": 0,
      "fields": {
        "vehicleState": 1,
        "batteryLevel": 78,
        "mowingPercentage": 42,
        "locationType": 0,
        "locationVehicleStateCode": 0
      }
    }
  }
}
```

The example values are structural examples only and are not live evidence.

### Observation status

Allowed values:

| Status | Meaning |
|---|---|
| `unavailable` | no valid tracked shadow state exists |
| `available` | exactly one valid tracked shadow state can be projected |
| `ambiguous` | more than one device is tracked; no sample is exposed |
| `invalid` | the retained internal state cannot be safely projected |

For `unavailable`, `ambiguous` and `invalid`, timestamp and field values must be
`null`. This prevents a newest-device heuristic from silently associating a
hint with the wrong REST device.

### Allowed fields

Only the accumulator allowlist may appear:

```text
vehicleState
batteryLevel
mowingPercentage
locationType
locationVehicleStateCode
```

Absent MQTT fields remain absent or are represented as `null` consistently
according to the implementation contract. Unknown keys must never pass through.

### Age

`ageSeconds` is calculated at diagnostic-read time:

```text
max(0, currentTimestamp - lastReceivedAt)
```

It is not persisted. A missing or invalid receipt timestamp makes the
observation `invalid`.

## 6. Value Validation

The projection is fail-closed.

| Value | Accepted diagnostic range |
|---|---|
| `vehicleState` | integer `0..11` |
| `batteryLevel` | integer or float `0..100` |
| `mowingPercentage` | integer or float `0..100` |
| `locationType` | bounded integer accepted by the parser contract |
| `locationVehicleStateCode` | bounded integer accepted by the parser contract |
| timestamps | positive integers, except a missing source timestamp may be `null` |

One malformed field must not be copied into the output. If the surrounding
state shape is malformed or unsupported, the complete observation becomes
`invalid`; raw retained content is never returned as an error detail.

## 7. Privacy Boundary

The projection must not expose:

- device ID, serial number or hashed device key;
- MQTT topic or subscription;
- raw MQTT payload;
- Authorization or WebSocket headers;
- MQTT credentials;
- endpoint, hostname or IP address;
- coordinate, geometry, route or map data;
- local ObjectID;
- installation path.

The parser currently validates the presence and shape of location geometry but
does not retain coordinates. This boundary remains unchanged.

Timestamps and mowing progress can still reveal private operating patterns.
The diagnostic result and all harness snapshots therefore remain private
operational evidence under `private/`.

## 8. Position Decision

No position or geometry variable is introduced for the pilot.

Position support would require a separate design covering:

- coordinate system and semantic meaning;
- precision reduction;
- retention and deletion;
- Archive Control defaults;
- frontend presentation;
- route reconstruction risk;
- vendor and payload stability;
- behavior when location messages are partial or stale.

The current pilot tests transport reliability and semantic reconciliation. It
does not justify expanding scope into route or garden-map retention.

## 9. Private Harness Projection

After the productive diagnostic method is implemented, the private
`symcon-readonly-probe.php` shall add a bounded side-by-side projection:

```json
{
  "mqttHint": {
    "availability": "available",
    "vehicleStateCode": 1,
    "vehicleState": "Running",
    "batteryLevel": 78,
    "mowingPercentage": 42,
    "locationType": 0,
    "locationVehicleStateCode": 0,
    "lastSourceTimestamp": 0,
    "lastReceivedAt": 0,
    "ageSeconds": 0
  },
  "restAuthoritative": {
    "vehicleStateCode": 1,
    "vehicleState": "Running",
    "batteryLevel": 78,
    "lastStatusUpdate": 0
  },
  "comparison": {
    "lastResult": "match",
    "lastComparedAt": 0
  }
}
```

The probe may map a vehicle-state code to a symbolic value only through the
existing fixed `NAVIMOW.VehicleState` profile or the frozen state map. It must
not copy installation-specific profile content.

The harness shall retain these fields only in its already bounded private
snapshot collection. They are diagnostic context, not additional completion
criteria for the 48-72-hour pilot.

## 10. Manual Check Contract

At each scheduled pilot checkpoint, the observer should be able to inspect:

1. current REST-authoritative vehicle state;
2. latest MQTT vehicle-state hint, if available;
3. MQTT hint age;
4. MQTT battery and mowing progress, if supplied;
5. last bounded REST comparison result and timestamp;
6. transport receive and reconciliation counters.

Interpretation:

| Observation | Meaning |
|---|---|
| fresh MQTT hint and later REST match | expected path |
| fresh MQTT hint and temporary mismatch | capture timing and await bounded REST reconciliation |
| repeated mismatch after REST reconciliation | pilot finding requiring evidence review |
| stale hint while mower is docked and quiet | may be normal |
| stale hint during a known mowing transition | transport or subscription finding |
| no hint with healthy connection and active mowing | ingress finding |
| ambiguous status | multi-device diagnostic limitation; do not infer association |

Manual inspection must not trigger an extra command or unbounded REST polling.

## 11. Lifecycle and Cleanup

The diagnostic observation follows the existing shadow lifecycle:

- it exists only while a valid internal shadow state exists;
- it is cleared when the shadow is disabled or cleaned up;
- it is not restored as semantic state after restart;
- it creates no independent persistent store;
- it creates no variable or archive record;
- it cannot keep MQTT credentials alive.

After mandatory pilot cleanup, the expected projection is:

```text
featureEnabled: false
trackedDeviceCount: 0
pendingReconciliationCount: 0
observation.status: unavailable
```

## 12. Implementation Scope

The next implementation step may change only:

1. Account diagnostic projection helpers and `GetMqttDiagnostics()`;
2. MQTT diagnostic offline tests and sanitized fixtures;
3. private pilot read-only probe and harness tests;
4. private harness documentation;
5. the Navimow case-study index and implementation report.

It must not change:

- MQTT parsing or accepted fields;
- MQTT subscriptions;
- credential retrieval or storage;
- reconnect behavior;
- REST reconciliation timing;
- public device variables;
- command behavior;
- Symcon forms or instance topology;
- Archive Control logging.

## 13. Regression Matrix

The implementation gate must cover at least:

| Case | Expected result |
|---|---|
| disabled and empty shadow | `unavailable`, no values |
| enabled and empty shadow | `unavailable`, no values |
| one valid device state | `available`, allowlisted values only |
| one partially populated state | `available`, missing values handled consistently |
| two tracked devices | `ambiguous`, no sample values |
| malformed root | `invalid`, no retained content leaked |
| malformed device state | `invalid`, no retained content leaked |
| unsupported field | not projected |
| invalid timestamp | `invalid` |
| future receipt timestamp | age clamped to zero |
| disabled cleanup | counters zero and `unavailable` |
| restart restore boundary | no old semantic sample reappears |
| private probe projection | MQTT hint and REST authority clearly labeled |
| privacy scan | no identity, topic, credential or geometry output |
| mutation scan | probe remains read-only |

The complete Navimow offline gate and distribution validation must pass before
publication is considered.

## 14. Publication and Live Gates

This design grants no publication or live authorization.

Required sequence:

```text
design
  -> offline implementation and regression
  -> exact-delta review
  -> standalone publication plan
  -> publication
  -> disabled Symcon update
  -> inactive preflight and harness initialization
  -> separate persistence acceptance
  -> receive-only pilot activation
```

The inactive preflight must use the published diagnostic implementation. It
must not be performed against a locally divergent or temporary module version.

## 15. Architecture Decisions

### AD-NAV-766: Keep REST authoritative

**Decision:** MQTT shadow values remain labeled diagnostic hints and cannot
write public device state.

**Reason:** The accepted architecture uses MQTT for timely wake-up and REST for
confirmed public state.

### AD-NAV-767: Use the diagnostic API instead of temporary variables

**Decision:** Extend `GetMqttDiagnostics()` and the private harness projection.

**Reason:** This provides manual visibility without object-tree mutation,
archive risk or a premature public variable contract.

### AD-NAV-768: Fail closed for multiple tracked devices

**Decision:** Expose a sample only when exactly one valid device shadow is
tracked.

**Reason:** A newest-entry heuristic could pair MQTT data with the wrong REST
device while hiding the ambiguity.

### AD-NAV-769: Keep geometry discarded

**Decision:** Do not retain or expose coordinates, geometry, routes or maps.

**Reason:** They are unnecessary for transport verification and introduce a
materially larger privacy and retention surface.

### AD-NAV-770: Version the additive diagnostic contract

**Decision:** Advance `GetMqttDiagnostics()` to `formatVersion = 2`.

**Reason:** Existing private consumers can reject or migrate explicitly instead
of silently assuming the version-1 aggregate-only shape.

### AD-NAV-771: Compose MQTT and REST views only in the private probe

**Decision:** The Account diagnostic method exposes the MQTT hint; the private
probe reads the existing Device variables and labels the side-by-side result.

**Reason:** This preserves module ownership and avoids making the Account
instance a second owner of public device state.

### AD-NAV-772: Preserve the existing ephemeral lifecycle

**Decision:** The observation is derived from the current shadow and receives
no independent persistence.

**Reason:** Cleanup, disable and restart semantics must remain deterministic and
must not leave stale pilot data behind.

## 16. Gate Decision

| Gate | Decision |
|---|---|
| manual MQTT visibility need | CONFIRMED |
| REST authority | PRESERVED |
| temporary Symcon variables | REJECTED FOR THIS PILOT |
| bounded diagnostic projection | APPROVED FOR IMPLEMENTATION |
| position or geometry retention | CLOSED |
| productive implementation | NOT STARTED |
| publication | CLOSED |
| live Symcon access | NOT AUTHORIZED |
| MQTT activation | CLOSED |
| current MQTT state | DISABLED AND CREDENTIAL-FREE |

## 17. Next Step

Proceed with:

```text
215-native-mqtt-private-pilot-shadow-diagnostics-implementation.md
```

That step should:

1. implement the version-2 bounded shadow observation;
2. extend the private read-only probe with labeled MQTT and REST views;
3. add malformed, multi-device, cleanup, privacy and mutation regressions;
4. run the complete Navimow offline gate;
5. freeze the exact publication candidate;
6. perform no publication, Symcon access, MQTT activation or mower action.
