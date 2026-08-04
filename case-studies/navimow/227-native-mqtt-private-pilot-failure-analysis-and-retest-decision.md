# 227 Native MQTT Private Pilot Failure Analysis and Retest Decision

**Case study:** Navimow native IP-Symcon module
**Status:** Failure causes separated; immediate unchanged retest rejected,
bounded episode diagnostics and proven automation required first
**Date:** 2026-07-30
**Scope:** Analyze the closed pilot without reactivating MQTT

## 1. Purpose

Step 226 closed the first longer private pilot as `FAIL` with:

```text
evidence-gap-exceeded
multiple-transport-episodes
```

This step determines:

1. why the overnight automation did not run;
2. what the transport counters actually mean;
3. whether the observed disconnects indicate instability or successful
   recovery;
4. whether the one-episode policy remains useful;
5. what must change before any retest.

No MQTT activation, Symcon mutation, service restart or mower command occurs.

## 2. Automation Root Cause

The scheduling sequence was:

1. an immediate automation create with an anchored local start time was
   rejected by the automation API;
2. the retry used `suggested_create`;
3. the application rendered an approval card;
4. no matching persisted automation record was later found;
5. no post-create inspection verified an active automation ID.

`suggested_create` is a proposal, not proof of persistence.

The root cause is therefore:

```text
procedural automation gate failure
```

The workflow incorrectly treated a rendered proposal as an installed
automation.

### Required correction

Before a future pilot:

1. create or approve the automation;
2. inspect its persisted record;
3. verify `ACTIVE`, target task and schedule;
4. run a near-term read-only dry-run;
5. prove that the dry-run produces a private snapshot and harness ingest;
6. only then authorize MQTT activation.

An unattended checkpoint may never depend solely on a rendered automation
card.

## 3. Counter Semantics

The productive module increments `unexpectedDisconnects` when:

- a normal lifecycle observation sees MQTT or WebSocket no longer healthy
  while state is `Connecting` or `ShadowActive`; or
- retained Core instances remain unhealthy after the bounded kernel-start
  observation deadline.

It does not increment this counter for normal credential rotation.

Credential rotation instead:

1. intentionally disconnects the owned transport;
2. increments `credentialRotations`;
3. resets the reconnect episode;
4. schedules a new rotation connection;
5. records one attempt and, after health confirmation, one success.

The overnight deltas were:

| Counter | Delta |
|---|---:|
| credential rotations | +11 |
| reconnect attempts | +2 |
| connection attempts | +13 |
| connection successes | +13 |
| connection failures | 0 |
| unexpected disconnects | +3 |
| reconnect exhausted | 0 |

The accounting is internally consistent:

```text
11 rotation attempts + 2 reconnect attempts = 13 attempts
13 attempts = 13 successes
```

The rotations must not be misclassified as unexpected disconnects.

## 4. Unplanned Symcon Restart

The retained diagnostics also prove an unplanned kernel restart:

```text
kernel start:       2026-07-29 23:22:00 Europe/Berlin
kernel observed:    2026-07-29 23:25:43 Europe/Berlin
kernel reconciled:  2026-07-29 23:27:13 Europe/Berlin
classification:     healthy
```

The retained Core chain was adopted without an Account reconnect. This is
consistent with the previously verified Core-resume behavior.

The restart should have produced an anomaly checkpoint under the operating
policy. The missing automation prevented that evidence from being captured in
real time.

The available data does not prove that the restart caused any of the three
unexpected disconnect counts.

## 5. Transport Interpretation

At the morning read:

```text
lifecycle:             ShadowActive / healthy
MQTT/WebSocket:        102 / 102
connection failures:   0
reconnect exhausted:   0
REST:                  operational
MQTT ingress:          continued
```

The observed transport therefore recovered technically.

However, the current diagnostics expose only:

- aggregate unexpected-disconnect count;
- aggregate reconnect count;
- latest error reason and timestamp;
- latest connection trigger and timestamp.

They do not retain enough bounded information to prove per episode:

- start time;
- detected Core statuses;
- recovery start and completion;
- recovery duration;
- attempt number;
- whether a token rotation overlapped the episode;
- whether the episode followed a kernel restart;
- final classification.

The 10-hour evidence gap makes retrospective attribution impossible.

## 6. One-Episode Policy Review

The original rule intentionally stopped after a second unexpected episode. It
was appropriate for a first pilot because no real multi-hour recovery evidence
existed.

The new evidence shows:

- three episodes over approximately ten hours;
- all observed reconnect capacity succeeded;
- no connection failure;
- no reconnect exhaustion;
- continuous REST authority;
- healthy final transport.

This suggests the one-episode rule may be too strict for normal household
network and Core-status variability.

It does not justify removing the bound immediately. Without per-episode
duration and cause, the same counters could represent either harmless short
flaps or repeated longer outages.

### Policy direction

Retain hard stops for:

- reconnect exhaustion;
- authentication or configuration failure;
- unresolved transport unhealthiness;
- REST authority loss;
- contract drift;
- evidence gap above six hours.

For recovered episodes, replace the absolute lifetime count with a bounded
episode-quality contract after diagnostics exist. Candidate dimensions are:

- recovery duration;
- attempts used;
- failure and exhaustion deltas;
- frequency within a defined window;
- REST continuity;
- final health.

No numeric replacement threshold is accepted in this step.

## 7. Retest Decision

Decision:

```text
immediate unchanged 72-hour retest: NO
immediate MQTT reactivation:        NO
diagnostic hardening first:         YES
automation dry-run first:           YES
new pilot acceptance required:      YES
new activation authorization:       YES
```

A shorter retest without episode diagnostics would only reproduce aggregate
counters and would not answer the causal question.

## 8. Required Diagnostic Delta

Before a retest, add a bounded identity-free episode projection to the existing
MQTT diagnostics. It should retain a small ring of episode summaries:

```text
sequence
detectedAt
detectionSource
mqttStatus
webSocketStatus
reconnectAttemptsUsed
recoveredAt
durationSeconds
outcome
overlappedRotation
kernelEpochChanged
```

It must contain no credential, topic, payload, device identity, endpoint,
hostname or ObjectID.

The projection remains diagnostic-only:

- no public variable;
- no Archive Control logging;
- no direct Device-state write;
- no additional connection attempt;
- no changed recovery behavior.

Use the existing Registry, Statistics and bounded ErrorRingBuffer patterns
before considering a new helper.

## 9. Automation Acceptance Gate

The next pilot automation must pass a pre-activation dry-run:

```text
persisted automation exists
status ACTIVE
correct task target
correct timezone and cadence
one near-term execution observed
private snapshot created
harness ingest succeeds
no Symcon mutation
```

For the real pilot, schedule checkpoints with margin before the six-hour hard
limit. A nominal five-hour cadence leaves one hour for delayed execution or
manual recovery.

The automation must be inspected after creation and after its dry-run.

## 10. Current Safe State

Step 226 remains authoritative:

```text
MQTT feature:           disabled
MQTT/WebSocket:         inactive
Authorization:          absent
MQTT username/password: absent
REST:                   operational and authoritative
cleanup:                complete
```

This analysis performed one bounded read of already sanitized disabled
diagnostics and no mutation.

## 11. Architecture Decisions

### AD-NAV-821: Verify persistence, not proposal rendering

**Decision:** Require an inspectable active automation record and successful
dry-run before relying on unattended evidence.

**Reason:** A suggested automation is not an execution guarantee.

### AD-NAV-822: Keep rotation separate from unexpected disconnect

**Decision:** Account for rotation and reconnect connection attempts
independently.

**Reason:** Intentional hourly credential replacement is expected behavior,
while `unexpectedDisconnects` represents observed Core unhealthiness.

### AD-NAV-823: Do not infer episode quality from aggregate recovery

**Decision:** Treat `13/13/0` connection accounting as positive recovery
evidence but not as proof that each episode was brief.

**Reason:** Aggregate counters omit duration and per-episode causality.

### AD-NAV-824: Defer policy relaxation until diagnostic hardening

**Decision:** Do not replace the one-episode stop rule with an unvalidated
numeric threshold.

**Reason:** The first real data questions the rule but cannot yet support a
better bound.

### AD-NAV-825: Preserve the evidence-gap hard stop

**Decision:** Keep six hours as a hard observation-continuity boundary and
schedule future checkpoints every five hours.

**Reason:** The failure was procedural, and scheduling margin is safer than
weakening evidence continuity.

## 12. Private Evidence

Machine-readable analysis evidence is retained at:

```text
private/navimow-capture/output/
  native-mqtt-private-pilot-failure-analysis/
    evidence-closure.json
```

It contains no credential, ObjectID, topic, payload, coordinate, hostname or
device identity.

## 13. Gate Decision

| Gate | Decision |
|---|---|
| failure causes separated | PASS |
| automation root cause | PROCEDURAL |
| rotation accounting | CONSISTENT |
| unplanned restart recovery | HEALTHY |
| per-episode causality | INSUFFICIENT |
| unchanged retest | REJECTED |
| immediate reactivation | CLOSED |
| episode diagnostic hardening | REQUIRED |
| automation dry-run | REQUIRED |
| current credential-free state | PASS |

## 14. Next Step

Proceed with:

```text
228-native-mqtt-transport-episode-diagnostics-hardening-design.md
```

That design should freeze the bounded episode schema, update semantics,
privacy boundary, offline regression matrix and publication gates. It must not
reactivate MQTT or change the existing reconnect behavior.
