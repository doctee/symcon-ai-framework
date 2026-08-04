# 213 Native MQTT Private Pilot Observation Harness Design and Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Private observation harness implemented and offline validated;
live preflight and activation remain closed
**Date:** 2026-07-29
**Scope:** Implement the bounded private evidence tooling required by step 212

## 1. Purpose

Step 212 approved a receive-only private-pilot policy with:

```text
maximum duration:            72 hours
earliest normal completion: 48 hours
minimum mowing cycles:       2
target mowing cycles:        3
minimum credential rotations: 1
```

That policy explicitly prohibited a multi-day run based on ad hoc manual
probes.

This step implements:

1. one bounded read-only Symcon projection;
2. one deterministic and resumable local evidence state machine;
3. one atomic state-file CLI;
4. archive-backed mowing-cycle reconstruction;
5. credential-rotation and reconnect accounting;
6. fixed deadline, evidence-gap and cleanup enforcement;
7. synthetic-clock and failure-path regressions;
8. syntax, coding-standard, static-analysis, mutation and privacy gates.

No productive module file, Symcon object, property, MQTT connection, OAuth
state, service or mower was accessed or changed.

## 2. Private Artifact Boundary

All implementation artifacts reside below:

```text
private/navimow-capture/native-mqtt-private-pilot/
```

Files:

| Artifact | Responsibility |
|---|---|
| `symcon-readonly-probe.php` | Produce one bounded sanitized live projection |
| `PilotHarness.php` | Evaluate the 48–72-hour evidence state machine |
| `pilot.php` | Create, ingest and inspect an atomic private state file |
| `offline-test.php` | Exercise time, lifecycle and failure boundaries |
| `validate.sh` | Run syntax, behavior, mutation and privacy checks |
| `README.md` | Document private local operation |

These artifacts remain private and are not copied into the standalone Symcon
module repository.

## 3. Read-Only Symcon Projection

The projection discovers the existing retained instances through module GUIDs.
It creates no object and emits no installation ObjectID.

It reads:

- installed module branch, commit, cleanliness and validity;
- stable variable, archive, command, topology and subscription hashes;
- variable count and Archive Control logging count;
- MQTT/WebSocket statuses and credential-presence Booleans;
- Account lifecycle and sanitized statistics;
- Receiver counters;
- OAuth connection state and remaining token horizon;
- REST success and error evidence;
- current symbolic `VehicleState`;
- up to eight hours of logged symbolic `VehicleState` transitions.

The eight-hour archive window overlaps the required six-hour checkpoint
interval by two hours. The local harness deduplicates transitions by timestamp
and symbolic state.

The probe contains no:

- `IPS_SetProperty()` or `IPS_ApplyChanges()`;
- object creation or deletion;
- `RequestAction()` or `SetValue()`;
- MQTT Connect or Disconnect wrapper call;
- MQTT publish operation;
- mower command.

A static validation rule rejects any later introduction of such calls.

## 4. Projection Privacy

The probe returns:

- no credential value;
- no Authorization header content;
- no private topic;
- no MQTT payload;
- no device identity;
- no endpoint or hostname;
- no ObjectID;
- no local path or IP address.

Credential state is represented only by Booleans. Stable contract comparisons
use SHA-256 hashes over bounded structural projections.

The result is still private operational evidence and must be stored only below
`private/`.

## 5. Local State Machine

The state machine phases are:

```text
inactive-baseline
ready-for-acceptance
active-baseline
active
stop-required
cleanup-pending
closed
```

The expected standalone commit is fixed at state creation:

```text
8fdab84bd2a2190a6025cedd11f1ae6248369c0e
```

Every ingested projection must match that exact commit and the frozen
projection schema.

## 6. Baseline Contract

### Inactive baseline

Two snapshots at least 65 seconds apart must prove:

- clean and valid expected module commit;
- stable identity, archive, command, topology and subscription contracts;
- MQTT disabled;
- lifecycle `Disabled`;
- no pending or reconnect attempt;
- WebSocket inactive;
- credential fields empty;
- Account connected;
- reauthentication not required;
- REST operational.

Only then does the harness enter `ready-for-acceptance`.

### Active baseline

After a separately authorized activation, two snapshots at least 65 seconds
apart must additionally prove:

- MQTT and WebSocket `102/102`;
- WebSocket active;
- all credential-presence Booleans true;
- lifecycle `ShadowActive`;
- stable connection/disconnect/rotation counters;
- no contract drift.

The 72-hour clock starts only at the second successful active baseline.

## 7. Fixed Time Contract

The state machine derives:

```text
earliestCompletionAt = startedAt + 172800 seconds
deadlineAt           = startedAt + 259200 seconds
maximum evidence gap = 21600 seconds
cleanup delay        = at least 180 seconds
```

Behavior:

- snapshots before the next expected checkpoint remain valid;
- a gap greater than six hours records a hard stop;
- a snapshot after the fixed deadline is rejected;
- reaching the deadline never extends the state;
- incomplete safe evidence at the deadline becomes
  `READY_TO_CLOSE_INCONCLUSIVE`;
- cleanup cannot close before the 180-second delayed check.

## 8. Mowing-Cycle Reconstruction

The harness merges overlapping archive windows, deduplicates transitions and
recognizes only this complete sequence:

```text
Running -> Docking -> Docked
```

An earlier `Docked` baseline is expected but is not required in every
overlapping archive slice.

The following do not increment the completed-cycle count:

- `Running -> Docked` without observed `Docking`;
- a partial open cycle;
- duplicate archive rows;
- `Paused` or `Idle` without the complete sequence;
- manually asserted evidence outside the Symcon projection.

The transition history is capped at 512 entries.

## 9. Rotation and Transport Episodes

Credential-rotation evidence requires:

```text
credentialRotations:  positive delta
connectionAttempts:   delta at least equal to rotation delta
connectionSuccesses:  delta at least equal to rotation delta
connectionFailures:   delta zero
final lifecycle:       ShadowActive
```

An incomplete rotation records a hard stop.

An increase in `unexpectedDisconnects` opens a transport episode:

- one fully recovered episode remains observable;
- a second episode records `multiple-transport-episodes`;
- any `reconnectExhausted` delta records a hard stop;
- counter regression is rejected as evidence corruption or reset.

The harness issues no reconnect itself.

## 10. Hard Stops

Executable hard-stop detection covers:

- repository invalid or dirty;
- reauthentication required;
- Account disconnected;
- REST unhealthy;
- lifecycle `ConfigurationError`;
- active transport unexpectedly disabled;
- ownership invalid;
- subscription contract invalid;
- archive contract invalid;
- contract hash drift;
- credential rotation incomplete;
- counter regression;
- second unexpected transport episode;
- reconnect exhaustion;
- evidence gap greater than six hours.

The state moves to `stop-required`. The harness does not perform cleanup
itself; activation separately arms the established normal Account cleanup.

## 11. Bounded Persistence

The state file retains at most:

```text
snapshots:       256
archive transitions: 512
events:          128
stop reasons:     32
```

Writes use:

1. JSON encoding with a fixed format version;
2. a temporary sibling file;
3. `LOCK_EX`;
4. atomic rename.

The decoder validates the format before a resumed operation. Replaying an
already seen archive transition does not duplicate a cycle.

The external orchestrator supplies read-only snapshots at the policy
checkpoints. The harness intentionally has no Symcon credentials and no direct
network transport.

## 12. CLI Contract

Create a private state:

```text
php pilot.php create <state-file> <40-character-commit>
```

Ingest a projection:

```text
php pilot.php ingest <state-file> <kind> <snapshot-file>
```

Supported kinds:

```text
inactive-baseline
active-baseline
checkpoint
anomaly
cleanup-immediate
cleanup-delayed
```

Inspect current status:

```text
php pilot.php status <state-file> [unix-time]
```

The CLI contains no operation for activation, MQTT Connect, restart or mower
command.

## 13. Offline Regression Matrix

Synthetic-clock tests prove:

| Contract | Result |
|---|---|
| exact expected commit | PASS |
| two inactive baselines with 65-second spacing | PASS |
| two active baselines with stable counters | PASS |
| clock starts at second active baseline | PASS |
| fixed 48-hour earliest completion | PASS |
| fixed 72-hour deadline | PASS |
| two archive-derived cycles | PASS |
| successful credential rotation | PASS |
| resumable JSON round-trip | PASS |
| immediate plus delayed cleanup | PASS |
| early delayed cleanup rejected | PASS |
| one recovered transport episode retained | PASS |
| second transport episode stops pilot | PASS |
| reauthentication stops pilot | PASS |
| evidence gap stops pilot | PASS |
| incomplete 72-hour result is inconclusive | PASS |
| post-deadline snapshot rejected | PASS |

No test contacts Symcon, Navimow or a broker.

## 14. Validation

Executed:

```text
sh private/navimow-capture/native-mqtt-private-pilot/validate.sh

vendor/bin/phpcs --standard=phpcs.xml \
  private/navimow-capture/native-mqtt-private-pilot/*.php

vendor/bin/phpstan analyse --configuration=phpstan.neon \
  private/navimow-capture/native-mqtt-private-pilot/PilotHarness.php \
  private/navimow-capture/native-mqtt-private-pilot/pilot.php

sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Results:

- PHP syntax: PASS;
- synthetic-clock harness tests: PASS;
- read-only mutation scan: PASS;
- private-material scan: PASS;
- PHPCS: PASS;
- PHPStan for the host-side harness and CLI: PASS;
- complete Navimow MQTT/REST/distribution gate: PASS.

The Symcon probe is syntax-, PHPCS-, mutation- and privacy-checked. Its runtime
API projection belongs to the separately authorized read-only live preflight.

## 15. Artifact Hashes

| Private artifact | SHA-256 |
|---|---|
| `PilotHarness.php` | `daaa375b3a3eea55313651e58082d29b6c33a251c02a736a61bac95a57372219` |
| `pilot.php` | `9614644843a447dd98ef7e8153697b0095a9d19950f82500ae3372e2a00fc6c9` |
| `symcon-readonly-probe.php` | `423bc4eb8cfd5079cb31f2cf94cf13f57b877bf543fbde040280b4deaa7ccab8` |
| `offline-test.php` | `8f7b260318b4f0af286d8da9b06499cc891dd833ae12e52521e757bc208ebbae` |
| `validate.sh` | `60acdca7c31c30270fcaf2eb54266bf63897ba314b28166bbfd21a304c1fdfb5` |

These hashes identify the offline-tested harness candidate. Any later change
requires validation and a new hash freeze before live use.

## 16. Architecture Decisions

### AD-NAV-759: Keep the harness outside Symcon

**Decision:** Use authorized read-only projections plus a local private state
file instead of temporary Symcon scripts, events or variables.

**Reason:** The observation layer must not change the installation it is
measuring.

### AD-NAV-760: Reconstruct cycles from Archive Control

**Decision:** Read an eight-hour overlapping `VehicleState` window at each
checkpoint.

**Reason:** Six-hour snapshots alone could miss intermediate `Running` and
`Docking` states, while existing logging already owns the durable history.

### AD-NAV-761: Start the pilot clock after stable activation

**Decision:** The second equal active baseline defines `startedAt`.

**Reason:** Connection establishment time should not consume the promised
operational observation window.

### AD-NAV-762: Separate evidence detection from cleanup mutation

**Decision:** The harness records `stop-required` but never changes Symcon.

**Reason:** Cleanup is security-sensitive and remains an explicitly armed,
audited Account operation.

### AD-NAV-763: Make deadline and evidence gaps executable

**Decision:** Reject post-deadline input and stop after a gap above six hours.

**Reason:** A policy statement without machine-enforced bounds would not make
a resumable multi-day pilot reliable.

### AD-NAV-764: Bound every retained evidence collection

**Decision:** Cap snapshots, transitions, events and stop reasons separately.

**Reason:** Multi-day diagnostics must remain predictable even during noisy
or malformed input.

### AD-NAV-765: Keep live orchestration separate

**Decision:** The harness has no Symcon transport credentials or scheduler.

**Reason:** MCP authorization, checkpoint timing and activation are deployment
concerns; the evidence evaluator remains deterministic and offline-testable.

## 17. Gate Decision

| Gate | Decision |
|---|---|
| private harness implementation | PASS |
| synthetic clock and resume tests | PASS |
| read-only probe static boundary | PASS |
| complete offline validation | PASS |
| productive module change | NONE |
| standalone publication | NOT REQUIRED |
| live Symcon preflight | CLOSED |
| persistence acceptance | NOT GIVEN |
| MQTT activation | CLOSED |
| pilot clock | NOT STARTED |
| current MQTT state | DISABLED AND CREDENTIAL-FREE |

## 18. Next Step

Before the inactive preflight, proceed with:

```text
214-native-mqtt-private-pilot-shadow-diagnostics-design.md
```

That step should define a bounded manual view of the internal MQTT hint next to
the REST-authoritative state without introducing temporary Symcon variables,
Archive Control logging, identity data or geometry retention.

The previously planned inactive preflight remains required after diagnostic
implementation, publication and disabled installation.
