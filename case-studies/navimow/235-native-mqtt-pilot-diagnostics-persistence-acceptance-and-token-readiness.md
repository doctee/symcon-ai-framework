# 235 Native MQTT Pilot Diagnostics Persistence Acceptance and Token Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Commit-bound persistence acceptance and passive token readiness
passed; MQTT activation remains separately closed

**Date:** 2026-07-30

**Scope:** Bind one monitored private-pilot run to the installed diagnostic
commit and observe normal OAuth token readiness without activating MQTT

## 1. Purpose

Step 234 established:

```text
installed commit: 793249ece1c0944192ea28dade7ecd2340a5135f
harness policy:   NAV-MQTT-PRIVATE-PILOT-72H
harness phase:    ready-for-acceptance
inactive samples: 2
baseline spacing: 82 seconds
stop reasons:     none
```

The user authorized the proposed commit-bound persistence and recovery
acceptance plus passive token-readiness observation. This step:

1. binds the accepted runtime terms to the exact installed commit;
2. performs two bounded read-only readiness projections;
3. observes the token horizon moving forward technically;
4. binds the passive classification to the user's confirmation that no manual
   authentication action occurred;
5. leaves MQTT activation separately closed.

## 2. Accepted Boundary

The authorization covers one future activation sequence with:

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

Both MCP calls passed:

```text
transportError: null
executionError: null
truncated:      false
projection:     PASS
```

Observed token horizons:

| Observation | Remaining | Classification |
|---|---:|---|
| before expected refresh | 347 s | below 2400-second threshold |
| after expected refresh | 3531 s | technical readiness PASS |

The increase proves that the token expiry moved forward. Codex performed no
manual refresh, OAuth login or other authentication mutation.

## 4. Stable Inactive Contract

Both reads continued to prove:

```text
repository:             clean and valid main@793249ec
harness phase:          ready-for-acceptance
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
pilot diagnostics:      inactive and empty
```

All structural hashes remained equal to step 234.

## 5. Confirmation Result

The technical token-readiness criterion passed:

```text
3531 seconds >= 2400 seconds
```

The user subsequently confirmed:

```text
Während der Beobachtung habe ich weder "Token aktualisieren", eine
OAuth-Anmeldung noch eine andere manuelle Authentifizierungsaktion in Symcon
ausgeführt.
```

The expiry movement is therefore accepted as normal passive scheduler
evidence:

```text
technical token readiness: PASS
passive refresh evidence:  PASS
MQTT activation:           CLOSED
pilot clock:               NOT STARTED
cleanup obligation:        NOT ARMED
```

## 6. Private Evidence

Machine-readable state is retained at:

```text
private/navimow-capture/output/
  native-mqtt-pilot-diagnostics-persistence-and-token-readiness/
  observation-state.json
  evidence-closure.json
```

It contains only commit binding, policy, bounded status Booleans, token-horizon
integers and operation counts. It contains no token, credential, ObjectID,
topic, payload, coordinate, hostname or private device identity.

## 7. Side-Effect Accounting

| Operation | Count |
|---|---:|
| bounded read-only Symcon projections | 2 |
| manual token refresh by Codex | 0 |
| OAuth login by Codex | 0 |
| Symcon mutations | 0 |
| MQTT credential requests | 0 |
| broker connections | 0 |
| service restarts | 0 |
| mower commands | 0 |

## 8. Architecture Decisions

### AD-NAV-855: Bind renewed acceptance to the diagnostic commit

Acceptance for an older pilot run does not transfer implicitly to
`main@793249ec`.

### AD-NAV-856: Combine acceptance and passive readiness without activation

Both activities are non-mutating. Passing them still leaves the separate
activation gate closed.

### AD-NAV-857: Observe once beyond the calculated refresh boundary

One delayed complete projection provides stronger evidence than repeated
short-cadence polling.

### AD-NAV-858: Require retrospective user confirmation

Expiry movement proves technical refresh but cannot identify whether a user
performed a manual authentication action.

## 9. Gate Decision

| Gate | Decision |
|---|---|
| exact installed commit | PASS |
| harness phase | `ready-for-acceptance` |
| persistence and recovery acceptance | PASS |
| inactive credential-free contract | PASS |
| expiry moved forward | PASS |
| token horizon at least 2400 seconds | PASS, 3531 seconds |
| no-manual-action confirmation | PASS |
| passive refresh evidence | PASS |
| MQTT activation | CLOSED |
| pilot clock | NOT STARTED |
| mandatory cleanup | NOT YET ARMED |

## 10. Next Step

Proceed with:

```text
236-native-mqtt-pilot-diagnostics-activation-and-active-baselines.md
```

That step requires a separate explicit MQTT activation authorization and an
immediate fresh readiness projection. The historical 3531-second observation
cannot be reused as a time-dependent activation precondition.
