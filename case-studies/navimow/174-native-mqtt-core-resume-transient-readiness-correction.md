# 174 Native MQTT Core Resume Transient Readiness Correction

**Case study:** Navimow native IP-Symcon module
**Status:** Offline correction complete; transient-readiness regression and
full Navimow validation pass, publication remains closed
**Date:** 2026-07-29
**Scope:** Implement only the offline correction required by step 173

## 1. Purpose

Step 173 established that the first Core-resume ordering correction still
performed semantic Core validation before `KR_READY`.

This step:

1. adds a synthetic transient-Core-readiness fixture;
2. extends the lifecycle harness with bounded unavailable Core reads;
3. reproduces the step-172 failure against the published implementation;
4. implements a durable two-phase kernel barrier;
5. adds positive, negative and idempotency regressions;
6. runs the complete Navimow offline validation;
7. stops before publication, Symcon update or live MQTT activity.

## 2. Authorization Boundary

The user authorized complete execution of the offline step.

This authorized changes only inside:

```text
case-studies/navimow/
```

It did not authorize:

- standalone-module publication;
- a Symcon Module Control update;
- MQTT activation or credential retrieval;
- a broker connection;
- a service restart;
- MQTT publish;
- mower commands.

No live Symcon or mower operation was performed.

## 3. Synthetic Fixture

Added:

```text
case-studies/navimow/fixtures/mqtt/
  core-resume-transient-core-readiness.json
```

The fixture models:

```text
active owned persisted Core
changed kernel epoch
three transiently unavailable MQTT configuration reads before KR_READY
zero permitted pre-ready configuration reads
Core readiness restored at IPS_KERNELSTARTED
15-second delayed healthy adoption
```

Expected post-ready result:

```text
lifecycle:                  ShadowActive
transition reason:          core-resumed
classification:             healthy
Core-resume observations:   +1
Account connection attempts: +0
Core operations:            0
```

All IDs and credential values in the fixture are synthetic or represented
only as expected Booleans and counters.

## 4. Harness Extension

The lifecycle harness now supports:

- per-Core bounded configuration-read failures;
- per-Core configuration-read counters;
- explicit restoration of readiness before the ready message;
- independent Core-operation accounting.

The default failure map is empty, so existing scenarios keep their previous
behavior.

This models the missing platform condition without adding sleeps, network
access or live installation data.

## 5. Red Test

The new regression was run before changing the Account implementation.

Fixture validation passed:

```text
Navimow MQTT fixture checks passed.
```

The lifecycle test then failed exactly at:

```text
Pre-ready transient Core unreadiness bypassed the durable barrier.
```

This proves the new test discriminates the published behavior and does not
pass trivially.

The reproduced path covered:

- pre-ready semantic Core read;
- lost kernel precedence;
- failure to enter `kernel-start-awaiting-ready`;
- premature lifecycle processing.

## 6. Durable Epoch Barrier

`mqttKernelReconciliationMustTakePrecedence()` now uses only:

```text
EnableMqttShadow
ownership registry presence
current kernel epoch
recorded kernel epoch
pending kernel schedule markers
```

It no longer uses:

```text
hasValidConfiguration()
hasUsableAccessToken()
inspectMqttShadowConfiguration()
mqttTopology()
assertMqttOwnership()
```

Therefore changed-epoch detection cannot fail merely because Core metadata or
configuration is temporarily unavailable.

## 7. Pre-Ready ApplyChanges Path

When the durable barrier owns startup, `ApplyChanges()` now skips:

- `disconnectOwnedMqttTransportSafely()`;
- `initializeMqttLifecycle()`;
- every semantic Core configuration read;
- `markCurrentKernelEpochReconciled()`;
- normal MQTT startup scheduling.

It preserves normal REST and authentication state setup.

The shared `continueMqttKernelReconciliation()` helper:

- restores an already pending reconciliation timer; or
- records `kernel-start-awaiting-ready` with the timer stopped.

The helper is also called before early returns for:

- invalid local Account configuration;
- missing access-token state.

## 8. Post-Ready Classification

The existing 15-second delayed reconciliation remains the semantic authority.

It now explicitly handles invalid local Account configuration:

1. classify `configuration-invalid`;
2. clear pending credential rotation;
3. perform normal owned credential cleanup when possible;
4. mark the epoch reconciled;
5. enter `ConfigurationError`.

Existing post-ready branches remain responsible for:

- disabled feature;
- unavailable authentication;
- invalid ownership or transport configuration;
- healthy resumed Core;
- credential-free fallback;
- unhealthy credential-bearing Core.

No connection is attempted merely to classify a resumed Core.

## 9. Productive Delta

Compared byte-for-byte with published standalone
`main@71a90f697031da017264d2a33555b9b6693d8776`:

```text
modified productive files: 1
NavimowAccount/module.php:  +58 / -16
added productive files:     0
deleted productive files:   0
```

Current canonical Account SHA-256:

```text
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5
```

No module metadata, form, locale, profile, variable, action, REST or Device
file changed.

## 10. Regression Matrix

### Transient apply-first

Required and passed:

- changed epoch detected without Core reads;
- zero Core operations before ready;
- active Core preserved;
- no new MQTT error;
- lifecycle `kernel-start-awaiting-ready`;
- reconciliation timestamp zero;
- lifecycle timer stopped.

After readiness:

- one 15-second timer;
- healthy classification;
- transition `core-resumed`;
- Core-resume counter `+1`;
- connection-attempt delta `0`;
- no Core mutation.

### Idempotency

Required and passed:

- repeated pre-ready `ApplyChanges()` keeps persistent barrier state exact;
- duplicate `IPS_KERNELSTARTED` keeps the same timer and state;
- no duplicate classification or connection attempt.

### Authentication unavailable

Apply-first with an expired token:

- preserves active Core until ready;
- classifies `authentication-unavailable` after 15 seconds;
- performs owned credential cleanup;
- enters `WaitingForAuthentication`;
- performs no credential request.

### Invalid local configuration

Apply-first with an empty client secret:

- preserves active Core until ready;
- classifies `configuration-invalid`;
- cleans Core credentials normally;
- enters `ConfigurationError`;
- performs no connection attempt.

### Existing negative paths

Still passing:

- message-first ordering;
- token rotation during reconciliation;
- unhealthy credential-bearing Core;
- ownership drift;
- explicit disable wins;
- duplicate lifecycle delivery;
- reconnect bounds;
- cleanup failure classification;
- public variable stability.

## 11. Reproducible Validation

The case-owned command now includes parser and Symcon probe coverage and an
explicit PHPStan memory limit:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
MQTT fixture checks:                 PASS
REST client and authentication:      PASS
native MQTT envelope:                PASS
partial payload parser:              PASS
Symcon MQTT receive probe:           PASS
MQTT shadow payload:                 PASS
Receiver diagnostics:                PASS
Account ingestion:                   PASS
REST reconciliation:                 PASS
transport lifecycle:                 PASS
distribution structure:              PASS
PHPCS:                                PASS
PHPStan, 512 MB:                      PASS
complete command exit:                0
```

Additional complete pilot observation harness:

```text
Dock/Pause/Resume/restart/refresh/adaptive polling checks: PASS
```

`git diff --check` passed for the Navimow changes.

## 12. Artifact Hashes

```text
NavimowAccount/module.php
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5

mqtt-transport-lifecycle.php
a29e0fce4f48d8cdba09c0c9ed6f53d6715890c190bcbca8806c065e04278a6a

mqtt-fixtures.php
45984794c018911bcbd554e2a6f39b0f2b6380cadf900a1be16629ee3919383d

core-resume-transient-core-readiness.json
3fcafc1934c4ba05ed20f7433a4148bdae88b8d57aea942a9acaed076d82657a

check-mqtt-shadow.sh
a59790d59371d0a17355ef44d1f192175653035f60d4aa8baa72bc1a524a942f
```

## 13. Architecture Decisions

### AD-NAV-601: Use durable state for restart precedence

**Decision:** Detect a changed active kernel epoch without reading Core
configuration or authentication readiness.

**Reason:** Epoch ownership must survive the transient platform state it is
designed to guard.

### AD-NAV-602: Perform zero semantic Core reads before ready

**Decision:** Skip both cleanup and lifecycle initialization while the barrier
owns startup.

**Reason:** Read-only semantic inspection and mutation share the same
pre-ready availability risk.

### AD-NAV-603: Preserve the barrier across authentication returns

**Decision:** Missing authentication or invalid local configuration may update
public Account state but may not bypass kernel reconciliation.

**Reason:** Credential cleanup must occur only after native Core readiness.

### AD-NAV-604: Classify invalid local configuration post-ready

**Decision:** Add a distinct delayed `configuration-invalid` branch with
normal owned cleanup.

**Reason:** The durable barrier must remain fail-closed for both transport and
Account configuration failures.

### AD-NAV-605: Require red-before-green evidence

**Decision:** Record the exact failing regression before applying the
productive correction.

**Reason:** A test added after a fix is weaker evidence unless it is known to
distinguish the prior implementation.

### AD-NAV-606: Make the case check self-contained

**Decision:** Include parser, Symcon probe and the required PHPStan memory
limit in the case-owned check command.

**Reason:** A release candidate should have one reproducible offline gate that
returns a truthful exit status.

## 14. Side-Effect Accounting

| Operation | Count |
|---|---:|
| productive case-study files modified | 1 |
| productive case-study files added | 0 |
| synthetic fixtures added | 1 |
| test files modified | 2 |
| check scripts modified | 1 |
| standalone publication commits | 0 |
| Symcon Module Control updates | 0 |
| MQTT activations | 0 |
| broker connections | 0 |
| service restarts | 0 |
| MQTT publish operations | 0 |
| mower commands | 0 |

## 15. Current Runtime Boundary

The live installation was not touched in this step. Its safe state from step
172 remains:

```text
installed commit:           71a90f69
MQTT feature:               disabled
WebSocket:                  inactive
Authorization headers:      empty
MQTT username and password: empty
REST state authority:       retained
```

The corrected source exists only in the canonical case-study distribution.

## 16. Gate Decision

| Gate | Decision |
|---|---|
| failure reproduction | PASS |
| durable barrier implementation | PASS |
| transient readiness regression | PASS |
| negative and idempotency matrix | PASS |
| complete offline validation | PASS |
| public variable/action contract | UNCHANGED |
| REST state authority | RETAINED |
| standalone publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |

## 17. Recommended Next Step

Create:

```text
175-native-mqtt-transient-readiness-correction-publication-and-live-test-plan.md
```

That plan must:

1. freeze final hashes after revalidation;
2. publish only the one-file productive delta;
3. install and verify it while MQTT remains disabled;
4. require renewed persistence acceptance;
5. repeat one separately authorized active restart;
6. require `core-resumed`, `healthy`, `+1` Core-resume observation and zero
   connection deltas;
7. perform mandatory cleanup after pass, failure or ambiguity.

No publication or live authorization is implied by this offline result.
