# 297 Combined MQTT Position Pilot Cleanup

**Case study:** Navimow native IP-Symcon module

**Status:** Cleanup complete; immediate and delayed credential-free state
proved

**Date:** 2026-08-09

## 1. Purpose

Step 296 closed the combined 72-hour pilot as `FAIL` and required cleanup.
This step records the separately authorized one-shot cleanup without changing
the installed module version or issuing a mower command.

## 2. Authorized Operation

The existing private cleanup harness performed exactly:

1. verify one Account and its owned Receiver, MQTT Client and WebSocket chain;
2. require MQTT and position diagnostics to be enabled;
3. set `EnableMqttPositionDiagnostics=false`;
4. set `EnableMqttShadow=false`;
5. execute exactly one Account `ApplyChanges()`;
6. verify inactive transport and absent credentials.

There was no explicit disconnect call, retry, OAuth action, service restart or
mower command.

## 3. Result

The mutation and both independent read-only checks passed:

| Check | Immediate | Delayed, 225 seconds |
|---|---:|---:|
| MQTT feature disabled | PASS | PASS |
| position diagnostics disabled | PASS | PASS |
| lifecycle `Disabled` | PASS | PASS |
| MQTT/WebSocket `104/104` | PASS | PASS |
| WebSocket inactive | PASS | PASS |
| Authorization absent | PASS | PASS |
| MQTT username/password absent | PASS | PASS |
| reconnect scheduled | NO | NO |
| REST operational | PASS | PASS |
| variable and Archive contracts | PASS | PASS |

Every MCP result separately reported:

```text
transportError: null
executionError: null
truncated:      false
```

## 4. Privacy And Evidence

Machine-readable evidence remains below `private/`. No credential, topic,
endpoint, ObjectID, device identity, coordinate or installation metadata is
copied into this report.

## 5. Architecture Decisions

### AD-NAV-1250: Disable both pilot features together

The combined pilot owns one cleanup boundary. MQTT and position diagnostics
must not remain in a partially enabled state after closure.

### AD-NAV-1251: Use one Account ApplyChanges

The Account remains the lifecycle owner. Cleanup therefore changes both
properties first and applies the Account configuration exactly once.

### AD-NAV-1252: Require delayed credential-free proof

Immediate cleanup success does not exclude a delayed reconciliation. A second
read-only check after more than 180 seconds proves that the transport does not
restart itself.

## 6. Gate Status

| Gate | Status |
|---|---|
| combined pilot | CLOSED, FAIL |
| mandatory cleanup | PASS |
| credentials absent | PASS |
| REST continuity | PASS |
| publication | NOT PERFORMED |
| Symcon update | NOT PERFORMED |
| MQTT reactivation | NOT PERFORMED |

## 7. Next Step

Analyze the pilot failure without live access. Preserve the coordinate cleanup
boundary, explain credential rotations separately from unexpected transport
episodes and design monotonic pilot-wide position accounting.
