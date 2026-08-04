# 251 Native MQTT Episode Accounting and Bounded Projection Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Implementation and offline validation complete; publication and all
live gates remain closed

**Date:** 2026-08-03

**Scope:** Implement the frozen step-250 accounting and bounded operational
projection contracts

## 1. Result

The Account module now exposes the existing cumulative pilot sequences through
the detailed diagnostic endpoint and provides a separate operational summary:

```text
NAVAC_GetMqttPilotSummary(int $InstanceID): string
formatVersion: 1
maximum encoded size: 16384 bytes
```

The private pilot harness now counts distinct transport episodes exclusively
from `episodeSequence`. `unexpectedDisconnects` remains an observation counter,
and repeated observations during one open episode are retained as diagnostic
duplicates.

No storage migration, live installation change or transport behavior change was
performed.

## 2. Implemented Detailed Projection

`GetMqttPilotDiagnostics()` remains format version 2 and retains all existing
arrays. The following cumulative fields were added:

```text
checkpointSequence
episodeSequence
rotationSequence
coreTransitionSequence
```

The fields are projected from the existing
`MqttPilotObservationRegistry`. Registry format version 2 is unchanged.

## 3. Implemented Summary Projection

The new summary contains:

- session state and absolute checkpoint schedule;
- all four cumulative sequences;
- the eleven frozen operational counters;
- up to 32 reduced checkpoint coverage markers;
- only the latest checkpoint, episode, rotation and Core transition;
- the currently open episode, if present.

The latest and open episode projections omit embedded Core-transition arrays.
Complete forensic arrays remain available only through the detailed endpoint.

The encoder measures the final JSON with `strlen()`. A result above 16384 bytes
throws a deterministic exception instead of returning truncated JSON.

The synthetic maximum-width, 32-checkpoint test projection encoded to 5835
bytes. The separate over-limit test proves the fail-closed branch.

## 4. Implemented Accounting

The private pilot state records:

```text
lastEpisodeSequence
lastUnexpectedDisconnects
lastReconnectAttempts
```

For each active snapshot it derives:

```text
episodeDelta = current episodeSequence - previous episodeSequence
observationDelta = current unexpectedDisconnects - previous unexpectedDisconnects
duplicateObservationDelta = observationDelta - episodeDelta
```

Policy behavior is now:

- `episodeDelta` advances `transportEpisodes`;
- duplicate observations append diagnostic evidence only;
- reconnect attempts do not create episodes;
- an observation delta below the episode delta fails closed;
- sequence or counter regression fails closed;
- more than one distinct episode still triggers
  `multiple-transport-episodes`;
- reconnect exhaustion remains an independent hard stop.

There is no fallback from a missing active `episodeSequence` to
`unexpectedDisconnects`.

## 5. Probe Contract

The bounded private Symcon probe now calls the summary wrapper for routine pilot
monitoring. It verifies separately:

```text
summary formatVersion == 1
all cumulative sequences are integers
checkpoint markers <= 32
encoded bytes <= 16384
forensic arrays are absent
```

Historical detailed format-version-2 snapshots remain accepted only as
inactive retained evidence. They cannot authorize an active pilot.

The probe remains read-only and does not retrieve credentials, activate MQTT,
restart Symcon or send mower commands.

## 6. Regression Fixture

The sanitized aggregate fixture
`fixtures/mqtt/episode-accounting-reconciled.json` records the step-249 case:

```text
disconnect observations: 12
distinct episodes:         8
duplicate observations:    4
evidence gap:              false
classification:            FAIL
```

It contains no endpoint, topic, identifier, timestamp, payload or installation
metadata.

## 7. Changed Files

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
case-studies/navimow/fixtures/mqtt/README.md
case-studies/navimow/fixtures/mqtt/episode-accounting-reconciled.json
private/navimow-capture/native-mqtt-private-pilot/PilotHarness.php
private/navimow-capture/native-mqtt-private-pilot/offline-test.php
private/navimow-capture/native-mqtt-private-pilot/symcon-readonly-probe.php
```

`module.json`, variable metadata, forms, locales, Device and Configurator did not
require changes. The general REST and command pilot harness was unaffected.

## 8. Offline Evidence

| Trace | Result |
|---|---|
| detailed additive sequences | PASS |
| summary format and privacy | PASS |
| 32 checkpoint markers retained | PASS |
| one-second checkpoint delay retained | PASS |
| maximal synthetic summary below 16 KiB | PASS, 5835 bytes |
| explicit over-limit input | PASS, rejected |
| one observation opens one episode | PASS |
| five observations in one episode | PASS, one episode plus four duplicates |
| three reconnect attempts in one episode | PASS, episode count unchanged |
| second distinct episode | PASS, hard stop |
| observation delta below episode delta | PASS, hard stop |
| episode-sequence regression | PASS, hard stop |
| reconciled 12/8 evidence | PASS, eight episodes plus four duplicates |
| inactive retained history | PASS |
| focused MQTT shadow suite | PASS |
| focused PHPStan | PASS |

## 9. Preserved Contracts

```text
public device-state authority: REST
MQTT direction:                receive-only
reconnect delays:              60, 300, 900 seconds
maximum reconnect attempts:    3
device variables:              14, unchanged
Archive loggings:              5, unchanged
command behavior:              unchanged
```

No productive state variable, archive configuration, retry schedule,
authentication behavior or MQTT publish path changed.

## 10. Architecture Decisions

### AD-NAV-936: Implement the summary as an Account projection

The source data already belongs to Account-owned Registry and Statistics
stores. A new helper or storage owner would duplicate responsibility.

### AD-NAV-937: Enforce the size after JSON encoding

The channel limit concerns encoded bytes. Counting array entries or characters
would not prove transport safety.

### AD-NAV-938: Keep checkpoint markers under the existing key

Using `checkpoints` preserves the harness coverage algorithm while each entry is
reduced to the four policy-required fields.

### AD-NAV-939: Keep forensic detail out of routine probes

The private routine probe consumes the small summary. The full diagnostic
endpoint remains available for explicitly bounded forensic work.

### AD-NAV-940: Fail active snapshots without episode sequence

Ambiguous historical accounting must not authorize another private pilot.

### AD-NAV-941: Preserve duplicate-observation evidence

The duplicates remain useful for outage-intensity analysis but cannot trip the
distinct-episode threshold by themselves.

### AD-NAV-942: Preserve historical detailed snapshots as inactive evidence

Compatibility is read-only. Historical format version 2 cannot be promoted to
an active policy input.

### AD-NAV-943: Avoid module metadata churn

IP-Symcon exposes public module methods through the existing `NAVAC` prefix; no
metadata contract changed.

### AD-NAV-944: Keep every live gate closed after offline success

Passing offline tests does not authorize publication, a Symcon update,
credential persistence, transport activation, restart or mower interaction.

## 11. Gate Decision

| Gate | Decision |
|---|---|
| frozen accounting contract | IMPLEMENTED |
| bounded summary | IMPLEMENTED |
| summary byte limit | VERIFIED |
| historical compatibility | VERIFIED OFFLINE |
| public variable change | NONE |
| storage migration | NONE |
| publication | CLOSED |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| restart | CLOSED |
| mower command | CLOSED |

## 12. Next Step

Proceed with:

```text
252-native-mqtt-episode-accounting-publication-and-symcon-test-plan.md
```

That step should freeze the exact publication candidate, define official
metadata validation and specify a disabled, credential-free Symcon update plus
read-only summary compatibility check. It must not itself publish, update
Symcon or activate MQTT.
