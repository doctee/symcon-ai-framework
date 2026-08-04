# 109 Native MQTT Explicit Adoption

**Case study:** Navimow native IP-Symcon module
**Status:** Gate D complete; dedicated chain adopted and idempotency proven,
broker connection not attempted
**Date:** 2026-07-28
**Scope:** Explicitly adopt the verified inactive native MQTT topology and
stop before credentials or activation

## 1. Purpose

This step executes Gate D from step 105 against the dedicated inactive
candidate prepared in step 108.

It covers:

- a fresh read-only candidate preflight;
- exactly one ownership-producing adoption;
- one prescribed idempotency repeat;
- ownership-aware semantic validation;
- before/after core-configuration and instance-set comparison;
- an independent post-adoption readback;
- private machine-readable evidence closure.

It does not:

- retrieve MQTT credentials;
- configure a WSS endpoint or authorization header;
- populate MQTT username, password or client ID;
- activate the WebSocket Client;
- connect to the broker;
- publish MQTT data;
- change a public Device variable;
- send a mower command;
- use `MC_ReloadModule()`.

REST remains authoritative for public device state.

## 2. Authorization

The user separately authorized Gate D after Gate C had closed.

The authorization was applied only to:

```text
one explicit adoption
one idempotency repeat
read-only verification
```

It did not authorize Gate E.

## 3. Fresh Preflight

The private preflight immediately before adoption required:

| Invariant | Result |
|---|---|
| dedicated Receiver selected | PASS |
| MQTT shadow enabled | PASS |
| candidate validation | `candidate-ready` |
| ownership validation | `configuration-invalid` |
| WebSocket inactive | PASS |
| URL and header list empty | PASS |
| MQTT credential slots empty | PASS |
| four exact QoS-0 subscriptions | PASS |
| wildcard absent | PASS |

`configuration-invalid` was required before the first call because Gate C had
prepared a valid candidate without writing ownership.

The MCP result had successful transport, no transport or execution error and
no truncation.

## 4. First Adoption

The bounded action called:

```text
AdoptMqttShadowChain
```

exactly once.

Returned result:

```text
MQTT chain adopted.
```

Immediate readback returned:

```text
enabled = true
valid = true
status = ready
```

The runtime can return `ready` only after validating:

- ownership format version 2;
- the exact Receiver, MQTT and WebSocket instance binding;
- the expected module GUIDs and connection order;
- the Account binding;
- the redacted subscription-shape hash;
- the redacted transport-shape hash;
- the local client-identity hash;
- a positive adoption timestamp.

The adoption changed private Account attributes only.

## 5. Idempotency Repeat

The second and final call returned:

```text
MQTT chain is already adopted.
```

Required repeat evidence:

| Invariant | Result |
|---|---|
| ownership validation remains `ready` | PASS |
| Account configuration hash unchanged | PASS |
| Receiver configuration hash unchanged | PASS |
| MQTT configuration hash unchanged | PASS |
| WebSocket configuration hash unchanged | PASS |
| complete instance sets unchanged | PASS |
| transport remains inactive | PASS |
| credential slots remain empty | PASS |

The repeat therefore performed no second ownership mutation and created no
new local client identity.

## 6. Core Immutability

Private hashes were captured before adoption, after the first call and after
the repeat.

The following remained byte-equivalent through both calls:

- Account property configuration;
- Receiver configuration;
- MQTT Client configuration;
- WebSocket Client configuration.

The complete Receiver, MQTT Client and WebSocket Client instance sets also
remained equal.

No ApplyChanges operation on a native Core client was required by adoption.

## 7. Ownership Redaction Evidence

IP-Symcon does not expose module attributes through a supported public
readback function suitable for this probe. The test deliberately did not read
internal persistence files or create a diagnostic backdoor.

Redaction is instead established by three complementary controls:

1. live ownership-aware validation checks every required semantic ownership
   field against the current topology;
2. the byte-verified published writer has a fixed allowlisted registry schema;
3. the offline lifecycle regression proves that device identity and synthetic
   credentials do not occur in ownership and that repeated adoption preserves
   the registry and local identity.

This proves the implemented and executed contract without exposing the private
registry value.

No claim is made that a raw private attribute was directly returned by MCP.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| adoption function invocations | 2 |
| ownership-producing mutations | 1 |
| idempotency invocations | 1 |
| credential endpoint calls | 0 |
| broker activation attempts | 0 |
| broker connection attempts | 0 |
| device actions | 0 |
| created or deleted instances | 0 |

The WebSocket remained inactive with empty URL and headers. MQTT username,
password and client ID remained empty.

## 9. Architecture Decisions

### AD-NAV-426: Validate ownership semantically

**Decision:** Use the module's ownership-aware validator as the live
acceptance boundary and combine it with the verified writer and offline
redaction regression.

**Reason:** Accessing internal Symcon persistence would violate the supported
module boundary and create unnecessary exposure of private attributes.

**Consequence:** The report distinguishes semantic live proof from direct raw
attribute readback.

### AD-NAV-427: Treat the repeat as a separate invocation

**Decision:** Permit exactly one second adoption call only after the first
call, ownership validation, inactivity and core immutability all pass.

**Reason:** This proves restart-safe operator repetition without allowing a
second mutation after ambiguous failure.

**Consequence:** Any first-call mismatch would have stopped the procedure
before the repeat.

## 10. Private Evidence

Private files:

```text
private/navimow-capture/
  native-mqtt-explicit-adoption.php
  native-mqtt-inactive-topology-probe.php

private/navimow-capture/output/native-mqtt-lifecycle/
  pre-adoption.json
  adoption.json
  post-adoption-validation.json
  gate-d-evidence-closure.json
```

The public report contains no ObjectID, registry value, identity, hash, topic,
endpoint, credential, token or private installation metadata.

## 11. Gate Decision

| Gate | Result |
|---|---|
| fresh candidate preflight | PASS |
| first explicit adoption | PASS |
| ownership semantic validation | PASS |
| idempotency repeat | PASS |
| core configuration immutability | PASS |
| instance-set immutability | PASS |
| transport inactivity | PASS |
| credential absence | PASS |
| broker communication | none |
| mower action | none |

**Gate D: CLOSED.**

**Gate E: BLOCKED pending explicit one-shot connection authorization.**

The adopted chain remains installed, selected, inactive and credential-empty.

## 12. Next Step

The next SAEF step is:

```text
110-native-mqtt-supervised-connect-and-receive.md
```

After separate Gate E authorization, it may make exactly one supervised
connection attempt, retrieve credentials once, activate the WebSocket once
and observe bounded receive-only evidence. It must not retry after failure,
timeout or ambiguity and must finish with explicit disconnect and verified
credential cleanup.
