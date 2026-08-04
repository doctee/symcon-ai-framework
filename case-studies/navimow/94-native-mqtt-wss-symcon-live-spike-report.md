# 94 Native MQTT WSS Symcon Live Spike Report

**Case study:** Navimow native IP-Symcon module
**Status:** Native receive-only transport PASS with one non-blocking private deadline finding
**Date:** 2026-07-28
**Scope:** Execute Gate B, prove the native WSS/MQTT receive path and restore the installation

## 1. Purpose

This step executes Gate B from
`92-native-mqtt-wss-symcon-live-spike-plan.md` with the probe implemented in
`93-native-mqtt-wss-symcon-spike-harness-implementation.md`.

The live spike had to prove:

- a Navimow WSS connection through the native WebSocket Client;
- an MQTT connection through the native MQTT Client;
- delivery of an allowed message to a custom Symcon child module;
- the actual native MQTT child envelope;
- zero MQTT publish attempts;
- zero mower-command attempts;
- complete removal of the temporary topology;
- restoration of the published `main` module;
- unchanged productive instances, variables and archive configuration.

It was not a productive MQTT integration and did not change the mower state.

## 2. Authorization

The user explicitly authorized the bounded native MQTT/WSS Symcon live spike.

The authorization permitted:

- temporary installation of the published probe branch;
- creation and deletion of one owned test topology;
- one bounded receive-only broker connection;
- local private credential handling;
- mandatory cleanup and baseline comparison.

It did not permit:

- MQTT publish;
- a REST command;
- a mower action;
- productive Navimow source changes;
- variable or archive changes;
- leaving the probe installed.

`MC_ReloadModule()` was not used.

## 3. Pre-Mutation Baseline

The private machine-readable baseline confirmed:

| Check | Baseline |
| --- | --- |
| Symcon version | `9.0` |
| Navimow branch | `main` |
| Navimow commit | `397b4b01` |
| productive Navimow instances | 3 |
| productive Navimow variables | 14 |
| temporary probe instances | 0 |
| productive configuration hashes | captured privately |
| variable types, profiles and actions | captured privately |
| archive logging and aggregation | captured privately |
| existing WebSocket/MQTT topology | captured privately |

No private ObjectID, configuration value or installation name is copied into
this public report.

## 4. Probe Source Gate

Module Control was temporarily changed to:

```text
branch:
spike/native-mqtt-wss-receive-probe

commit:
ce507287c94dc5f15637a849f93723a800e7f450
```

The branch added only:

```text
NavimowMqttReceiveProbe/
```

The productive source manifest remained equal to standalone `main`. The three
previously documented differences between the SAEF distribution and standalone
`main` were not included.

After the update:

- all productive module types remained available;
- all productive instance IDs, parents, connections and configuration hashes
  remained equal;
- the temporary probe module became available;
- no probe instance existed before the owned topology was created.

## 5. Temporary Topology

One uniquely named, isolated topology was created:

```text
temporary category
  -> native WebSocket Client
    -> native MQTT Client
      -> Navimow MQTT Receive Probe
```

The WebSocket Client was initially inactive. The MQTT Client and probe were
connected through the interfaces proven in step 91.

Before activation, a shape-only validation confirmed:

- `wss://`;
- port 443;
- binary WebSocket transfer;
- certificate verification enabled;
- exactly one complete Bearer authorization header;
- non-empty MQTT username, password and client ID;
- bounded keepalive;
- exactly four subscriptions;
- QoS 0 for all subscriptions;
- no `#` or `+` wildcard;
- the same private device identity in the probe and subscriptions;
- WebSocket `Active = false`.

No secret value was returned through MCP or written to a public artifact.

## 6. Bounded Activation

The probe was armed for 180 seconds. The WebSocket `Active` property was then
changed from `false` to `true` exactly once.

There was:

- no configuration retry;
- no second activation;
- no reconnect experiment;
- no MQTT publish path;
- no REST command path.

The native WebSocket Client and native MQTT Client both reached healthy status.

## 7. Receive Result

The probe received two allowed `location` messages before the observation was
closed.

| Counter | Result |
| --- | ---: |
| receive calls | 2 |
| accepted messages | 2 |
| rejected messages | 0 |
| oversized messages | 0 |
| unknown topics | 0 |
| state messages | 0 |
| event messages | 0 |
| attributes messages | 0 |
| location messages | 2 |
| MQTT publish attempts | 0 |
| mower-command attempts | 0 |

The payload sizes were bounded:

```text
minimum: 33 bytes
maximum: 115 bytes
```

No topic or payload value was retained in the public evidence.

## 8. Proven Native MQTT Envelope

The native MQTT Client delivered this top-level JSON shape to
`ReceiveData()`:

```text
object
  DataID: string
  PacketType: integer
  Payload: string
  QualityOfService: integer
  Retain: boolean
  Topic: string
```

This closes the envelope uncertainty from steps 91 through 93.

The `Payload` field contains a JSON string and therefore requires a second
bounded JSON decode after the outer envelope has passed DataID, topic and size
validation.

The observed location payload shapes confirmed arrays containing objects with
subsets of:

```text
postureTheta: string
postureX: string
postureY: string
time: integer
type: integer
vehicleState: integer
```

The partial-message behavior matches the private capture and parser evidence
from steps 87 through 90.

## 9. Closure and Counter Freeze

After the second accepted message:

1. the probe was closed;
2. the WebSocket Client was deactivated;
3. the receive counters were read again after a delay;
4. all counters remained unchanged;
5. the final sanitized report was retained privately;
6. the probe, MQTT Client, WebSocket Client and category were deleted in that
   order.

The owned category contained exactly the expected three instances before
deletion. No unrelated object was removed.

## 10. Module and Installation Rollback

Module Control was restored to:

```text
branch:
main

commit:
397b4b01
```

The final comparison produced:

| Invariant | Result |
| --- | --- |
| temporary object count | 0 |
| probe instance count | 0 |
| probe module available after restore | no |
| productive instance topology | equal |
| productive configuration hashes | equal |
| productive variable identities | equal |
| variable types, profiles and actions | equal |
| archive logging | equal |
| archive aggregation | equal |
| native WebSocket Client count | equal |
| native MQTT Client count | equal |
| productive instance status | healthy |

The user's existing logging configuration for Navimow variables remained
unchanged.

## 11. Private Package Deadline Finding

The private input package was created with this local deletion deadline:

```text
2026-07-28 06:45:01 Europe/Berlin
```

The single activation began at:

```text
2026-07-28 06:47:18 Europe/Berlin
```

The evidence was closed at:

```text
2026-07-28 06:47:58 Europe/Berlin
```

The private package was deleted during cleanup several minutes after its
deadline.

Classification:

- transport evidence remains valid because the native connection succeeded
  and messages were received;
- no credential was exposed through chat, MCP output or public files;
- all credential-bearing temporary Symcon objects were deleted;
- the private package was deleted;
- the 15-minute local handling deadline was nevertheless exceeded.

This is a non-blocking private process finding, not a transport PASS for that
specific deadline invariant. A future helper should either enforce expiry
before activation or generate the package only after the inactive topology is
ready.

No repeat live connection is justified solely to erase this finding.

## 12. Gate Results

| Gate | Result |
| --- | --- |
| explicit Gate B authorization | PASS |
| pre-mutation baseline | PASS |
| exact probe branch | PASS |
| productive source unchanged | PASS |
| inactive topology creation | PASS |
| shape-only private configuration gate | PASS |
| one-shot WSS activation | PASS |
| native WSS connection | PASS |
| native MQTT connection | PASS |
| custom-child message receipt | PASS |
| actual receive envelope | PASS |
| exact-topic enforcement | PASS |
| MQTT publish invariant | PASS |
| mower-command invariant | PASS |
| counter freeze after close | PASS |
| temporary object cleanup | PASS |
| `main` restoration | PASS |
| productive topology equality | PASS |
| variable and archive equality | PASS |
| private package deletion | PASS |
| package deletion by local deadline | FINDING |

## 13. Architecture Decisions

### AD-NAV-352: Accept the native WSS-to-MQTT transport topology

**Decision:** The native WebSocket Client followed by the native MQTT Client is
the selected transport for the productive shadow design.

**Rationale:** The live installation proved authentication, MQTT connection and
custom-child delivery without a custom MQTT protocol engine.

**Consequence:** The custom-splitter and external-bridge options remain
fallbacks only.

### AD-NAV-353: Implement the proven outer envelope explicitly

**Decision:** Future production code must validate `DataID`, `Topic` and
`Payload` from the proven envelope before decoding the payload string.

**Rationale:** The live contract is now stronger than a guessed or synthetic
envelope.

**Consequence:** Envelope regression fixtures may now use the sanitized key and
type shape without including private values.

### AD-NAV-354: Keep REST authoritative during the MQTT shadow phase

**Decision:** A first productive MQTT integration remains receive-only and
diagnostic. REST continues to own public mower state.

**Rationale:** This spike proves transport and envelope delivery, not restart,
credential-refresh or REST/MQTT reconciliation behavior.

**Consequence:** MQTT must not write the existing public variables until the
shadow comparison gates pass.

### AD-NAV-355: Move credential-package creation later

**Decision:** Future live procedures create short-lived private input only
after branch installation and inactive topology creation.

**Rationale:** The manual configuration phase consumed the original 15-minute
window.

**Consequence:** Expired packages must fail closed before activation.

## 14. Decision

**Native WebSocket Client transport: PASS.**

**Native MQTT Client transport: PASS.**

**Custom child receive path: PASS.**

**Actual Symcon MQTT envelope: PROVEN.**

**MQTT publish and mower commands: NONE.**

**Cleanup and productive rollback: PASS.**

**Private package deadline: NON-BLOCKING FINDING.**

**Productive MQTT authority: NOT YET APPROVED.**

## 15. Recommended Next Step

Create:

```text
95-native-mqtt-shadow-integration-design.md
```

The next design step should define:

1. account ownership of the native WebSocket and MQTT instances;
2. OAuth-to-WSS credential refresh without exposing tokens;
3. exact-topic lifecycle for discovered devices;
4. the proven outer-envelope parser;
5. reuse of the partial MQTT payload accumulator;
6. bounded receive diagnostics;
7. restart and disconnect recovery;
8. REST/MQTT timestamp reconciliation;
9. a receive-only shadow mode that does not change public variables;
10. gates before MQTT can become authoritative for any field.
