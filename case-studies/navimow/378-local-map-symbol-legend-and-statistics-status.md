# 378 Local Map Symbol Legend And Statistics Status

**Case study:** Navimow native IP-Symcon module

**Status:** Offline implementation and private visual verification passed;
publication and live rollout remain separately gated

**Date:** 2026-08-28

## 1. Goal

Add a compact symbol legend to the free lower-right area of the Local Map and
state precisely which map statistics already exist. The change must preserve
the accepted private geometry, the current Dark-Skin presentation, the 14
established mower variables and their Archive logging.

## 2. Legend Contract

The renderer now adds one passive SVG legend containing:

- the charging station with the current REST-authoritative station color;
- the current mower marker;
- a retained mowing-path line;
- the subtle excluded-area outline;
- the three diagnostic colors for outside, ambiguous and unknown-task-zone
  attribution.

The legend is rendered last in the free lower-right map area. Its dimensions
and typography derive from the bounded viewport instead of browser width. The
Dark and Light themes use separate background, border and text colors.

Legend elements use dedicated `legend-*` classes. They therefore cannot be
mistaken for productive station, mower, path, obstacle or diagnostic-point
elements by runtime checks or future statistics reducers.

## 3. Current Statistics Status

The active private map currently has a validated structural basis of four
zones and seven excluded areas. At the read-only inventory for this step,
MQTT remained disabled and the rendered scene contained:

| Evidence | Current result |
| --- | --- |
| retained path segments | none |
| diagnostic position points | none |
| current MQTT mower marker | none |
| REST-authoritative station marker | one |
| public per-zone mowing statistics | none |

The runtime already has bounded internal candidates for revision-bound tracks,
task observations and zone statistics. They are not yet a proven user-facing
area statistic. In particular:

- manufacturer task progress is pass-local evidence;
- point count and path length are not mowed-area percentages;
- geometric coverage needs a calibrated mower width, overlap handling and a
  stable zone-area denominator;
- map edits in the official app require a new private geometry revision before
  new paths and statistics may be combined with the changed boundaries.

## 4. Verification

The candidate and distribution renderers remain identical except for their
intended namespace. Targeted tests prove:

- exactly one complete legend;
- correct station-state color selection;
- separate Dark and Light presentation;
- unchanged productive layer counts;
- no script, external reference, event handler or other active SVG content;
- valid bounded distribution structure.

A private coordinate-bearing Dark-Skin scene was rendered locally. The visual
review confirmed that the legend is readable, occupies the free lower-right
area and does not overlap the mapped zones, station or retained path. Private
coordinates and zone geometry remain outside the public case study.

## 5. Architecture Decisions

### AD-NAV-378-01: Keep the legend presentation-only

**Decision:** Use dedicated legend classes and derive the station sample color
from the same explicit REST-authoritative presentation input as the real
station marker.

**Reason:** A visual key must not change layer counts, data ownership or state
authority.

### AD-NAV-378-02: Do not present provisional coverage as fact

**Decision:** Expose no percentage until its denominator and evidence source
are explicit. Manufacturer pass progress and geometric area coverage remain
separate metrics.

**Reason:** Sparse MQTT points can support route visualization but cannot by
themselves prove actually mowed area.

### AD-NAV-378-03: Preserve map-revision boundaries

**Decision:** Bind later paths and statistics to the accepted private geometry
revision and stop combining them after an app-side map change until the new
revision is reviewed and accepted.

**Reason:** Changed boundaries or excluded areas invalidate otherwise plausible
zone attribution and area denominators.

## 6. Gate Status

| Gate | Status |
| --- | --- |
| renderer implementation | passed offline |
| semantic layer isolation | passed offline |
| private Dark-Skin visual review | passed |
| SAEF publication | closed |
| standalone publication | closed |
| Symcon update and rerender | closed |
| MQTT activation | not requested |
| mower command | not performed |

## 7. Next Step

After publication and a disabled-MQTT Symcon update, rerender the existing
accepted map once and verify the legend plus unchanged productive layer and
Archive contracts. A later, separately gated receive-only MQTT observation can
then populate revision-bound path evidence. Define statistics only after that
evidence distinguishes pass progress from calibrated geometric coverage.
