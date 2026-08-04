# 132 Native MQTT Sibling Cross-Probe Symcon Staging Report

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary branch installed and one sibling probe staged inactive;
live gate closed
**Date:** 2026-07-28
**Scope:** Execute Gate B from step 130 without retrieving credentials or
connecting to the broker

## 1. Purpose

Step 131 published and remotely verified the exact five-file probe branch.

This step:

1. captures the complete `main` compatibility baseline twice;
2. installs the exact temporary branch once;
3. verifies productive compatibility twice;
4. verifies the probe wrapper surface in a new PHP execution;
5. creates and connects exactly one inactive sibling probe;
6. confirms both compatible children share the retained MQTT parent;
7. proves MQTT remains disabled and credential-empty;
8. stops before probe arming or broker connection.

## 2. Authorization

The user explicitly authorized Gate B:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-Staging freigegeben.
```

This authorized one Module Control branch update, one temporary probe instance
and the mandatory rollback if no live gate follows.

It did not authorize:

- probe arming;
- credential retrieval;
- MQTT enablement;
- a broker connection;
- mower activity or a mower command.

## 3. Repeated Pre-Update Baseline

The frozen aggregate probe ran twice on:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

Both executions reported:

```text
MCP transport success: true
transportError:        null
executionError:        null
truncated:             false
probe pass:            true
```

The runs produced identical:

- productive instance topology hash;
- 14-variable identity and metadata hash;
- five-variable Archive Control contract hash;
- command evidence hash.

Verified:

- all five logged-variable contracts retained;
- archive history queryable;
- Account connected;
- no reauthentication required;
- access token usable;
- MQTT disabled;
- WebSocket inactive;
- authorization header empty;
- MQTT username and password empty;
- four exact QoS-0 subscriptions without wildcard;
- no probe module or probe instance available.

## 4. Module Control Update

Exactly one mutation was executed:

```text
MC_UpdateModuleRepositoryBranch(
  ModuleControl,
  "symcon-navimow",
  "experiment/native-mqtt-sibling-cross-probe-20260728"
)
```

Result:

```text
true
```

After:

```text
branch: experiment/native-mqtt-sibling-cross-probe-20260728
commit: 5d994106
clean:  true
valid:  true
```

Calls:

```text
MC_UpdateModuleRepositoryBranch: 1
MC_UpdateModule:                 0
MC_ReloadModule:                 0
```

The branch operation installed the target commit completely, so no redundant
second update was used.

## 5. Repeated Post-Update Verification

The same complete compatibility projection ran twice after the update.

Both runs passed without transport error, PHP error or truncation.

All four productive contract hashes remained byte-equal to the repeated
pre-update baseline.

Compatibility:

| Contract | Result |
|---|---|
| productive instances and connections | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history queryability | PASS |
| command evidence | unchanged |
| Receiver pairing | retained |
| Account authentication | retained |
| exact subscriptions | retained |
| MQTT feature | disabled |
| WebSocket | inactive |
| credentials | empty |

The user's configured mower-variable logging remains intact.

## 6. Wrapper Surface

A separate PHP execution verified:

```text
probe Arm wrapper:        available
probe Close wrapper:      available
probe Report wrapper:     available
normal Connect wrapper:   available
normal Disconnect wrapper: available
Receiver diagnostics:     available
probe instances:          0
```

No wrapper was invoked during this check.

## 7. Inactive Sibling Staging

The frozen staging source was executed exactly once.

Result:

```text
probe Create calls:        1
parent Connect calls:      1
broker connection attempts:0
rollback used:             no
stage pass:                true
```

The private Device ID was derived and configured only inside Symcon. It was
not output through MCP or copied into evidence.

## 8. Staged Topology

Verified shape:

```text
retained WebSocket Client
  -> retained MQTT Client
    -> productive Navimow MQTT Receiver
    -> temporary known-good Receive Probe
```

Read-only checks proved:

- productive Receiver retained;
- probe connected;
- both children share the same MQTT parent;
- exactly one probe exists;
- no productive instance was reparented;
- no Core instance was created or changed.

## 9. Inactive Probe State

The probe report is:

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
MQTT shadow:            disabled
WebSocket:              inactive
authorization headers: empty
MQTT username:          empty
MQTT password:          empty
broker connections:     0
```

The post-staging full compatibility projection passed with all productive
hashes unchanged.

## 11. Private Evidence

Evidence is stored below:

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe/
    pre-update-baseline.json
    module-update.json
    post-update-compatibility.json
    inactive-staging.json
    gate-b-evidence-closure.json
```

The files contain only hashes, counts, branch identifiers and bounded
booleans/codes.

## 12. Architecture Decisions

### AD-NAV-487: Verify source activation in a new execution

**Decision:** Check probe wrappers after the Module Control call has returned.

**Reason:** PHP functions already loaded in the mutation execution are not
reliable post-update source evidence.

### AD-NAV-488: Stage only after repeated compatibility

**Decision:** Require two equal post-update projections before probe creation.

**Reason:** Source compatibility and topology mutation must remain separately
attributable.

### AD-NAV-489: Keep the probe unarmed during Gate B

**Decision:** Do not call Arm until the separately authorized live harness.

**Reason:** Inactive topology proof does not require accepting telemetry.

### AD-NAV-490: Preserve rollback authority

**Decision:** Gate B retains authority to delete the probe and return to
`main` if Gate C does not follow.

**Reason:** Temporary source and objects must not depend on a later approval
for cleanup.

## 13. Gate Result

Temporary Symcon update:

```text
PASS
```

Productive compatibility:

```text
PASS
```

Inactive sibling staging:

```text
PASS
```

Broker connection:

```text
NOT AUTHORIZED / NOT ATTEMPTED
```

## 14. Recommended Next Step

Gate C may now be opened.

Required authorization:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Immediately before execution, additionally require:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

If Gate C is not opened, execute the frozen inactive cleanup immediately,
delete the probe, return Module Control to verified `main` and remove the
temporary branch after evidence closure.
