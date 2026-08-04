# 154 Native MQTT Service Restart Recovery Redesign

**Case study:** Navimow native IP-Symcon module
**Status:** Restart ownership redesigned; implementation, publication and live
reactivation remain gated
**Date:** 2026-07-28
**Scope:** Analyze the failed restart contract from step 153 and define a
supported, observable and fail-safe native MQTT recovery model

## 1. Purpose

Step 153 proved that a normal IP-Symcon service restart does not necessarily
execute the Account `ApplyChanges()` recovery path.

The retained native WebSocket and MQTT Core instances instead resumed from
their stored active configuration before any new Account-owned connection
attempt was recorded.

This step:

1. verifies the supported IP-Symcon kernel-start lifecycle hook;
2. separates observed facts from inferred startup ordering;
3. analyzes Core-owned continuity and Account-owned rotation;
4. distinguishes planned and unplanned service restarts;
5. corrects the credential persistence model;
6. selects a restart ownership contract for the next offline implementation.

It performs no code change, publication, Symcon mutation, REST request, MQTT
connection or mower command. MQTT remains disabled and credential-free.

## 2. Inputs

### SAEF

- `standards/SYMCON_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-004-internal-state-management.md`
- `147-native-mqtt-passive-pilot-recovery-hardening-implementation.md`
- `152-native-mqtt-passive-pilot-activation.md`
- `153-native-mqtt-passive-pilot-restart-observation.md`

### Official IP-Symcon documentation

- [RegisterMessage](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/registermessage/)
- [MessageSink](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/messagesink/)
- [Nachrichten](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/nachrichten/)
- [IPS_GetKernelRunlevel](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/programminformationen/ips-getkernelrunlevel/)
- [IPS_GetKernelStartTime](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/programminformationen/)
- [ApplyChanges](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/applychanges/)
- [IPS_SetProperty](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/instanzenverwaltung/konfiguration/ips-setproperty/)

The official message reference defines `IPS_KERNELSTARTED` as message `10001`,
sent synchronously after `KR_READY`. `RegisterMessage()` and `MessageSink()`
are the supported module mechanisms for receiving that message.

`ApplyChanges()` is documented for applying instance configuration and after
instance creation. It is not documented as a guaranteed service-start hook.

## 3. Proven Runtime Facts

The supervised restart in step 153 proved:

```text
kernel epoch changed:              yes
Core WebSocket/MQTT resumed:       yes
natural ingress continued:         yes
Account connection attempt added: no
Account attempt timestamp postboot:no
Account ApplyChanges recovery:     not observed
duplicate topology:                no
REST operation:                    retained
```

The active WebSocket flag, Authorization header and MQTT username/password
were still available to the Core instances after restart.

The authorized Disable fallback subsequently proved that the Account can
deactivate the retained transport and clear those properties after startup.

## 4. Corrected Credential Model

The previous pilot documentation described MQTT credentials as transient
values present only in the active owned Core configuration.

That wording was incomplete.

The Account applies credentials through Core instance properties using
`IPS_SetProperty()` and `IPS_ApplyChanges()`. Those properties are part of the
instance configuration. While the native transport is enabled, they survive a
service restart and are therefore persistent configuration values.

The valid security statement is now:

```text
Credentials are excluded from public variables, bounded diagnostics,
logs, fixtures and public evidence.

Credentials are nevertheless persisted inside the active native Core
instance configuration for as long as the retained transport is enabled.
```

Cleanup on a graceful lifecycle event shortens exposure after that event. It
cannot make the credentials runtime-only while the native Core configuration
itself requires them.

If persistence in active Core configuration is unacceptable, the retained
native WebSocket/MQTT architecture cannot be activated. A separate transport
whose secrets remain only in process memory would require a new architecture
and is outside the current MVP.

## 5. Supported Startup Hook

The supported module-level startup registration is:

```php
$this->RegisterMessage(0, IPS_KERNELSTARTED);
```

The module can then handle the message in:

```php
public function MessageSink(
    $TimeStamp,
    $SenderID,
    $Message,
    $Data
) {
    // Detect IPS_KERNELSTARTED and defer work to a module timer.
}
```

The exact signature must remain compatible with the module's current
`IPSModule` base class.

The handler should perform only bounded local state classification and arm a
timer. It should not retrieve credentials, reconfigure Core instances or
connect to the network inline. The existing `MqttLifecycle` timer and
semaphore remain the single execution path for recovery work.

### Timing boundary

`IPS_KERNELSTARTED` is delivered after `KR_READY`. Therefore this hook can:

- identify a new kernel epoch;
- schedule delayed reconciliation;
- observe the retained Core topology;
- adopt healthy Core continuity;
- clean or rebuild an unhealthy transport.

It cannot guarantee:

- execution before native Core instances load their stored configuration;
- cleanup before a Core-owned automatic reconnect;
- prevention of any brief connection using persisted credentials.

The live evidence indicates that Core resume may already have occurred by the
time Account reconciliation becomes observable.

## 6. Recovery Models

### Model A: Force Account rotation after every kernel start

Sequence:

1. receive `IPS_KERNELSTARTED`;
2. defer to the lifecycle timer;
3. deactivate Core;
4. clear credentials;
5. request fresh MQTT credentials;
6. reconnect once.

Advantages:

- eventually returns the transport to an Account-counted lifecycle;
- refreshes credentials after every service restart;
- leaves familiar connection-attempt evidence.

Risks:

- cannot prove cleanup before the first Core-native reconnect;
- intentionally interrupts an already healthy connection;
- creates two startup connections: Core resume, then Account rotation;
- increases broker churn and ordering races;
- can turn a healthy restart into an avoidable outage;
- still stores fresh credentials after reconnection.

Decision: reject as the default restart path.

### Model B: Accept Core-native continuity without reconciliation

Sequence:

1. Core resumes from stored active configuration;
2. Account timers and diagnostics continue without classifying the new epoch;
3. normal health observation eventually resumes.

Advantages:

- matches observed platform behavior;
- preserves ingress continuity;
- avoids an unnecessary reconnect.

Risks:

- Account state may claim continuity without proving topology ownership;
- attempt counters cannot explain the resumed connection;
- stale or foreign configuration could be accepted;
- no explicit kernel-epoch evidence exists;
- the architecture would retain two implicit lifecycle owners.

Decision: reject in this incomplete form.

### Model C: Observe and adopt healthy Core continuity

Sequence:

1. register the supported kernel-start message;
2. on `IPS_KERNELSTARTED`, record the new kernel epoch and arm one delayed
   reconciliation timer;
3. after the startup grace period, verify feature flag, authentication,
   topology, symmetric Receiver pairing, ownership hashes, subscription
   contract and both Core statuses;
4. if the retained transport is healthy and owned, classify it as
   `CoreResumed`, adopt it without reconnect and resume health observation;
5. if it is inactive but correctly credential-free, use the existing bounded
   delayed connection path;
6. if it is unhealthy with credentials present, clean it and enter the
   existing bounded reconnect path;
7. if ownership, configuration or authentication is invalid, clean what can
   be safely proven as owned and remain terminal without retry.

Advantages:

- matches proven native startup behavior;
- preserves healthy ingress;
- avoids forced duplicate connection churn;
- restores explicit Account observation and diagnostics;
- keeps retries bounded;
- retains REST as the public state authority.

Residual limits:

- accepts persisted Core credentials while the feature is active;
- cannot prevent native Core activity before post-ready reconciliation;
- cannot prove a new MQTT credential endpoint call at restart because none is
  required for a healthy resume.

Decision: select for the next offline implementation, subject to explicit
acceptance of active Core credential persistence.

## 7. Planned Restart Contract

A planned restart has a pre-shutdown control opportunity.

The safe operational sequence is:

```text
1. disable the Navimow MQTT feature;
2. apply the Account configuration once;
3. verify WebSocket inactive;
4. verify Authorization headers and MQTT credentials empty;
5. restart IP-Symcon externally;
6. verify REST continuity and credential-free MQTT topology;
7. re-enable only through a separately authorized activation.
```

No module can infer that an arbitrary upcoming shutdown is planned. A future
explicit `PrepareMqttForRestart()` operation could make steps 1 through 4
ergonomic, but it must not restart the service itself and is not required for
the immediate recovery implementation.

The official `IPS_KERNELSHUTDOWN` message may support best-effort graceful
cleanup, but it must not be the only planned-restart control:

- shutdown handling is synchronous and time-bounded;
- a crash, power loss or forced termination may bypass it;
- cleanup during shutdown cannot be used as proof for the following startup.

The first implementation should therefore defer a shutdown hook until its
mutation ordering has separate offline and live evidence.

## 8. Unplanned Restart Contract

An unplanned restart has no reliable pre-cleanup opportunity.

When MQTT was enabled before the interruption:

- native Core may reconnect using persisted configuration;
- the Account must not claim that credentials were transient;
- the Account must reconcile the new kernel epoch after `KR_READY`;
- healthy owned continuity may be adopted without forced reconnect;
- unhealthy or ambiguous ownership must fail closed;
- REST polling must continue independently.

When MQTT was disabled before the interruption:

- Core must remain inactive and credential-free;
- no automatic activation is permitted;
- the kernel-start observer may record the disabled epoch but must not request
  credentials.

## 9. Revised Ownership Contract

Ownership is split by lifecycle phase, not duplicated:

| Phase | Owner |
|---|---|
| active socket operation and native restart reconnect | IP-Symcon Core |
| feature opt-in and topology adoption | Navimow Account |
| credential retrieval and rotation | Navimow Account |
| post-kernel ownership and health reconciliation | Navimow Account |
| bounded recovery after unhealthy observation | Navimow Account |
| public mower state | REST reconciliation |
| MQTT payload handling | receive-only Receiver and Account shadow |

Core-native restart continuity is an accepted platform capability, not an
Account connection attempt.

## 10. State and Diagnostic Changes

The next implementation should extend private lifecycle metadata only.

Required fields:

```text
kernelStartObservedAt
kernelStartReconciledAt
kernelStartTime
coreResumeObservations
lastTransitionReason
```

Required transition reason:

```text
core-resumed
```

Required rules:

- `connectionAttempts` counts only Account credential/connect attempts;
- `coreResumeObservations` counts validated native restart continuities;
- a kernel epoch is reconciled at most once;
- repeated `IPS_KERNELSTARTED` delivery or timer execution is idempotent;
- timestamps and counters are bounded and contain no private values;
- no public Device variable or profile changes;
- no Archive Control changes.

Suggested lifecycle flow:

```text
IPS_KERNELSTARTED
  -> KernelReconcileScheduled
  -> owned and healthy
       -> ShadowActive / core-resumed
  -> owned, inactive and credential-free
       -> existing delayed initial connection
  -> owned but unhealthy
       -> cleanup -> existing bounded reconnect
  -> invalid auth/configuration/ownership
       -> terminal state, no transport retry
```

`KernelReconcileScheduled` may be represented as a scheduled kind inside the
existing private lifecycle registry. It does not require a new public state.

## 11. Delay and Concurrency

The kernel-start handler should schedule exactly one reconciliation after a
short startup grace period. The initial implementation should use:

```text
startup reconciliation delay: 15 seconds
```

Reason:

- the message is already post-`KR_READY`;
- the observed Core chain was healthy after restart;
- a short delay allows native parent/child status propagation;
- it remains far below the normal 60-second health interval.

The existing MQTT lifecycle semaphore remains mandatory. A concurrent OAuth
refresh, explicit disable or lifecycle timer wins according to lock order;
the losing path schedules at most one bounded recheck and never opens a
second connection.

An explicit disable always has precedence. A delayed kernel reconciliation
must re-read the feature flag and abort without network work when disabled.

## 12. Failure Matrix

| Post-start observation | Required action |
|---|---|
| feature disabled | remain disabled; verify/clean owned credentials |
| auth unavailable | clean owned transport; wait for auth |
| ownership invalid | terminal configuration error; no retry |
| Core healthy and owned | adopt as `core-resumed`; no reconnect |
| Core inactive and credential-free | schedule one delayed initial attempt |
| Core unhealthy with owned credentials | clean; bounded `60/300/900` recovery |
| healthy Core but Receiver pairing invalid | terminal configuration error |
| no mower messages but Core healthy | retain transport; REST remains authority |
| retry exhaustion | remain disconnected; REST continues |

## 13. Security Acceptance Gate

Persistent pilot activation requires one explicit decision:

```text
While the native MQTT feature is enabled, OAuth-derived Authorization data
and MQTT credentials are stored in owned IP-Symcon Core instance
configuration and can be reused by Core after a service restart.
```

Acceptance permits neither:

- logging or exposing credentials;
- committing credentials;
- MQTT publish;
- MQTT state authority;
- automatic Core creation or adoption without ownership proof.

Without this acceptance, MQTT remains disabled and the REST MVP remains fully
operational.

## 14. Architecture Decisions

### AD-NAV-523: `ApplyChanges()` is not the service-start contract

**Decision:** Use `IPS_KERNELSTARTED` through `RegisterMessage()` and
`MessageSink()` for explicit post-ready reconciliation.

**Reason:** Official documentation defines the message for this lifecycle
phase, and step 153 disproved the assumption that `ApplyChanges()` provides
the required restart callback.

### AD-NAV-524: Do not force rotation of a healthy Core resume

**Decision:** Validate and adopt a healthy owned native reconnect.

**Reason:** Forced rotation cannot prevent the earlier Core reconnect, creates
avoidable churn and does not eliminate credential persistence.

### AD-NAV-525: Correct the persistence claim

**Decision:** Treat active native Core credentials as persisted configuration.

**Reason:** This is the observed behavior and follows from applying them as
Core properties. Security documentation must describe the actual storage
boundary.

### AD-NAV-526: Separate planned and unplanned restarts

**Decision:** Planned restarts require disable-and-clean before the external
restart. Unplanned restarts use post-ready observe-and-adopt reconciliation.

**Reason:** Only the planned case has a reliable pre-shutdown control point.

### AD-NAV-527: Preserve one recovery executor

**Decision:** `MessageSink()` only schedules work. The existing lifecycle
timer and semaphore perform all transport inspection and mutation.

**Reason:** This preserves bounded execution, retry ownership and
idempotency.

### AD-NAV-528: Keep MQTT gated

**Decision:** Do not reactivate the pilot from this analysis.

**Reason:** The revised lifecycle is not implemented or tested, and active
credential persistence still requires explicit acceptance.

## 15. Verification Gates for the Next Implementation

Offline tests must prove:

1. startup registration uses sender `0` and `IPS_KERNELSTARTED`;
2. `MessageSink()` performs no network or Core mutation;
3. one kernel epoch schedules one reconciliation;
4. duplicate startup messages remain idempotent;
5. disabled startup remains inactive and credential-free;
6. healthy owned Core is adopted without a credential request or reconnect;
7. inactive credential-free Core uses one delayed initial attempt;
8. unhealthy owned Core is cleaned before bounded recovery;
9. ownership, auth and configuration failures are not retried;
10. explicit disable wins over a pending startup timer;
11. OAuth rotation remains serialized;
12. counters distinguish Account attempts from Core resume;
13. public variables and archive contracts remain unchanged;
14. REST remains authoritative;
15. MQTT remains receive-only.

Later live gates must remain separately authorized:

```text
A. publish exact implementation
B. update Symcon while MQTT is disabled
C. verify inactive credential-free startup
D. explicitly accept Core credential persistence
E. activate receive-only pilot
F. perform one supervised service restart
G. verify core-resumed adoption without forced reconnect
H. disable and clean on any ambiguity
```

## 16. Current Gate State

| Gate | Decision |
|---|---|
| supported kernel-start hook identified | PASS |
| restart root cause bounded | PASS |
| credential model corrected | PASS |
| recovery model selected | PASS |
| code implementation | NOT STARTED |
| publication | NOT AUTHORIZED |
| Symcon mutation | NOT AUTHORIZED |
| MQTT activation | NOT AUTHORIZED |
| credential persistence acceptance | OPEN |
| MQTT state authority | PROHIBITED |
| MQTT publish | PROHIBITED |
| REST state authority | RETAINED |

Current live state:

```text
MQTT feature:         disabled
WebSocket:            inactive
Core credentials:     cleared
OAuth/REST operation: retained
```

## 17. Recommended Next Step

Create:

```text
155-native-mqtt-kernel-start-reconciliation-implementation.md
```

That step should implement the selected observe-and-adopt model exclusively
inside the case-study distribution, extend deterministic lifecycle tests and
run the complete offline Navimow gate.

It must not publish, mutate Symcon or reactivate MQTT. A later publication plan
must include the explicit active-Core credential-persistence acceptance gate.
