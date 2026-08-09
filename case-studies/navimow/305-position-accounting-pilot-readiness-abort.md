# 305 Position Accounting Pilot Readiness Abort

**Case study:** Navimow native IP-Symcon module

**Status:** Read-only readiness gate stopped before activation because the
token horizon was below threshold

**Date:** 2026-08-09

## 1. Result

The first bounded read-only readiness probe against exact installed commit
`50b365200e0c5c55990214c31f4a46f28b1406c7` failed one mandatory activation
condition:

```text
token remaining:  1416 seconds
required minimum: 2400 seconds
```

The gate stopped before any mutation. No automatic retry was attempted.

## 2. Passing Preconditions

The same probe proved:

- exact clean and valid standalone commit on branch `main`;
- REST operational and authentication connected;
- no reauthentication requirement;
- MQTT and WebSocket inactive;
- Authorization header and MQTT username/password absent;
- exact receive-only subscription allowlist without wildcards;
- transport ownership valid;
- 14-variable identity contract retained;
- all five Archive logging contracts retained and queryable;
- position diagnostics disabled and coordinate-empty;
- new pilot-wide `positionAccounting` projection present and empty;
- prior pilot closed with no reconnect scheduled.

Every MCP channel result independently satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 3. Safety Outcome

```text
Symcon mutations:       0
ApplyChanges calls:     0
OAuth actions:          0
MQTT credential reads:  0
MQTT activations:       0
service restarts:       0
mower commands:         0
automatic retries:      0
```

## 4. Architecture Decisions

### AD-NAV-1276: Treat token horizon as a mutation-time gate

Connected authentication alone is insufficient. A short token horizon would
force an early credential rotation and weaken the first accounting segment.

### AD-NAV-1277: Do not refresh authentication to manufacture readiness

The readiness probe remains passive. It must not invoke token refresh, OAuth or
another authentication action merely to pass its own gate.

### AD-NAV-1278: Do not retry a failed live gate automatically

The first failed threshold observation closes this attempt. A later retry needs
a fresh passive token observation and renewed activation authorization.

## 5. Gate State

| Gate | Status |
|---|---|
| installed candidate | PASS |
| structural and logging contracts | PASS |
| disabled credential-free baseline | PASS |
| empty monotonic accounting baseline | PASS |
| token horizon at least 2400 seconds | FAIL |
| MQTT activation | NOT PERFORMED |
| new pilot | NOT STARTED |

## 6. Recommendation

Wait for a passive token refresh without manual authentication activity. Then
run exactly one new read-only token-readiness probe. Only a fresh result of at
least 2400 seconds may reopen a separately authorized activation attempt.
