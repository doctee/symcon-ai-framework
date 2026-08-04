# 108 Native MQTT Inactive Topology Preparation

**Case study:** Navimow native IP-Symcon module
**Status:** Gate C complete; dedicated inactive candidate ready, adoption
not performed
**Date:** 2026-07-28
**Scope:** Prepare and verify exactly one credential-empty native transport
chain without broker communication

## 1. Purpose

This step executes Gate C from step 105 after the update and compatibility gate
closed in step 107.

It covers:

- a fresh read-only topology preflight;
- preparation of exactly one dedicated native transport chain;
- pairing the new Receiver with the existing Navimow Account;
- an independent read-only candidate validation;
- comparison of all pre-existing native MQTT and WebSocket instances;
- private machine-readable evidence closure.

It does not:

- adopt the chain;
- create transport ownership metadata;
- retrieve MQTT credentials;
- configure a WSS endpoint or authorization header;
- activate the WebSocket Client;
- connect to the broker;
- publish an MQTT message;
- call a mower command;
- use `MC_ReloadModule()`.

REST remains the authoritative source for public device state.

## 2. Authorization

The user explicitly authorized the next gated step after step 107.

The authorization was interpreted narrowly as Gate C:

```text
Prepare one dedicated inactive and credential-empty native chain.
Stop before adoption and broker connection.
```

No later lifecycle gate was implied.

## 3. Preflight

The bounded read-only preflight passed with separate transport and execution
evaluation:

| Check | Result |
|---|---|
| MCP transport | success |
| `transportError` | `null` |
| `executionError` | `null` |
| output truncated | no |
| exactly one Account and Device | PASS |
| existing Navimow Receiver | none |
| MQTT shadow enabled | no |
| selected Receiver | none |

The installation already contained four native MQTT Clients and one native
WebSocket Client unrelated to Navimow. Their private identities were captured
only as a before-set below `private/`.

None was selected, reused, reconfigured or deleted.

## 4. Prepared Topology

The bounded staging procedure created exactly:

```text
native WebSocket Client (inactive)
  -> native MQTT Client
    -> Navimow MQTT Receiver
      -> paired Navimow Account
```

The arrows describe Symcon parent connections from the child toward its
transport provider.

Configured WebSocket contract:

- `Active = false`;
- empty URL;
- empty header list;
- binary transport type;
- certificate verification enabled.

Configured MQTT contract:

- empty username;
- empty password;
- empty client ID;
- four exact device-scoped subscriptions;
- QoS 0 for every subscription;
- no wildcard;
- parent is the newly created dedicated WebSocket Client.

Configured Receiver contract:

- paired with the established Account;
- parent is the newly created dedicated MQTT Client.

Configured Account contract:

- experimental MQTT shadow enabled;
- newly created Receiver selected;
- no adoption or ownership attribute written.

The private device identity was used transiently to construct the exact topic
allowlist. It was not emitted into the public report.

## 5. Bounded Mutation

The staging result recorded:

| Mutation | Count |
|---|---:|
| created instances | 3 |
| internal topology connections | 2 |
| broker connection attempts | 0 |
| broker activation attempts | 0 |
| credential endpoint calls | 0 |
| device actions | 0 |
| adoption attempts | 0 |

The rollback contract was prepared before mutation. On failure it would:

1. disable MQTT shadow and clear the Receiver selection;
2. delete only the newly created Receiver;
3. delete only the newly created MQTT Client;
4. delete only the newly created WebSocket Client.

Rollback was not triggered because staging and readback passed.

## 6. Independent Readback

A separate read-only probe validated the resulting live state rather than
trusting the values requested by the staging script.

| Invariant | Result |
|---|---|
| one new Receiver | PASS |
| one new MQTT Client | PASS |
| one new WebSocket Client | PASS |
| Receiver-to-MQTT parent exact | PASS |
| MQTT-to-WebSocket parent exact | PASS |
| expected native module types | PASS |
| WebSocket inactive | PASS |
| URL and headers empty | PASS |
| MQTT credential slots empty | PASS |
| four exact subscriptions | PASS |
| all subscriptions QoS 0 | PASS |
| no wildcard | PASS |
| Account pairing exact | PASS |
| adoption candidate | `candidate-ready` |

Ownership-aware MQTT configuration validation returned
`configuration-invalid`. This is the expected pre-adoption result: the
candidate topology is valid, but ownership has deliberately not been created.

## 7. Existing Core Instances

The complete private before/after instance sets proved:

```text
post MQTT set = pre MQTT set + exactly one new dedicated MQTT Client
post WebSocket set = pre WebSocket set + exactly one new dedicated client
post Receiver set = pre Receiver set + exactly one Navimow Receiver
```

Therefore:

- every pre-existing MQTT Client remained present;
- the pre-existing WebSocket Client remained present;
- no unrelated native transport instance was adopted;
- no unrelated native transport instance was mutated by the staging script.

## 8. Architecture Decisions

### AD-NAV-423: Create a dedicated chain

**Decision:** Never reuse an existing native MQTT or WebSocket instance for
the first productive lifecycle pilot.

**Reason:** Dedicated ownership and rollback cannot be demonstrated safely on
an instance with unrelated consumers.

**Consequence:** Gate C adds exactly one isolated chain and proves the complete
foreign instance set remains present.

### AD-NAV-424: Keep network configuration empty

**Decision:** Store only the exact subscription allowlist during Gate C. Leave
endpoint, authorization header, username, password and client ID empty.

**Reason:** Topology correctness can be proven independently of private
credentials and network side effects.

**Consequence:** Candidate validation is possible while broker communication
remains structurally impossible.

### AD-NAV-425: Separate candidacy from ownership

**Decision:** Treat `candidate-ready` as the only success criterion for Gate C
and require ownership validation to remain invalid before Gate D.

**Reason:** Selecting a Receiver must not silently imply ownership or authorize
a later connection.

**Consequence:** Adoption remains a distinct, auditable and separately
authorized mutation.

## 9. Private Evidence

Private files:

```text
private/navimow-capture/
  native-mqtt-inactive-topology-probe.php
  native-mqtt-inactive-topology-stage.php

private/navimow-capture/output/native-mqtt-lifecycle/
  inactive-topology-preflight.json
  inactive-topology-stage.json
  inactive-topology-validation.json
  gate-c-evidence-closure.json
```

The public report contains no ObjectID, device identity, topic, endpoint,
credential, token or private installation metadata.

## 10. Gate Decision

| Gate | Result |
|---|---|
| read-only preflight | PASS |
| exact dedicated topology | PASS |
| inactive transport | PASS |
| empty credential slots | PASS |
| exact QoS-0 allowlist | PASS |
| foreign core instances preserved | PASS |
| candidate validation | PASS |
| broker communication | none |
| adoption | not performed |
| mower action | none |

**Gate C: CLOSED.**

**Gate D: BLOCKED pending explicit adoption authorization.**

The inactive candidate remains installed and selected for the next gate.

## 11. Next Step

The next SAEF step is:

```text
109-native-mqtt-explicit-adoption.md
```

After separate Gate D authorization, it may execute one explicit adoption and
one idempotency repeat. It must prove unchanged native core configuration,
redacted ownership metadata and continued inactivity, then stop before any
credential retrieval or broker connection.
