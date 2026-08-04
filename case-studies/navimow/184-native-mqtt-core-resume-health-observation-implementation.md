# 184 Native MQTT Core Resume Health Observation Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation complete; complete Navimow gate passed,
publication remains closed
**Date:** 2026-07-29
**Scope:** Implement only the bounded Core-resume observation contract from
step 183

## 1. Purpose

Step 183 replaced the one-shot post-ready health decision with an absolute,
bounded observation contract.

This step:

1. adds the synthetic bounded-observation fixture;
2. proves the existing implementation red;
3. implements `+15/+30/+60/+90 s` Core-health observations;
4. preserves the native Core until health or the final deadline;
5. adds privacy-safe pre-cleanup diagnostics;
6. covers delayed timers, duplicates, ingress and new epochs;
7. passes the complete Navimow offline gate.

No standalone publication, Symcon update, live MQTT operation, restart or mower
command was performed.

## 2. Red Regression

Added before the productive correction:

```text
fixtures/mqtt/core-resume-bounded-health-observation.json
tests/mqtt-fixtures.php
tests/mqtt-transport-lifecycle.php
```

The fixture test passed and the lifecycle test failed against the existing
implementation:

```text
Navimow MQTT fixture checks passed.
Fatal error:
Delayed Core readiness was cleaned at the first observation.
```

The failure occurred at the first synthetic `104/104` projection at `+15 s`.
This reproduces the behavioral defect from step 181 and proves the regression
does not pass trivially.

## 3. Implemented Lifecycle

The durable `IPS_KERNELSTARTED` barrier remains unchanged.

For every new kernel epoch, the Account now records:

```text
kernelStartObservedAt
kernelCoreObservationDeadlineAt = observedAt + 90
kernelCoreObservations = []
kernelCoreObservationCount = 0
```

The timer uses absolute offsets:

```text
+15 s -> +30 s -> +60 s -> +90 s
```

When strict health is false before the deadline:

```text
state:          CoreResumeObserving
reason:         core-readiness-pending
classification: pending-with-credentials
```

The owned native Core remains untouched.

## 4. Positive Adoption

At any observation:

```text
MQTT status == 102
WebSocket status == 102
WebSocket Active == true
```

immediately produces:

```text
state:          ShadowActive
reason:         core-resumed
classification: healthy
Core-resume observations: +1
```

It performs:

```text
MQTT credential requests: 0
Account connection attempts: 0
native Core mutations: 0
```

The synthetic delayed-30 and delayed-60 paths both adopt the retained Core
without reconnecting.

## 5. Final Recovery

When strict health remains false at or after `+90 s`, the Account:

1. records the final projection;
2. classifies `unhealthy-with-credentials`;
3. increments `unexpectedDisconnects` exactly once;
4. executes the existing owned credential cleanup;
5. marks the epoch reconciled;
6. schedules the existing first 60-second reconnect delay.

The never-ready regression proves:

```text
Core mutations before +90 s: 0
final cleanup Core operations: 7
Account connection operations: 0
observation entries: 4
```

## 6. Late-Timer Semantics

Missed offsets are not replayed.

The executable regression advances directly to `+47 s`:

```text
one projection at +47 s
next timer in 13 s
next absolute projection at +60 s
```

Another case advances directly beyond the deadline:

```text
first timer execution at +96 s
one projection at +96 s
one final recovery
no +15/+30/+60 replay
```

This preserves the absolute 90-second bound without operation bursts.

## 7. Diagnostic Contract

`GetMqttDiagnostics()` now exposes:

```text
kernelCoreObservationCount
kernelCoreObservationDeadlineAt
lastKernelCoreFailedPredicates
kernelCoreObservations
```

At most four observations are retained. Each contains only:

```text
ordinal
observedAt
offsetSeconds
mqttStatus
webSocketStatus
webSocketActive
authorizationPresent
mqttUsernamePresent
mqttPasswordPresent
lastReceivedAt
healthy
```

Credential values, endpoint, topic, payload, device identity and ObjectIDs are
never persisted in this timeline or returned by diagnostics.

Malformed diagnostic-state tests prove:

- unknown strings are normalized;
- only known failed-predicate labels survive;
- observation history is capped at four entries;
- private-looking fields are discarded;
- diagnostics remain read-only and below the existing size bound.

## 8. Ingress Boundary

The test injects one accepted synthetic receive-only envelope while the native
Core statuses remain `104/104`.

Result:

```text
lastReceivedAt: advanced
Core lifecycle: CoreResumeObserving
Core adoption: no
Core mutation: no
```

Ingress remains evidence only and cannot replace
`102/102/Active=true`.

## 9. Idempotency and Epoch Handling

Passed contracts:

- duplicate timer invocation before the next offset adds no observation;
- duplicate `IPS_KERNELSTARTED` preserves deadline and history;
- repeated `ApplyChanges()` preserves pending reconciliation;
- a changed kernel epoch resets the old bounded history and deadline;
- the new epoch reconciles independently;
- final cleanup and disconnect counting occur exactly once;
- a normal initial or kernel-fallback connection clears stale observation
  history.

## 10. Immediate Negative Gates

The bounded native-readiness window does not delay:

- explicit feature disable;
- invalid Account configuration;
- unavailable Account authentication;
- invalid ownership or topology;
- exact inactive credential-free fallback.

Executable results:

| Gate | Result |
|---|---|
| disable during pending reconciliation | `Disabled`, no reconnect |
| expired token | `WaitingForAuthentication`, no credential request |
| ownership drift | `ConfigurationError`, no unverified Core mutation |
| exact credential-free Core | kernel fallback, no disconnect count |

Disable and a new normal initial episode clear the bounded observation
history.

## 11. Productive Delta

Compared byte-for-byte with published standalone:

```text
main@7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
```

the productive delta is:

```text
modified productive files: 1
NavimowAccount/module.php: +315 / -9
added productive files: 0
deleted productive files: 0
```

Published Account SHA-256:

```text
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5
```

Current canonical Account SHA-256:

```text
1bbc18327564bca52a9257f11485b4b8c9340e2f5f51e5066caa4fec253d79d7
```

No form, locale, module metadata, Device, Receiver, REST, variable, action or
archive contract changed.

The published standalone hash inventory remains unchanged until a separately
authorized publication step.

## 12. Changed Offline Artifacts

| Artifact | SHA-256 |
|---|---|
| `distribution/NavimowAccount/module.php` | `1bbc18327564bca52a9257f11485b4b8c9340e2f5f51e5066caa4fec253d79d7` |
| `tests/mqtt-transport-lifecycle.php` | `49d18738dd07f7785649e4d3dfe492f48d1a817a2c0d414b9f807390f64ff938` |
| `tests/mqtt-fixtures.php` | `fd8e8dab157c3a91afa4d16ea30862db21dda4ec19e1986e3c0f3d1287ade652` |
| `fixtures/mqtt/core-resume-bounded-health-observation.json` | `a2d1d416954c16bd26b8d02bb197815fcfb38e808e21f654ead81e37d7f66431` |
| `fixtures/mqtt/bounded-diagnostics-shadow-active.json` | `370e00a4a61c01be55ae812f33610a74205a265a551e546472b90c07076b3211` |

Documentation also updates the MQTT fixture index, case-study index and the
step-183 credential-free wording.

## 13. Validation

Executed:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

Passed:

```text
MQTT fixture checks
REST client and authentication checks
native MQTT envelope checks
MQTT partial payload parser checks
Symcon MQTT receive probe checks
MQTT shadow payload checks
MQTT Receiver diagnostics checks
MQTT Account ingestion checks
MQTT shadow reconciliation checks
MQTT transport lifecycle checks
distribution structure validation
PHP syntax checks
PHPStan
Symcon Module Validator
complete Navimow MQTT shadow gate
```

PHPStan initially reported the now-unused private method
`mqttCoreIsHealthy()`. The new atomic observation projection had replaced all
callers, so the dead method was removed rather than suppressed. The complete
gate then passed.

## 14. Architecture Decisions

### AD-NAV-646: Capture and decide from one atomic projection

**Decision:** One observation reads statuses, active state and credential
presence once and uses that same projection for diagnostics and health.

**Reason:** Separate reads could make the persisted evidence disagree with the
decision during a native status transition.

### AD-NAV-647: Preserve error statuses until the bounded deadline

**Decision:** Native status `>=200` is diagnostic evidence but does not bypass
the 90-second window when all structural gates remain valid.

**Reason:** The module lacks a documented startup guarantee that distinguishes
a terminal retained-Core error from a transient startup projection.

### AD-NAV-648: Retain final evidence through reconnect scheduling

**Decision:** Final recovery preserves the four-entry observation timeline.

**Reason:** Credential cleanup must not erase the facts needed to explain why
the deadline was reached.

### AD-NAV-649: Clear history at explicit disable or a new initial episode

**Decision:** Disable cleanup and normal initial/kernel-fallback connection
start clear stale Core-resume history.

**Reason:** Diagnostics should describe the current lifecycle episode while
remaining available through the failed episode's bounded reconnect schedule.

## 15. Gate Decision

| Gate | Decision |
|---|---|
| discriminating red regression | PASS |
| bounded observation implementation | PASS |
| delayed native adoption | PASS |
| never-ready final recovery | PASS |
| timer and epoch idempotency | PASS |
| diagnostic privacy | PASS |
| complete offline validation | PASS |
| productive file scope | ONE FILE |
| publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| further restart | CLOSED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

## 16. Recommended Next Step

Proceed with:

```text
185-native-mqtt-core-resume-health-observation-publication-and-live-test-plan.md
```

That step should freeze the exact one-file productive delta and separate
publication, disabled installation, inactive staging, renewed credential
persistence acceptance, one receive-only active restart and mandatory cleanup
into explicit gates.
