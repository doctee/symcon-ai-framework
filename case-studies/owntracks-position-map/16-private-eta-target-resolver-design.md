# Private ETA Target Resolver Design

**Status:** Bounded two-target inference approved as a repository candidate;
no live target or routing authority configured

**Date:** 2026-08-31

## 1. Decision Boundary

The selected OwnTracks source may resolve one likely destination from a small,
explicitly configured private target set. For the first live design this set
contains exactly the two user-named alternatives. The resolver does not infer
arbitrary places, inspect place names or treat the final point of a selected
day as intent.

The existing external source remains a path-display anchor. Reusing it as a
destination would merge two unrelated semantics and could expose a misleading
ETA.

## 2. Evidence Roles

| Evidence | Role | Excluded interpretation |
| --- | --- | --- |
| Quality-accepted WGS84 positions | Ground speed and change in distance to each candidate | Road-route distance or destination intent by itself |
| Archived `motionactivities` | Bounded state and plausibility filter | Numeric speed or route choice |
| Referenced `SharedLocation` descriptors | Stable key and WGS84 candidate set | Copied coordinates or a second place catalog |
| External path anchor | Historical presentation | ETA destination |

The resolver selects a candidate only when recent segments predominantly
close its distance, show minimum net progress and beat the next candidate by a
configured confidence margin. Otherwise it returns `ambiguous`. Stationary,
stale, insufficient and motion/speed-conflicting evidence returns
`unavailable`; it does not retain a guessed target indefinitely.

## 3. Activity and Speed Policy

The installed profile normalizes to:

```text
unknown | stationary | walking | running | cycling | automotive
```

Activity archives contain changes, not samples for every position. The most
recent activity at or before the latest position can be carried forward only
within a bounded maximum age. A later activity must never be applied
backwards.

No historical velocity field is available in the three inspected sources.
The candidate therefore derives robust ground speed from consecutive WGS84
positions and uses activity-specific plausible ranges. Activity never creates
a missing speed. For ETA, target-closing speed is better diagnostic evidence
than raw ground speed, because sideways motion does not reduce remaining
distance.

## 4. Resolver Output

```text
status                         selected | ambiguous | unavailable
reason                         stable machine-readable reason
targetKey                      opaque key or null
motionMode                     normalized mode
motionObservedAt               carried-forward evidence time or null
groundSpeedMetersPerSecond     robust recent ground speed or null
closingSpeedMetersPerSecond    selected-target closing speed or null
remainingDistanceMeters        geodesic diagnostic distance or null
confidence                     best score or null
confidenceMargin               margin over runner-up or null
evidenceSegmentCount           bounded supporting segment count
```

The private configuration supplies exactly two positive `SharedLocation`
instance references. The runtime verifies the exact module type, bounds the
descriptor, validates its stable key and WGS84 coordinate locally and
registers both references. Raw destination coordinates are not duplicated in
the OwnTracks module configuration. Destination names, coordinates and object
references do not enter public files or renderer diagnostics.

## 5. ETA Semantics

A selected target permits a motion-aware **diagnostic geodesic ETA**. It is
not route-aware and can be optimistic for road travel. A future fresh route
estimate remains authoritative over this fallback. The UI must distinguish
`Diagnostic ETA` from a route-aware ETA and show no estimate for ambiguous or
stationary evidence.

Candidate eligibility precedes motion scoring. Only a target strictly less
than `100000` metres from the latest quality-approved current position may be
selected. If neither of the two targets is eligible, the resolver returns
`outside-target-radius` and clears any previous inference. This is deliberately
an ETA-only rule; selected-day rendering and fit-all remain independent.

## 6. Gate Result and Remaining Boundary

The case-study-local `OwnTracksMotionAwareTargetResolver` and synthetic tests
cover movement toward either target, stationary state, activity/speed
conflict, stale and future activity evidence, and ambiguous sideways motion.
It is not a public SAEF abstraction and does not cross the Navimow local
coordinate boundary.

The repository integration packages the bounded activity read, consumes the
existing provider-neutral `SharedLocation` contract and feeds selected-target
closing speed into the diagnostic ETA projector. A further explicit gate is
required to install that package, configure the two private instance
references, activate target inference in the live candidate, or add routing.
No WebHook, provider, existing map, archive or OwnTracks instance is changed by
this gate.
