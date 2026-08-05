# 286 Combined MQTT Transport and Position Pilot Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Combined transport and local-position pilot contract ready
offline; no publication, Symcon update or MQTT activation performed

**Date:** 2026-08-05

**Scope:** Combine general receive-only MQTT stability observation with local
position evidence in one bounded 48-to-72-hour private pilot

## 1. Decision

Transport and position should be evaluated in parallel, but not through two
parallel MQTT connections or two independent live pilots.

The combined pilot uses:

```text
MQTT connection count:       one owned receive-only chain
public state authority:      REST
transport observation:       native pilot diagnostics
position observation:        Position Diagnostic v1
checkpoint owner:            Navimow Account
checkpoint interval:         5 hours
earliest completion:         48 hours
mandatory deadline:          72 hours
```

This avoids connection competition, duplicated credentials and ambiguous
recovery evidence.

## 2. Parallel Evidence Channels

### 2.1 Transport channel

The established native diagnostics continue to record:

- connection attempts and successes;
- distinct transport episodes;
- disconnect observations and reconnect attempts;
- credential rotations;
- Core status transitions;
- REST and MQTT ingress context;
- fixed five-hour checkpoints.

### 2.2 Position channel

Position Diagnostic v1 records:

- latest complete local pose;
- monotonic receive sequence;
- a 512-sample detail ring;
- cumulative received and retained samples;
- coordinate changes and local path length;
- local bounds and maximum step distance;
- source-time regressions and maximum positive gap.

No position value writes to a public variable or Archive Control.

## 3. Native Checkpoint Bridge

The detail ring covers approximately 43 minutes and therefore cannot by
itself represent an entire 48-to-72-hour pilot.

Each existing native five-hour pilot checkpoint additionally captures only:

```text
positionAvailable
positionReceivedSamples
positionCoordinateChanges
positionOutOfOrderTimestamps
positionRetainedSamples
```

No coordinate, orientation, device identity, topic or payload enters the
native checkpoint history. Thirty-two retained checkpoints cover more than
the maximum pilot horizon.

## 4. Evidence Completion

A combined `PASS` requires all existing transport conditions plus:

```text
natural REST-observed mowing cycles:       at least 2
position evidence windows:                 at least 2
cycles correlated with position growth:   at least 2
received position samples:                 greater than 0
coordinate changes:                        greater than 0
position counter regression:               none
```

A position evidence window exists when both received-sample and
coordinate-change counters increase between bounded observations. A cycle is
position-covered when its REST `Running -> Docking -> Docked` transition falls
inside such a window.

## 5. Stop Conditions

Existing transport and authentication stop conditions remain unchanged.
Additional position stops are:

- malformed or ambiguous position projection;
- retained-track count above 512;
- received-sample or coordinate-change counter regression;
- position diagnostics disabled during the active pilot;
- position values entering public artifacts or Archive Control;
- cleanup leaving retained coordinates available.

Missing useful position evidence at the 72-hour deadline produces
`INCONCLUSIVE`, not a mower or transport command.

## 6. Cleanup

One mandatory cleanup closes both channels:

```text
EnableMqttShadow -> false
Account ApplyChanges once
WebSocket inactive
Authorization header absent
MQTT username/password absent
position status inactive
position latest null
position retained track cleared
```

Immediate and delayed read-only verification remain required.

## 7. Architecture Decisions

### AD-NAV-1208: Use one combined activation

Transport and position evidence share one receive-only chain and one pilot
clock.

### AD-NAV-1209: Extend native checkpoints with counters only

Unattended evidence continuity requires position progress, but native history
does not need installation coordinates.

### AD-NAV-1210: Correlate position growth with REST cycles

REST remains authoritative for cycle boundaries; MQTT proves only that local
pose evidence changed within the same bounded interval.

### AD-NAV-1211: Keep missing position evidence non-commanding

An incomplete observation ends inconclusively and never triggers mower
movement or MQTT publication.

## 8. Gate Status

| Gate | Status |
|---|---|
| combined pilot contract | PASS OFFLINE |
| one-chain transport boundary | PASS |
| position evidence criteria | PASS |
| native position checkpoint design | PASS |
| productive implementation | IMPLEMENTED, NOT PUBLISHED |
| private harness implementation | IMPLEMENTED, NOT LIVE |
| publication | CLOSED |
| Symcon update | CLOSED |
| pilot activation | CLOSED |

## 9. Next Step

Validate and document the implemented combined private harness, including
legacy transport-mode compatibility, bounded snapshot format 3, unattended
native checkpoint reconstruction and cleanup behavior.
