# 334 Early Closure Task Parser Disabled Symcon Rollout

**Case study:** Navimow native IP-Symcon module

**Status:** Passed

**Date:** 2026-08-20

## 1. Scope

This step installs standalone commit
`6f8a6a9e139b64881eadd6527b5f7b883bf2f3df` while MQTT and position diagnostics
remain disabled. It verifies the stale early-cleanup registry correction and
the additive diagnostic contract without starting a live pilot.

## 2. Preflight

The bounded read-only Symcon-MCP preflight passed with all three result channels
checked separately:

```text
transportError:          empty
executionError:          empty
truncated:               false
installed commit:        405fd24b
Navimow instances:       102 / 102 / 102 / 102
MQTT and WebSocket:      104 / 104
MQTT and position:       disabled
Core credentials:        absent
REST:                    operational
closure before update:   active=false, state=Active
```

The last line reproduces the step-330 bookkeeping defect without exposing
installation identifiers or private payload data.

## 3. Single Update

Exactly one supported `MC_UpdateModule()` operation was executed. Its complete
precondition set passed, the call returned `true`, and immediate repository
readback reported clean and valid commit `6f8a6a9e`.

Neither `MC_ReloadModule()` nor an external `IPS_ApplyChanges()` call was used.

## 4. Immediate Verification

The corrected bounded read-only postflight passed:

- Account, Configurator, Device and Receiver remain status 102;
- MQTT and WebSocket remain inactive and credential-free;
- REST authentication and status polling remain operational;
- all 14 public variables retain the exact pre-update identity hash;
- all configured histories remain queryable;
- all twelve additive task-diagnostic fields are present under the private
  `mqtt-hint` projection; and
- module-owned reconciliation completed as `Closed / operator-disabled`.

The first diagnostic assertion used an incorrect top-level field path. It made
no mutation and is not acceptance evidence. The corrected check used
`shadow.observation.fields` and passed with clean MCP result channels.

## 5. Gate State

After more than two minutes, a second independent read-only probe reproduced
the immediate result. The installed commit remained clean and valid, all four
module instances remained status 102, REST remained operational, transport
credentials remained absent, the variable identity hash remained unchanged and
the closure stayed `Closed / operator-disabled`.

All accepted probes had empty `transportError` and `executionError` channels
and `truncated=false`.

| Gate | Status |
|---|---|
| disabled preflight | PASS |
| exactly one supported update | PASS |
| immediate postflight | PASS |
| delayed postflight | PASS |
| L1 overall | PASS |
| L2 receive-only activation | CLOSED |

L2 remains separate and requires a fresh token-horizon preflight plus the
established temporary credential-persistence acceptance. No restart, OAuth
action or mower command is part of this rollout.
