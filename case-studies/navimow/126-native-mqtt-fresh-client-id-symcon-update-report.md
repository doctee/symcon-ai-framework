# 126 Native MQTT Fresh-Client-ID Symcon Update Report

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary branch installed and read-only compatibility passed;
live connection gate closed
**Date:** 2026-07-28
**Scope:** Execute Gate B from step 124 without invoking the experiment

## 1. Purpose

Step 125 published the frozen one-file experiment branch.

This step:

1. captures a repeatable read-only `main` baseline;
2. changes Module Control to the exact experiment branch once;
3. verifies the installed branch and commit;
4. confirms both temporary wrappers exist;
5. repeats the complete compatibility projection;
6. proves MQTT remains disabled and credential-empty;
7. stops before either temporary wrapper or any broker connection.

## 2. Authorization

The user explicitly authorized Gate B:

```text
Symcon-Update auf den temporären Fresh-Client-ID-Branch und read-only Prüfung freigegeben.
```

This authorized one Module Control branch update and bounded read-only
verification.

It did not authorize:

- invoking the temporary Connect wrapper;
- invoking the temporary Restore wrapper;
- enabling MQTT;
- a broker connection;
- mower activity or a mower command.

## 3. Read-Only Probe

A private bounded aggregate probe was added:

```text
private/navimow-capture/fresh-client-id-experiment/
  gate-b-readonly.php
```

SHA-256:

```text
153fed49867ea400dbfb1aba27ed3a179de9f0d2357868033a8f4a1c0c6c96c1
```

The probe passed PHP syntax and PHPCS.

It returns only:

- installed branch and abbreviated commit;
- clean/valid booleans;
- instance, variable, archive and command hashes;
- variable and logging counts;
- transport and authentication booleans;
- wrapper availability;
- bounded Receiver and Account diagnostic counters.

It returns no ObjectID, object name, configuration, credential, endpoint,
topic, client ID value, device identity or payload.

## 4. Pre-Update Baseline

The probe ran twice before mutation.

Both runs reported:

```text
MCP transport success: true
executionError:        null
truncated:             false
probe pass:            true
```

Installed source:

```text
branch: main
commit: 046529c5
clean:  true
valid:  true
```

Verified:

- one Account, Configurator, Device and Receiver;
- retained Receiver, MQTT Client and WebSocket Client topology;
- 14 variable identity and metadata contracts;
- five Archive Control logging contracts;
- history queryability for every logged variable;
- Account connected;
- no reauthentication required;
- access token usable;
- Receiver-to-Account binding retained;
- four exact QoS-0 subscriptions;
- no wildcard;
- MQTT shadow disabled;
- WebSocket inactive;
- authorization headers empty;
- MQTT username and password empty;
- stable Client ID present.

Before update:

```text
fresh Connect wrapper:  absent
fresh Restore wrapper:  absent
```

The two baseline runs produced identical structure hashes.

## 5. Module Control Capability

Read-only capability inspection confirmed:

```text
MC_UpdateModuleRepositoryBranch
MC_UpdateModule
MC_ReloadModule
```

The official Module Control documentation describes branch selection through
the module gear and subsequent source use from that repository branch.

The runtime-specific branch function was selected because it represents the
same Module Control operation without UI automation.

## 6. Branch Update

Exactly one mutation was executed:

```text
MC_UpdateModuleRepositoryBranch(
    ModuleControl,
    "symcon-navimow",
    "experiment/native-mqtt-fresh-client-id-20260728"
)
```

Result:

```text
true
```

Before:

```text
branch: main
commit: 046529c5
```

After:

```text
branch: experiment/native-mqtt-fresh-client-id-20260728
commit: 7e1ce7a9
clean:  true
valid:  true
```

The branch operation installed the target commit completely. Therefore:

```text
MC_UpdateModule calls: 0
MC_ReloadModule calls: 0
```

An additional `MC_UpdateModule()` call would have been a redundant second
update and was intentionally omitted.

## 7. Post-Update Read-Only Verification

The same frozen probe ran twice after the update.

Both executions reported:

```text
MCP transport success: true
executionError:        null
truncated:             false
probe pass:            true
```

The following hashes remained exactly equal to the repeated pre-update
baseline:

```text
instance topology
variable identity and metadata
Archive Control contracts
command evidence
```

Compatibility:

| Contract | Result |
|---|---|
| productive instance identities | unchanged |
| parent and connection relationships | unchanged |
| variable identities and metadata | 14/14 unchanged |
| Archive Control logging | 5/5 unchanged |
| archive history queryability | PASS |
| Receiver pairing | retained |
| subscriptions | four exact QoS 0 |
| wildcard | absent |
| Account authentication | retained |
| command evidence | unchanged |

The user's configured mower-variable logging remains intact.

## 8. Temporary Wrapper Verification

After update:

```text
NAVAC_ConnectMqttShadowWithFreshClientIdForExperiment:
available

NAVAC_RestoreMqttAfterFreshClientIdExperiment:
available
```

Both productive diagnostic wrappers also remain available.

Neither temporary wrapper was invoked.

## 9. Final Transport State

After all read-only checks:

```text
MQTT shadow:             disabled
WebSocket:               inactive
authorization headers:  empty
MQTT username:           empty
MQTT password:           empty
stable Client ID:        present
Receiver receive calls:  0
Receiver forwarded:      0
Receiver last result:    none
```

No broker connection occurred.

## 10. Private Evidence

Evidence is stored below:

```text
private/navimow-capture/output/
  native-mqtt-fresh-client-id-experiment/
    pre-update-baseline.json
    module-update.json
    post-update-readonly.json
    gate-b-evidence-closure.json
```

The files contain only hashes, counts, public branch identifiers and bounded
booleans/codes.

## 11. Architecture Decisions

### AD-NAV-461: Use the supported branch mutation once

**Decision:** Invoke `MC_UpdateModuleRepositoryBranch()` exactly once.

**Reason:** The operation installed the target branch and commit directly.

### AD-NAV-462: Do not add a redundant update

**Decision:** Skip `MC_UpdateModule()` after the branch operation reached the
target commit cleanly.

**Reason:** A second update would add mutation without additional evidence.

### AD-NAV-463: Verify wrappers by existence only

**Decision:** Do not call either experiment method during Gate B.

**Reason:** Wrapper availability proves source activation; behavior belongs to
the separately authorized live gate.

## 12. Gate Result

Temporary Symcon update:

```text
PASS
```

Read-only compatibility:

```text
PASS
```

Variable and Archive Control preservation:

```text
PASS
```

MQTT disabled and credential-empty:

```text
PASS
```

Live broker connection:

```text
NOT AUTHORIZED
```

## 13. Recommended Next Step

Gate C may now be opened.

Required authorization:

```text
Ein einmaliger Fresh-Client-ID-Live-Test mit automatischem Restore und Rückkehr zu main ist freigegeben.
```

Immediately before execution, the user must additionally confirm:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

The run will use the frozen one-shot harness, restore runtime state in
`finally`, disable MQTT and then return Module Control to verified `main`.
