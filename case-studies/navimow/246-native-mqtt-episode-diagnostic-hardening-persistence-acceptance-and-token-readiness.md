# 246 Native MQTT Episode Diagnostic Hardening Persistence Acceptance and Token Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Commit-bound persistence acceptance and passive token readiness
passed; MQTT activation remains separately closed

**Date:** 2026-08-01

**Scope:** Bind one monitored private-pilot run to the installed diagnostic
commit and prove normal OAuth token readiness without activating MQTT

## 1. Purpose

Step 245 established:

```text
installed commit: 79686e52f0bbaad77d37b9cd6e4b367797d96f2e
harness policy:   NAV-MQTT-PRIVATE-PILOT-72H
harness phase:    ready-for-acceptance
inactive samples: 2
baseline spacing: 89 seconds
stop reasons:     none
```

The user explicitly granted persistence and recovery acceptance for this exact
commit. This step:

1. binds the accepted runtime terms to the installed commit;
2. performs bounded read-only token-readiness projections;
3. observes normal OAuth expiry movement without a manual refresh;
4. uses one scheduled read-only check to hit the short readiness window;
5. binds passive classification to the user's full-window confirmation;
6. leaves MQTT activation separately closed.

## 2. Accepted Boundary

The authorization covers at most one future activation sequence with:

```text
maximum duration:          72 hours
earliest evidence closure: 48 hours
transport direction:       receive-only
public-state authority:    REST
automatic recovery:        existing bounded transient-error path
credential rotation:       normal OAuth-driven replacement
post-activation cleanup:   mandatory
```

During an activated transport, Authorization and MQTT access data may be held
temporarily in the owned IP-Symcon Core instances and replaced during normal
OAuth rotation.

The acceptance does not authorize:

- MQTT activation;
- credential retrieval before activation;
- an IP-Symcon service restart;
- MQTT publishing or commands;
- public-variable authority for MQTT;
- operation beyond 72 hours;
- a second activation after a failed baseline.

## 3. Read-Only Observation

All five complete projections passed:

```text
transportError: null
executionError: null
truncated:      false
projection:     PASS
```

Observed token horizons:

| Observation | Remaining | Classification |
|---|---:|---|
| initial readiness | 1998 s | below threshold |
| before expected refresh | 1527 s | below threshold |
| later passive refresh | 2201 s | refresh observed, window missed |
| later cycle | 2198 s | refresh observed, window missed |
| scheduled check | 3483 s | readiness PASS |

The earlier increases proved that OAuth expiry moved forward, but the checks
occurred too late to retain the required 2400-second horizon. No activation was
attempted from those observations.

## 4. One-Time Scheduled Check

The user separately authorized one automatic read-only check for the calculated
refresh window.

It ran exactly once at:

```text
2026-07-31 22:22:43 Europe/Berlin
```

Result:

```text
token remaining: 3483 seconds
required minimum: 2400 seconds
difference:       +1083 seconds
```

The automation:

- executed only the existing private bounded projection;
- did not modify Symcon;
- did not retrieve MQTT credentials;
- did not activate or connect MQTT;
- did not trigger OAuth;
- did not restart a service;
- did not command the mower.

## 5. Stable Inactive Contract

The successful readiness read proved:

```text
repository:             clean and valid main@79686e5
MQTT feature:           disabled
lifecycle:              Disabled
MQTT/WebSocket:         104/104
WebSocket active:       false
Authorization present: false
MQTT user/password:     absent
Account:                Connected
ReauthRequired:         false
REST:                   operational and authoritative
variables:              14 retained
Archive loggings:       5 retained
pilot diagnostics:      format 2, inactive and closed
```

The structural hashes remained equal to step 245. The retained diagnostic
projection still contained two closed legacy episodes, 15 rotations, no open
episode, no Core transition and zero Core-status event drops.

## 6. Passive Confirmation

Codex performed no manual refresh, OAuth login or authentication mutation.

After the scheduled readiness check, the user confirmed:

```text
Während des gesamten Beobachtungsfensters bis zum automatischen Check habe ich
keine manuelle OAuth-, Anmelde- oder Token-Aktualisierungsaktion in Symcon
ausgeführt.
```

The expiry movement is therefore accepted as normal passive scheduler evidence:

```text
technical token readiness: PASS
passive refresh evidence:  PASS
MQTT activation:           CLOSED
pilot clock:               NOT STARTED
cleanup obligation:        NOT ARMED
```

## 7. Private Evidence

Machine-readable evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-episode-diagnostic-hardening-persistence-and-token-readiness/
  readiness-snapshot.json
  readiness-after-refresh.json
  readiness-passive-refresh.json
  readiness-final.json
  automatic-readiness-check.json
  observation-state.json
  evidence-closure.json
```

It contains only commit binding, bounded status projections, token-horizon
integers and operation counts. It contains no token, credential, ObjectID,
topic, payload, coordinate, hostname or private device identity.

## 8. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only Symcon projections | 5 |
| scheduled read-only checks | 1 |
| manual token refresh by Codex | 0 |
| OAuth login by Codex | 0 |
| Symcon mutations | 0 |
| MQTT credential requests | 0 |
| broker connections | 0 |
| MQTT publish operations | 0 |
| service restarts | 0 |
| mower commands | 0 |

## 9. Architecture Decisions

### AD-NAV-906: Bind persistence acceptance to commit and one run

Acceptance for an older module revision or pilot does not transfer implicitly
to `main@79686e5`.

### AD-NAV-907: Treat readiness as a time-dependent mutation precondition

A valid passive refresh is insufficient when the remaining horizon has already
fallen below 2400 seconds. Every later activation must recheck the full gate.

### AD-NAV-908: Schedule one read instead of polling through refresh windows

A single commit-bound read-only check at the calculated refresh boundary
provides stronger evidence with fewer live calls than repeated polling.

### AD-NAV-909: Require retrospective confirmation through the final check

Expiry movement proves technical refresh but cannot identify manual user
action. Passive classification requires confirmation for the complete window.

### AD-NAV-910: Keep activation and cleanup obligations separate

Readiness does not activate MQTT or arm cleanup. Both begin only after a new
explicit activation authorization and successful mutation-time preflight.

## 10. Gate Decision

| Gate | Decision |
|---|---|
| exact installed commit | PASS |
| harness phase | `ready-for-acceptance` |
| persistence and recovery acceptance | PASS |
| inactive credential-free contract | PASS |
| expiry moved forward | PASS |
| final token horizon | PASS, 3483 seconds |
| no-manual-action confirmation | PASS |
| passive refresh evidence | PASS |
| MQTT activation | CLOSED |
| pilot clock | NOT STARTED |
| mandatory cleanup | NOT YET ARMED |
| REST authority | RETAINED |

## 11. Next Step

Proceed with:

```text
247-native-mqtt-episode-diagnostic-hardening-activation-and-active-baselines.md
```

That step requires separate explicit authorization:

```text
Aktivierung des receive-only MQTT-Transports für den überwachten
72-Stunden-Private-Pilot auf Commit
79686e52f0bbaad77d37b9cd6e4b367797d96f2e freigegeben.
```

Immediately before mutation, the activation runner must recheck the exact
commit, disabled and credential-free state, REST operation, unchanged contracts
and a token horizon of at least 2400 seconds. The historical 3483-second value
cannot be reused as a mutation-time precondition.
