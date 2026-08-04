# 101 Native MQTT Targeted REST Reconciliation

**Case study:** Navimow native IP-Symcon module
**Status:** Targeted REST reconciliation complete offline; transport lifecycle,
publication and live testing remain blocked
**Date:** 2026-07-28
**Scope:** Execute `WP-6` from the approved MQTT shadow implementation plan

## 1. Authority Model

REST remains authoritative.

In this implementation:

- MQTT supplies a fast, private state candidate;
- MQTT queues one bounded read-only REST impulse;
- the Account targets the matching Device;
- the Device uses its existing `GetStatus` path;
- only the mapped REST result reaches `applyStatusResult()`;
- only that REST result updates public Symcon variables.

An MQTT payload never directly updates:

```text
VehicleState
Online
BatteryLevel
LastStatusUpdate
```

It also never invokes a command.

## 2. Reconciliation Timer

Added to `NavimowAccount`:

```text
MqttReconcile
NAVAC_ProcessMqttReconciliation($_IPS["TARGET"]);
```

The timer:

- is registered with interval `0`;
- remains inactive when MQTT shadow mode is disabled;
- remains inactive without a usable access token;
- is reset to `0` during `ApplyChanges()` and authentication reset;
- is scheduled only when a valid MQTT hint is queued;
- never changes the normal `PollStatus` cadence.

## 3. Queue Contract

Each private queue entry contains:

```text
deviceId
firstQueuedAt
lastHintAt
notBefore
reasonCode
```

Rules:

- maximum 64 devices;
- one entry per deterministic device hash;
- repeated hints update `lastHintAt` only;
- first queue time and first due time remain stable;
- minimum delay is 30 seconds;
- processing order is oldest first, then hash;
- maximum four devices are processed per timer run;
- one timer wake sends no duplicate REST impulse.

The queue is cleared on `ApplyChanges()`.

## 4. Targeted Poll Message

For each due, currently discovered target, the Account sends:

```json
{
  "DataID": "{54620029-127D-470D-97C7-44265496FAA0}",
  "SchemaVersion": 1,
  "Function": "PollStatus",
  "DeviceId": "target device",
  "Reason": "mqtt-shadow-reconciliation"
}
```

The `Reason` is a fixed code and is not interpreted as a command.

Before sending, the Account revalidates:

- MQTT feature opt-in;
- symmetric Account/Receiver pairing;
- native connection chain;
- ownership and configuration hashes;
- usable access token;
- target membership in the current discovery cache.

An unknown target is removed without a REST call.

## 5. Device Filtering

`NavimowDevice::ReceiveData()` retains the existing broadcast behavior:

```text
PollStatus without DeviceId:
accepted by every Device
```

For targeted polling:

```text
PollStatus with matching DeviceId:
accepted

PollStatus with another DeviceId:
ignored without error
```

The matching Device invokes its unchanged `RefreshStatus()` implementation.
That implementation sends the existing Account message:

```text
Function: GetStatus
DeviceId: configured DeviceId
```

No second REST transport or mapper was introduced.

## 6. REST Result Application

The established path remains:

```text
Device ReceiveData
  -> Device RefreshStatus
    -> Account ForwardData(GetStatus)
      -> Account performStatus
        -> ApiClient getVehicleStatus
        -> PayloadMapper mapStatus
      -> Device applyStatusResult
```

Consequences:

- REST errors use the existing read-failure behavior;
- MQTT shadow state remains private after REST failure;
- command verification continues using the same REST implementation;
- variable Idents and registrations remain unchanged;
- Archive Control continues tracking the existing variable ObjectIDs.

## 7. Private Comparison

After successful REST mapping, the Account compares the result with a current
MQTT candidate.

Compared fields:

```text
vehicleState: exact
batteryLevel: tolerance of 1 percentage point
```

Rules:

- candidate age must not exceed 300 seconds;
- absent fields are not compared;
- stale candidates are counted but not classified as mismatches;
- comparison changes neither REST output nor Device output;
- only fixed private counters and reason codes are stored.

Private results:

```text
match
mismatch
stale
```

No topic, raw payload, coordinate or credential enters diagnostics.

## 8. Failure and Retry Semantics

Each due queue entry causes at most one targeted poll handoff.

There is:

- no immediate retry;
- no command fallback;
- no MQTT publish;
- no optimistic public write.

A later MQTT message may queue another read after the 30-second bound. Normal
REST polling remains the independent recovery path.

This deliberately avoids a second retry state machine before live lifecycle
evidence exists.

## 9. Offline Harness

Added:

```text
tests/mqtt-shadow-reconciliation.php
```

The harness proves:

- state MQTT queues one target;
- repeated hints coalesce;
- the first due time remains stable;
- processing cannot occur before `notBefore`;
- the timer is scheduled to the next due second;
- the emitted message contains the exact target and fixed reason;
- an unrelated Device performs no REST read and no variable write;
- the target Device performs exactly one existing REST status request;
- only the REST result updates target variables;
- successful REST/MQTT comparison remains private;
- one-percent battery difference is tolerated;
- larger battery difference records a private mismatch;
- stale candidates do not become mismatches;
- REST failure leaves the MQTT shadow unchanged;
- one run processes no more than four targets;
- MQTT ingestion leaves Account public variables unchanged.

The focused runner now includes Account, Device and Receiver in PHPCS and
PHPStan.

## 10. Compatibility

Unchanged:

- all existing module GUIDs;
- all existing variable Idents;
- all variable profiles;
- all action Idents;
- Device variable registration order;
- command transport and verification;
- normal REST polling timer;
- OAuth and token refresh behavior;
- Archive Control configuration;
- standalone published module;
- installed Symcon module.

Added runtime surface:

```text
one Account timer, default inactive
one public timer callback
target filtering in existing Device ReceiveData
private comparison counters
```

## 11. Architecture Decisions

### AD-NAV-401: Keep REST as the only public state authority

**Decision:** MQTT can trigger a read but cannot write a Device variable.

**Rationale:** REST is the established mapped and failure-aware status
contract.

**Consequence:** MQTT improves reaction time without becoming a second source
of public truth.

### AD-NAV-402: Reuse the existing Device status path

**Decision:** Send a targeted `PollStatus` child message instead of calling a
new REST method from the timer.

**Rationale:** Variable application, error handling and mapper ownership
already reside in the Device-to-Account flow.

**Consequence:** Reconciliation cannot bypass `applyStatusResult()`.

### AD-NAV-403: Coalesce before reading

**Decision:** Preserve one queue entry and one first due time per device.

**Rationale:** Two-second MQTT location traffic must not multiply cloud reads.

**Consequence:** A burst produces one REST impulse after at least 30 seconds.

### AD-NAV-404: Bound every timer run

**Decision:** Process at most four oldest due devices.

**Rationale:** Timer work and cloud traffic must remain bounded for multi-device
accounts.

**Consequence:** Remaining entries are scheduled for a later run.

### AD-NAV-405: Compare only fresh semantic candidates

**Decision:** Use exact state, one-percent battery tolerance and a five-minute
age limit.

**Rationale:** Comparison is diagnostic evidence, not a reason to override
REST.

**Consequence:** Stale or partial MQTT evidence cannot create false mismatch
alarms.

### AD-NAV-406: Do not add reconciliation retries

**Decision:** Remove an entry after one bounded handoff.

**Rationale:** Existing polling already provides recovery, while an additional
retry machine lacks live evidence.

**Consequence:** A new MQTT hint may queue a later read; no retry storm is
possible.

### AD-NAV-407: Keep publication closed

**Decision:** Do not publish or install WP-6.

**Rationale:** Credential retrieval and owned transport lifecycle are not yet
implemented.

**Consequence:** Productive Symcon remains on the known-good REST release.

## 12. Verification Result

Passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
make check
git diff --check
```

This includes:

```text
fixture and parser checks
Receiver handoff checks
Account pairing and ingestion checks
targeted REST reconciliation checks
distribution validation
PHPCS
PHPStan
```

The complete SAEF repository gate, including repository-wide PHPStan, passed.

## 13. Decision

**`WP-6` targeted REST reconciliation: COMPLETE OFFLINE.**

**REST-only public state authority: PASS.**

**Queue coalescing: PASS.**

**Thirty-second lower bound: PASS.**

**Four-target run bound: PASS.**

**Targeted Device filtering: PASS.**

**Existing REST mapper reuse: PASS.**

**Private comparison: PASS.**

**REST failure isolation: PASS.**

**Existing variable and archive contract: PRESERVED.**

**Full repository gate: PASS.**

**MQTT credential retrieval: NOT IMPLEMENTED.**

**Owned transport lifecycle: NOT IMPLEMENTED.**

**Standalone publication: NOT AUTHORIZED.**

**Live Symcon mutation: NONE.**

## 14. Recommended Next Step

Create:

```text
102-native-mqtt-credential-endpoint-implementation.md
```

That step should execute `WP-7` only:

1. add the read-only MQTT credential endpoint to `ApiClient`;
2. map and validate the WSS URL, username and password;
3. keep every secret out of logs, errors, fixtures and attributes;
4. add synthetic transport and redaction tests;
5. create no core instance and connect no transport;
6. keep MQTT shadow disabled by default;
7. keep publication and live Symcon testing blocked.
