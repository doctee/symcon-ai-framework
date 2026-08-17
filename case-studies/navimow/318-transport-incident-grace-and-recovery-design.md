# 318 Transport Incident Grace And Recovery Design

**Case study:** Navimow native IP-Symcon module

**Status:** Session-5 episodes reconstructed; bounded incident policy designed
without productive implementation or live activation

**Date:** 2026-08-17

## 1. Purpose

Step 317 proved that automatic closure and credential cleanup work live. It
also showed that closing on the second distinct transport episode can terminate
an otherwise useful receive-only pilot after less than three hours.

This step reconstructs both session-5 episodes and designs a bounded policy
that distinguishes:

1. repeated observations inside one episode;
2. a relapse during the existing healthy-reset interval;
3. a later independent transport incident; and
4. persistent or repeatedly unstable transport.

It performs no productive PHP change, publication, Symcon mutation, OAuth
action, restart, MQTT activation or mower command.

## 2. Session-5 Reconstruction

The session started with cumulative episode sequence 52. Two new episodes,
sequences 53 and 54, were recorded correctly.

| Evidence | Episode 53 | Episode 54 |
|---|---:|---:|
| Core fault observed | 07:22:53 UTC | 07:25:14 UTC |
| Episode detected | 07:23:11 UTC | 07:26:11 UTC |
| MQTT ingress age | 60 s | 65 s |
| REST success age | 30 s | 35 s |
| REST connected | yes | yes |
| reconnect attempts used | 1 | 1 |
| Core ready | 07:24:11 UTC | not attempted |
| recovery confirmed | 07:25:11 UTC | pilot closed |
| recorded duration | 120 s | 0 s |

The second Core fault occurred only three seconds after recovery confirmation
for episode 53. Both episodes had the same failure domain:

- native MQTT and WebSocket Core status `200/200`;
- healthy REST and connected Account authentication;
- recent MQTT ingress;
- no service restart;
- no credential-rotation overlap; and
- no synchronous connection-failure classification.

The exact external trigger remains unresolved. Evidence supports classifying
episode 54 as a relapse inside one unstable transport incident, not as a later
independent incident.

## 3. Current Policy Defect

The implementation correctly suppresses duplicate observations while an
episode is open. Once a healthy Core observation has been confirmed for 60
seconds, it closes that episode as recovered. Any later error opens another
episode and immediately consumes the session's second-episode stop condition.

This mixes two different concepts:

```text
episode: one detected outage and its bounded reconnect sequence
incident: one instability cluster ending only after sustained health
```

The lifecycle already defines sustained health as 900 seconds through
`MQTT_LIFECYCLE_HEALTHY_RESET_SECONDS`. Closing an incident after only the
60-second observation cadence is inconsistent with that established recovery
boundary.

## 4. Designed Policy

### 4.1 Preserve episode diagnostics

Every outage continues to open and close an episode exactly as today. Episode
sequence, timing, Core transitions, reconnect attempts and outcome remain
available for diagnosis. Existing episode evidence is not rewritten or merged.

### 4.2 Add session-local incident accounting

The private pilot registry should additionally retain bounded fields:

```text
incidentSequence
sessionIncidentBaseline
openIncidentStartedAt
openIncidentLastEpisodeAt
openIncidentRecoveryCandidateAt
openIncidentEpisodeCount
openIncidentReconnectAttempts
```

These fields belong to the existing Account-owned Registry. They require no
new public Device variable, helper or storage mechanism.

### 4.3 Reuse the 900-second healthy boundary

An incident remains open after an episode reports recovery. It closes only
after both Core instances and the WebSocket active state have remained healthy
for the existing 900-second healthy-reset interval.

A new episode before that point is a relapse in the same incident. It does not
consume another independent-incident allowance, but it does increment bounded
incident-local episode and reconnect counters.

### 4.4 Bounded closure conditions

Automatic closure remains mandatory when any condition is true:

1. the absolute 72-hour hard stop is due;
2. one reconnect sequence exhausts its established three attempts;
3. an authentication or configuration terminal state is reached;
4. one incident remains unresolved for more than 1800 seconds;
5. one incident contains more than three detected episodes; or
6. a second independent incident opens after the prior incident achieved 900
   seconds of sustained health.

The 1800-second incident cap is absolute and is not shifted by reconnect,
credential rotation, restart or temporary healthy observations. The existing
credential-first idempotent closure state machine remains unchanged.

### 4.5 Conservative pilot boundary

This policy still permits only one recoverable independent incident during a
bounded pilot. Its improvement is narrower: brief post-recovery flapping is
evaluated as one bounded incident instead of consuming the independent-incident
allowance every 60 seconds.

It does not authorize permanent MQTT operation, direct MQTT authority, MQTT
publishing, a longer hard stop or unbounded reconnect behavior.

## 5. State Model

```text
Healthy
  -> EpisodeOpen / IncidentOpen
  -> EpisodeRecovered / IncidentStabilizing

IncidentStabilizing
  -> relapse before 900 s -> EpisodeOpen in same incident
  -> 900 s healthy       -> IncidentRecovered

IncidentOpen or IncidentStabilizing
  -> >3 episodes         -> ClosureRequested
  -> >1800 s total       -> ClosureRequested
  -> reconnect exhausted -> ClosureRequested

IncidentRecovered
  -> next episode        -> second independent incident
                         -> ClosureRequested

Any active state
  -> 72-hour deadline    -> ClosureRequested
```

## 6. Restart And Ordering

Incident timestamps and counters must survive restart with the existing pilot
registry. On `ApplyChanges()`, kernel start and lifecycle timer execution:

1. automatic closure already in progress has priority;
2. an expired 72-hour deadline closes immediately;
3. an expired 1800-second incident cap closes immediately;
4. a pending 900-second stabilization boundary is recomputed from its absolute
   timestamp; and
5. no restart resets incident allowance, deadline or counters.

No new retry is issued merely because the module restarted.

## 7. Diagnostics Contract

The additive pilot summary may expose only coordinate-free incident data:

- session incident count;
- whether an incident is open or stabilizing;
- incident age and remaining cap;
- episode and reconnect count in the current incident;
- last incident outcome; and
- closure reason.

Credentials, topics, endpoints, device identity, Core ObjectIDs and coordinates
remain excluded. REST remains the sole authority for public mower state.

## 8. Required Offline Tests

The next implementation must cover at least:

1. repeated observations inside one episode remain deduplicated;
2. a fault three seconds after recovery remains in incident 1;
3. two relapses remain recoverable when the incident stays under 1800 s;
4. a third relapse, the fourth episode in one incident, closes the pilot;
5. an incident reaching 1800 s closes even after transient health;
6. 900 seconds of continuous health closes incident 1;
7. a later episode after stable recovery closes as incident 2;
8. reconnect exhaustion still closes immediately;
9. authentication and configuration terminal states close immediately;
10. the 72-hour deadline remains absolute;
11. restart preserves stabilization and incident deadlines;
12. cleanup remains credential-first, idempotent and exactly once; and
13. disabled-state, REST, Archive and public-variable contracts remain
    unchanged.

## 9. Architecture Decisions

### AD-NAV-1308: Count incidents separately from episodes

Episodes remain exact transport diagnostics. Pilot stability policy operates on
independent incidents so short flapping does not masquerade as multiple
unrelated failures.

### AD-NAV-1309: Reuse the lifecycle healthy-reset interval

The existing 900-second boundary is the canonical definition of sustained
transport health. Reusing it aligns reconnect-attempt reset and incident
closure without introducing parallel timing semantics.

### AD-NAV-1310: Bound every grace dimension

Grace is limited by episode count, cumulative incident duration, reconnect
exhaustion and the unchanged absolute pilot deadline. No sequence can retain
credentials indefinitely by alternating brief healthy and failed states.

### AD-NAV-1311: Preserve the conservative independent-incident allowance

Only one independently recovered incident is allowed per pilot. This change
addresses the observed three-second relapse without weakening the separate
signal of a later new instability event.

## 10. Gate State

| Gate | Status |
|---|---|
| live automatic cleanup | PASS |
| session-5 episode reconstruction | PASS |
| exact external outage trigger | UNRESOLVED |
| incident-policy design | COMPLETE |
| productive implementation | CLOSED |
| standalone publication | CLOSED |
| Symcon update | CLOSED |
| another live pilot | CLOSED |

## 11. Recommendation

Proceed with a separately reviewed implementation step that adds only the
session-local incident reducer, additive diagnostics and focused offline tests.
Do not modify reconnect delays, credential rotation, REST authority, public
variables or the automatic cleanup state machine in that implementation.
