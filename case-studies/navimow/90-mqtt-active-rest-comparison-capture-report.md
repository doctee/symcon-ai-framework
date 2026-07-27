# 90 MQTT Active REST Comparison Capture Report

**Case study:** Navimow native IP-Symcon module
**Status:** Active receive-only comparison passed; Symcon shadow transport design approved
**Date:** 2026-07-27
**Scope:** Close two private active sessions, promote minimal fixtures and refine the offline parser

## 1. Purpose

This report closes the live procedure prepared by
`89-mqtt-active-rest-comparison-capture-procedure.md`.

It records:

- two bounded receive-only MQTT/WSS sessions;
- contemporaneous read-only REST status polling;
- operator-observed Running, Docking and final Docked markers;
- MQTT channel cadence and payload shapes;
- REST/MQTT state and battery agreement;
- numeric location-state evidence;
- fixture promotion;
- parser changes required by active traffic;
- the next Symcon architecture gate.

The capture tool sent no MQTT publish and no mower command.

## 2. Operating Context

The mower had already started normally from its official schedule when the
first comparison session began.

The first 30-minute session captured Running but ended before the mower
returned. A second bounded session then continued observation through:

```text
Running -> Docking -> Docked
```

The mower activity was not started, paused, resumed, stopped or docked by the
capture tool.

The official app and normal physical safety controls remained independent of
the evidence process.

## 3. Aggregate Result

| Measurement | Result |
| --- | ---: |
| private sessions | 2 |
| total duration | approximately 86 minutes |
| exact topics requested per session | 4 |
| exact topics accepted per session | 4 |
| MQTT messages | 2,691 |
| REST status requests | 171 |
| REST successful responses | 171 |
| REST errors | 0 |
| unknown MQTT topics | 0 |
| MQTT publish attempts | 0 |
| mower command attempts | 0 |

The disconnect recorded in each session occurred during intentional client
shutdown.

No session reached its message or payload-volume limit.

## 4. Session Results

### 4.1 First Session

| Measurement | Result |
| --- | ---: |
| duration | 1,800.176 seconds |
| outcome | completed by duration |
| `state` messages | 14 |
| `location` messages | 921 |
| `event` messages | 0 |
| `attributes` messages | 0 |
| REST successes | 60 |
| operator markers | Running |

REST remained:

```text
isRunning
```

throughout the session.

### 4.2 Second Session

| Measurement | Result |
| --- | ---: |
| duration | 3,360.923 seconds |
| outcome | completed by operator stop |
| `state` messages | 41 |
| `location` messages | 1,715 |
| `event` messages | 0 |
| `attributes` messages | 0 |
| REST successes | 111 |
| operator markers | Running, Docking, Docked final, Stop |

The second session covered the complete visible return transition.

## 5. MQTT State Channel

The active run proves the `state` payload as a JSON object:

```text
battery: integer
device_id: string
state: string
timestamp: integer milliseconds
```

Observed state strings:

```text
isRunning
isDocking
isDocked
```

The state message used the same logical strings already observed through REST.

During the second session:

- MQTT state changed to `isDocking` at relative offset 3,301,927 ms;
- MQTT state changed to `isDocked` at 3,351,525 ms;
- another `isDocked` state message arrived at 3,359,682 ms.

The second Docked message arrived after the physical final-Docked marker and
before operator stop. Final Docked is therefore protocol-confirmed despite the
absence of another REST poll.

## 6. MQTT Location Channel

The location channel remained high frequency:

| Session | Median gap | Maximum observed gap |
| --- | ---: | ---: |
| first | 2,003 ms | 8,563 ms |
| second | 1,996 ms | 7,378 ms |

Most messages were `type: 1` pose updates containing numeric
`vehicleState`.

Observed message counts by type:

| Type | First session | Second session |
| --- | ---: | ---: |
| 1 | 900 | 1,675 |
| 2 | 15 | 27 |
| 3 | 6 | 11 |
| 4 | 0 | 2 |

The active shapes were:

- `type: 1`: pose, time and numeric vehicle state;
- `type: 2`: mowing and progress-related partial fields;
- `type: 3`: partition IDs plus time;
- `type: 4`: `taskDelay` without time.

No shape may be treated as a complete replacement snapshot.

## 7. Numeric Location-State Evidence

The complete second-session transition observed:

| Numeric `vehicleState` | Direct MQTT state | Physical phase |
| ---: | --- | --- |
| 4 | `isRunning` | Running |
| 5 | `isDocking` | Docking |
| 2 | `isDocked` | final Docked |

Relative numeric transition offsets:

```text
4 at 1,251 ms
5 at 3,301,956 ms
2 at 3,351,490 ms
```

The direct state topic changed within tens of milliseconds of the corresponding
numeric location transition:

```text
Docking: direct state 29 ms before numeric location state
Docked: numeric location state 35 ms before direct state
```

This is strong same-session correlation.

It does not fully define the vendor's numeric state domain. The initial docked
capture in step 87 observed value 1 while the mower was physically docked.
Value 1 therefore remains unresolved and must not be silently collapsed into
the final-Docked value 2 without further evidence.

## 8. Transition Timing

Second-session markers:

| Marker | Relative offset |
| --- | ---: |
| Running | 9,379 ms |
| Docking | 3,318,617 ms |
| Docked final | 3,359,379 ms |
| operator stop | 3,360,768 ms |

### 8.1 Running

Initial observations:

```text
REST isRunning: 457 ms
location state 4: 1,251 ms
physical Running marker: 9,379 ms
first state-channel isRunning: 105,933 ms
```

The first direct state-channel message was delayed because no state or battery
change had yet caused a state publication. Location provided the immediate
activity indication.

### 8.2 Docking

```text
MQTT state isDocking: 3,301,927 ms
MQTT location state 5: 3,301,956 ms
physical Docking marker: 3,318,617 ms
REST isDocking: 3,318,793 ms
```

MQTT observed Docking approximately 16.9 seconds before the next successful
REST observation.

The operator marker occurred shortly before that REST observation but after
the MQTT transition. This difference includes human observation and entry
delay; it is not a negative protocol latency defect.

### 8.3 Docked

```text
MQTT location state 2: 3,351,490 ms
MQTT state isDocked: 3,351,525 ms
physical Docked marker: 3,359,379 ms
second MQTT state isDocked: 3,359,682 ms
operator stop: 3,360,768 ms
```

The tool stopped roughly 1.4 seconds after the Docked marker, before the next
30-second REST request. The missing final REST Docked sample is therefore an
expected observation-window effect, not an API disagreement.

## 9. Battery Comparison

MQTT state-channel battery and REST battery followed the same discharge:

| Session | MQTT range | REST range |
| --- | --- | --- |
| first | 77 through 65 percent | 78 through 65 percent |
| second | 43 through 9 percent | 44 through 9 percent |

The occasional one-percentage-point difference is consistent with different
receipt times around a battery transition.

No contradictory or out-of-range battery value was observed.

The state channel is therefore a strong shadow candidate for battery updates,
but REST remains productive authority until the shadow pilot passes.

## 10. Channel Stability Finding

The channels have distinct useful roles:

| Channel | Observed behavior | Candidate role |
| --- | --- | --- |
| `location` | approximately two-second cadence while active | rapid activity and transition wake hint |
| `state` | direct state/battery on changes; slower while stable | semantic state and battery shadow |
| `event` | no messages | unsupported pending evidence |
| `attributes` | no messages | unsupported pending evidence |
| REST | complete successful polling throughout | authority, reconciliation and fallback |

The state-channel interval while stable was much longer and driven largely by
battery changes. It must not be used alone as a rapid initial Running detector.

The location channel is fast but contains sensitive geometry and a vendor
numeric state domain. Productive integration should parse it in memory and
discard geometry.

## 11. Timestamp-Less Type 4

Two identical-shape messages were observed:

```text
taskDelay: true
type: 4
```

They contained no `time`.

This supersedes the universal timestamp requirement from AD-NAV-316 for
location parsing:

- the message is structurally valid evidence;
- it may be classified;
- it cannot be ordered against timestamped patches;
- it must not mutate accumulated state;
- its unknown `taskDelay` value is not retained by the offline parser.

The accumulator now returns:

```text
accepted: false
reason: missing-timestamp
```

without changing its snapshot.

## 12. Promoted Fixtures

Seven minimal synthetic fixtures were added:

```text
fixtures/mqtt/state-running.json
fixtures/mqtt/state-docking.json
fixtures/mqtt/state-docked.json
fixtures/mqtt/location-running.json
fixtures/mqtt/location-docking.json
fixtures/mqtt/location-docked.json
fixtures/mqtt/location-type-4-no-time.json
```

Promotion rules:

- real device ID replaced by `DEVICE_001`;
- real timestamps shifted to synthetic values;
- real coordinates replaced by `1.0` and `2.0`;
- only minimum state-transition fields retained;
- no raw or complete sanitized session file promoted;
- no endpoint, token, MQTT credential, mower name or absolute capture time
  retained.

## 13. Parser Refinement

The offline candidate now:

- parses the fixture-backed `state` object;
- verifies payload device ID against the exact topic device;
- validates state-string shape;
- bounds battery to 0 through 100;
- requires integer state timestamp;
- preserves unknown field names without values;
- parses timestamp-less location messages;
- rejects timestamp-less patches at the accumulator boundary;
- retains `event` and `attributes` as fail-closed channels.

The parser does not:

- assign productive Symcon profile values;
- persist coordinates;
- subscribe to MQTT;
- perform reconciliation;
- change the distribution.

## 14. Private Evidence Closure

Exact private evidence remains below:

```text
private/navimow-capture/output/mqtt-comparison/
```

The ignored machine-readable closure contains:

- both session IDs and outcomes;
- authorization boundary;
- message and REST counts;
- zero publish and command counts;
- SHA-256 hash, byte size and mode for every raw and sanitized file;
- cumulative evidence bounds;
- sensitive scalar leak-check result;
- public promotion count.

All captured files and the closure have mode `600`.

The sensitive-value scan found zero retained token, credential, endpoint,
identifier, name or timestamp values. Coordinate fields were checked
structurally to contain only the synthetic `postureX=1.0` and
`postureY=2.0` placeholders. This avoids false positives where one real
coordinate scalar happened to occur elsewhere as a different sanitized
numeric field.

## 15. Regression Coverage

The fixture and parser tests now cover:

- direct Running, Docking and Docked state objects;
- device/topic identity agreement;
- battery bounds;
- state timestamp;
- numeric transition sequence 4, 5 and 2;
- timestamp-less type-4 classification;
- non-mutating missing-timestamp rejection;
- all previous partial, null, unknown, ordering, topic and size cases.

Focused validation passed:

```text
Navimow MQTT fixture checks passed.
Navimow MQTT partial payload parser checks passed.
PHPCS: PASS
PHPStan: PASS
```

## 16. Readiness Matrix

| Gate | Decision |
| --- | --- |
| active exact-topic reception | PASS |
| active location cadence | PASS |
| direct state payload contract | PASS |
| MQTT/REST Running agreement | PASS |
| MQTT/REST Docking agreement | PASS |
| final MQTT/physical Docked agreement | PASS |
| final REST Docked in same session | NOT OBSERVED due immediate stop |
| battery agreement | PASS |
| numeric state 4/5/2 correlation | PASS for shadow evidence |
| numeric state 1 semantics | BLOCKED |
| event payload contract | BLOCKED |
| attributes payload contract | BLOCKED |
| offline parser refinement | PASS |
| Symcon transport topology | NEXT GATE |
| productive MQTT variable authority | NO-GO |

## 17. Architecture Decisions

### AD-NAV-325: Accept the direct MQTT state payload contract

**Decision:** Treat device ID, state string, battery and timestamp as
fixture-backed for offline and future shadow parsing.

**Rationale:** Forty-one second-session messages included the complete
transition and battery progression.

**Consequence:** The Symcon transport spike may consume this channel in shadow
mode.

### AD-NAV-326: Prefer direct state strings over numeric location mapping

**Decision:** Use the state topic as the semantic MQTT source when available.

**Rationale:** It uses the same state strings as REST and avoids unnecessary
numeric-domain inference.

**Consequence:** Numeric location state remains reconciliation evidence and a
rapid wake signal.

### AD-NAV-327: Use location only as a rapid in-memory wake candidate

**Decision:** Consider validated location traffic and numeric transitions for
immediate read-only REST wakeup, not direct productive state authority.

**Rationale:** Location arrived about every two seconds and detected Docking
before the next REST poll.

**Consequence:** Coordinates must be discarded and never archived or exposed.

### AD-NAV-328: Keep numeric state mapping provisional

**Decision:** Record 4/5/2 as Running/Docking/final-Docked correlations without
declaring the complete vendor state domain.

**Rationale:** Value 1 remains unresolved from the earlier docked observation.

**Consequence:** Unknown numeric values fail closed and trigger reconciliation.

### AD-NAV-329: Permit non-orderable location messages without mutation

**Decision:** Parse the timestamp-less type-4 shape but reject it at the
accumulator boundary.

**Rationale:** The shape is real but cannot be ordered safely.

**Consequence:** AD-NAV-316's parser-level timestamp requirement is superseded;
its non-regression objective remains enforced by the accumulator.

### AD-NAV-330: Retain REST as productive authority

**Decision:** Do not update existing Symcon variables from MQTT yet.

**Rationale:** Transport lifecycle, restart behavior, stale detection and
reconciliation have not been proven inside Symcon.

**Consequence:** The next implementation stage is a transport topology spike,
then internal shadow mode.

### AD-NAV-331: Preserve existing variable and archive identity

**Decision:** Add no public variable and recreate no existing variable for
MQTT.

**Rationale:** Existing ObjectIDs and user-enabled archive logging are
installation contracts.

**Consequence:** Any later authority decision must update the same variable
objects through the existing account-to-device data path.

## 18. Decision

**Active MQTT/WSS evidence gate: PASS.**

**Direct state and battery schema: PROVEN FOR SHADOW MODE.**

**Rapid location wake evidence: PROVEN.**

**Numeric state domain: PARTIALLY PROVEN, NOT COMPLETE.**

**Productive MQTT variable updates: NO-GO.**

**MQTT publish and mower commands: NONE.**

**Existing Symcon variables and archive logging: UNCHANGED.**

## 19. Recommended Next Step

Create:

```text
91-mqtt-symcon-transport-topology-spike.md
```

The spike should determine whether IP-Symcon can provide the required
Bearer-authenticated MQTT-over-WSS stack through supported native instances.
It must compare:

- native MQTT Client plus WebSocket-capable parent;
- a bounded Navimow splitter over WebSocket I/O;
- an external receive-only bridge as fallback.

No option may update productive mower variables during the spike. The selected
topology must define credential ownership, restart behavior, subscription
idempotency, stale detection, bounded diagnostics and REST reconciliation
before shadow-mode implementation begins.
