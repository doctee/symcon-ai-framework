# 117 Native MQTT Receiver Bounded Diagnostics Design and Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation complete; publication and live use not yet
approved
**Date:** 2026-07-28
**Scope:** Close the Receiver ingress observability gap identified in step 116
without connecting MQTT, publishing data or changing public mower state

## 1. Purpose

Steps 110 and 115 proved healthy native MQTT and WebSocket Core states but
observed no Account receive counter change.

Step 116 established that the Account `received` counter begins only after the
Receiver successfully invokes `NAVAC_IngestMqttEnvelope()`. The previous
diagnostics therefore could not distinguish:

1. no native Core delivery;
2. Receiver delivery followed by local rejection;
3. successful Receiver-to-Account forwarding;
4. Account-side rejection.

This step implements the smallest bounded Receiver-owned diagnostic projection
needed to separate those cases.

## 2. Safety Boundary

This step performs no:

- MQTT enable, Connect, Disconnect or publish operation;
- credential retrieval;
- Symcon module update;
- live Receiver or Account call;
- mower command or physical-state change;
- public variable, profile, action or Archive Control change;
- source publication or tag creation.

All execution is synthetic and offline.

The user's offer to start the mower manually is retained only as a supervised
option for a later live evidence gate. It is not used by this implementation
step.

## 3. SAEF Helper Review

The required SAEF diagnostics building blocks were reviewed before extending
the Receiver:

- `Statistics` creates explicit typed variables below a Symcon object;
- `Registry` stores small script-owned JSON metadata in a string variable;
- `ErrorRingBuffer` stores bounded recent event context;
- `ConfigurationHash` fingerprints normalized configuration.

The Receiver requires no object tree, visible statistic variables, event
history or configuration fingerprint. It needs one small implementation-owned
aggregate attached to the module instance.

Importing the script helper layer into the standalone module distribution
would:

- create new visible Symcon objects;
- broaden the module's deployment dependency surface;
- change the established variable and archive contract;
- not improve reuse for this single Receiver boundary.

The implementation therefore follows the bounded Registry and Statistics
semantics in one private module attribute without copying or introducing a new
public SAEF helper.

## 4. Diagnostic Contract

The Receiver now exposes the automatically wrapped public method:

```text
NAVMQTTRX_GetReceiveDiagnostics(ReceiverInstanceID)
```

The method returns JSON with exactly:

```text
formatVersion
receiveCalls
forwarded
oversized
invalidEnvelope
retainedRejected
unpaired
invalidAccount
handoffFailed
accountResultInvalid
lastResult
lastReceivedAt
lastForwardedAt
```

Example initial projection:

```json
{
  "formatVersion": 1,
  "receiveCalls": 0,
  "forwarded": 0,
  "oversized": 0,
  "invalidEnvelope": 0,
  "retainedRejected": 0,
  "unpaired": 0,
  "invalidAccount": 0,
  "handoffFailed": 0,
  "accountResultInvalid": 0,
  "lastResult": "none",
  "lastReceivedAt": 0,
  "lastForwardedAt": 0
}
```

## 5. Counter Semantics

`receiveCalls` increments at the first Receiver boundary before:

- outer-size validation;
- native-envelope parsing;
- retained-message validation;
- Account pairing validation;
- Account wrapper lookup or invocation.

This makes every `ReceiveData()` entry visible even when later validation
fails.

The local rejection counters have one responsibility each:

| Counter | Meaning |
|---|---|
| `oversized` | Receiver outer byte limit rejected the input |
| `invalidEnvelope` | strict native envelope parsing failed |
| `retainedRejected` | Receiver rejected a retained envelope |
| `unpaired` | no Account was configured |
| `invalidAccount` | configured instance was missing or not Navimow Account |
| `handoffFailed` | wrapper was unavailable or invocation threw |
| `accountResultInvalid` | wrapper returned a value outside the fixed contract |

`forwarded` increments only after the Account wrapper returns. It increments
even when the returned value is later normalized to `account-result-invalid`,
because the Receiver-to-Account invocation did occur.

It does not increment when wrapper lookup or execution throws.

`lastForwardedAt` follows the same rule.

## 6. Bounds and Recovery

All counters:

- must be integers;
- must be nonnegative;
- are capped at `2147483647`;
- saturate instead of overflowing.

Timestamps use the same nonnegative integer bound.

The internal `ReceiveDiagnostics` attribute:

- is limited to 4096 input bytes;
- is decoded with JSON exceptions and depth 8;
- is projected through a fixed schema;
- defaults safely when absent, malformed or oversized;
- never passes unknown keys through;
- is rewritten in normalized form on the next Receiver event.

An unrecognized stored result becomes `unknown`. A fresh result is `none`.

The public output is regression-bounded below 1024 bytes.

## 7. Result Allowlist

Receiver-local result codes are fixed:

```text
oversized-envelope
invalid-envelope
retained-rejected
unpaired
invalid-account
account-handoff-failed
account-result-invalid
```

Accepted Account result codes are fixed:

```text
accepted
busy
invalid-input
oversized-envelope
pairing-rejected
reconciliation-queued
retained-rejected
```

The previous generic lowercase-pattern acceptance was deliberately narrowed.
This prevents an unexpected Account string from becoming persistent or public
diagnostic content.

## 8. Privacy Contract

Neither the stored projection nor its public method can contain:

- MQTT topic or payload;
- native `DataID`;
- account, Receiver, device or Core ObjectID;
- device or account identity;
- WSS endpoint or host;
- authorization header or credential;
- access or refresh token;
- MQTT username, password or client ID;
- raw exception text;
- raw Account response;
- arbitrary stored JSON keys.

Every public string comes from a local fixed allowlist.

The existing debug output remains limited to a local result code and envelope
byte count.

## 9. Architecture Decisions

### ADR-117-01: Receiver-Owned Attribute

**Decision:** Store the fixed aggregate in one Receiver-owned string attribute.

**Rationale:** This is implementation state, not a user-facing statistic or
archive series. It preserves all public variable ObjectIDs and logging
settings.

### ADR-117-02: Two-Phase Recording

**Decision:** Record ingress before parsing and completion after the terminal
result.

**Rationale:** A single terminal write could miss an unexpected failure before
classification. The two-phase model guarantees evidence that the Receiver
boundary was entered.

The short intermediate state may show a newer `receiveCalls` and
`lastReceivedAt` with the previous terminal result. That state is truthful:
processing entered but has not yet completed.

### ADR-117-03: Fixed Result Contract

**Decision:** Accept only known Receiver and Account result codes.

**Rationale:** Regex-shaped strings are syntactically bounded but not
semantically or privacy bounded.

### ADR-117-04: No Reset Action

**Decision:** Add no diagnostic reset method or configuration-form button.

**Rationale:** Monotonic before/after deltas are sufficient for the planned
test. A reset would add an unnecessary live mutation and a new operational
contract.

### ADR-117-05: REST Authority Unchanged

**Decision:** Receiver counters produce evidence only.

**Rationale:** They do not change MQTT shadow semantics, public mower
variables, polling, reconciliation or REST ownership.

## 10. Compatibility

The implementation adds:

```text
ReceiveDiagnostics
```

as one private Receiver attribute.

It adds no:

- property;
- variable or profile;
- timer;
- instance;
- form action;
- command;
- Account or Device attribute;
- Archive Control change.

Existing device variable Idents, ObjectIDs, profiles, values, actions and
logging selections are structurally unaffected.

## 11. Offline Regression Evidence

The Receiver regression now verifies:

- exact fresh projection and field order;
- ingress counting before all validation branches;
- every local rejection counter;
- successful Account forwarding;
- invalid Account result normalization;
- wrapper exception handling;
- no false `forwarded` increment on a thrown handoff;
- malformed and oversized stored-state recovery;
- negative and wrongly typed value rejection;
- fixed unknown-result normalization;
- saturating counter behavior;
- output-size bound;
- diagnostic and debug privacy exclusions.

The complete offline gate passed:

```text
Navimow MQTT fixtures
REST client and authentication
native MQTT envelope
MQTT shadow payload
MQTT Receiver diagnostics
MQTT Account ingestion
MQTT shadow reconciliation
MQTT transport lifecycle
distribution validation
PHPCS
PHPStan
```

## 12. Evidence Matrix After Publication

The later live gate must compare deltas, not absolute lifetime counters:

| Receiver delta | Account delta | Interpretation |
|---:|---:|---|
| `receiveCalls = 0` | `received = 0` | no Core delivery or no broker publication |
| `receiveCalls > 0`, `forwarded = 0` | `received = 0` | local Receiver rejection; use its fixed counter |
| `forwarded > 0` | `received = 0` | unexpected Receiver/Account accounting boundary defect |
| `forwarded > 0` | `received > 0`, `accepted = 0` | Account-side rejection |
| `forwarded > 0` | `accepted > 0` | productive receive path proven |

## 13. Gate Decision

Offline implementation gate:

```text
GO
```

Publication and Symcon update:

```text
NOT EXECUTED
```

Live MQTT connection and manual mower start:

```text
NOT APPROVED BY THIS STEP
```

No source from this step should be used live until an exact publication and
compatibility plan has been reviewed.

## 14. Recommended Next Step

Create:

```text
118-native-mqtt-receiver-diagnostics-publication-and-live-test-plan.md
```

It should define:

1. the exact standalone publication files and commit;
2. a pre-update instance, variable and Archive Control baseline;
3. Symcon module update and wrapper-only readback;
4. a single bounded receive-only MQTT session with automatic cleanup;
5. Receiver and Account before/after delta capture;
6. a passive scheduled-run preference;
7. a supervised manual app start only when passive traffic is unavailable;
8. no module command, MQTT publish or retry;
9. final Disconnect, disable and credential-cleanup proof.

The manual start is useful because active mowing previously produced location
messages approximately every two seconds. It must remain a physical,
user-controlled stimulus in the official app, not a new module command.
