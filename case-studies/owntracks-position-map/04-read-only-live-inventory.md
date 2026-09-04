# OwnTracks Read-Only Live Inventory

**Status:** Privacy-safe inventory complete; all mutation gates closed

**Observation date:** 2026-08-27

## 1. Scope and Method

The authorized inventory used Symcon MCP only. Bounded PHP probes performed
read-only object, variable, configuration-shape and archive queries inside
Symcon and returned sanitized structure or aggregates. The map hook response
was read once through the local loopback endpoint and reduced server-side to
engine and feature booleans.

No instance, variable, value, logging setting, archive record, script, map,
link or visualization configuration was changed. No temporary Symcon object
was created.

One initial probe called an unavailable enumeration convenience function. Its
transport succeeded but `executionError` was non-empty, so its result was
discarded. Every result used below had:

- empty `transportError`;
- empty `executionError`; and
- `truncated = false`.

ObjectIDs, instance and tracker names, topics, place identities, credentials,
coordinates, exact observation times and movement histories remain transient
private context and are omitted here.

## 2. Instance Topology

The target consists of exactly three healthy `OwnTrackData` instances. All
three reported normal instance status and share the same healthy OwnTracks
hook connection. Their public structural configuration is equivalent:

```text
Topic             private string
showAddress       boolean, enabled
showPositionData  boolean, enabled
```

The topic values and tracker-derived display names are private identifiers and
are not part of the future presentation contract.

An additional healthy `ExternalData` instance exists and is currently included
in the installed map. The user clarified that it supports a selector-driven
historical path display. It is a projected start/current point and centering
anchor, not a fourth tracker input.

This is consistent with the associated
[Symcon Community discussion](https://community.symcon.de/t/modul-owntracks-anwesenheitserkennung-und-live-tracking/126972?page=10):
the selected tracker and date determine the archived position set,
timestamp-labelled points represent the path, and an `ExternalData` instance
provides the start point for map centering. The current implementation uses
points without connections.

## 3. Variable Roles and Archive Contract

All three OwnTracks instances expose the same core roles:

| Ident | Role | Type | Archive | Finding |
| --- | --- | --- | --- | --- |
| `position` | position payload | string | raw logging, aggregation type 0 | JSON with integer `tst`, float `lat`, float `lon`, integer `alt` on all three sources. |
| `acc` | horizontal accuracy | integer, distance profile | raw logging, aggregation type 0 | Separate changing-value stream; not embedded in `position`. |
| `vac` | vertical accuracy | integer, distance profile | not logged | Current-state only; unavailable for historical attribution. |
| `motionactivities` | activity state | integer, association profile | raw logging, aggregation type 0 | Optional provider-owned state stream. |
| `alt` | current altitude | integer, distance profile | not logged separately | Historical altitude is already present in `position`. |
| `p` | pressure | float, pressure profile | not logged | Not required by the map contract. |
| `batt` | battery | integer, percentage profile | not logged | Presentation-adjacent, not position geometry. |
| `bs` | charge state | integer, association profile | not logged | Presentation-adjacent, not position geometry. |
| `place` | rendered place/address | HTML string | not logged | Private presentation output; not a stable adapter input. |
| dynamic presence Idents | configured place membership | boolean | mostly raw logging | Installation-specific; Idents encode private configured-place identity. |
| `distance<private-suffix>` | distance to configured place | float | not logged | Installation-specific derived state; excluded from the generic source contract. |

The dynamic place Idents are stable only within the installed module
configuration and may reveal configured-place relationships. The adapter must
not publish or generalize them.

Archive Control logs changes, not every unchanged update. This is material for
accuracy and activity reconstruction.

### Supplementary motion and speed inventory

A separately authorized read-only follow-up inspected a bounded recent window
without exporting current values or movement history. All three sources use
the same six-state activity profile and archive raw activity changes. The
states cover unknown, stationary, walking, running, cycling and automotive.

None of the three sources exposes a separate speed or course variable. A
bounded sample of each position archive also contained no velocity or course
field. The common position payload remained limited to source time, WGS84
latitude/longitude and altitude. Consequently, activity can classify and
plausibility-check motion, while numeric speed must be calculated from the
accepted WGS84 track. No per-source counts or state histories are retained in
this public artifact.

## 4. Current Freshness

At the observation snapshot, the latest position reception ages were
approximately:

| Anonymous source | Latest archive age |
| --- | ---: |
| Source A | 36.4 hours |
| Source B | 9.8 hours |
| Source C | 12.0 hours |

All three module instances were healthy despite different source freshness.
The map must therefore distinguish module health from tracker freshness.

## 5. Bounded 30-Day Position Analysis

Each source was read over one explicit 30-day interval with a limit of 10,000
raw position records. No series reached the limit.

| Metric | Source A | Source B | Source C |
| --- | ---: | ---: | ---: |
| position records | 1,140 | 2,598 | 2,139 |
| median interval | 96 s | 97 s | 139 s |
| p95 interval | 1,449 s | 2,161 s | 3,785 s |
| maximum interval | 88.1 h | 21.3 h | 17.9 h |
| gaps over 1 h | 37 | 100 | 109 |
| gaps over 6 h | 21 | 33 | 33 |
| gaps over 24 h | 8 | 0 | 0 |
| coordinate changes | 1,132 | 2,562 | 2,060 |
| duplicate consecutive coordinates | 7 | 35 | 78 |
| malformed/out-of-range positions | 0 | 0 | 0 |
| out-of-order payload timestamps | 0 | 0 | 1 |

The median active cadence is roughly two minutes, but it is not a fixed
sampling interval. Long gaps are normal enough that a renderer must segment
tracks explicitly instead of connecting every adjacent archive record.

The archive/payload timestamp difference further proves delayed mobile
delivery:

| Reception delay magnitude | Source A | Source B | Source C |
| --- | ---: | ---: | ---: |
| median | 1 s | 1 s | 1 s |
| p95 | 357 s | 5 s | 655 s |
| maximum | 47.5 h | 17.5 h | 16.6 h |

The future adapter must order movement by payload time and retain archive time
for freshness and diagnostics.

## 6. Accuracy Analysis

Because `acc` is a separate change stream, a bounded diagnostic temporal join
carried the latest accuracy change forward to each position. This produced
100% join coverage in the observed window, but it remains an inference: the
module contract must be verified before the renderer treats it as
sample-equivalent accuracy.

| Last-known horizontal accuracy | Source A | Source B | Source C |
| --- | ---: | ---: | ---: |
| median | 23 m | 4 m | 14 m |
| p95 | 2,339 m | 189 m | 1,369 m |
| attributed samples over 1,000 m | 14.5% | 2.8% | 5.8% |
| accuracy-state age median | 0 s | 0 s | 0 s |
| accuracy-state age p95 | 197 s | 1,203 s | 390 s |

Extremely poor accuracy values are not rare enough to ignore. A future map
needs an explicit quality policy and must not draw every position as equally
precise. The policy threshold cannot be inferred solely from this snapshot.

## 7. History Horizon and Retention

A bounded monthly presence probe covered the last 24 months. All sources had
records in the oldest probed monthly window; Source A had records in 22 of the
24 windows, while Sources B and C had records in all 24.

This proves an observed history horizon reaching approximately 23 to 24 months
ago. It does not prove the configured retention policy, the true oldest record
or continuity before that boundary. No unbounded full-history read was made.

## 8. Current Map Function

The existing map boundary is:

```text
three OwnTrackData instances ----+
                                 |
one ExternalData path anchor -----+--> OwnTracksMap instance
                                          |
                                          v
                                HTML iframe / local hook
                                          |
                                          v
                              two existing object links
```

Privacy-safe configuration and response findings:

- map instance and local hook are healthy;
- three tracker entries and one external path-anchor entry are configured,
  visible and included in auto-fit;
- auto-zoom/fit is enabled and map rotation is disabled;
- source entries support private name, icon, scale and color settings;
- the iframe uses a relative local-hook URL with opaque query state;
- the rendered hook response uses the modern OpenLayers API;
- point features and a vector layer are present;
- auto-fit and click interaction are present;
- no line or polygon geometry API was detected;
- no explicit accuracy rendering was detected; and
- remote resource references exist and require a separate provider/privacy
  review.

At the inventory snapshot the configured `Places` list was empty. That is
compatible with an inactive or cleared selector-driven path and does not
contradict the separately documented path workflow. The inventory did not
trigger the selection variable or rebuild a path.

The inventory does not claim that every runtime feature can be inferred from
static token inspection. It establishes the current ownership and replacement
boundary without exposing or changing map content.

## 9. Rollback Boundary

The existing map is the complete rollback target. A future pilot must preserve
byte-for-byte or configuration-equivalent ownership of:

- all three OwnTracks instances and their hook connection;
- the external path projection/anchor;
- the existing selection-variable behavior and its action wiring;
- the current map instance and map variable;
- current private source style configuration;
- the relative hook and its private access configuration;
- both existing links;
- all logging, aggregation and raw archive records; and
- existing visualization placement until the native-editor gate is opened.

The safest future transition is additive: create a separately owned candidate
map, place it in parallel, validate it, and roll back by removing or hiding
only that new entry. Reconfiguring the installed map is not an acceptable
rollback mechanism.

## 10. Open Questions

1. What bounded freshness, accuracy and gap thresholds fit the intended use?
2. Which one-day and multi-day point budgets preserve mobile performance?
3. Can module source or sanitized fixtures prove exact `acc` update semantics?
4. Which tile/resource provider is acceptable for private movement data?
5. Which visualization contains the two existing links, and what parallel
   placement preserves desktop and touch behavior?
6. How long must the existing map and any candidate rollback artifacts be
   retained after a future pilot?

Answering these questions or changing any live state requires a separate gate.
