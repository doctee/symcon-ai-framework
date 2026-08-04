# 165 Native MQTT Core Resume Ordering Correction Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Offline implementation and regression validation passed; not
published or installed
**Date:** 2026-07-28
**Scope:** Implement the ordering correction selected in step 164

## 1. Purpose

Step 164 confirmed a deterministic contract conflict:

```text
Core-resume contract:
  preserve -> classify -> adopt

previous ApplyChanges contract:
  disconnect -> clear credentials -> reconnect
```

This step implements a kernel-epoch precedence barrier, adds private causal
diagnostics and closes the missing startup-order regression coverage.

No publication, Symcon update, MQTT activation, service restart, REST request,
MQTT network operation or mower command is part of this step.

## 2. Changed Artifacts

```text
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
case-studies/navimow/fixtures/mqtt/
  bounded-diagnostics-shadow-active.json
case-studies/navimow/
  165-native-mqtt-core-resume-ordering-correction-implementation.md
```

The existing Symcon runtime harness was sufficient. It required no public
helper or framework extension.

## 3. Kernel-Epoch Precedence Barrier

`ApplyChanges()` now evaluates the current kernel epoch before clearing or
restarting the owned transport.

An enabled transport enters kernel precedence when:

- Account configuration and authentication are usable;
- the adopted topology and ownership proof are valid;
- the current kernel timestamp is positive;
- the persisted timestamp belongs to an earlier kernel; or
- reconciliation for the current epoch is already pending.

The resulting ordering is:

```text
new or pending kernel epoch
  -> preserve Core configuration
  -> preserve WebSocket activation and credentials
  -> wait without a timer for post-KR_READY IPS_KERNELSTARTED
  -> start or retain 15-second kernel reconciliation after that message
  -> return without generic startup scheduling

same reconciled kernel epoch
  -> retain credential-safe explicit ApplyChanges restart
  -> schedule normal 5-second initial attempt
```

Feature disable, invalid authentication and invalid ownership remain safety
boundaries. They do not gain permission to adopt an unverified Core.

## 4. Timer Idempotency

`ApplyChanges()` still stops the lifecycle timer before rebuilding its runtime
state. When it detects the new epoch first, it records
`kernel-start-awaiting-ready` and leaves the timer stopped.

`IPS_KERNELSTARTED` is the post-`KR_READY` boundary and starts the 15-second
grace period. When the message arrived first, a following `ApplyChanges()`
re-arms the pending reconciliation with its remaining bounded delay.

This closes both possible callback orders:

```text
ApplyChanges -> MessageSink
MessageSink -> ApplyChanges
```

Duplicate kernel messages do not mutate persistent state or extend the
original deadline.

## 5. Explicit Epoch Recording

The Account records the current kernel as reconciled when it intentionally
starts a connection in that kernel or performs an explicit same-kernel apply.

The record is written only when the current epoch is not already reconciled.
Normal reconnects therefore do not overwrite the original kernel observation
and reconciliation timestamps.

For missing or malformed legacy epoch metadata, the implementation retains the
existing credential-safe delayed startup. It does not infer ownership from
Core status alone.

## 6. Token Rotation Ordering

An automatic token refresh can occur while kernel reconciliation is pending.
The previous implementation could immediately disconnect the resumed Core and
replace the kernel timer with a rotation timer.

The corrected flow is:

```text
token refresh during pending kernel reconciliation
  -> store refreshed OAuth state
  -> mark private credentialRotationPending
  -> retain kernel timer
  -> classify and adopt or recover Core
  -> if adopted healthy, defer rotation to next 60-second observation
  -> perform the existing credential-safe rotation
```

For credential-free or unhealthy Core classifications, the pending marker is
cleared because the selected fallback connection already retrieves current
credentials.

Explicit disable and terminal authentication/configuration states still win.

## 7. Connection Trigger Provenance

Each Account-owned credential and connection attempt now records one bounded
private trigger:

```text
manual
initial
kernel-fallback
reconnect
rotation
```

The diagnostics expose:

```text
lastConnectionTrigger
lastConnectionTriggerAt
```

The attempt counter retains its previous meaning. Core-native resume does not
increment it.

## 8. Kernel Core Classification

Kernel reconciliation records one bounded classification and timestamp:

```text
healthy
credential-free
unhealthy-with-credentials
disabled
authentication-unavailable
configuration-invalid
ownership-invalid
```

The diagnostics expose:

```text
lastKernelCoreClassification
lastKernelCoreClassificationAt
```

Unknown or malformed values normalize to `unknown` and zero. No credential,
topic, endpoint, device identity or payload is projected.

## 9. Regression Coverage

### 9.1 Apply First

The test now executes:

```text
Create
restore active persistent state
set new kernel epoch
ApplyChanges
MessageSink
advance 15 seconds
ProcessMqttLifecycle
```

Verified:

- no Core property mutation;
- no credential request;
- active WebSocket preserved;
- timer stopped until `IPS_KERNELSTARTED`;
- one 15-second reconciliation;
- `core-resumed`;
- `healthy` Core classification;
- attempt counter unchanged;
- one Core-resume observation.

### 9.2 Message First

The inverse sequence executes:

```text
MessageSink
ApplyChanges
advance 15 seconds
ProcessMqttLifecycle
```

The pending kernel schedule survives the timer reset and the healthy Core is
adopted without a connection attempt.

### 9.3 Refresh During Reconciliation

The test performs an OAuth refresh after the kernel message but before
reconciliation.

Verified:

- no immediate Core mutation;
- kernel timer remains 15 seconds;
- no MQTT credential request before classification;
- Core is first adopted as `core-resumed`;
- rotation resumes at the next health observation;
- the existing 5-second credential-safe rotation schedule is retained.

### 9.4 Trigger Diagnostics

The tests verify:

- explicit connection reports `manual`;
- token rotation reports `rotation`;
- credential-free kernel recovery reports `kernel-fallback`;
- malformed trigger and classification values are redacted and normalized.

## 10. Preserved Contracts

The implementation does not change:

- REST as authority for public mower variables;
- receive-only MQTT behavior;
- MQTT publish prohibition;
- mower commands or command safety;
- public variable Idents, types, profiles or positions;
- Archive Control logging or variable identity;
- explicit adoption and ownership proof;
- bounded `60/300/900` reconnect policy;
- no-retry policy for authentication and configuration failures;
- prohibition on automatic Core creation, deletion or module reload.

## 11. Offline Validation

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
PHPStan 512 MB:                PASS
git diff --check:              PASS
```

The combined validation script completed all functional checks, distribution
validation and PHPCS. Its first PHPStan execution reached the local 128 MB PHP
memory limit without a source finding. The identical PHPStan file set was
repeated with `--memory-limit=512M` and completed with no errors.

## 12. Source Hashes

```text
NavimowAccount/module.php:
3ec8c72bdbe68be434b3990e094d8dd3270b2d1ef694ecda04d3102051e9a63b

mqtt-transport-lifecycle.php:
fd49f7caee5b715fdaa8bd4112903d3f757169b8403292f705b67f964de63d96

bounded-diagnostics-shadow-active.json:
56be77e3b58b4970f7b9d9ca04dc73e2d6cc18e139a830d96fada47e21286dca

tests/harness/SymconRuntime.php:
4e09af33db6f58d415797e9f9e03b3796d264375eb3df6f945a647e3a74e375e
```

## 13. Architecture Decisions

### AD-NAV-563: Detect epoch precedence before cleanup

**Decision:** `ApplyChanges()` classifies kernel ordering before touching the
owned transport.

**Reason:** Resume evidence cannot be inspected after credentials and
activation have been destroyed.

### AD-NAV-564: Start the grace period only after `KR_READY`

**Decision:** Apply-first ordering waits timerless for `IPS_KERNELSTARTED`;
message-first ordering preserves the already pending timer.

**Reason:** An early `ApplyChanges()` must protect the Core without starting
the health-classification grace period before the platform is ready.

### AD-NAV-565: Defer rotation until after classification

**Decision:** Token refresh records a pending rotation while kernel
reconciliation owns the lifecycle.

**Reason:** Current OAuth state must be retained without allowing rotation to
erase Core-resume evidence.

### AD-NAV-566: Record causal codes, not private context

**Decision:** Connection triggers and Core classifications use strict
allowlists and bounded timestamps.

**Reason:** Future live failures become attributable without exposing
credentials or increasing network activity.

### AD-NAV-567: Preserve credential-safe explicit apply behavior

**Decision:** A same-epoch explicit `ApplyChanges()` continues to clean and
restart the transport after five seconds.

**Reason:** The correction is limited to restart ordering and must not weaken
configuration-change safety.

### AD-NAV-568: Keep corrected MQTT offline

**Decision:** Passing offline tests does not authorize publication,
installation, activation or restart.

**Reason:** The change modifies active credential lifecycle and requires the
normal separate publication and supervised live gates.

## 14. Gate Decision

| Gate | Decision |
|---|---|
| kernel precedence implementation | PASS |
| apply-first ordering | PASS |
| message-first ordering | PASS |
| token-refresh ordering | PASS |
| causal diagnostics | PASS |
| public variable contract unchanged | PASS |
| offline validation | PASS |
| publication | NOT STARTED |
| Symcon installation | NOT STARTED |
| MQTT activation | NO |
| service restart | NOT AUTHORIZED |
| REST state authority | RETAINED |

## 15. Recommended Next Step

Create:

```text
166-native-mqtt-core-resume-ordering-correction-publication-and-live-test-plan.md
```

That step should define:

1. standalone-module publication and source-hash verification;
2. Symcon update while MQTT remains disabled;
3. inactive configuration and diagnostic validation;
4. one bounded temporary activation;
5. an exact active pre-restart baseline;
6. one separately authorized supervised restart;
7. pass criteria requiring `core-resumed`, `healthy`,
   `connectionAttempts +0` and `coreResumeObservations +1`;
8. mandatory disable-and-clean cleanup for pass, failure or ambiguity.

No publication or live mutation is authorized by this implementation report.
