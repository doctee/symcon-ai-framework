# 155 Native MQTT Kernel Start Reconciliation Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Kernel-start reconciliation implemented and verified offline;
publication and live activation remain gated
**Date:** 2026-07-28
**Scope:** Implement the observe-and-adopt restart model selected in step 154
without publication, Symcon mutation or network activity

## 1. Purpose

Step 153 proved that retained native WebSocket and MQTT Core instances can
resume from persistent active configuration without a new Account
`ApplyChanges()` call.

Step 154 selected a supported post-ready reconciliation model:

- observe `IPS_KERNELSTARTED`;
- defer all work to the existing MQTT lifecycle timer;
- adopt a healthy owned Core resume without reconnect;
- rebuild only a credential-free inactive transport;
- clean and use bounded recovery for an unhealthy owned transport;
- fail without retry on authentication, ownership or configuration errors.

This step implements and verifies that model exclusively inside the Navimow
case study.

## 2. Changed Files

```text
case-studies/navimow/
  distribution/NavimowAccount/module.php
  fixtures/mqtt/bounded-diagnostics-shadow-active.json
  tests/harness/SymconRuntime.php
  tests/mqtt-transport-lifecycle.php
```

No standalone publication repository, public module branch, Symcon instance,
private capture or mower state was changed.

## 3. Kernel Message Registration

`NavimowAccount::Create()` now registers sender `0` and the official
`IPS_KERNELSTARTED` message.

The project-wide PHPStan Symcon stub does not yet declare:

- `IPSModule::RegisterMessage()`;
- `IPS_KERNELSTARTED`;
- `IPS_GetKernelStartTime()`.

This case study must not change shared SAEF infrastructure. The implementation
therefore uses the existing runtime-callable pattern already present for
Symcon functions:

```text
RegisterMessage callable -> sender 0 / message 10001
kernel message constant  -> IPS_KERNELSTARTED or documented value 10001
kernel start timestamp   -> runtime-checked IPS_GetKernelStartTime
```

The fallback value is the official IP-Symcon message value, not an
installation-specific identifier.

## 4. Bounded MessageSink

The new public `MessageSink()`:

1. rejects every sender other than `0`;
2. rejects every message other than `IPS_KERNELSTARTED`;
3. reads the current kernel start timestamp;
4. records one new kernel epoch;
5. schedules `MqttLifecycle` after 15 seconds.

It performs no:

- REST or MQTT credential request;
- WebSocket or MQTT Core mutation;
- connection attempt;
- OAuth mutation;
- mower command.

Repeated delivery for an already scheduled or reconciled kernel epoch is
idempotent.

## 5. Single Recovery Executor

The existing `MqttLifecycle` timer and lifecycle semaphore remain the only
recovery executor.

The new private scheduled kind is:

```text
kernel-reconcile
```

At its due time the executor re-reads current configuration and
authentication. A feature disable that occurred during the 15-second wait
takes precedence and prevents network work.

No second timer, script event or automatic Core object was introduced.

## 6. Reconciliation Decisions

### Healthy owned Core

Required evidence:

```text
ownership and topology valid
MQTT Core status = 102
WebSocket Core status = 102
WebSocket Active = true
```

Result:

- no credential endpoint call;
- no Core property mutation;
- no new Account `connectionAttempts`;
- one `coreResumeObservations` increment;
- lifecycle `ShadowActive`;
- transition reason `core-resumed`;
- normal 60-second observation resumes.

The healthy interval starts at reconciliation time. Health accumulated before
the new kernel epoch is not used to reset reconnect history.

### Inactive credential-free Core

Required evidence:

```text
WebSocket Active = false
Authorization headers empty
MQTT username empty
MQTT password empty
ownership and topology valid
```

Result:

- kernel reconciliation completes;
- reconnect episode metadata resets;
- one existing initial connection is scheduled after five seconds;
- no credential request occurs during kernel reconciliation.

### Unhealthy owned Core with credentials

Result:

- record one unexpected disconnect;
- deactivate WebSocket;
- clear Authorization headers and MQTT credentials;
- schedule the existing bounded reconnect sequence;
- first delay remains 60 seconds.

The existing `60/300/900` contract and three-attempt limit are unchanged.

### Authentication unavailable

If ownership remains valid:

- clean the owned transport;
- stop the lifecycle timer;
- enter `WaitingForAuthentication`;
- perform no retry.

If authentication and ownership are both invalid:

- do not mutate ambiguous Core objects;
- record bounded cleanup failure evidence;
- enter `ConfigurationError`;
- perform no retry.

### Configuration or ownership invalid

Result:

- no Core mutation;
- no credential request;
- no retry;
- lifecycle `ConfigurationError`.

### Feature disabled

If an owned topology exists, it must already be credential-free or is cleaned.
The final lifecycle is `Disabled` with no timer and no credential request.

## 7. Private Diagnostics

The bounded diagnostics contract now adds:

```text
lifecycle.kernelStartObservedAt
lifecycle.kernelStartReconciledAt
lifecycle.kernelStartTime
statistics.coreResumeObservations
```

New allowlisted transition reasons:

```text
kernel-start-observed
core-resumed
```

Rules:

- timestamps normalize to non-negative integers;
- counters saturate through the existing bounded counter helper;
- malformed values normalize to zero;
- diagnostics remain read-only;
- no token, credential, topic, endpoint, device identity or payload is
  projected.

`connectionAttempts` continues to count only Account-owned credential and
connection attempts. A native Core resume is counted separately.

## 8. Deterministic Test Harness

The local Symcon runtime harness now models:

- idempotent `RegisterMessage()` calls;
- inspection of registered sender/message pairs;
- the official synthetic `IPS_KERNELSTARTED` value.

The Account test subclass supplies a fake kernel start time. Service restart
is modeled by:

1. snapshotting Account persistence while Core is active;
2. creating a fresh module runtime without calling `ApplyChanges()`;
3. restoring only persistent Account state;
4. retaining active Core configuration and statuses;
5. delivering `IPS_KERNELSTARTED`;
6. advancing a fake clock.

This directly models the behavior observed in step 153 instead of repeating
the disproven `ApplyChanges()` assumption.

## 9. Regression Matrix

| Contract | Result |
|---|---|
| sender `0` / message `10001` registration | PASS |
| unrelated messages ignored | PASS |
| `MessageSink()` has no Core or network side effect | PASS |
| 15-second deferred reconciliation | PASS |
| duplicate startup message idempotent | PASS |
| reconciled epoch idempotent | PASS |
| healthy Core adopted without reconnect | PASS |
| Account attempt count unchanged on Core resume | PASS |
| one separate Core-resume observation | PASS |
| inactive credential-free Core schedules one delayed connection | PASS |
| disabled startup remains credential-free | PASS |
| expired auth cleans and suspends | PASS |
| unhealthy Core enters bounded recovery | PASS |
| auth plus ownership drift mutates nothing | PASS |
| explicit disable wins over pending reconciliation | PASS |
| malformed new diagnostics normalize safely | PASS |
| public Account variables unchanged | PASS |
| no automatic Core create/delete/reload | PASS |
| REST remains public state authority | PASS |
| MQTT remains receive-only | PASS |

## 10. Complete Offline Validation

```text
MQTT fixtures:                 PASS
REST client and auth:          PASS
native MQTT envelope:          PASS
MQTT shadow payload:           PASS
Receiver diagnostics:          PASS
Account ingestion:             PASS
targeted REST reconciliation:  PASS
transport lifecycle:           PASS
distribution structure:        PASS
PHPCS:                         PASS
PHPStan:                       PASS
git diff --check:              PASS
```

The first PHPStan execution reached the local PHP memory limit of 128 MB
without reporting a source finding. The identical analysis scope was repeated
with `--memory-limit=512M` and completed with no errors.

## 11. Source Hashes

```text
NavimowAccount/module.php:
544a594569c63aaf942e455fed6fdecc163d404710cb338876e91362ed06e440

mqtt-transport-lifecycle.php:
853372c66cf2b07ac2e326d8e959314344f5eede739887478089d2a2ac5347ca

bounded-diagnostics-shadow-active.json:
e052e1174e872090bb94f77aa1c2a055a4614abe87b3857443157cc34383a0c6

tests/harness/SymconRuntime.php:
4e09af33db6f58d415797e9f9e03b3796d264375eb3df6f945a647e3a74e375e
```

## 12. Architecture Decisions

### AD-NAV-529: Register startup observation in `Create()`

**Decision:** Register the supported kernel-start message when the Account
runtime is created.

**Reason:** The registration must exist before the post-`KR_READY` message and
must not depend on `ApplyChanges()` being called during service restart.

### AD-NAV-530: Keep `MessageSink()` mutation-free

**Decision:** The message handler only records and schedules.

**Reason:** Synchronous kernel message handling is not the correct place for
network I/O or multi-instance Core reconfiguration.

### AD-NAV-531: Adopt healthy native continuity

**Decision:** A healthy, fully validated owned Core resume is adopted without
forced rotation.

**Reason:** This matches live platform behavior, avoids duplicate broker
connections and preserves accurate Account connection-attempt semantics.

### AD-NAV-532: Reset health at the new kernel epoch

**Decision:** `healthySince` begins again when Core resume is adopted.

**Reason:** Pre-restart healthy time must not satisfy the 15-minute
post-recovery stability window.

### AD-NAV-533: Authentication never overrides ownership safety

**Decision:** Combined authentication and ownership failure is a terminal
configuration error.

**Reason:** Missing authentication does not authorize mutation of an
ambiguously owned Core chain.

### AD-NAV-534: Keep the public contract unchanged

**Decision:** Kernel metadata stays in bounded private diagnostics.

**Reason:** Restart recovery does not require a public variable, profile,
action or Archive Control change.

## 13. Remaining Boundaries

This step does not prove behavior inside a real IP-Symcon restart.

It does not authorize:

- copying files to the standalone module repository;
- committing or pushing a publication;
- updating Symcon;
- accepting persistent active Core credentials;
- enabling MQTT;
- restarting Symcon;
- MQTT publish;
- a mower command.

MQTT remains disabled and credential-free in the live installation according
to the final evidence from step 153.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| redesign from step 154 implemented | PASS |
| deterministic restart coverage | PASS |
| complete offline gate | PASS |
| public variable contract | UNCHANGED |
| Archive Control contract | UNCHANGED |
| publication | NOT AUTHORIZED |
| Symcon update | NOT AUTHORIZED |
| credential persistence acceptance | OPEN |
| MQTT activation | NOT AUTHORIZED |
| MQTT publish | PROHIBITED |
| MQTT state authority | PROHIBITED |
| REST state authority | RETAINED |

## 15. Recommended Next Step

Create:

```text
156-native-mqtt-kernel-start-reconciliation-publication-and-live-test-plan.md
```

That plan should freeze the exact publication candidate and separate:

1. publication;
2. disabled Symcon update and compatibility verification;
3. inactive credential-free restart verification;
4. explicit acceptance of active Core credential persistence;
5. receive-only activation;
6. one supervised real service restart;
7. proof of `core-resumed` without an extra Account connection attempt;
8. Disable fallback on any ambiguity.

The plan itself must perform no publication, Symcon mutation or MQTT
activation.
