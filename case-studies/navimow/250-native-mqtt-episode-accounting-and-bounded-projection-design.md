# 250 Native MQTT Episode Accounting and Bounded Projection Design

**Case study:** Navimow native IP-Symcon module

**Status:** Design complete; implementation and all live gates remain closed

**Date:** 2026-08-03

**Scope:** Separate disconnect observations from distinct transport episodes
and define a fixed-size operational diagnostic projection

## 1. Purpose

Step 249 proved two independent diagnostic contract problems:

1. `unexpectedDisconnects` increased 12 times while only 8 distinct episodes
   were opened;
2. the complete pilot diagnostic projection exceeded the bounded 64-KiB MCP
   result channel.

Both behaviors are explainable from the implementation:

- the observation counter advances before the episode opener rejects a second
  record while an episode is already open;
- each retained episode embeds up to eight Core transitions, while the registry
  also retains checkpoints, rotations and session-level transitions.

This design corrects policy accounting and operational projection size without
changing recovery behavior or transport authority.

## 2. Preserved Boundary

The implementation must preserve:

```text
public device-state authority: REST
MQTT direction:                receive-only
reconnect delays:              60, 300, 900 seconds
maximum reconnect attempts:    3
command retry behavior:        unchanged
device variables:              unchanged, 14
Archive loggings:              unchanged, 5
```

The change must not:

- activate or connect MQTT;
- publish an MQTT message;
- command the mower;
- update public device variables from MQTT;
- change OAuth behavior;
- change retry delays or exhaustion semantics;
- add a user-facing variable;
- store credentials, topics, endpoints, payloads or installation IDs;
- discard retained detailed diagnostic evidence.

## 3. Terminology

The following terms become normative.

### 3.1 Disconnect observation

One lifecycle or kernel-reconciliation evaluation that finds the native
transport unexpectedly unavailable while the Account expected it to be active.

Persistent counter:

```text
unexpectedDisconnects
```

The counter may advance more than once during one open episode.

### 3.2 Distinct transport episode

One successful transition from no open episode to an open episode. It ends only
when recovery, exhaustion, disable or cleanup closes it.

Persistent sequence:

```text
episodeSequence
```

This is the pilot policy unit.

### 3.3 Reconnect attempt

One actual execution of the bounded reconnect path for an open episode.

Persistent counter:

```text
reconnectAttempts
```

Multiple reconnect attempts do not create multiple episodes.

### 3.4 Duplicate observation

A disconnect observation while an episode is already open:

```text
duplicate observation delta =
    unexpectedDisconnect delta - episode-sequence delta
```

The derived value is diagnostic intensity, not a separate failure.

## 4. Existing Storage Contract

`MqttPilotObservationRegistry` already persists:

```text
checkpointSequence
episodeSequence
rotationSequence
coreTransitionSequence
```

No storage migration is required. Registry format version 2 remains valid.

The defect is projection and consumption:

- `episodeSequence` is not exposed by `GetMqttPilotDiagnostics()`;
- the private harness uses `unexpectedDisconnects` as its episode count;
- the full projection is unsuitable for routine bounded monitoring.

## 5. Detailed Projection Extension

`GetMqttPilotDiagnostics()` remains the forensic detail endpoint. Its existing
arrays and format version 2 remain unchanged.

It receives these additive scalar fields:

```text
checkpointSequence
episodeSequence
rotationSequence
coreTransitionSequence
```

The values are cumulative and do not depend on ring retention. This is
important because retained arrays may eventually omit older entries.

No existing key is removed or renamed. Historical consumers remain compatible.

## 6. Bounded Summary Projection

A new Account method is justified because the detailed projection has a
different operational purpose and has exceeded the maximum structured MCP
channel even with every individual ring bounded.

Public wrapper:

```text
NAVAC_GetMqttPilotSummary(int $InstanceID): string
```

The summary uses its own:

```text
formatVersion: 1
maximum encoded size: 16384 bytes
```

It contains only:

### 6.1 Session

```text
featureEnabled
active
sessionSequence
startedAt
stoppedAt
lastCheckpointAt
nextCheckpointAt
checkpointIntervalSeconds
```

### 6.2 Cumulative sequences

```text
checkpointSequence
episodeSequence
rotationSequence
coreTransitionSequence
```

### 6.3 Operational counters

```text
connectionAttempts
connectionSuccesses
connectionFailures
unexpectedDisconnects
reconnectAttempts
reconnectExhausted
credentialRotations
received
accepted
rejected
coreStatusEventDrops
```

### 6.4 Coverage markers

The summary retains all currently stored checkpoint markers, but only:

```text
sequence
sessionSequence
recordedAt
delaySeconds
```

At most 32 markers are returned. They are never reduced to only the latest
entry because the private harness needs the complete retained sequence for gap
coverage.

### 6.5 Latest bounded context

The summary includes at most one:

```text
latestCheckpoint
latestEpisode
latestRotation
latestCoreTransition
openEpisode
```

`latestEpisode` and `openEpisode` omit embedded `coreTransitions`. They retain
timing, statuses, ingress and REST ages, reconnect count, outcome and
completeness.

The summary never includes:

- arrays of all episodes, rotations or Core transitions;
- native message payloads;
- credentials or Authorization values;
- MQTT topics or endpoints;
- ObjectIDs or device identities.

## 7. Size Enforcement

The summary builder encodes the final result once and verifies:

```text
strlen(encoded JSON) <= 16384
```

Exceeding the fixed limit is an implementation defect. It must fail closed in
offline tests and may not silently truncate JSON.

Worst-case tests use:

- 32 checkpoint coverage markers;
- maximum-width integer values;
- one maximal projected episode;
- one open episode;
- all counter keys;
- longest allowed enum values.

The size assertion applies to encoded bytes, not character count.

## 8. Harness Accounting Contract

The candidate private harness baseline records:

```text
lastEpisodeSequence
lastUnexpectedDisconnects
lastReconnectAttempts
```

For every active snapshot:

```text
episodeDelta =
    current episodeSequence - previous episodeSequence

observationDelta =
    current unexpectedDisconnects - previous unexpectedDisconnects

duplicateObservationDelta =
    observationDelta - episodeDelta
```

Rules:

1. a negative sequence or counter delta is a hard contract failure;
2. `observationDelta < episodeDelta` is a hard accounting failure;
3. `episodeDelta` advances the pilot's distinct episode count;
4. `duplicateObservationDelta > 0` appends diagnostic evidence only;
5. more than one distinct episode triggers `multiple-transport-episodes`;
6. multiple reconnect attempts inside one episode do not increase the episode
   count;
7. `reconnectExhausted` remains an independent hard stop.

The one-episode pilot threshold is not weakened. It is applied to the correct
causal unit.

## 9. Open-Episode Semantics

`episodeSequence` advances when the episode is opened. The harness therefore
counts an episode even when it is still open at checkpoint time.

An open episode:

- is not automatically a second episode;
- remains visible through `openEpisode`;
- may accumulate duplicate observations and reconnect attempts;
- still triggers existing exhaustion or configuration hard stops;
- must be closed by normal recovery or deterministic cleanup.

## 10. Compatibility Rules

Historical closed snapshots without `episodeSequence` remain readable as
historical evidence. They must not authorize a new pilot.

A new active pilot requires:

```text
summary formatVersion: 1
episodeSequence: integer
checkpoint markers: array
encoded size: <= 16384 bytes
```

There is no fallback from missing `episodeSequence` to
`unexpectedDisconnects` for an active policy decision. Ambiguous accounting
fails closed before activation.

The detailed diagnostic format stays at version 2 because the stored schema and
existing keys do not change. The summary is a new independently versioned
contract.

## 11. Reuse Decision

Existing SAEF diagnostics responsibilities remain suitable:

- Statistics owns frequently updated counters;
- Registry owns bounded structured episode and checkpoint metadata;
- the new summary is a projection over those stores.

No new generic SAEF helper is justified. The projection is Navimow-specific and
must remain inside `NavimowAccount` until a recurring cross-module pattern is
demonstrated.

## 12. Offline Test Matrix

The implementation step must add at least these traces:

| Trace | Expected result |
|---|---|
| one observation opens one episode | observation +1, episode +1 |
| five observations while episode open | observation +5, episode +1 |
| three reconnect attempts in one episode | episode count remains 1 |
| second episode after recovery | episode count becomes 2, hard stop |
| observation delta below episode delta | accounting failure |
| sequence regression | hard stop |
| 32 checkpoint markers | coverage retained, summary within 16 KiB |
| one-second checkpoint delay | coverage remains valid |
| compact summary with maximal values | valid JSON below byte limit |
| disabled retained history | summary inactive and credential-free |
| historical detail projection | additive fields do not break fixtures |
| privacy scan | no private fields |

The corrected step-249 evidence becomes a regression fixture:

```text
unexpectedDisconnect delta: 12
episode-sequence delta:      8
duplicate observations:      4
distinct episode result:     FAIL, more than one
evidence gap:                false
```

## 13. Planned File Scope

Implementation is limited to:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
case-studies/navimow/tests/pilot-observation-harness.php
private/navimow-capture/native-mqtt-private-pilot/PilotHarness.php
private/navimow-capture/native-mqtt-private-pilot/symcon-readonly-probe.php
case-studies/navimow/fixtures/mqtt/
```

`module.json` changes only if required by IP-Symcon method exposure validation.
No variable metadata, form, locale, Device or Configurator file belongs to this
change.

## 14. Architecture Decisions

### AD-NAV-926: Count distinct episodes by cumulative sequence

The episode sequence changes exactly when one causal record is opened and is
independent of retained-ring truncation.

### AD-NAV-927: Preserve the observation counter

`unexpectedDisconnects` remains valuable because repeated observations reveal
fault intensity and prolonged instability.

### AD-NAV-928: Do not infer active policy from historical arrays

Retained episode arrays may be bounded or migrated. A new pilot requires the
explicit cumulative sequence.

### AD-NAV-929: Add a separate summary endpoint

Routine monitoring and forensic detail have incompatible size requirements.
Keeping both contracts prevents silent loss of detailed evidence.

### AD-NAV-930: Bound the summary to 16 KiB

The limit leaves margin below the 32-KiB structured channel and makes one
complete operational read predictable.

### AD-NAV-931: Preserve all checkpoint coverage markers

Coverage timestamps remain bounded and policy-critical even when other detail
arrays are reduced to their latest entry.

### AD-NAV-932: Keep storage format version 2

All required sequence values already exist in the stored registry. The change
is additive projection, not storage migration.

### AD-NAV-933: Version the summary independently

`formatVersion: 1` gives the new small contract an explicit evolution path
without forcing migration of the detail endpoint.

### AD-NAV-934: Keep duplicate observations diagnostic

Duplicate observations may explain outage severity but do not represent
additional causal episodes.

### AD-NAV-935: Require offline proof before any live gate

Publication, Symcon update and another pilot remain blocked until accounting,
size, compatibility and privacy traces all pass.

## 15. Gate Decision

| Gate | Decision |
|---|---|
| accounting terminology | FROZEN |
| distinct episode source | `episodeSequence` |
| observation source | `unexpectedDisconnects` |
| full detail endpoint | RETAIN |
| bounded summary endpoint | DESIGN APPROVED |
| summary maximum | 16384 bytes |
| checkpoint coverage | ALL RETAINED MARKERS |
| storage migration | NOT REQUIRED |
| public variable changes | PROHIBITED |
| recovery changes | PROHIBITED |
| implementation | READY AS NEXT STEP |
| publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |

## 16. Next Step

Proceed with:

```text
251-native-mqtt-episode-accounting-and-bounded-projection-implementation.md
```

That step may implement and offline-test only the frozen contracts above. It
must not publish, update Symcon or activate MQTT.
