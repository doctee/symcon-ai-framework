# 315 Automatic Pilot Closure L2 Activation Safe Abort

**Case study:** Navimow native IP-Symcon module

**Status:** Exactly one activation attempt reached the accepted asynchronous
reconnect state, then a private synchronous postcondition triggered mandatory
cleanup; immediate and delayed cleanup passed

**Date:** 2026-08-13

## 1. Result

After passive token refresh, the corrected restart-free preflight passed. The
single authorized mutation attempt enabled receive-only MQTT and position
diagnostics through one Account ApplyChanges.

The module returned the already established asynchronous startup state
`ReconnectScheduled`. All feature, configuration, validation, lifecycle and
session-transition checks passed. Two newly added private checks incorrectly
required automatic-closure armament and empty session accounting synchronously
before the transport had started a new session. The probe therefore invoked
its mandatory Disable fallback once.

```text
installed commit:                 888325d8649160c5bae473f4f8a052cf86e703b6
mutation-time token horizon:      3551 seconds
minimum restart-free horizon:     1200 seconds
activation ApplyChanges:          1
fallback cleanup ApplyChanges:    1
activation attempts consumed:     1
second activation attempt:        0
```

Every MCP result separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Precondition Result

Every mutation-time precondition passed:

- exact clean and valid commit on `main`;
- unchanged variable, Archive, command, topology and subscription contracts;
- REST operational and authentication connected;
- passive token horizon above the restart-free minimum;
- MQTT and position diagnostics disabled;
- Core chain inactive and credential-free;
- previous session closed;
- retained position accounting structurally bounded; and
- automatic closure inactive before activation.

## 3. Asynchronous Result

The immediate result proved:

```text
feature enabled:                true
position feature enabled:       true
configuration and validation:   ready
lifecycle:                      ReconnectScheduled
transition reason:              restart-scheduled
session sequence:               unchanged
closure state:                  not armed yet
position accounting:            retained previous session
```

This is internally consistent: a new pilot session, its zero-based accounting
and its absolute 72-hour closure deadline begin only when the asynchronous
transport startup reaches the session-start path. They cannot be required in
the same call that merely schedules reconnection.

## 4. Mandatory Cleanup

The private probe treated its overstrict postconditions as ambiguous and
immediately disabled both features through its existing fallback. Immediate
and delayed read-only verification proved:

- both features disabled;
- MQTT and WebSocket Core instances inactive;
- Authorization and MQTT credentials absent;
- no pending automatic-closure phase;
- REST operational;
- installed commit and all public and Archive contracts unchanged; and
- no delayed reactivation after more than three minutes.

## 5. Architecture Decisions

### AD-NAV-1299: Arm automatic closure at actual session start

The absolute deadline and episode baseline belong to a real receive-only
session. `ReconnectScheduled` is an accepted pre-session state and must not
fabricate a session start or deadline.

### AD-NAV-1300: Evaluate asynchronous activation in two phases

The mutation probe may accept `ReconnectScheduled` only when the old session
and accounting remain unchanged and closure remains inactive. A later bounded
read-only baseline must then prove the incremented session, empty session
accounting, active closure state and exact 259200-second hard-stop interval.

### AD-NAV-1301: Do not retry after fallback cleanup

Although the failure was in private evidence logic, the credential-bearing
mutation occurred. The exactly-one authorization is therefore consumed. Any
corrected retry requires a separate fresh gate and renewed persistence
acceptance.

## 6. Gate State

| Gate | Status |
|---|---|
| passive refresh | PASS |
| mutation-time 1200-second horizon | PASS, 3551 seconds |
| one activation attempt | CONSUMED |
| module startup behavior | ACCEPTED ASYNCHRONOUS STATE |
| private immediate postcondition | FAIL, OVERSTRICT |
| mandatory immediate cleanup | PASS |
| mandatory delayed cleanup | PASS |
| active pilot established | NO |

## 7. Next Step

Review the corrected two-phase private activation probe offline. A later retry
must remain separately gated and may execute only after a fresh disabled,
credential-free preflight, sufficient passive token horizon and renewed user
acceptance of temporary Core credential persistence. No service restart or
mower command belongs to that retry.
