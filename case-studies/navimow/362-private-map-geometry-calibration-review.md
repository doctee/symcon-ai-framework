# 362 Private Map Geometry Calibration Review

**Case study:** Navimow native IP-Symcon module

**Status:** Private calibration analysis complete; local bootstrap geometry is
usable, productive integration remains gated

**Date:** 2026-08-27

## 1. Objective And Boundary

This step evaluates the retained private projection from step 361 as a source
for zone rendering, path correlation and area denominators. It also compares
the map station with a prior independent docked position observation.

All processing is offline. No Navimow request, authentication, token use,
Symcon access, message transport, mower command or publication occurs.

Exact zone names, aliases, ids, coordinates, bounds, point counts, obstacle
counts, areas and timestamps remain in ignored mode-`600` private reports. This
public review contains only structural findings and architecture decisions.

## 2. Repeatable Private Analyzer

The ignored analyzer
`private/navimow-capture/analyze_private_map_calibration.py`:

- accepts only the reduced projection and selected retained read-only evidence;
- bounds input sizes, zone, obstacle and ring cardinality;
- validates closed finite rings;
- assigns obstacle representatives to containing zones;
- compares reported zone area with boundary area minus assigned obstacles;
- compares map-reported area with the sum of zone-reported areas;
- compares map station position and direction with current and historical
  docked observations;
- detects strict crossings between zone boundaries;
- verifies the private alias configuration without exporting it;
- writes one detailed private report and one boolean-only sanitized report;
- uses atomic mode-`600` writes and rejects existing output targets;
- includes a fully synthetic no-network self-test.

Analyzer SHA-256:

```text
42f141842df8be958ebcc094cbc371ab7e198861dcf96c6a932d0494ad50cc8a
```

The alias configuration and private evidence hashes are intentionally omitted
from public SAEF because they are installation-specific.

## 3. Zone Identity Findings

The private payload contains the complete expected zone set. Vendor ids are
distinct but are not equal to the human-visible zone numbers. The visible
vendor names provide the stable number labels needed to apply the private
installation aliases.

Every scheduled zone has a private alias. One additional zone remains
intentionally unassigned in the local schedule mapping.

Consequences:

- never derive a display zone from `vendor_id == display_number`;
- retain the opaque vendor id as source identity;
- bind the user-facing alias through private configuration;
- reject duplicate ids, duplicate names or a changed expected number set;
- treat a renamed or replaced vendor zone as a reconciliation event, not as an
  automatic alias migration.

## 4. Metric And Area Evidence

Three independent checks support a local metric interpretation:

1. the current docked local position lies within a narrow sub-unit tolerance of
   the charging-station point in the map projection;
2. an older independent Docked MQTT/REST position observation matches the same
   station anchor and expected opposing mower orientation;
3. for every zone, boundary area minus the obstacles assigned to that zone
   agrees with the manufacturer-reported area within two per mille.

The map-reported total also agrees with the sum of reported zone areas within
one per mille. Every retained obstacle is assigned to exactly one zone.

This is strong evidence that the local coordinate unit is a metre candidate and
the reported areas are square-metre candidates. It is not vendor documentation
of units, so the productive contract should retain an explicit
`navimow-local-metre-candidate` frame until another independent geometric scale
check closes the semantic label.

For statistics, manufacturer-reported net zone area is the preferred
denominator. Raw polygon area includes obstacle regions and must not be used as
the configured mowable-area denominator.

## 5. Station And Frame Repeatability

The station anchor agrees in the map capture and an older independent docked
position session. The mower orientation in both observations is consistently
opposed to the station direction, which is plausible for the docked pose.

Therefore:

- station-anchor repeatability across sessions is **PASS**;
- equality of the map and telemetry local frame at the dock is **strongly
  supported**;
- full two-dimensional frame repeatability remains **OPEN**.

The station is one control point. Scale, rotation and translation for a
geographic transform still require at least two additional non-collinear
landmarks. Repeated station observations alone cannot establish a WGS84 or tile
map transform.

## 6. Zone Topology And Classification

The private polygons pass ring-closure and finite-coordinate checks. The
analyzer also compares every pair of zone boundaries and finds one pair with
strict crossings. Consequently, a point can be geometrically ambiguous in
that local overlap. General per-polygon self-intersection validation remains a
prototype requirement rather than completed evidence.

Architecture rule:

1. task or schedule zone identity is authoritative when available;
2. geometry confirms that a point is plausible for that zone;
3. point-in-polygon is only a fallback outside ambiguous overlap;
4. a point matching multiple zones receives an `ambiguous` diagnostic and is
   not counted twice;
5. the renderer may show overlapping boundaries, but statistics must preserve
   one owned zone attribution per sample or pass.

This keeps map display useful without converting polygon overlap into false
area or progress totals.

## 7. Path And Area Statistics Decision

### 7.1 Map paths

Local MQTT positions can be rendered directly over the retained local zone
projection. The first implementation needs no external tile service and no
geographic coordinate conversion. It should provide:

- zone and obstacle layers;
- current mower position and freshness;
- bounded segmented path history;
- task-zone highlighting;
- explicit ambiguous-overlap styling;
- local axis scale without claiming geographic north.

### 7.2 Mowed percentage

The first statistical percentage should use manufacturer task progress or
manufacturer-reported area deltas against the manufacturer-reported zone area.
It should be stored per private zone key and per mowing pass.

A path-stripe coverage percentage remains diagnostic until mower cutting width,
overlap, obstacle clipping, repeated-lane handling and sampling gaps are
validated. Path length or a buffered track must not silently replace the
manufacturer progress percentage.

### 7.3 Multiple passes

When a schedule continues an incomplete zone, observations belong to the same
logical pass only while the existing task-correlation contract supports that
link. Reaching 100 percent closes the pass. A new traversal after 100 percent is
a new pass even if it occurs in the same schedule window.

## 8. Provider-Neutral Renderer Boundary

The future Navimow and OwnTracks maps may reuse one presentation engine, but
not one coordinate adapter.

The shared scene contract should carry:

- provider-owned source key;
- explicit coordinate reference identifier;
- current position and freshness;
- bounded timestamped segments;
- optional polygon, exclusion and progress layers;
- a coordinate-system-specific distance and viewport strategy.

Navimow supplies local Cartesian coordinates and Euclidean distance. OwnTracks
supplies WGS84 coordinates and geodesic distance. A renderer can support both
as separate scenes. Combining them on one geographic base map remains blocked
until the Navimow three-point transform passes.

This preserves reuse without forcing private Navimow geometry into the future
OwnTracks data contract.

## 9. Privacy And Retention

The detailed calibration report contains identifying garden geometry and local
semantics. It remains private and must not be copied to chat, a pull request or
the productive module repository.

The current raw capture and private projection remain temporarily retained for:

- a local map-rendering prototype;
- geometry/task-correlation tests;
- a later bounded retention decision.

The boolean-only V2 report passes a forbidden-field and private-name scan. It
contains no ids, aliases, coordinates, bounds, areas, names or timestamps.

## 10. Architecture Decisions

### AD-NAV-362-01: Accept manufacturer geometry as private bootstrap authority

**Decision:** Use the retained polygons for an offline local map prototype and
private zone configuration bootstrap.

**Reason:** Zone structure, station correlation and net-area reconciliation are
internally consistent without requiring another cloud call.

### AD-NAV-362-02: Keep productive authority gated

**Decision:** Do not yet make the single captured projection an automatically
refreshed productive module contract.

**Reason:** Full frame repeatability, map-change detection and private protocol
lifecycle are not closed.

### AD-NAV-362-03: Use reported net area as denominator

**Decision:** Prefer manufacturer-reported zone area over raw polygon area.

**Reason:** Obstacle subtraction explains the geometric difference, and the
reported value represents the configured mowable-area semantics more directly.

### AD-NAV-362-04: Keep task identity ahead of geometry

**Decision:** Resolve zone attribution from task evidence first and use geometry
as validation or fallback.

**Reason:** A confirmed boundary-crossing pair makes pure point-in-polygon
classification ambiguous.

### AD-NAV-362-05: Share rendering, not coordinate assumptions

**Decision:** Design one provider-neutral scene renderer with separate Navimow
local and OwnTracks WGS84 adapters.

**Reason:** Presentation reuse is valuable; applying WGS84 rules to local mower
coordinates is not.

## 11. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Expected zone-set reconciliation | **PASS privately** |
| Scheduled private alias coverage | **PASS** |
| Station/map frame correlation | **PASS** |
| Independent station-anchor repeatability | **PASS** |
| Net-area reconciliation | **PASS** |
| Local metric frame candidate | **PASS strong evidence** |
| Geometry-only attribution | **NO-GO due overlap** |
| Task-first zone attribution | **Required** |
| Full frame repeatability | **OPEN** |
| Geographic transform | **NO-GO pending two more control points** |
| Offline local map prototype | **GO** |
| Productive automatic map integration | **NO-GO** |
| New vendor request | **NO-GO and unnecessary** |

The recommended next step is an offline local-map and zone-statistics prototype
that consumes synthetic fixtures publicly and the retained projection only in
the ignored private overlay. It should prove viewport fitting, obstacle layers,
task-first zone assignment, overlap diagnostics and reported-area denominators.

Only after that prototype should SAEF decide the productive IP-Symcon variable,
archive and renderer contracts. Geographic tiles and the separate OwnTracks
adapter remain later independent gates.
