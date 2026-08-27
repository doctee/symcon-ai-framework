# 352 Zone Geometry Structure Evidence And OwnTracks Handoff

**Case study:** Navimow native IP-Symcon module

**Status:** Static private evidence review complete; manufacturer geometry not
yet proven

**Date:** 2026-08-27

## 1. Scope And Safety Boundary

This step searches the existing private Navimow captures and public structural
reports for evidence of zone geometry. It performs no live read, transport
activation, OAuth action, module publication, Symcon mutation or mower command.

Private coordinates, identifiers, topics, credentials and payload values are
not copied into this public report. Only field names, bounded counts, types and
privacy-safe conclusions cross the evidence boundary.

## 2. Existing Position Frame

The retained location evidence proves a high-frequency local mower pose with
the following structural fields:

```text
postureX
postureY
postureTheta
time
vehicleState
```

The evidence supports a Navimow-local coordinate frame. It does not prove
WGS84 coordinates, a geographic transform or metric scale. The local frame is
already suitable for relative tracks, but not yet for a geographic base map or
area calculation.

## 3. Area And Progress Fields

The private structural analysis contains 2,636 location records. Optional task
or area context occurs less frequently and includes:

```text
currentMowBoundary
partitionIds
mapWorkPosition
currentMowProgress
mowingPercentage
subtotalArea
mowingWeekArea
```

The bounded review proves:

- `currentMowBoundary` is an integer area identifier, not a polygon;
- `partitionIds` is a list of integer identifiers, not geometry;
- the observed partition lists were singleton lists;
- only one boundary and one partition-list identity occurred inside the
  reviewed correlation set;
- `mapWorkPosition` is an opaque, changing fixed-length hexadecimal value;
- progress and area counters were monotonic in the reviewed task evidence.

The opaqueness of `mapWorkPosition` prevents treating it as coordinates or a
polygon. Decoding it requires independent upstream or multi-sample evidence.

## 4. MQTT Map Signal

The MQTT credential metadata exposes the logical subscription labels
`realtime` and `mapChange`. These labels are not broker topic strings and do
not define a payload contract.

The retained receive-only captures prove realtime location messages. They do
not contain a verified `mapChange` payload with zone rings, vertices, holes or
excluded islands. Therefore the presence of the logical label is only a lead
for a future bounded capture, not evidence that manufacturer polygons are
available through the currently implemented transport.

## 5. Geometry Decision

The geometry source priority from step 351 remains unchanged:

1. authenticated read-only manufacturer polygons in the same local frame;
2. privately digitized polygons after a validated local-to-map calibration;
3. inferred track envelopes as diagnostic assistance only.

The existing identifiers can correlate a task or pass with an area. They
cannot define the area's boundary or denominator. No percentage of actual
geometric coverage may be calculated from them alone.

## 6. Next Navimow Evidence Gate

A future private receive-only probe may target the logical `mapChange` path
only after it defines:

- an exact topic allowlist derived without publishing private topic strings;
- a short absolute module-owned deadline;
- structure-only output with values redacted;
- strict payload, size and message-count bounds;
- no public-state mutation and no MQTT command path;
- automatic credential-first cleanup with immediate and delayed verification.

If no map payload can be obtained, the next fallback is an offline calibration
experiment with at least three non-collinear private control-point pairs.

## 7. OwnTracks Workstream Handoff

The planned replacement for the existing OwnTracks map is a separate case
study and workstream. Its first phase is read-only and inventories exactly
three configured tracker instances without changing their current module,
archive logging or visualization.

The OwnTracks study should record only privacy-safe contracts:

- instance and variable roles by Ident or semantic name, not public ObjectIDs;
- latitude, longitude, timestamp, accuracy and optional activity types;
- archive cadence, retention and gap behavior;
- tracker freshness and segmentation requirements;
- current map ownership and replacement/rollback boundary.

The study must not import Navimow-local assumptions. OwnTracks positions use
WGS84 and geodesic distance, while Navimow positions use a local frame and a
separate distance strategy.

Only after both case studies have stable adapters should a framework workstream
extract the smallest provider-neutral track contract and renderer. Until that
evidence exists, shared behavior remains an architecture candidate rather than
a new public SAEF helper.

## 8. Result

**Static geometry evidence review: COMPLETE.**

**Manufacturer zone polygon contract: NOT PROVEN.**

**OwnTracks implementation in the Navimow workstream: REJECTED.**

The next work can proceed independently as a bounded private Navimow
`mapChange` structure probe and a separate OwnTracks requirements and live
inventory case study.
