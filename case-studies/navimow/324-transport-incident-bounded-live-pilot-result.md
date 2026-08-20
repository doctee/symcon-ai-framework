# 324 Transport Incident Bounded Live Pilot Result

**Case study:** Navimow native IP-Symcon module

**Status:** 72-hour incident-policy pilot passed with automatic deadline
closure and verified credential cleanup

**Date:** 2026-08-20

## 1. Result

The bounded receive-only pilot on exact standalone commit
`405fd24b5450c909c35e038a12bd69378d33deb6` completed successfully.

```text
pilot start:       2026-08-17 10:57:37 CEST
absolute deadline: 2026-08-20 10:57:37 CEST
pilot stopped:     2026-08-20 10:57:38 CEST
closure completed: 2026-08-20 10:57:39 CEST
closure reason:    deadline-reached
```

Immediate and delayed read-only closure checks passed. Every accepted MCP
result separately satisfied:

```text
transportError: null
executionError: null
truncated:      false
```

## 2. Automatic Closure

The module closed the pilot at its absolute 259200-second deadline without a
manual mutation. It then disabled MQTT shadow and position diagnostics,
deactivated both owned Core instances and removed the Authorization header,
MQTT username and MQTT password.

The immediate readback followed closure by seconds. A second readback more
than two minutes later proved that the disabled and credential-free state was
stable.

```text
pilot active:              false
closure state:             Closed
next checkpoint:           none
MQTT / WebSocket status:   104 / 104
WebSocket active:          false
Authorization present:     false
MQTT username present:     false
MQTT password present:     false
position diagnostics:      disabled
retained position payload: absent
REST operational:          true
```

## 3. Incident Result

The new reducer observed exactly one incident in pilot session 6:

```text
incident sequence:       1
session incident count:  1
episodes in incident:    1
reconnect attempts:      1
incident outcome:        recovered
incident started:        2026-08-19 19:58:07 CEST
transport recovered:     2026-08-19 20:00:08 CEST
incident closed:         2026-08-19 20:15:09 CEST
bounded duration:        1022 seconds
open episode:            none
open incident:           none
```

Transport readiness returned after 121 seconds. The incident remained open
until the configured 900-second sustained-health interval elapsed, then closed
as recovered. No second independent incident occurred, so the pilot correctly
continued to its deadline instead of closing on the former second-episode
rule.

## 4. MQTT And Position Evidence

Compared with the activation baseline, the final readback reported:

```text
MQTT received delta:            9647
MQTT accepted delta:            9645
MQTT rejected delta:               2
connection-attempt delta:          81
connection-success delta:          81
unexpected-disconnect delta:        1
reconnect-attempt delta:             1
pilot position samples:           7779
pilot coordinate changes:         7696
out-of-order source timestamps:      7
```

This proves sustained receive-only ingress and useful local-map movement
evidence across the pilot. Position accounting stayed monotonic across
credential rotations and ephemeral diagnostic cleanup. Absolute coordinates
and route data remain private and are not included here.

## 5. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT publish path:             absent
MQTT mower-command path:       absent
public variables:              unchanged
Archive logging identities:    unchanged
```

The pilot changed no mower behavior. MQTT and position evidence remained
diagnostic and did not overwrite REST-owned public state.

## 6. Private Evidence

The reduced private evidence and probes are retained under:

```text
private/navimow-capture/transport-incident-l2/
private/navimow-capture/output/transport-incident-l2/
```

They are excluded from publication. Public evidence contains no credentials,
private topics, payloads, coordinates, device identities, ObjectIDs or host
metadata.

## 7. Architecture Decisions

### AD-NAV-1331: Accept the incident reducer after natural recovery

One transport episode formed one incident, recovered within the duration
limit and closed only after sustained health. This is the intended policy and
directly addresses the premature closure observed before the reducer.

### AD-NAV-1332: Accept automatic deadline closure independently

Automatic closure occurred at the absolute deadline and remained disabled and
credential-free in delayed readback. Manual cleanup is unnecessary when both
closure proofs pass.

### AD-NAV-1333: Retain position accounting but not live geometry

Aggregate counters are useful engineering evidence. Live coordinates and
route geometry remain private and are cleared from the active diagnostic
projection when the pilot closes.

## 8. Gate State And Recommendation

| Gate | Status |
|---|---|
| SAEF merge | PASS |
| standalone publication | PASS |
| metadata conformance | PASS BY BYTE EQUIVALENCE |
| disabled Symcon rollout | PASS |
| bounded incident-policy activation | PASS |
| 72-hour live observation | PASS |
| automatic deadline closure | PASS |
| immediate credential cleanup | PASS |
| delayed credential-free stability | PASS |

The transport-incident reducer and automatic closure are accepted for the
bounded private-pilot contract. MQTT remains disabled by default after the
test.

The next SAEF step should analyze the retained optional task, area and map
fields before designing route visualization or per-area mowing statistics.
That work must remain separate from any decision about permanent MQTT
activation.
