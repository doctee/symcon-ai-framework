# OwnTracks WGS84 Track Contract Candidate

**Status:** Case-study-local contract candidate; not a public SAEF API

**Date:** 2026-08-27

## 1. Purpose

The contract isolates the existing OwnTracks variable and archive layout from
a future renderer. It is deliberately local to this case study. Navimow step
351 identifies a possible future common track shape, but step 352 requires the
OwnTracks adapter to be proven independently first.

## 2. Source Contract

One configured source produces this conceptual structure:

```text
source
  sourceKey                 opaque installation-local key
  label                     private presentation label
  style                     private color/icon/visibility configuration
  coordinateReference      "EPSG:4326"
  current                   optional TrackObservation
  history                   bounded list of TrackObservation
  historyWindow             requested/returned range and truncation state
  pathQuery                 optional selector-driven PathQuery
  renderOptions             point/line and detail budgets
  fitBounds                 complete valid selected-day extent
  eta                       bounded EtaProjection
```

`sourceKey` must not be derived from a public tracker identifier or topic. It
only needs to be stable inside the private map configuration.

## 3. Observation Contract

```text
TrackObservation
  observedAt                OwnTracks payload time, UTC instant
  receivedAt                Archive Control record time, UTC instant
  latitudeDegrees           finite float, -90..90
  longitudeDegrees          finite float, -180..180
  altitudeMeters            optional numeric source value
  horizontalAccuracyMeters  optional non-negative numeric value
  accuracyObservedAt        optional UTC instant
  accuracyAttribution       direct | last-known | unknown
  activity                  optional normalized source state
  qualityFlags              bounded set of explicit flags
```

Candidate quality flags:

```text
delayed-reception
source-time-ahead
source-clock-skew-tolerated
out-of-order
duplicate-position
accuracy-unknown
accuracy-stale
accuracy-poor
gap-before
implausible-jump
malformed-source
```

The contract stores numeric coordinates for computation. Formatting and
locale conversion belong only to presentation.

## 4. Existing OwnTracks Mapping

| Contract field | Existing role | Mapping rule |
| --- | --- | --- |
| `observedAt` | `position.tst` | Required integer source timestamp. |
| `receivedAt` | raw archive `TimeStamp` | Required for archived observations. |
| `latitudeDegrees` | `position.lat` | Required finite float in WGS84 latitude range. |
| `longitudeDegrees` | `position.lon` | Required finite float in WGS84 longitude range. |
| `altitudeMeters` | `position.alt` | Optional numeric value; no vertical-accuracy claim. |
| `horizontalAccuracyMeters` | `acc` variable/archive | Separate changing-value stream; temporal attribution required. |
| `accuracyObservedAt` | `acc` archive timestamp | Reception-side evidence, not a payload timestamp. |
| `activity` | `motionactivities` | Optional changing-value stream with provider-owned normalization. |

The live inventory found the same `position` keys and types for all three
sources. It also proved that accuracy is not embedded in those archived
position records.

The supplementary read-only inventory found the same activity profile on all
three sources. Its private integer values normalize case-study-locally to
`unknown`, `stationary`, `walking`, `running`, `cycling` and `automotive`.
Activity remains a change-only stream: the last value may be carried forward
only up to an explicit maximum age and never backwards across its timestamp.

No separate speed variable exists on the inspected sources, and the archived
position payloads do not contain an OwnTracks velocity field. Historical
speed evidence must therefore be derived from consecutive quality-accepted
WGS84 observations. Activity may validate or reject that derived speed; it
must not fabricate a numeric speed.

ETA target candidates reuse the existing provider-neutral `SharedLocation`
descriptor. The OwnTracks runtime stores two positive instance references,
not copied coordinates or names. After exact module-type and bounded-result
validation, only the descriptor's stable key and WGS84 coordinate enter the
case-study-local resolver.

Before motion ranking, the resolver calculates the geodesic distance from the
latest quality-approved current position to each candidate. Only candidates
strictly below `100000` metres remain eligible. An empty eligible set produces
`unavailable` with reason `outside-target-radius`; it does not reuse a prior
selection. The radius is an ETA policy and is not a map-coverage or history
filter.

## 5. Temporal Join Rule

Archive Control records changed values. It does not preserve every update of
an unchanged accuracy value. A renderer therefore cannot join accuracy and
position by assuming equal record counts.

The candidate algorithm is:

1. read the bounded position window;
2. read bounded accuracy changes for the same window plus at most one
   preceding value;
3. process both streams chronologically;
4. attribute the latest accuracy change at or before each position as
   `last-known`;
5. record its archive-age at the position timestamp;
6. change attribution to `unknown` when that age exceeds the configured bound.

This is only a candidate. Before implementation, static module evidence or a
sanitized fixture must prove that `acc` represents the same horizontal
accuracy semantics across the three installed sources. An equal current
`VariableUpdated` time is supporting evidence, not a durable contract.

## 6. Ordering and Segmentation

The archive returns raw values newest first. The adapter reverses or explicitly
sorts the bounded result and then orders valid points by:

1. `observedAt`;
2. `receivedAt` as a deterministic tie-breaker; and
3. original bounded-read position as the final stable tie-breaker.

Segments must break on a configured observation-time gap. They may also break
on an implausible geodesic jump, stale or poor accuracy, or a delayed-upload
boundary. A duplicate coordinate is retained as evidence of an observation
unless the presentation deliberately de-duplicates it and reports that fact.

Distance is calculated by a WGS84-aware strategy. The strategy must document
its Earth model and antimeridian handling. No threshold is ever applied to raw
latitude/longitude degrees as if they were meters.

## 7. Freshness Contract

Freshness is evaluated independently for each source:

```text
sourceObservationAge = now - observedAt
sourceReceptionAge   = now - receivedAt
receptionDelay       = receivedAt - observedAt
```

Negative, extremely delayed or out-of-order values are quality states. A
bounded OwnTracks source-clock lead of at most five seconds is retained for
line and ETA evidence and explicitly marked `source-clock-skew-tolerated`.
Larger negative delays are marked `source-time-ahead` and excluded from line
and ETA projection. This tolerance belongs only to OwnTracks WGS84 evidence;
it does not alter or mix with the local Navimow coordinate model. Quality
states must not silently reorder unrelated sources or make an old buffered
position look live.

## 8. Bounded History Contract

Every adapter result reports:

- requested start/end;
- returned start/end;
- returned record count;
- configured record limit;
- whether the limit was reached;
- invalid and filtered counts; and
- gap/segment counts.

Limit exhaustion is a partial-result state, not success with an implicitly
complete track.

The case-study adapter applies `maxArchiveRecords` independently to the
position stream and to the combined accuracy stream. It reserves room for at
most one accuracy change preceding the requested window, because a changing
value archive otherwise cannot attribute the first positions of the day. An
exhausted position or accuracy bound makes the complete path result partial;
the two exhaustion states remain separately visible in adapter diagnostics.

## 9. Selector-Driven Path Contract

The browser has two view modes:

```text
current-overview   at most one current point and time per configured source
path               one selected source and day as a segmented line with
                   sampled timestamps and bounded direction markers
```

The initial current overview reads no archive. ETA remains unselected until the
user taps one current-position marker; only that source may then trigger the
bounded ETA evidence read. The historical path is a view of one selected
OwnTracks source, not a fourth position provider:

```text
PathQuery
  requestGeneration        monotonic UI request token
  sourceKey                one configured OwnTracks source
  from                     inclusive UTC instant
  to                       exclusive UTC instant
  renderMode               timestamp-points | segmented-line | line-with-sampled-timestamps
  maxArchiveRecords        positive bounded integer
  maxRenderedPoints        positive bounded integer
  qualityPolicyKey         versioned private policy reference
  selectedTimeZone         private IANA time-zone configuration
  etaSourceKey             optional selected current source; overview only
```

Point labels use `observedAt`, not array order or archive reception time. A
current point also carries its configured-time-zone `observedDate`; the renderer
adds that date when it differs from the overview day. The
default compatible mode is `timestamp-points`: timestamp-labelled markers
without a connecting line. `segmented-line` and
`line-with-sampled-timestamps` are optional and must honor segment gaps and
quality exclusions rather than drawing one uninterrupted polyline. They must
not create a marker for every observation unless the point budget permits it.
An omitted persistent label does not remove the point's inspection contract:
one reusable hover/tap tooltip projects `observedAt` and available accuracy
from the already loaded render feature. Tooltip activity neither expands the
point budget nor issues a new `PathQuery`.

The selection variable belongs to the control plane. It maps its association
value to a configured private `sourceKey`; labels or numeric association values
must not become tracker identity in public artifacts. A future action boundary
uses `RequestAction()` and emits a new `requestGeneration`. Results from an
older generation are discarded if the user changes the selection or window
while archive work is running.

The existing `ExternalData` instance is a projection/anchor used by the legacy
path presentation. It is preserved for rollback but does not emit a fourth
`source` contract or browser option. The renderer calculates its extent from
the current three-source overview or selected bounded path result instead of
mutating a module property on every selection.

## 10. Performance and Result Metadata

Path results extend the bounded-history metadata with:

```text
requestGeneration
archiveRecordsRead
validObservations
renderedPoints
removedByQuality
removedBySimplification
segmentCount
archiveLimitReached
renderBudgetReached
cacheStatus
```

The adapter reads only the selected source. Simplification is chronological,
preserves first/last points and segment boundaries, and never merges across a
gap. Cache entries are bounded and invalidated by an archive watermark; a
cache is an optimization, not a second history authority.

`fitBounds` is calculated before marker simplification from every valid
selected-day observation. It declares west/east/south/north and whether the
minimal longitudinal interval crosses the antimeridian. A renderer must not
derive fit-all from only the retained marker subset.

## 11. ETA Contract

The next target is supplied by a separately owned resolver:

```text
EtaTarget
  targetKey                 opaque private key
  latitudeDegrees           finite WGS84 latitude
  longitudeDegrees          finite WGS84 longitude
  routeEstimate             optional provider result
```

The projection is explicit:

```text
EtaProjection
  status                    available | reached | unavailable | stale
  strategy                  external-route | geodesic-observed-speed | none
  routeAware                boolean
  basisObservedAt           source or provider basis time
  estimatedArrivalAt        optional UTC instant
  etaSeconds                optional bounded integer
  remainingDistanceMeters   optional finite value
  speedMetersPerSecond      optional finite diagnostic value
  evidenceSampleCount       non-negative integer
  reason                    stable machine-readable reason
```

A fresh external route estimate wins. The optional fallback uses geodesic
remaining distance and a robust speed derived from recent, quality-accepted
OwnTracks observations. It is never described as road, traffic or routing
ETA. The renderer must expose `routeAware` and freshness instead of showing
both strategies identically.

Both strategies require an eligible target at a geodesic distance strictly
below `100000` metres. Provider evidence cannot override this local eligibility
gate. At or above the boundary, the projection is `unavailable`, uses strategy
`none` and exposes reason `outside-target-radius`.

Target resolution is outside the renderer and this offline core. In
particular, dynamic OwnTracks place Idents are not silently treated as the
next target.

## 12. Strict Separation from Navimow

Navimow local positions require a different adapter contract:

```text
coordinateReference = private local frame identifier
position            = local X/Y
distanceStrategy    = Euclidean after unit proof
calibration         = explicit and independently validated
```

The OwnTracks adapter accepts none of those fields or assumptions. A future
renderer may consume both adapters only after it dispatches distance,
segmentation and projection by coordinate reference. No conversion between
the frames is part of this case study.
