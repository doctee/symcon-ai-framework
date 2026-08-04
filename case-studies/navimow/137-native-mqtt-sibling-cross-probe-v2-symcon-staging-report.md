# 137 Native MQTT Sibling Cross-Probe V2 Symcon Staging Report

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary V2 branch installed and one sibling probe staged
inactive; broker gate closed
**Date:** 2026-07-28
**Scope:** Execute Gate B from step 135 without retrieving credentials or
connecting to the broker

## 1. Purpose

Step 136 published and remotely verified the exact temporary five-file probe
branch.

This step:

1. captures the complete productive baseline twice;
2. installs the exact V2 experiment branch once;
3. verifies productive compatibility twice;
4. verifies temporary and productive wrapper surfaces separately;
5. creates exactly one inactive sibling probe;
6. proves both compatible children share the retained MQTT parent;
7. proves MQTT remains disabled and credential-empty;
8. stops before probe arming or broker connection.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-V2-Staging freigegeben.
```

This authorized one Module Control branch update, one temporary probe instance
and mandatory cleanup if Gate C does not follow.

It did not authorize:

- probe arming;
- credential retrieval;
- MQTT enablement;
- broker connection;
- mower activity or mower command;
- `MC_ReloadModule()`.

## 3. Repeated Main Baseline

The established complete projection ran twice on:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

Both runs reported:

```text
MCP transport success: true
transportError:        null
executionError:        null
truncated:             false
projection pass:       true
```

Both runs produced identical:

- productive instance topology hash;
- 14-variable identity and metadata hash;
- five-variable Archive Control contract hash;
- command evidence hash.

Verified:

- all five configured logging contracts retained;
- logged history queryable;
- Account connected;
- no reauthentication required;
- access token usable;
- Receiver paired;
- MQTT disabled;
- WebSocket inactive;
- headers and MQTT credentials empty;
- exactly four QoS-0 subscriptions without wildcard;
- no probe instance.

## 4. Module Control Update

Exactly one supported operation ran:

```text
MC_UpdateModuleRepositoryBranch(
  ModuleControl,
  "symcon-navimow",
  "experiment/native-mqtt-sibling-cross-probe-v2-20260728"
)
```

Result:

```text
true
```

After:

```text
branch: experiment/native-mqtt-sibling-cross-probe-v2-20260728
commit: a32146a6
clean:  true
valid:  true
```

Calls:

```text
MC_UpdateModuleRepositoryBranch: 1
MC_UpdateModule:                 0
MC_ReloadModule:                 0
```

## 5. Repeated Post-Update Compatibility

The complete projection ran twice after the update in separate PHP processes.

Both executions passed without transport error, PHP error or truncation. All
four productive contract hashes remained equal to the repeated `main`
baseline.

| Contract | Result |
|---|---|
| productive instances and connections | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Receiver pairing | retained |
| authentication | retained |
| subscriptions | retained |
| MQTT feature | disabled |
| WebSocket | inactive |
| credentials | empty |

The user's configured mower-variable logging remains intact.

## 6. Wrapper Readback

A separate PHP process verified before staging:

```text
probe Arm wrapper:          available
probe Close wrapper:        available
probe Report wrapper:       available
productive Connect wrapper: available
productive Disconnect:      available
productive diagnostics:     available
probe instances:            0
```

No wrapper was invoked during this readback.

## 7. Inactive Staging

The frozen staging source ran exactly once.

```text
probe Create calls:         1
probe Connect calls:        1
broker connection attempts: 0
rollback used:              no
staging pass:               true
```

The private Device ID was derived and configured only inside Symcon. It was not
returned through MCP or copied into public evidence.

## 8. Staged Topology

Verified shape:

```text
retained WebSocket Client
  -> retained MQTT Client
    -> productive Navimow MQTT Receiver
    -> temporary known-good Receive Probe
```

Read-only checks proved:

- exactly one probe exists;
- productive Receiver retained;
- probe and Receiver share the retained MQTT parent;
- no productive instance was reparented;
- no Core instance was created or changed.

## 9. Inactive Probe State

```text
accepting:             false
receive calls:         0
accepted messages:     0
publish attempts:      0
command attempts:      0
last result:           not-armed
```

The probe is installed but not armed.

## 10. Final Gate-B Transport State

```text
MQTT shadow:           disabled
WebSocket:             inactive
authorization headers: empty
MQTT username:         empty
MQTT password:         empty
broker connections:    0
```

No mower command or other external action was issued.

## 11. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v2/
    pre-update-baseline.json
    module-update.json
    post-update-compatibility.json
    inactive-staging.json
    gate-b-evidence-closure.json
```

No private credential, token, endpoint, topic, Device ID, ObjectID or garden
detail appears in this public report.

## 12. Gate Decision

| Gate | Decision |
|---|---|
| Gate A temporary publication | PASS |
| Gate B update compatibility | PASS |
| Gate B inactive staging | PASS |
| broker connection | NOT ATTEMPTED |
| Gate C V2 live retest | CLOSED |
| REST state authority | RETAINED |

The temporary probe remains staged inactive only for the next explicit gate.
If Gate C does not follow, the already authorized mandatory cleanup must delete
the probe and return Module Control to verified `main`.

## 13. Recommended Next Step

Gate C may now be opened.

Required authorization:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-V2-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Immediately before execution, additionally require:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```
