# SAEF Step 405: Local Map Station Orientation Refinement

- **Date:** 2026-09-12
- **Status:** Refinement implemented and verified offline; publication and live
  validation remain closed
- **Scope:** Navimow Local Map station presentation only

## 1. Purpose

This step applies two findings from the physical map review:

- rotate the station symbol by one degree clockwise; and
- show the docked mower inside the station pointing right instead of left.

No geometry, station coordinate, map revision, mower state, retained track,
analytics, REST, MQTT, OAuth, command or Archive contract changes.

## 2. Interpretation

The station base is rotationally symmetric by 180 degrees. Its embedded
occupancy glyph is directional. The previously rendered private station angle
was approximately `149.98°`, which is visually equivalent to `329.98°` for the
base but points the occupancy glyph in the opposite direction.

The corrected orientation is approximately `330.98°`. The base therefore
moves only one degree clockwise while the docked mower points right. For the
synthetic fixture with a station direction of `π`, the corresponding renderer
angle changes from `-188°` to `-7°`.

## 3. Implementation

When station direction is available, the renderer now:

1. mirrors the supplied station axis by `180°` for the directional occupancy
   glyph;
2. retains the existing Y-axis direction conversion; and
3. applies a `-7°` visual calibration instead of `-8°`.

When no station direction is available, the deterministic fallback changes
from `-8°` to `-7°` without introducing a direction claim.

Candidate and distribution renderers contain the same implementation. The
station and occupancy paths remain unchanged, so legend geometry and all state
colors remain stable.

## 4. Verification

Focused tests prove:

- direction `π` renders as `-7°`;
- direction `0` renders as `173°`, demonstrating the directional reversal;
- missing direction uses the calibrated `-7°` fallback;
- the docked occupancy path still points right in local symbol coordinates;
- Device HTML SDK messages contain the corrected station rotation; and
- renderer syntax and existing station-state visibility contracts remain
  valid.

The accepted private geometry was rendered locally without contacting Symcon
or Navimow services. Visual inspection confirmed the one-degree base movement
and the right-pointing docked occupancy glyph.

## 5. Architecture Decisions

### AD-NAV-405-01: Correct orientation without changing the glyph

**Decision:** Reverse the direction-aware station transform rather than create
a second occupancy path.

**Reason:** The existing glyph already points right in its local coordinate
system and is reused correctly by the unrotated legend. Correcting the map
transform preserves one symbol definition and keeps docked visibility governed
solely by the established CSS state contract.

### AD-NAV-405-02: Keep the calibration presentation-only

**Decision:** Do not rewrite captured station direction or accepted geometry.

**Reason:** The finding concerns visual interpretation of the station axis,
not the private geometry source. Retained revisions and coordinate evidence
must remain immutable.

## 6. Gate Result

| Gate | Status |
|---|---|
| Local refinement | PASS |
| Focused renderer and Device tests | PASS |
| Accepted-geometry visual check | PASS |
| Generated fileset and complete repository check | PASS |
| Local candidate commit | PASS |
| Push and SAEF pull request | CLOSED |
| Standalone publication | CLOSED |
| Symcon module update | CLOSED |
| Physical Safari and iPad validation | CLOSED |
