# 308 Position Accounting Pilot Corrected Readiness Abort

**Case study:** Navimow native IP-Symcon module

**Status:** Corrected private activation contract reviewed; fresh read-only
readiness stopped on token horizon before any mutation

**Date:** 2026-08-09

## 1. Result

The corrected private activation candidate was reviewed against the productive
native-pilot lifecycle on standalone commit
`50b365200e0c5c55990214c31f4a46f28b1406c7`.

The subsequent fresh bounded read-only readiness probe stopped the gate:

```text
captured:             2026-08-09 08:28:26 UTC
token remaining:      1599 seconds
required minimum:     2400 seconds
readiness result:     FAIL
Symcon mutations:     0
MQTT activations:     0
```

No activation candidate was invoked.

## 2. Corrected State Contract Review

The productive module starts native pilot observation from the bounded MQTT
connection path. Account `ApplyChanges()` may first result in
`ReconnectScheduled`; the native session sequence advances only when the
connection attempt starts.

The corrected private candidate now distinguishes:

| Immediate lifecycle | Required pilot state |
|---|---|
| `ReconnectScheduled` | previous session remains closed, sequence unchanged, no next checkpoint |
| `Ready`, `Connecting`, `ShadowActive` | sequence advanced exactly once, session active, next checkpoint scheduled |

Both branches still require:

- both receive-only features enabled in Account configuration;
- transport configuration and ownership valid;
- position diagnostics enabled only as diagnostic data;
- pilot-wide position accounting present and initially zero; and
- the existing mandatory disable cleanup on any rejected postcondition.

The later active-baseline gate remains responsible for proving that the
scheduled connection actually advanced the session and reached stable ingress.

## 3. Readiness Evidence

All non-token gates passed:

- exact standalone `main` commit, clean and valid;
- identity, Archive, command-evidence, topology and subscription contracts
  unchanged;
- 14 public variables retained;
- all five Archive logging contracts retained and queryable;
- REST operational and Account authentication connected;
- no reauthentication requirement;
- MQTT and WebSocket inactive;
- Authorization header and MQTT username/password absent;
- receive-only subscription allowlist valid and without wildcards;
- previous native pilot session closed;
- no reconnect pending; and
- corrected pilot-wide position accounting present and exactly zero.

The only failed condition was:

```text
tokenRemainingSeconds >= 2400
```

## 4. MCP Channel Verification

The structured result channels were evaluated independently:

```text
transportError: null
executionError: null
truncated:      false
```

The `contract-failed` result came from the readiness policy, not from MCP
transport or PHP execution.

## 5. Side-Effect Accounting

| Operation | Count |
|---|---:|
| read-only readiness probes | 1 |
| Account `ApplyChanges()` | 0 |
| MQTT credential requests | 0 |
| MQTT activations | 0 |
| OAuth actions | 0 |
| service restarts | 0 |
| mower commands | 0 |

No automatic readiness retry was performed.

## 6. Architecture Decisions

### AD-NAV-1286: Review asynchronous acceptance before another live attempt

The private activation guard must model the productive transition boundary
before it is allowed to mutate Account configuration again.

### AD-NAV-1287: Keep the token threshold mutation-bound

A structurally valid disabled baseline does not authorize activation when less
than 2400 seconds remain on the observed access-token horizon.

### AD-NAV-1288: Do not convert a read-only failure into an automatic retry

Passive token refresh may create a later eligible window, but observing that
window requires a separately bounded read-only retry and operator attribution.

## 7. Gate State

| Gate | Status |
|---|---|
| corrected private activation contract review | PASS |
| exact installed candidate | PASS |
| disabled credential-free baseline | PASS |
| REST and public contracts | PASS |
| empty corrected accounting baseline | PASS |
| token horizon at least 2400 seconds | FAIL, 1599 SECONDS |
| activation candidate invocation | NOT PERFORMED |
| automatic retry | NOT PERFORMED |
| receive-only pilot | NOT STARTED |

## 8. Next Step

Wait for a passive token-refresh opportunity without manual authentication.
Then perform exactly one separately bounded read-only token-readiness retry.
If it passes, obtain explicit operator attribution for the observation window
and bind any one-attempt activation authorization to that fresh result.
