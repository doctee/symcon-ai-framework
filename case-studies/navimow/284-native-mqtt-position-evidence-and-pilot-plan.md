# 284 Native MQTT Position Evidence and Pilot Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Existing private location evidence analyzed structurally; local
pose diagnostics planned separately from REST-authoritative device state; no
productive implementation, publication or live activation performed

**Date:** 2026-08-05

**Scope:** Determine whether retained private MQTT captures contain usable
position evidence, define a privacy-bounded diagnostic contract and decide how
to combine it with the next receive-only transport pilot

## 1. Result

The retained private evidence is sufficient to design a first local-position
diagnostic without another discovery capture.

Two completed receive-only captures contain:

```text
MQTT messages:             2691
location messages:         2636
state messages:              55
location records:          2636
records with complete pose:2575
location list cardinality: exactly one record per message
```

The complete pose consists of:

```text
postureX
postureY
postureTheta
time
vehicleState
```

Absolute coordinates, device identities, topics, timestamps and payloads stay
in the private overlay and are not reproduced in this report.

## 2. Evidence Classification

`postureX` and `postureY` are numeric strings that vary over time.
`postureTheta` is also a numeric string and remains compatible with a bounded
angular representation. `time` has the shape of an epoch-millisecond value.

The nominal source-time cadence has a two-second median and approximately a
two-second 95th percentile. A small bounded set of source timestamps arrives
out of order. Receive sequence and an internal monotonic sample sequence must
therefore preserve ingestion order; consumers must not blindly sort or reject
poses solely because the device-provided timestamp regresses.

This supports the following bounded inference:

- `postureX` and `postureY` represent a mower pose in a Navimow-local map
  coordinate system;
- `postureTheta` represents orientation, likely in radians;
- the evidence does not establish a WGS84 latitude/longitude mapping;
- a garden-relative track can be evaluated before any global-map transform is
  known.

The coordinate-system and angle-unit conclusions remain inferences until they
are corroborated by upstream code, additional payload semantics or a bounded
live movement comparison.

## 3. Additional Location Fields

The captures also prove optional task and progress context:

```text
action
currentMowBoundary
currentMowProgress
mapWorkPosition
mowStartType
mowingPercentage
mowingWeekArea
partitionIds
subAction
subtotalArea
taskDelay
type
```

These fields do not all occur at pose-message frequency. Position and
task-progress parsing must therefore remain partial-state tolerant and must
not require every optional field in every message.

## 4. Separation From Existing State

The current public contract remains unchanged:

```text
public mower-state authority: REST
MQTT direction:               receive-only
public device variables:      REST-owned
MQTT position authority:      diagnostic only
MQTT command path:            absent
```

Position evidence must not overwrite `VehicleState`, `BatteryLevel`, `Online`
or command evidence. REST/MQTT comparison remains diagnostic.

## 5. Position Diagnostic v1

The first internal diagnostic projection should expose only:

```text
availability
sourceTimestamp
receivedAt
ageSeconds
localX
localY
orientation
vehicleStateCode
sampleSequence
droppedSampleCount
outOfOrderTimestampCount
```

The projection must label `localX` and `localY` explicitly as local map
coordinates. It must not label or convert them to latitude and longitude.

No new public variable or action contract is introduced in v1.

## 6. Bounded Track Evaluation

The first pilot needs a bounded private track projection rather than an
unlimited raw-message history.

Candidate policy:

- opt-in and disabled by default;
- retain at most 512 diagnostic samples;
- accept no more than one retained sample per five seconds;
- retain a newer sample immediately when the vehicle-state code changes;
- store no topic, device identity or complete raw payload;
- expose only the private diagnostic API during the pilot;
- use no Archive Control logging;
- clear the bounded track during mandatory pilot cleanup;
- never place captured coordinates in public fixtures or SAEF reports.

The 512-sample and five-second bounds are initial design values. Offline tests
must prove encoded-size and execution-time bounds before publication.

## 7. Parser Boundary

The existing parser already validates location geometry and then discards it.
The implementation should extend that established structured parser rather
than parse raw payload strings in the Account module.

Required behavior:

1. accept numeric strings and finite numeric values;
2. reject non-finite, malformed or unbounded values;
3. preserve source timestamp precision;
4. retain receive order independently from source timestamp order;
5. count bounded out-of-order source timestamps without exposing values;
6. keep missing pose fields distinct from zero;
7. update pose independently from optional progress fields;
8. never include raw geometry in exceptions or diagnostics;
9. leave existing MQTT shadow fields backward compatible.

## 8. Evaluation Questions

The combined pilot should answer:

1. Does a complete pose arrive throughout a natural mowing cycle?
2. Is the nominal cadence stable enough for five-second downsampling?
3. Do local coordinates form a continuous relative track?
4. Does orientation behave consistently with mower movement?
5. Are out-of-order source timestamps transport reordering or device behavior?
6. Which vehicle states continue sending pose data?
7. Do Docking and Docked produce distinguishable track behavior?
8. How do gaps correlate with transport episodes and REST availability?
9. Can track retention remain within the fixed encoded-size bound?

Global-map placement, route visualization and permanent archive storage are
explicitly outside the first pilot.

## 9. Pilot Sequence

The next live pilot should not start with the current geometry-discarding
build. The efficient sequence is:

1. wait for passive OAuth token readiness;
2. implement and offline-test Position Diagnostic v1;
3. review privacy, size and compatibility contracts;
4. publish and install the exact receive-only candidate through bounded gates;
5. run one inactive, credential-free Symcon preflight;
6. activate one combined 48-to-72-hour transport and position pilot;
7. observe at least two natural mowing cycles;
8. perform automatic checkpoints and mandatory cleanup;
9. evaluate only private, sanitized or aggregate evidence.

One combined activation authorization may cover activation, scheduled
read-only checkpoints and mandatory cleanup when it binds the exact installed
commit and stop conditions. Publication and Symcon update remain separate from
live activation.

## 10. Stop Conditions

The pilot must stop and clean up if:

- MQTT attempts a publish or command path;
- REST authority or public variables change;
- a transport episode exceeds the accepted policy;
- raw payloads, topics, identities or coordinates enter public output;
- the track bound or diagnostic byte limit is exceeded;
- malformed coordinates reach the retained projection;
- authentication requires manual intervention;
- the Account leaves normal status `102` outside a bounded transition;
- cleanup cannot prove disabled, inactive and credential-free state.

## 11. Architecture Decisions

### AD-NAV-1195: Treat captured pose as local coordinates

The evidence supports a relative Navimow map pose but not a geographic
latitude/longitude contract.

### AD-NAV-1196: Keep position diagnostic-only in v1

No automation or public variable should depend on a coordinate system that is
not yet fully characterized.

### AD-NAV-1197: Extend the structured MQTT parser

Pose extraction belongs beside existing validated location parsing, not in an
ad hoc Account payload path.

### AD-NAV-1198: Bound and downsample retained track evidence

High-frequency location messages must not create unbounded buffers, archives
or output.

### AD-NAV-1199: Keep coordinate evidence private

Tracks and absolute pose samples are installation data and remain outside
public Git history.

### AD-NAV-1200: Combine transport and position observation

After offline implementation, one natural pilot can validate transport
stability and pose usefulness without spending another mowing cycle on a
geometry-discarding build.

### AD-NAV-1201: Preserve REST authority

MQTT position evidence does not alter the established REST-owned state and
command contracts.

### AD-NAV-1202: Separate sample order from source time

The captures contain bounded timestamp regressions. A monotonic sample
sequence preserves ingestion truth while source time remains diagnostic data.

## 12. Gate Status

| Gate | Status |
|---|---|
| private structural evidence analysis | PASS |
| coordinate-system inference | LOCAL MAP ONLY |
| Position Diagnostic v1 implementation | CLOSED |
| productive publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| coordinate retention | CLOSED UNTIL IMPLEMENTED |
| public position variables | NOT PLANNED FOR V1 |
| permanent position archive | CLOSED |
| mower command | CLOSED |

## 13. Next Step

Implement Position Diagnostic v1 offline in the dedicated Navimow worktree,
including parser, bounded-track and privacy regression tests. Do not publish,
update Symcon or activate MQTT without the corresponding later authorization.
