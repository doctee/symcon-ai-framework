# 100 Native MQTT Account Pairing and Ingestion

**Case study:** Navimow native IP-Symcon module
**Status:** Offline Account pairing and internal ingestion complete;
reconciliation, publication and live testing remain blocked
**Date:** 2026-07-28
**Scope:** Execute `WP-5` from the approved MQTT shadow implementation plan

## 1. Purpose

This step connects the optional native MQTT Receiver to the semantic boundary
owned by `NavimowAccount`.

It implements:

- disabled-by-default Account configuration;
- symmetric Receiver-to-Account pairing;
- side-effect-free native chain validation;
- exact, QoS-0, wildcard-free subscription validation;
- ownership and configuration-drift validation;
- bounded Receiver handoff;
- strict envelope and fixture-backed payload parsing;
- private timestamp-aware shadow reduction;
- a private bounded REST reconciliation queue;
- sanitized bounded diagnostics.

It does not:

- create, connect, configure, adopt or delete a core instance;
- retrieve MQTT/WSS credentials;
- activate the WebSocket Client;
- execute pending REST reconciliation;
- update a public variable;
- change a timer;
- send MQTT;
- send a mower command;
- publish or install the implementation.

REST remains authoritative.

## 2. Account Configuration

Added properties:

```text
EnableMqttShadow: boolean, default false
MqttReceiverInstanceId: integer, default 0
```

The Account form uses a `SelectInstance` restricted to the fixed Receiver
module GUID:

```text
{1B9960A2-A30C-D846-DF55-800F583AA812}
```

Saving or updating an existing Account with default values:

- creates no object;
- changes no connection;
- changes no REST timer;
- changes no public variable;
- reports MQTT validation as `disabled`.

## 3. Symmetric Pairing Contract

The public side-effect-free method is:

```text
ValidateMqttShadowConfiguration(): string
```

Its return value is sanitized JSON containing only:

```text
enabled
valid
status
receiverInstanceId
```

A ready chain must satisfy all of these conditions:

1. Account shadow mode is explicitly enabled;
2. selected Receiver exists and has the expected module GUID;
3. Receiver `AccountInstanceId` points back to this Account;
4. Receiver direct connection is the native MQTT Client;
5. MQTT Client direct connection is the native WebSocket Client;
6. subscriptions use QoS 0;
7. every device has exactly `state`, `event`, `attributes` and `location`;
8. no wildcard is present;
9. no more than 64 devices are subscribed;
10. recorded ownership IDs, GUIDs, order and Account binding match;
11. current subscription and transport hashes match the ownership record.

Validation reports drift and never repairs it.

## 4. Ownership Boundary

The existing `MqttOwnershipRegistry` design is now enforced.

The registry contains only:

```text
formatVersion
receiverInstanceId
mqttInstanceId
webSocketInstanceId
moduleGuids
connectionOrder
accountBinding
subscriptionConfigurationHash
transportConfigurationHash
adoptedAt
```

It contains no URL, header, username, password, client ID, device ID or topic.
Current native configurations are read transiently only to calculate and
compare hashes.

This step intentionally provides no adoption or repair action. A default empty
registry therefore fails closed when MQTT shadow mode is enabled.

## 5. Receiver Handoff

After its existing outer checks, the Receiver calls:

```text
NAVAC_IngestMqttEnvelope(
    accountInstanceId,
    receiverInstanceId,
    envelopeJson
)
```

The Receiver still rejects:

- an envelope above 65,536 bytes;
- invalid native envelope JSON;
- retained input;
- missing Account selection;
- missing or wrong Account module.

The Account validates the entire pairing and size again. This preserves two
independent trust boundaries.

Receiver debug output remains limited to:

```text
result
envelopeBytes
```

No topic, payload, identity or exception text is emitted.

## 6. Account Ingestion

The public entry point is:

```text
IngestMqttEnvelope(
    int receiverInstanceId,
    string envelopeJson
): string
```

It returns only bounded result codes:

```text
accepted
reconciliation-queued
pairing-rejected
oversized-envelope
busy
retained-rejected
invalid-input
```

The method:

1. validates symmetric pairing before parsing;
2. enforces the outer byte limit again;
3. acquires a dedicated Account MQTT semaphore;
4. parses the exact native envelope;
5. rejects retained input;
6. extracts and validates the device identity from an exact topic;
7. invokes the fixture-backed semantic payload parser;
8. reduces patches with source timestamps;
9. writes private bounded state only;
10. queues but does not execute REST reconciliation;
11. releases the semaphore in every path.

There is no retry and no command fallback.

## 7. Internal State

Added private string attributes:

```text
MqttOwnershipRegistry
MqttLifecycleRegistry
MqttStatistics
MqttErrorHistory
MqttShadowState
MqttPendingReconciliation
```

The shadow:

- stores at most 64 devices;
- keys devices by SHA-256 of the device ID;
- stores only approved semantic fields and timestamps;
- stores no geometry;
- removes oldest entries deterministically;
- is not exposed as a variable.

Pending reconciliation:

- stores at most 64 entries;
- may retain the device ID because a later targeted REST read requires it;
- records only a fixed reason and bounded timestamps;
- does not execute any REST request in this step.

`ApplyChanges()` clears both ephemeral stores. Ownership, lifecycle statistics
and bounded error history remain separate.

## 8. Existing Variable and Archive Contract

No existing variable registration changed:

```text
ConnectionState
ReauthRequired
TokenExpiresAt
LastDiscovery
LastRestSuccess
RestErrorCount
```

The executable test snapshots all Account variable values before ingestion and
proves byte-for-byte equality afterwards.

No Device variable, Ident, profile or action changed. Archive Control logging
therefore retains the existing variable ObjectIDs and configuration.

## 9. Offline Tests

Added:

```text
tests/mqtt-account-ingestion.php
```

Extended:

```text
tests/mqtt-receiver-scaffold.php
tools/check-mqtt-shadow.sh
```

The tests cover:

- disabled default behavior;
- missing Receiver rejection;
- exact native connection order;
- symmetric property binding;
- full four-topic QoS-0 subscription set;
- wildcard rejection;
- ownership and current configuration hashes;
- successful state-envelope handoff;
- hashed private shadow state;
- queued but unexecuted reconciliation;
- unchanged public Account variables;
- ephemeral state clearing on `ApplyChanges()`;
- bounded Receiver debug metadata.

The focused runner executes fixtures, parsers, Receiver, Account ingestion,
distribution validation, PHPCS and PHPStan.

Symcon's generated Account wrapper and property API are invoked by their
runtime function names. This keeps the production contract intact without
adding fake global functions to the distribution or the repository-wide
PHPStan bootstrap.

## 10. Architecture Decisions

### AD-NAV-394: Keep MQTT explicitly opt-in

**Decision:** Add `EnableMqttShadow` with default `false`.

**Rationale:** An existing REST installation must remain behaviorally
unchanged after an update.

**Consequence:** No missing-chain warning or transport activity occurs by
default.

### AD-NAV-395: Require symmetric pairing

**Decision:** Require both Account-to-Receiver and Receiver-to-Account
references.

**Rationale:** Module type alone does not authorize semantic ingestion.

**Consequence:** Incomplete or stale pairings fail closed while REST continues.

### AD-NAV-396: Validate the dedicated native chain

**Decision:** Verify module GUIDs, exact connection order, exact QoS-0 topics,
ownership metadata and current configuration hashes.

**Rationale:** A selected Receiver must not accidentally bind an unrelated or
drifted MQTT installation.

**Consequence:** Drift is observable but never automatically repaired.

### AD-NAV-397: Keep shadow state private and ephemeral

**Decision:** Persist only bounded internal semantic candidates and clear them
on `ApplyChanges()`.

**Rationale:** MQTT evidence is not yet authoritative and must not survive a
restart as current device truth.

**Consequence:** REST must re-establish authoritative public state.

### AD-NAV-398: Queue reconciliation without executing it

**Decision:** Record a bounded private read request hint with a 30-second
earliest time.

**Rationale:** Ingestion and REST scheduling require separate implementation
and testing gates.

**Consequence:** `WP-5` cannot alter REST traffic.

### AD-NAV-399: Preserve public variables and logging

**Decision:** Add no MQTT-facing variable and mutate no existing variable from
ingestion.

**Rationale:** Existing Archive Control history and pilot dashboards must
remain stable.

**Consequence:** The MQTT path is invisible to existing user-facing state in
this step.

### AD-NAV-400: Keep publication closed

**Decision:** Do not copy this increment to the standalone module repository.

**Rationale:** Ownership adoption and reconciliation are not implemented, so a
live chain cannot yet be configured productively.

**Consequence:** Installed Symcon systems remain on the known-good REST
release.

## 11. Verification Result

Passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
make check
git diff --check
```

This includes:

```text
fixture checks
native envelope checks
semantic reducer checks
Receiver handoff checks
Account ingestion checks
distribution validation
PHPCS
PHPStan
```

The complete SAEF repository gate, including repository-wide PHPStan, passed.

## 12. Decision

**`WP-5` Account pairing: COMPLETE OFFLINE.**

**Disabled default: PASS.**

**Symmetric pairing: PASS.**

**Native chain and drift validation: PASS.**

**Wildcard prohibition: PASS.**

**Bounded Account ingestion: PASS.**

**Private shadow reduction: PASS.**

**Public variable invariance: PASS.**

**Full repository gate: PASS.**

**REST reconciliation execution: NOT IMPLEMENTED.**

**Core-instance lifecycle: NOT IMPLEMENTED.**

**Standalone publication: NOT AUTHORIZED.**

**Live Symcon mutation: NONE.**

## 13. Recommended Next Step

Create:

```text
101-native-mqtt-targeted-rest-reconciliation.md
```

That step should execute `WP-6` only:

1. add a default-inactive Account reconciliation timer;
2. process only due, paired and currently discovered devices;
3. coalesce repeated MQTT hints;
4. perform bounded read-only REST status calls;
5. update public state only through the existing REST mapper path;
6. preserve normal polling cadence and command isolation;
7. add deterministic retry, restart and queue-bound tests;
8. keep publication and live Symcon testing blocked.
