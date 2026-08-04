# 144 Native MQTT Subscription Schema Correction Symcon Staging

**Case study:** Navimow native IP-Symcon module
**Status:** V3 branch installed and one sibling probe staged inactive; live
broker gate closed
**Date:** 2026-07-28
**Scope:** Execute Gate C from step 141 without retrieving credentials or
connecting to the broker

## 1. Purpose

Step 142 published the productive native `QoS` correction. Step 143 published
the unchanged receive-only sibling probe on a temporary branch based on that
corrected commit.

This step:

1. captures the complete installed baseline twice;
2. installs the exact V3 branch once;
3. verifies productive compatibility twice;
4. verifies all required wrappers without invocation;
5. stages exactly one inactive sibling probe;
6. proves the retained legacy subscriptions are accepted as migration input;
7. verifies productive contracts again after staging;
8. stops before credential retrieval, broker connection or probe arming.

## 2. Authorization

The user explicitly authorized:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-V3-Staging freigegeben.
```

This authorized one Module Control branch update and one temporary inactive
probe instance.

It did not authorize:

- probe arming;
- credential retrieval;
- MQTT enablement;
- broker connection;
- mower activity or mower command;
- `MC_ReloadModule()`.

## 3. Repeated Pre-Update Baseline

The bounded read-only projection ran twice in separate PHP executions.

Both MCP results reported:

```text
transport success: true
transportError:    null
executionError:    null
truncated:         false
projection pass:   true
```

Installed source before the update:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

Both runs produced identical:

- productive instance topology hash;
- 14-variable identity and metadata hash;
- five-variable Archive Control contract hash;
- command evidence hash.

Verified:

- 14 of 14 variables retained;
- all five configured logging contracts retained;
- logged history queryable;
- Account connected;
- no reauthentication required;
- access token usable;
- Receiver paired;
- MQTT disabled;
- WebSocket inactive;
- headers and MQTT credentials empty;
- four QoS-0 exact subscriptions without wildcard;
- no probe instance.

## 4. Supported Module Control Update

Exactly one supported operation ran:

```text
MC_UpdateModuleRepositoryBranch(
  ModuleControl,
  "symcon-navimow",
  "experiment/native-mqtt-sibling-cross-probe-v3-20260728"
)
```

Result:

```text
operation result: true
branch:           experiment/native-mqtt-sibling-cross-probe-v3-20260728
commit:           b126ec16
clean:            true
valid:            true
```

Call counts:

```text
MC_UpdateModuleRepositoryBranch: 1
additional update calls:         0
MC_ReloadModule:                 0
```

## 5. Repeated Post-Update Compatibility

The complete projection ran twice after the update.

Both executions passed without transport error, PHP error or truncation.
Every productive contract hash remained equal to the repeated baseline.

| Contract | Result |
|---|---|
| productive instances and connections | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history | queryable |
| command evidence | unchanged |
| Receiver pairing | retained |
| authentication | retained |
| MQTT feature | disabled |
| WebSocket | inactive |
| credentials | empty |

The user's configured mower-variable logging remains intact.

## 6. Wrapper Readback

Before staging, a separate read-only execution verified:

```text
probe Arm wrapper:          available
probe Close wrapper:        available
probe Report wrapper:       available
productive Connect wrapper: available
productive Disconnect:      available
productive diagnostics:     available
probe instances:            0
```

No wrapper was invoked.

## 7. Inactive Staging

The frozen staging harness ran exactly once.

```text
probe Create calls:         1
probe Connect calls:        1
broker connection attempts: 0
rollback used:              no
staging pass:               true
```

The private Device ID was derived and configured only inside Symcon. It was
not returned through MCP or copied into public evidence.

## 8. Staged Topology

Verified:

```text
retained WebSocket Client
  -> retained MQTT Client
    -> productive Navimow MQTT Receiver
    -> temporary known-good Receive Probe
```

- exactly one probe exists;
- productive Receiver retained its parent;
- Receiver and probe share the retained MQTT Client;
- no Core instance was created, deleted or reparented.

## 9. Probe and Transport State

The final read-only projection reported:

```text
probe accepting:        false
receive calls:          0
accepted messages:      0
publish attempts:       0
command attempts:       0
last result:            not-armed

MQTT shadow:            disabled
WebSocket:              inactive
authorization headers: empty
MQTT username:          empty
MQTT password:          empty
```

No external communication occurred.

## 10. Legacy Migration Gate

The retained MQTT Client still stores its pre-correction property because no
corrected Connect has run yet.

Sanitized shape:

```text
subscription count:             4
legacy Topic/QualityOfService:  4
canonical Topic/QoS:            0
invalid entries:                0
wildcards:                      0
legacy migration input accepted: true
```

This is the intended intermediate state:

- corrected code accepts the exact old representation;
- no staging action rewrites transport configuration;
- Gate D must prove the single normal Connect rewrites all four entries to
  exact `Topic` plus integer `QoS = 0`.

## 11. Post-Staging Contract Check

After probe creation, the complete productive projection ran once more.

All four hashes remained equal to the pre-update baseline:

```text
instance topology:  unchanged
variable identity:  unchanged
archive contract:   unchanged
command evidence:   unchanged
```

All 14 variables, five logging contracts and archive queryability remain
intact.

## 12. Architecture Decisions

### AD-NAV-509: Do not migrate configuration during staging

**Decision:** Leave the exact legacy property unchanged until the authorized
normal Connect.

**Reason:** Staging must remain inactive and must not simulate the productive
transport lifecycle.

### AD-NAV-510: Verify archive continuity after temporary instance creation

**Decision:** Repeat the complete productive projection after staging.

**Reason:** The user's logging configuration is a persistent compatibility
contract, not incidental test state.

## 13. Private Evidence

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v3/
    pre-update-baseline.json
    module-update.json
    post-update-compatibility.json
    inactive-staging.json
    gate-c-evidence-closure.json
```

No private credential, endpoint, topic, payload, Device ID, ObjectID or garden
detail appears in this public report.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| Gate A productive correction | PASS |
| Gate B temporary V3 publication | PASS |
| Gate C update compatibility | PASS |
| Gate C inactive staging | PASS |
| corrected schema rewrite | PENDING |
| Gate D broker connection | CLOSED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

The inactive probe remains staged only for the next explicit gate. If Gate D
does not follow, the already required cleanup must delete it and return Module
Control to corrected `main`.

## 15. Recommended Next Step

Gate D may now be opened.

Required authorization:

```text
Ein einmaliger korrigierter MQTT-Sibling-Cross-Probe-V3-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Immediately before execution, additionally require:

```text
Mäher mäht sichtbar, bleibt voraussichtlich mindestens drei Minuten aktiv und ist beaufsichtigt.
```
