# ETA Radius Implementation

**Date:** 2026-08-31

## Outcome

The repository candidate now enforces the agreed ETA eligibility boundary in
`OwnTracksMotionAwareTargetResolver`. It is case-study-local and does not add a
general SAEF map, location or ETA abstraction.

After validating freshness, the resolver calculates WGS84 geodesic distance
from the latest quality-approved current position to every configured target.
Only targets strictly below `100000` metres enter segment construction and
motion scoring. If none remain, the result is:

```text
status = unavailable
reason = outside-target-radius
targetKey = null
basisObservedAt = latest current observation time
```

The distance is not configurable and cannot be widened by private module
configuration or provider output. It does not filter selected-day history,
fit-all geometry or map coverage.

## Verification

Synthetic resolver tests cover:

- an exact 100-kilometre target, which is rejected;
- a target immediately inside the boundary, which remains eligible;
- exclusion of a farther candidate before scoring; and
- the existing directional selection, stationary, activity conflict, stale
  activity and ambiguity cases.

The deterministic Symcon module fileset was regenerated from canonical sources
and the complete OwnTracks case-study test suite remains the acceptance gate.

## Closed Live Boundary

This gate changes repository and generated package artifacts only. It does not
install the new package, change either `SharedLocation`, mutate an OwnTracks
instance, register a hook, alter archives or modify the visualization. A fresh
byte-exact rollback candidate and separate authorization remain mandatory for
live activation.
