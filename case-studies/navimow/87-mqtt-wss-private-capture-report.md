# 87 MQTT/WSS Private Capture Report

**Case study:** Navimow native IP-Symcon module
**Status:** Docked receive-only transport gate passed; active payload semantics pending
**Date:** 2026-07-27
**Scope:** Close one private docked MQTT/WSS run and promote minimal sanitized fixtures

## 1. Purpose

This step closes the first live run prepared by
`86-mqtt-wss-private-capture-procedure.md`.

It records:

- OAuth and MQTT transport outcome;
- exact topic-subscription outcome;
- received channel and payload shapes;
- no-publish and no-command evidence;
- privacy closure;
- fixture promotion;
- the remaining gate before MQTT can influence productive variables.

The run changed no Symcon object, module configuration, public variable,
archive setting or mower state.

## 2. Authorization and Operating Condition

The user explicitly started the bounded private receive-only procedure.

The mower remained docked. The tool was authorized only to:

- perform OAuth token exchange;
- discover the account mower;
- retrieve temporary MQTT credentials;
- connect through WSS;
- subscribe to four exact downlink topics;
- receive for three minutes.

It was not authorized or capable of:

- publishing an MQTT message;
- sending a mower command;
- changing a Symcon object;
- starting, pausing, resuming, stopping or docking the mower.

## 3. Terminal Result

The observed terminal summary was:

```text
Connecting with TLS verification and exact-topic allowlist...
Received location message #1 (115 bytes)
Received location message #2 (33 bytes)
Result: completed; subscriptions=4/4; messages=2
```

The capture duration was approximately:

```text
180 seconds
```

## 4. Transport Gate

| Gate | Result |
| --- | --- |
| OAuth exchange | PASS |
| mower discovery | PASS |
| MQTT credential retrieval | PASS |
| WSS connection | PASS |
| TLS certificate verification | PASS |
| exact topics requested | 4 |
| exact topics acknowledged | 4 |
| QoS | 0 |
| wildcard topics | 0 |
| unknown-topic messages | 0 |
| clean disconnect | PASS |
| MQTT publishes | 0 |
| mower commands | 0 |

The report records one disconnect. It occurred during the intentional clean end
of the bounded capture and is not classified as a transport failure.

## 5. Channel Result

| Channel | Messages |
| --- | ---: |
| `state` | 0 |
| `event` | 0 |
| `attributes` | 0 |
| `location` | 2 |
| unknown | 0 |

No conclusion may be drawn that the zero-message channels are unsupported.
They may emit only on relevant changes or after different operating events.

Receiving two `location` messages while docked proves that this topic is not
limited to active mowing.

## 6. Credential Response Shape

The successful response contains:

```text
code
desc
data.userId
data.userName
data.pwdInfo
data.ak
data.mqttHost
data.mqttUrl
data.subTopics
```

The observed logical `subTopics` values were:

```text
mapChange
realtime
```

These labels are not treated as broker topic strings. The four exact downlink
topics remain derived from current official and independent source evidence.

## 7. First Location Payload Shape

The larger message was a JSON array with one object containing:

```text
postureTheta: string
postureX: number
postureY: number
time: integer milliseconds
type: integer
vehicleState: integer
```

Important type findings:

- `postureTheta` arrived as a string, not a JSON number;
- coordinates arrived as values that require numeric parsing;
- `vehicleState` is numeric and does not use the REST strings such as
  `isDocked`;
- the payload root is an array, not an object.

The mower was physically docked, but one observation is insufficient to define
the general numeric `vehicleState` mapping.

## 8. Second Location Payload Shape

The smaller message was also a JSON array with one object, but it contained
only:

```text
time: integer milliseconds
type: integer
```

It omitted:

- pose;
- coordinates;
- vehicle state;
- battery;
- mowing progress.

This is decisive parser evidence: a later message on the same topic is not
necessarily a complete replacement snapshot.

Missing fields must remain absent. They must not be converted to null and must
not clear a previously known value.

The semantics of `type: 1` and `type: 3` remain open. Calling `type: 3` a
heartbeat would currently be an unsupported inference.

## 9. REST/MQTT Comparison Result

The run did not capture a contemporaneous REST status snapshot inside the MQTT
tool. Physical Docked observation provides operating context but not a
field-level protocol mapping.

Current conclusion:

```text
MQTT transport: proven
MQTT location envelope: partially proven
MQTT numeric state semantics: not proven
REST/MQTT precedence change: not approved
```

REST remains authoritative for:

- `VehicleState`;
- `Online`;
- `BatteryLevel`;
- `LastStatusUpdate`.

MQTT remains evidence-only.

## 10. Promoted Fixtures

The following reviewed fixtures were promoted:

```text
fixtures/mqtt/credential-shape.json
fixtures/mqtt/location-pose-partial.json
fixtures/mqtt/location-type-3-partial.json
```

Promotion changes:

- device ID replaced with `DEVICE_001`;
- MQTT endpoint and credentials redacted;
- timestamps replaced with synthetic millisecond integers;
- coordinates replaced with synthetic numbers;
- payload keys, nesting and value types retained.

The fixture validator proves:

- all files are valid JSON;
- exact topics contain no wildcard;
- credential placeholders remain redacted;
- pose and numeric state types remain stable;
- the partial message retains only `time` and `type`;
- absent `vehicleState` remains absent;
- prohibited private strings are absent.

## 11. Private Evidence Closure

Exact private evidence remains under:

```text
private/navimow-capture/output/mqtt/
```

The machine-readable closure records:

- UTC capture time;
- authorization scope;
- connection and subscription counts;
- channel message counts;
- zero publish and command attempts;
- SHA-256 hashes of raw report, messages and credential response;
- file-permission remediation;
- sensitive-scalar leak scan;
- public fixture promotion.

After the run, raw and sanitized files were set to mode `600`.

The first run revealed that two Python-created files initially inherited mode
`644` inside the private mode-`700` directory. The current capture tool now
sets process `umask 077` in both shell and Python before creating files.

The ignored private scripts were modified by this hardening before their hashes
were calculated. The closure therefore does not falsely claim an exact
capture-time source hash. It records the exact raw evidence hashes and the
current hardened successor hashes separately.

## 12. Validation

Validation passed:

- sanitized evidence compared against eight sensitive raw scalar values with
  zero retained matches;
- raw and sanitized private files have mode `600`;
- three public JSON fixtures parse successfully;
- dedicated MQTT fixture validator passes;
- private Bash syntax passes;
- private Python compilation passes;
- private no-network no-publish validation passes.

The complete repository gate also passed after this report and fixture set were
completed, including PHPStan, PHPCS, all existing Navimow regressions and the
distribution validator.

## 13. Readiness Matrix

| Gate | Decision |
| --- | --- |
| OAuth-to-MQTT credential flow | PASS |
| WSS/TLS connection | PASS |
| exact subscription acknowledgement | PASS |
| receive-only enforcement | PASS |
| docked location payload | PASS |
| partial-array parser requirement | PASS |
| state/event/attributes payload fixtures | BLOCKED |
| numeric state mapping | BLOCKED |
| active location cadence | BLOCKED |
| REST/MQTT timing comparison | BLOCKED |
| offline parser implementation | CONDITIONAL GO |
| Symcon shadow transport | NO-GO |
| productive variable updates | NO-GO |

## 14. Architecture Decisions

### AD-NAV-308: Accept the Smart Home WSS transport contract

**Decision:** Treat OAuth-derived WSS MQTT reception on four exact downlink
topics as proven for the private pilot account.

**Rationale:** Connection and all subscriptions completed successfully.

**Consequence:** Work may advance to fixture-backed parser design.

### AD-NAV-309: Model MQTT location as a stream of partial array entries

**Decision:** Parse every present field independently and distinguish absent
from null.

**Rationale:** Two consecutive messages on one topic had different field sets.

**Consequence:** Snapshot replacement semantics are prohibited.

### AD-NAV-310: Keep numeric MQTT states unmapped

**Decision:** Do not infer `vehicleState: 1` from the physical Docked context
alone.

**Rationale:** One operating state does not define the state code domain or
transition behavior.

**Consequence:** REST remains authoritative until active comparison evidence
exists.

### AD-NAV-311: Preserve exact-topic subscriptions

**Decision:** Do not add logical `subTopics` values or wildcard subscriptions
to the broker allowlist.

**Rationale:** Current exact topics are acknowledged and sufficient for the
evidence track.

**Consequence:** New topics require separate evidence.

### AD-NAV-312: Enforce private file creation with `umask 077`

**Decision:** Harden both shell and Python processes rather than relying only
on directory permissions.

**Rationale:** Raw payload and report files initially inherited mode `644`.

**Consequence:** Future private evidence files default to owner-only access.

## 15. Decision

**Docked MQTT/WSS transport gate: PASS.**

**Initial MQTT fixture promotion: COMPLETE.**

**Productive MQTT state authority: NO-GO.**

**Mower commands during capture: NONE.**

**Existing variables and archive logging: UNCHANGED.**

## 16. Recommended Next Step

Create `88-mqtt-partial-payload-parser-design-and-implementation.md`.

The offline parser should be completed before the natural activity capture so
the next evidence can be validated immediately. After that, run one passive
scheduled or official-app-initiated mowing observation that captures MQTT and
contemporaneous REST without sending any command.
