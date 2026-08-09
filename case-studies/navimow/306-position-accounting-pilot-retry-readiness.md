# 306 Position Accounting Pilot Retry Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** One read-only retry passed; activation remains separately gated

**Date:** 2026-08-09

## 1. Result

Exactly one new bounded read-only readiness probe passed against installed
standalone commit `50b365200e0c5c55990214c31f4a46f28b1406c7`.

```text
token remaining:  3329 seconds
required minimum: 2400 seconds
readiness result: PASS
```

The probe performed no mutation and did not request MQTT credentials.

## 2. Verified Baseline

- repository branch `main`, clean and valid;
- REST operational and authentication connected;
- no reauthentication requirement;
- MQTT and WebSocket inactive;
- Authorization header and MQTT username/password absent;
- exact receive-only subscription allowlist without wildcards;
- transport ownership valid;
- 14-variable identity contract retained;
- all five Archive logging contracts retained and queryable;
- position diagnostics disabled and coordinate-empty;
- pilot-wide `positionAccounting` present and empty;
- prior pilot closed and no reconnect pending.

The MCP result independently satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 3. Authentication Attribution

The token horizon increased between step 305 and this retry. The read-only
evidence proves the new horizon but cannot alone prove that no manual OAuth or
token action occurred between observations. Passive-refresh attribution remains
open until confirmed by the operator.

## 4. Safety Outcome

```text
Symcon mutations:       0
ApplyChanges calls:     0
OAuth actions by probe: 0
MQTT credential reads:  0
MQTT activations:       0
service restarts:       0
mower commands:         0
```

## 5. Architecture Decisions

### AD-NAV-1279: Separate readiness from activation

A passing token and structure gate establishes eligibility only. It does not
authorize storing transport credentials or starting the WebSocket.

### AD-NAV-1280: Keep operator attribution explicit

Token-horizon change is observable, but passive refresh is not inferred without
operator confirmation that no manual authentication action occurred.

### AD-NAV-1281: Start future accounting from a zero baseline

The first corrected pilot may begin only while cumulative position accounting
is present and empty, making every later increase attributable to that session.

## 6. Gate State

| Gate | Status |
|---|---|
| exact installed candidate | PASS |
| disabled credential-free baseline | PASS |
| token horizon at least 2400 seconds | PASS |
| empty corrected accounting baseline | PASS |
| passive-refresh operator confirmation | OPEN |
| credential-persistence acceptance | OPEN |
| MQTT activation authorization | OPEN |
| new pilot | NOT STARTED |

## 7. Required Activation Confirmation

Before one activation attempt, obtain both statements:

1. No manual OAuth, login or token-refresh action occurred between the failed
   and passing readiness observations.
2. Authorization and MQTT credentials may be stored temporarily in the owned
   IP-Symcon Core instances during the bounded receive-only pilot, with
   mandatory disabled and credential-free cleanup afterward.

The activation itself then requires an explicit one-attempt authorization.
