# 219 Native MQTT Private Pilot Shadow Diagnostics Symcon Update

**Case study:** Navimow native IP-Symcon module
**Status:** Disabled Symcon update and read-only version-2 verification passed;
inactive pilot preflight and MQTT activation remain closed
**Date:** 2026-07-29
**Scope:** Execute Gate C from step 216 against the exact published and
metadata-conformant commit

## 1. Purpose

Steps 217 and 218 established:

```text
published commit:    3d223a9c24e396d4ba55ca40aede6742592fbe8f
metadata conformance: PASS
```

The user authorized:

```text
Symcon-Update auf die MQTT-Shadow-Diagnostik v2 mit deaktiviertem MQTT
freigegeben.
```

This step:

1. captures a bounded read-only pre-update snapshot;
2. proves the disabled credential-free update gate;
3. executes exactly one `MC_UpdateModule()`;
4. captures a full post-update snapshot;
5. verifies the version-2 empty shadow contract;
6. performs an independent final safety readback;
7. closes private and sanitized evidence.

No `MC_ReloadModule()`, MQTT activation, service restart or mower action was
performed.

## 2. MCP Result Contract

Every bounded PHP execution was evaluated across:

```text
transportError
executionError
truncated
captured output
```

For the update and all read-only probes:

```text
transportError: null
executionError: null
truncated:      false
```

A successful RPC transport alone was not treated as successful PHP execution.

## 3. Sanitized Pre-Update Baseline

Repository:

```text
branch:  main
commit:  8fdab84b
clean:   true
valid:   true
```

Runtime:

```text
MQTT feature:          disabled
lifecycle:             Disabled
WebSocket:             inactive
Authorization header: absent
MQTT username:         absent
MQTT password:         absent
REST authentication:  connected
reauth required:       false
REST status evidence: operational
```

Retained contracts:

```text
variables:       14/14
archive logging: 5/5
```

Frozen hashes:

| Contract | Before |
|---|---|
| identity | `02c2973d5a8d914f33d950b1ac73cb90894807a8178a68661403a2e0869a8ffc` |
| archive | `ca553115285c5c5336650ee2d635896df4cbdd109208c00a6f53aecc7f825d81` |
| command evidence | `f237c68db19ee3358a9d009b1e9acdc2aec6aa402dde487958425c4a7d72b9d9` |
| topology | `e2e2de1ca65b4c98de78a517fd98daba51436da901bda53a450c064e678af1d9` |
| subscriptions | `375dc242b1a0ae91e28a62abcd8da2df6a6496df7c49939839ba1ab8f69074fa` |

The installed pre-update diagnostic format was version 1 with zero tracked and
pending shadows.

## 4. Authorized Mutation

Executed:

```text
MC_UpdateModule(): 1
```

Result:

```text
true
```

Not executed:

```text
MC_ReloadModule(): 0
ApplyChanges():    0
service restart:   0
```

No temporary Symcon object or script was created.

## 5. Installed Target

The first complete post-update readback proved:

```text
branch:  main
commit:  3d223a9c
clean:   true
valid:   true
```

This binds the installation to:

```text
3d223a9c24e396d4ba55ca40aede6742592fbe8f
```

No reload or restart was needed.

## 6. Contract Preservation

All pre-update hashes remained byte-equal after update:

| Contract | Result |
|---|---|
| identity | EQUAL |
| archive | EQUAL |
| command evidence | EQUAL |
| topology | EQUAL |
| subscriptions | EQUAL |
| variable count | `14 -> 14` |
| logged count | `5 -> 5` |

The previously enabled Archive Control logging therefore remains attached to
the same retained variables.

No public variable was added, deleted or re-registered by this update.

## 7. Version-2 Diagnostic Result

The installed Account now returns:

```text
formatVersion:              2
featureEnabled:             false
configurationStatus:        disabled
lifecycle.state:            Disabled
trackedDeviceCount:         0
pendingReconciliationCount: 0
```

Shadow observation:

```text
status:              unavailable
authority:           mqtt-hint
lastSourceTimestamp: null
lastReceivedAt:      null
ageSeconds:          null
vehicleState:        null
batteryLevel:        null
mowingPercentage:    null
locationType:        null
location state code: null
```

Two consecutive diagnostic reads were equal. The read-only probe changed no
variable, property, attribute, timer or Core configuration.

## 8. Independent Safety Readback

A second independently bounded probe reconfirmed:

- installed `main@3d223a9c`;
- repository clean and valid;
- `14` retained variables;
- `5` retained Archive Control loggings;
- MQTT feature disabled;
- WebSocket inactive;
- Authorization absent;
- MQTT username and password absent;
- diagnostics format version 2;
- lifecycle `Disabled`;
- empty `unavailable` observation;
- all observation timestamps and fields `null`;
- repeated reads equal.

This readback deliberately did not wait the 65 seconds required by the later
inactive pilot preflight. Gate D was not started.

## 9. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-private-pilot-shadow-diagnostics-symcon-update/
  evidence-closure.json
```

It contains only:

- published and installed commit bindings;
- sanitized hashes and counts;
- operation counts;
- credential-presence Booleans;
- bounded diagnostic status;
- closed safety gates.

It contains no ObjectID, topic, credential, payload, coordinate, hostname or
private device identity.

## 10. Safety Result

The installed state is:

```text
module:      main@3d223a9c
valid:       true
clean:       true
MQTT:        disabled
credentials: absent from MQTT Core instances
REST:        operational and authoritative
```

This step:

- retrieved no MQTT credential;
- established no MQTT connection;
- published no MQTT data;
- performed no service restart;
- sent no mower command;
- created no temporary object;
- granted no persistence acceptance.

## 11. Architecture Decisions

### AD-NAV-792: Update through the supported module path

**Decision:** Execute one `MC_UpdateModule()` and no reload.

**Reason:** The supported update installed the exact public commit without an
unnecessary runtime reload.

### AD-NAV-793: Prove contract equality by hash

**Decision:** Compare identity, archive, command, topology and subscription
hashes before and after update.

**Reason:** Counts alone cannot prove that the same variables, logging
assignments and topology were retained.

### AD-NAV-794: Keep inactive preflight separate

**Decision:** Verify version 2 immediately but do not start the two-snapshot
65-second pilot preflight in this step.

**Reason:** Installation verification and pilot evidence initialization are
separate gates with different evidence purposes.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A publication | PASS |
| Gate B metadata conformance | PASS |
| pre-update safety baseline | PASS |
| one supported module update | PASS |
| installed commit | PASS |
| contract preservation | PASS |
| version-2 diagnostic contract | PASS |
| disabled credential-free state | PASS |
| Gate C disabled Symcon update | PASS |
| inactive pilot preflight | CLOSED |
| persistence acceptance | NOT GIVEN |
| MQTT activation | CLOSED |
| service restart | NOT PERFORMED |
| mower command | PROHIBITED |

## 13. Next Step

Proceed with:

```text
220-native-mqtt-private-pilot-inactive-preflight-and-harness-initialization.md
```

That read-only live step should:

1. run the frozen private version-2 projection twice at least 65 seconds apart;
2. prove equal disabled and credential-free contracts;
3. prove `mqttHint.availability = unavailable`;
4. create the private pilot state file;
5. ingest both inactive snapshots;
6. verify `ready-for-acceptance`;
7. perform no MQTT activation, credential retrieval, restart or mower command.
