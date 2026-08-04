# 173 Native MQTT Core Resume Post-Ready Barrier Failure Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Root cause established; further live testing blocked pending an
offline transient-Core-readiness correction
**Date:** 2026-07-29
**Scope:** Analyze the failed Gate-F restart from step 172 without changing
productive code or Symcon

## 1. Purpose

Step 172 proved:

- native MQTT and WebSocket Core instances resumed healthy;
- no Account reconnect occurred;
- topology and Core configuration remained unchanged;
- Account reconciliation nevertheless ended in `ConfigurationError`;
- observation and reconciliation were recorded in the same second;
- no Core classification or `core-resumed` transition was recorded;
- a new `credential-cleanup-skipped` error appeared after kernel start.

This step correlates that exact signature with the published source and the
offline test harness.

## 2. Evidence Boundary

No live Symcon, MQTT, REST or mower operation was performed.

Inputs:

- sanitized step-172 report;
- private machine-readable step-172 closure evidence;
- published `main@71a90f69` Account source;
- current lifecycle tests and runtime harness;
- existing synthetic MQTT fixtures.

No credential, private topic, endpoint, payload, Device ID or ObjectID was
read into this public analysis.

## 3. Intended Startup Contract

The correction from step 165 intended:

```text
early Account ApplyChanges
  -> detect changed kernel epoch
  -> preserve active owned Core
  -> wait timerless for IPS_KERNELSTARTED
  -> start a 15-second post-ready grace period
  -> validate ownership and Core health
  -> adopt as core-resumed
```

The key architectural rule was:

> Detect restart precedence before touching the owned transport.

The implementation only partially satisfies that rule because the precedence
decision itself performs a semantic Core validation.

## 4. Faulty Precedence Predicate

`ApplyChanges()` first calls:

```php
$kernelReconciliationRequired =
    $this->mqttKernelReconciliationMustTakePrecedence();
```

The predicate returns `false` unless all of these are already true:

```text
MQTT feature enabled
general Account configuration valid
access token usable
ownership registry present
inspectMqttShadowConfiguration() valid
new or pending kernel epoch detected
```

`inspectMqttShadowConfiguration()` calls:

```text
mqttTopology()
  -> native instance metadata
  -> Core configurations
  -> subscription parsing
  -> assertMqttOwnership()
  -> transport configuration hash comparison
```

That is a semantic readiness check, not a durable restart signal.

Before `KR_READY`, native Core instances may already have resumed their
persisted connection while their complete configuration is not yet available
to module-level semantic validation. A transient validator result of
`configuration-invalid` therefore makes the changed kernel epoch lose
precedence.

## 5. Reconstructed Live Path

The only published path matching every observed field is:

### 5.1 Early false-negative

```text
mqttKernelReconciliationMustTakePrecedence()
  -> inspectMqttShadowConfiguration() transiently invalid
  -> kernelReconciliationRequired = false
```

### 5.2 Premature normal cleanup

Because MQTT is enabled, ownership exists and precedence is false,
`ApplyChanges()` executes:

```text
disconnectOwnedMqttTransportSafely()
```

The same transient Core-readiness problem causes the owned disconnect to
throw. The safe wrapper suppresses the exception and appends:

```text
credential-cleanup-skipped
```

This matches the new post-kernel error from step 172. The Core remains active
and healthy because the failed cleanup did not mutate it.

### 5.3 Transient lifecycle error

`initializeMqttLifecycle()` immediately performs the same semantic
validation. It fails and sets:

```text
state = ConfigurationError
```

No new transition reason is supplied, so the prior persisted reason
`healthy` remains. This explains the otherwise contradictory live pair:

```text
state:  ConfigurationError
reason: healthy
```

### 5.4 Premature epoch completion

Because the precedence Boolean remains false, `ApplyChanges()` continues to:

```text
markCurrentKernelEpochReconciled()
```

That method writes both:

```text
kernelStartObservedAt   = now
kernelStartReconciledAt = now
```

It does not classify the Core or increment `coreResumeObservations`.

This exactly explains:

```text
observation-to-reconciliation gap: 0 seconds
lastKernelCoreClassification:       none
coreResumeObservations delta:       0
```

### 5.5 Ready message neutralized

When `IPS_KERNELSTARTED` later arrives,
`scheduleMqttKernelReconciliation()` sees:

```text
same kernel epoch
kernelStartReconciledAt > 0
```

It returns without scheduling the 15-second timer. The now-ready and healthy
Core is never classified or adopted.

## 6. Root Cause

**Root cause:** The changed-kernel precedence decision depends on semantic
Core readiness before `KR_READY`.

This creates a circular dependency:

```text
post-ready barrier is selected only if pre-ready Core validation succeeds
```

The barrier is needed precisely because pre-ready semantic validation is not
reliable.

The failure is not caused by:

- broker availability;
- invalid credentials;
- a reconnect attempt;
- changed topology or subscriptions;
- an unhealthy Core;
- token expiry;
- MQTT payload handling;
- REST state processing.

## 7. Why Offline Tests Passed

The lifecycle harness models Core data with immediately available global
arrays:

```text
IPS_GetInstance()      -> complete metadata
IPS_GetConfiguration() -> complete configuration
IPS_ApplyChanges()     -> immediate status 102
```

Apply-first and message-first restart tests restore the complete active
configuration before calling Account `ApplyChanges()`.

The harness cannot currently express:

```text
new kernel epoch
Core-native active transport already resumed
semantic Core configuration temporarily unavailable
KR_READY not delivered yet
```

Therefore the faulty semantic predicate always returns valid in the positive
restart tests.

Verification during this analysis:

```text
MQTT transport lifecycle tests: PASS
MQTT fixture tests:             PASS
distribution validation:       PASS
```

The green suite is consistent with, and helps localize, the missing startup
readiness model.

## 8. Required Correction

The restart decision must be split into two phases.

### Phase 1: Durable epoch barrier

Before touching Core instances, determine precedence only from durable
Account-owned state:

```text
EnableMqttShadow
ownership registry presence
current kernel start time
recorded kernel start time
pending kernel schedule markers
```

Do not call during this phase:

```text
inspectMqttShadowConfiguration()
mqttTopology()
assertMqttOwnership()
disconnectOwnedMqttTransport()
```

When a changed active owned epoch is detected:

- preserve the Core untouched;
- skip semantic lifecycle initialization;
- never call `markCurrentKernelEpochReconciled()`;
- mark `kernel-start-awaiting-ready`;
- keep the lifecycle timer stopped until `IPS_KERNELSTARTED`.

### Phase 2: Post-ready semantic classification

After the ready message:

1. record `kernelStartObservedAt`;
2. schedule exactly 15 seconds;
3. perform authentication, ownership, configuration and health checks;
4. record exactly one Core classification;
5. mark reconciliation only after classification;
6. adopt a healthy Core as `core-resumed`.

This phase already largely exists in
`processMqttKernelReconciliationLocked()`.

## 9. Minimum Productive Change

The smallest defensible implementation should:

1. remove `inspectMqttShadowConfiguration()` from the early precedence
   predicate;
2. make changed-epoch detection independent of transient Core reads;
3. skip `initializeMqttLifecycle()` while the durable barrier owns startup;
4. prohibit `disconnectOwnedMqttTransportSafely()` on that path;
5. prohibit `markCurrentKernelEpochReconciled()` on that path;
6. preserve the existing ready-message and 15-second classification code.

The implementation must review how unusable authentication and invalid local
Account configuration interact with the durable barrier. The existing
post-ready code already handles unusable authentication and must remain the
single active-restart cleanup authority.

## 10. Required Harness Extension

The runtime harness needs bounded transient Core availability controls, for
example:

```text
Core configuration unavailable for N reads
Core instance metadata unavailable for N reads
Core becomes fully readable at KR_READY
```

The harness must distinguish:

- no Core mutation;
- failed Core read;
- Core mutation attempted;
- ready-message delivery;
- elapsed 15-second grace period.

No secret value is required. Synthetic headers, username and password remain
sufficient.

## 11. Mandatory Regression Matrix

### 11.1 Live-failure reproduction

Before applying the correction:

```text
new epoch
ApplyChanges first
Core configuration transiently unavailable
```

Expected reproduction:

```text
premature cleanup attempted
credential-cleanup-skipped appended
same-second reconciliation
ConfigurationError
```

### 11.2 Corrected apply-first path

After correction:

```text
ApplyChanges before KR_READY
Core temporarily unreadable
```

Required:

- zero Core operations;
- no new MQTT error;
- active Core configuration preserved;
- lifecycle `kernel-start-awaiting-ready`;
- timer stopped;
- reconciliation timestamp zero.

After Core readiness and `IPS_KERNELSTARTED`:

- one 15-second timer;
- no Core mutation;
- healthy classification;
- `core-resumed`;
- `coreResumeObservations +1`;
- connection counters unchanged.

### 11.3 Message-first path

The ready message before Account `ApplyChanges()` must remain idempotent and
must not be overwritten by transient initialization.

### 11.4 Authentication unavailable

An expired or absent token during apply-first restart must preserve the Core
until post-ready classification, then execute the existing bounded
authentication-unavailable cleanup path.

### 11.5 Ownership invalid after ready

Transient unreadiness must not be confused with persistent ownership drift.
After the grace period, real drift must classify fail-closed without a
connection attempt.

### 11.6 Disable wins

An explicit `EnableMqttShadow=false` before reconciliation must still cancel
the pending restart path and remove credentials through the normal owned
cleanup.

### 11.7 Duplicate delivery

Repeated `ApplyChanges()`, ready messages and lifecycle callbacks must not:

- restart the grace period;
- classify twice;
- increment Core-resume counters twice;
- issue a Connect;
- mutate public variables.

## 12. Fixture Decision

Add a sanitized synthetic runtime fixture representing:

```text
active owned persisted Core
changed kernel epoch
pre-ready semantic configuration unavailable
post-ready configuration valid and Core 102/102
```

Suggested artifact:

```text
case-studies/navimow/fixtures/mqtt/
  core-resume-transient-core-readiness.json
```

The fixture must contain synthetic IDs and credentials only. It must encode
expected diagnostic transitions, not real Core configuration.

## 13. Architecture Decisions

### AD-NAV-595: Separate epoch detection from Core validation

**Decision:** A changed-kernel barrier may use only durable Account-owned
state.

**Reason:** Semantic Core readiness is not guaranteed before `KR_READY`.

### AD-NAV-596: Make post-ready classification the sole reconciliation writer

**Decision:** On a changed active epoch, only the delayed post-ready path may
set `kernelStartReconciledAt`.

**Reason:** Same-second completion suppresses the ready message and bypasses
classification.

### AD-NAV-597: Skip pre-ready cleanup and initialization

**Decision:** A durable active-restart barrier performs no Core cleanup and no
semantic lifecycle initialization.

**Reason:** Both operations depend on the Core readiness the barrier is
designed to await.

### AD-NAV-598: Model transient platform readiness

**Decision:** Extend the offline harness with bounded unavailable-read
semantics.

**Reason:** Static complete Core arrays cannot validate startup ordering on a
real modular runtime.

### AD-NAV-599: Preserve fail-closed post-ready ownership checks

**Decision:** Removing early semantic validation must not remove delayed
ownership, subscription or transport validation.

**Reason:** Readiness delay and ownership trust are separate concerns.

### AD-NAV-600: Block repeated live testing

**Decision:** No further publication, activation or restart is permitted until
the transient-read regression fails before and passes after the correction.

**Reason:** The current live failure is deterministic and already
discriminating.

## 14. Risk Review

| Risk | Current classification | Required mitigation |
|---|---|---|
| false restart negative before ready | proven | durable epoch-only barrier |
| premature credential cleanup | proven | prohibit Core mutation behind barrier |
| same-second reconciliation | proven | delayed path is sole writer |
| stale `healthy` reason with error state | proven diagnostic ambiguity | explicit awaiting transition before ready |
| expired token during apply-first restart | coverage gap | post-ready auth regression |
| persistent ownership drift | already fail-closed | retain delayed validation tests |
| duplicate message/timer work | covered, extend transient case | idempotency assertions |
| public variable or archive churn | not observed | retain compatibility tests |

## 15. Gate Decision

| Gate | Decision |
|---|---|
| root cause | ESTABLISHED |
| broker or credential fault | REJECTED |
| transient Core-readiness gap | PROVEN BY CORRELATION |
| existing test suite | PASS, INCOMPLETE MODEL |
| productive correction | NOT STARTED |
| publication | CLOSED |
| Symcon update | CLOSED |
| MQTT activation | CLOSED |
| service restart | CLOSED |
| current runtime state | DISABLED AND CREDENTIAL-FREE |
| REST state authority | RETAINED |

## 16. Recommended Next Step

Implement offline:

```text
174-native-mqtt-core-resume-transient-readiness-correction.md
```

That step must:

1. add the synthetic transient-readiness fixture;
2. extend the harness to reproduce the step-172 signature;
3. prove the new regression fails against the current behavior;
4. implement the durable two-phase barrier;
5. pass the complete MQTT, REST, command, variable and distribution suite;
6. stop before publication or any live Symcon action.
