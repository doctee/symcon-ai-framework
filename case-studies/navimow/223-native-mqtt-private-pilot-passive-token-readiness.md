# 223 Native MQTT Private Pilot Passive Token Readiness

**Case study:** Navimow native IP-Symcon module
**Status:** Passive refresh and 2883-second horizon confirmed without manual
authentication; token readiness passed, MQTT activation remains closed
**Date:** 2026-07-29
**Scope:** Observe the scheduled OAuth refresh without manual authentication
or MQTT activation

## 1. Purpose

Step 222 blocked activation because the fresh token horizon fell below the
required 2400 seconds.

The user then confirmed:

```text
Während der Beobachtung habe ich keine manuelle OAuth-, Anmelde- oder
Token-Aktualisierungsaktion in Symcon ausgeführt.
```

This step observes only the module's normal scheduled refresh path. It does
not manually refresh a token, retrieve MQTT credentials, activate MQTT, change
Symcon configuration, restart a service or command the mower.

## 2. Productive Refresh Contract

The installed Account implementation defines:

```text
TOKEN_REFRESH_MARGIN_SECONDS = 300
```

For a normally scheduled token, the timer delay is:

```text
remaining token lifetime - 300 seconds
```

The earlier 1800-second restart-arm threshold is a separate safety gate for an
already active transport. It is not the OAuth refresh trigger.

Based on the latest observation:

```text
captured:                 2026-07-29 18:36:56 Europe/Berlin
remaining:                1704 seconds
estimated expiry:         2026-07-29 19:05:20 Europe/Berlin
expected refresh:         about 2026-07-29 19:00:20 Europe/Berlin
recommended next read:    after 2026-07-29 19:01:20 Europe/Berlin
```

The estimate is observational. The live timer and API response remain
authoritative.

## 3. Passive Observations

All reads returned:

```text
transportError: null
executionError: null
truncated:      false
projection pass: true
```

Observed token horizons:

| Local time | Remaining | Result |
|---|---:|---|
| 18:31:52 | 2008 s | no refresh yet |
| 18:32:33 | 1967 s | no refresh yet |
| 18:33:54 | 1886 s | no refresh yet |
| 18:35:39 | 1781 s | no refresh yet |
| 18:36:56 | 1704 s | no refresh yet |
| 19:12:17 | 2883 s | refresh observed |

The initial decrease was monotonic and consistent with the old expiry. The
later increase from 1704 to 2883 seconds proves that the expiry moved forward.

## 4. Cadence Correction

One interval was 41 seconds although a 60-second local wait was requested.
The local wait completion and the live Symcon timestamp did not advance
equally.

After detecting this, the captured Symcon timestamp became the cadence source
of truth. Subsequent intervals were greater than 60 seconds.

The early read:

- was read-only;
- changed no timer, token or runtime state;
- did not contact the OAuth endpoint itself;
- does not count as refresh evidence;
- is retained transparently in private evidence.

Further observation is deferred to the expected refresh window instead of
continuing unnecessary short-interval reads.

## 5. Stable Safety State

Every projection continued to prove:

```text
repository:             clean and valid main@3d223a9c
MQTT feature:           disabled
lifecycle:              Disabled
MQTT/WebSocket:         inactive
MQTT credentials:       absent
Account:                Connected
ReauthRequired:         false
REST:                   operational and authoritative
structural contracts:   equal to step 220
MQTT hint:              unavailable
```

The mower's current operational state is irrelevant to this read-only OAuth
observation and is not published in this report.

## 6. Refresh Result

The completion read proved:

```text
captured:               2026-07-29 19:12:17 Europe/Berlin
token remaining:        2883 seconds
required minimum:       2400 seconds
expiry moved forward:   yes
Account:                Connected
ReauthRequired:         false
REST:                   operational
MQTT:                   disabled and credential-free
contracts:              unchanged
```

The technical passive-refresh and token-readiness criteria pass.

The user subsequently confirmed:

```text
Während der gesamten passiven Beobachtung habe ich keine manuelle OAuth-,
Anmelde- oder Token-Aktualisierungsaktion in Symcon ausgeführt.
```

The expiry movement is therefore accepted as passive scheduled refresh
evidence.

## 7. Completion Criteria

After the expected refresh window, one complete projection must prove:

```text
new token horizon:      at least 2400 seconds
expiry moved forward:   yes
manual auth action:     none
Account:                Connected
ReauthRequired:         false
REST:                   operational
MQTT:                   disabled and credential-free
contracts:              unchanged
```

If the horizon does not move forward:

- activation remains blocked;
- no manual refresh is initiated;
- the normal bounded refresh-retry behavior is observed;
- any reauthentication requirement is a hard stop.

All technical and contextual criteria pass.

## 8. Private Evidence

The resumable observation state is stored at:

```text
private/navimow-capture/output/
  native-mqtt-private-pilot-passive-token-readiness/
    observation-state.json
```

It contains no credential value, ObjectID, topic, payload, coordinate,
hostname or private device identity.

## 9. Architecture Decisions

### AD-NAV-805: Derive the observation window from productive code

**Decision:** Use the installed 300-second refresh margin to schedule the next
meaningful read.

**Reason:** The 1800- and 2400-second safety thresholds do not define when the
OAuth timer executes.

### AD-NAV-806: Use the Symcon capture time for cadence

**Decision:** Evaluate observation spacing from `capturedAtUtc`, not from local
wait completion.

**Reason:** The live projection timestamp is the relevant runtime clock and
exposed one shorter-than-intended interval.

### AD-NAV-807: Defer instead of polling through the horizon

**Decision:** Stop short-cadence reads and resume after the calculated refresh
window.

**Reason:** Additional reads provide no new evidence and conflict with the
bounded-observation principle.

### AD-NAV-808: Require confirmation after the observed refresh

**Decision:** Do not classify the refresh as passive until the user confirms
the absence of manual authentication actions across the complete interval.

**Reason:** The horizon movement proves refresh success but cannot by itself
distinguish the scheduler from a user-triggered refresh.

## 10. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only projections in this step | 6 |
| manual token refresh | 0 |
| OAuth login | 0 |
| Symcon mutations | 0 |
| MQTT credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| mower commands | 0 |

## 11. Current Gate Decision

| Gate | Decision |
|---|---|
| manual authentication absent | CONFIRMED |
| passive monotonic token observation | PASS |
| normal refresh observed | TECHNICALLY PASS |
| fresh horizon at least 2400 seconds | PASS, 2883 seconds |
| final no-manual-action confirmation | PASS |
| inactive credential-free contract | PASS |
| activation authorization | NOT REQUESTED |
| MQTT activation | CLOSED |
| pilot clock | NOT STARTED |

## 12. Next Step

Proceed with:

```text
224-native-mqtt-private-pilot-activation-and-active-baselines.md
```

That step requires the separate authorization:

```text
Aktivierung des receive-only MQTT-Transports für den überwachten
72-Stunden-Private-Pilot freigegeben.
```

Immediately before mutation it must recheck the time-dependent token horizon
and all inactive contracts. Only a fresh value of at least 2400 seconds permits
one activation and two active baselines at least 65 seconds apart.
