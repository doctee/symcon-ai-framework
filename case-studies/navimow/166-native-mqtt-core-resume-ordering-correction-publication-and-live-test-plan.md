# 166 Native MQTT Core Resume Ordering Correction Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Candidate frozen; publication and all live gates closed
**Date:** 2026-07-28
**Scope:** Plan publication, disabled update, temporary activation, one corrected
Core-resume restart and mandatory cleanup

## 1. Purpose

Step 163 failed the active Core-resume gate safely and returned the live
installation to a disabled, credential-free state.

Step 164 identified the conflicting startup contracts. Step 165 now provides:

- kernel-epoch precedence before `ApplyChanges()` cleanup;
- a timerless apply-first barrier until post-`KR_READY`
  `IPS_KERNELSTARTED`;
- preservation of a message-first reconciliation timer;
- deferred token rotation while reconciliation owns the lifecycle;
- private connection-trigger and Core-classification diagnostics;
- real-order and inverse-order offline regression coverage.

This step freezes the corrected candidate and defines separately authorized
execution gates. It performs no publication, Symcon mutation, MQTT activation,
service restart, network request or mower command.

## 2. Fixed Architecture Boundary

Every execution gate must preserve:

- REST as the only authority for public mower variables;
- MQTT as receive-only private acceleration evidence;
- no MQTT publish path;
- no mower command from MQTT lifecycle code;
- retained Account, Configurator, Device and Receiver instances;
- retained native MQTT and WebSocket Core instances;
- explicit topology and ownership proof;
- no automatic Core creation, deletion, reparenting or replacement;
- all 14 Device variable identities and metadata;
- all five Archive Control logging contracts;
- queryable archive history;
- MQTT default-disabled;
- `MC_ReloadModule()` prohibited;
- no tag or release;
- no private installation data in public artifacts.

MQTT failure must not interrupt REST polling, authentication or supported
commands.

## 3. Frozen Candidate

Canonical productive candidate:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Frozen hashes:

```text
NavimowAccount/module.php:
3ec8c72bdbe68be434b3990e094d8dd3270b2d1ef694ecda04d3102051e9a63b

tests/mqtt-transport-lifecycle.php:
fd49f7caee5b715fdaa8bd4112903d3f757169b8403292f705b67f964de63d96

fixtures/mqtt/bounded-diagnostics-shadow-active.json:
56be77e3b58b4970f7b9d9ca04dc73e2d6cc18e139a830d96fada47e21286dca

tests/harness/SymconRuntime.php:
4e09af33db6f58d415797e9f9e03b3796d264375eb3df6f945a647e3a74e375e
```

Tests, fixtures, harnesses and SAEF reports remain in SAEF. Only productive
module files belong in the standalone repository.

## 4. Standalone Baseline

Read-only local inspection established:

```text
repository:  private/navimow-publish-20260708
branch:      main
HEAD:        aed0b4348c6e104f6c2f455e71b861d8620a3c95
origin/main: aed0b4348c6e104f6c2f455e71b861d8620a3c95
worktree:    clean
subject:     fix(mqtt): reconcile native transport after kernel start
```

Published Account hash:

```text
544a594569c63aaf942e455fed6fdecc163d404710cb338876e91362ed06e440
```

Current candidate delta:

```text
modified productive files: 1
added productive files:    0
deleted productive files:  0
insertions:                 338
deletions:                  16
```

Only `NavimowAccount/module.php` differs. Gate A must fetch and revalidate the
remote baseline before mutation.

## 5. Current Live Baseline

The last verified live state from step 163 is:

```text
installed module:             main@aed0b434
MQTT feature:                 disabled
lifecycle:                    Disabled
MQTT/WebSocket:               inactive
WebSocket Active:             false
Authorization headers:        empty
MQTT username/password:       empty
Account authentication:       connected
REST state authority:         retained
Device variables:             14/14 retained
Archive logging contracts:    5/5 retained
```

Gate B must establish this baseline again immediately before update. Historical
evidence is not a substitute for current read-only verification.

## 6. Gate A: Publication

Required explicit authorization:

```text
Veröffentlichung der MQTT-Core-Resume-Ordering-Korrektur auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone repository;
2. require clean local `main` equal to fetched `origin/main`;
3. recheck all frozen hashes;
4. run the complete Navimow offline validation;
5. copy exactly `NavimowAccount/module.php`;
6. prove one modified and no added or deleted productive file;
7. run standalone syntax, JSON, metadata and privacy checks;
8. run SAEF distribution validation, PHPCS and PHPStan;
9. run or attempt the official Symcon Module Validator;
10. inspect the complete staged diff;
11. create one commit and push one fast-forward `main`;
12. fetch and prove remote commit and Account blob equality;
13. close private machine-readable and sanitized public evidence.

Suggested commit:

```text
fix(mqtt): preserve core resume across startup ordering
```

Gate A permits no Symcon update, MQTT activation, service restart, tag or
release.

### Module Validator classification

- an actual module validation error blocks publication;
- browser, cookie, authentication or transport failure is `INCONCLUSIVE`;
- `INCONCLUSIVE` is neither a pass nor a module defect;
- local exact JSON, structure and metadata checks remain mandatory.

### Gate-A stop conditions

Stop before commit when:

- fetched remote `main` differs from the expected clean baseline;
- the standalone worktree is dirty;
- any productive file except the Account module differs;
- a frozen hash changed;
- any offline, syntax, PHPCS, PHPStan or structure check fails;
- the official validator reports an actual module error;
- an MQTT publish or mower-command path appears;
- private data is detected.

## 7. Gate B: Disabled Symcon Update

Required separately after verified publication:

```text
Symcon-Update auf die MQTT-Core-Resume-Ordering-Korrektur mit deaktiviertem MQTT freigegeben.
```

Before update, execute the established bounded projection twice and require
stable evidence for:

- installed branch, commit and clean module state;
- productive instance identities and parents;
- retained Receiver/MQTT/WebSocket topology;
- MQTT disabled and credential-free;
- Account authentication connected;
- REST and command compatibility;
- 14 Device variable contracts;
- five Archive Control logging contracts;
- queryable archive history.

Gate B permits exactly:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
```

Post-update verification must prove:

- installed commit equals the published Gate-A commit;
- module and wrappers are available;
- MQTT remains disabled;
- Core credentials remain empty;
- no instance, variable or archive identity changed;
- bounded diagnostics expose the new fields with normalized values;
- REST remains operational.

An ambiguous update permits no second update. Read-only evidence must classify
the installed state.

## 8. Gate C: Inactive Staging

Required separately:

```text
Inaktives Staging der MQTT-Core-Resume-Ordering-Korrektur freigegeben.
```

Gate C performs no mutation when the retained topology is valid.

It must prove:

- exact Receiver, MQTT and WebSocket module types;
- symmetric Account/Receiver pairing;
- retained parent chain;
- four exact device-scoped `Topic`/`QoS` subscriptions;
- no wildcard or duplicate subscription;
- ownership and transport hashes valid;
- MQTT feature disabled;
- WebSocket inactive;
- Authorization headers empty;
- MQTT username and password empty;
- lifecycle timer stopped;
- no credential or broker request.

No Core object may be created, deleted, reparented or reconfigured.

## 9. Gate D: Renewed Persistence Acceptance

The acceptance from step 161 was limited to the completed test from steps 162
and 163. It is consumed and does not authorize another active restart.

After Gates A through C pass, require this new explicit acceptance:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.
```

This permits neither activation nor restart by itself. It only accepts the
bounded storage behavior for the later separately authorized gates.

## 10. Gate E: Temporary Activation

Required separately after Gate D:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den korrigierten Core-Resume-Restarttest freigegeben.
```

### Preconditions

Require:

- Gates A through D passed;
- current kernel epoch already reconciled;
- MQTT disabled and credential-free;
- ownership and topology valid;
- Account authentication connected;
- access token usable for at least 900 more seconds;
- no token refresh due inside the bounded restart window;
- complete variable/archive compatibility.

If token validity is insufficient, stop. Authentication refresh requires a
separate decision before activation.

### Allowed activation

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
```

Expected sequence:

```text
same reconciled kernel epoch
  -> credential-safe ApplyChanges
  -> initial attempt after 5 seconds
  -> healthy Core
  -> normal 60-second observation
```

Activation passes only with:

- one Account connection attempt;
- one connection success after health observation;
- trigger `initial`;
- Core status `102/102`;
- WebSocket active;
- valid ownership;
- no connection failure;
- no duplicate topology;
- no private diagnostic value;
- unchanged REST, variable and archive contracts.

Natural mower ingress is optional. Healthy Core without a fresh mower message
is `transport-ready/data-pending`, not failure.

## 11. Active Pre-Restart Baseline

Immediately before restart, capture one private machine-readable baseline and
a sanitized projection containing:

```text
kernel start time
kernel reconciliation fields
lifecycle state and transition reason
last kernel Core classification and timestamp
connectionAttempts
connectionSuccesses
connectionFailures
coreResumeObservations
lastConnectionTrigger
lastConnectionTriggerAt
lastConnectionAttemptAt
received/accepted/rejected counters
Receiver call/forward/reject counters
MQTT/WebSocket status
WebSocket Active
credential-presence booleans
ownership/configuration hashes
token-validity threshold boolean
variable/archive compatibility hashes
```

Credential values must not be returned. Private configuration may be compared
by hash inside the bounded script.

Required baseline:

```text
lifecycle:                 ShadowActive
Core:                      102/102
WebSocket Active:          true
last connection trigger:  initial
token validity >= 900 s:  true
cleanup:                   armed
```

No restart authorization is requested until this baseline passes.

## 12. Gate F: Corrected Active Restart

Required separately:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur korrigierten Core-Resume-Prüfung ist freigegeben.
```

The user performs exactly one external IP-Symcon service restart. No restart
is initiated from Symcon PHP.

No pre-restart cleanup occurs because this gate intentionally tests accepted
Core-native resume.

### Expected startup ordering

```text
1. Core loads retained active transport configuration.
2. Account ApplyChanges may run before IPS_KERNELSTARTED.
3. A changed epoch makes ApplyChanges preserve Core and wait timerless.
4. IPS_KERNELSTARTED arrives after KR_READY.
5. Account starts one 15-second reconciliation grace period.
6. Healthy owned Core is classified healthy and adopted.
7. No Account credential request or connection attempt occurs.
8. Normal 60-second health observation resumes.
```

### Bounded observation

After MCP becomes available:

1. confirm the kernel timestamp changed;
2. poll only bounded sanitized diagnostics;
3. stop polling immediately when `kernelStartReconciledAt` is present for the
   new epoch;
4. capture the first post-reconciliation projection before the next
   60-second health observation can replace transition reason
   `core-resumed`;
5. perform no explicit Connect or retry.

### Exact pass contract

The first reconciled projection must prove:

```text
kernelStartTime:                       exact new epoch
kernelStartObservedAt:                 present after ready
kernelStartReconciledAt:               observed + 15 seconds
lastTransitionReason:                  core-resumed
lastKernelCoreClassification:          healthy
classification timestamp:              reconciliation timestamp
coreResumeObservations delta:          +1
connectionAttempts delta:              0
connectionSuccesses delta:             0
connectionFailures delta:              0
lastConnectionTrigger:                 unchanged
lastConnectionTriggerAt:               unchanged
lastConnectionAttemptAt:               unchanged
MQTT/WebSocket status:                 102/102
WebSocket Active:                      true
ownership/configuration hashes:        unchanged
duplicate topology:                    none
REST and public contracts:             retained
```

Natural accepted ingress supports transport continuity but is not required for
the lifecycle pass. It never substitutes for the counter and classification
contract.

### Immediate stop conditions

Proceed directly to cleanup when:

- no new kernel epoch is reconciled;
- reconciliation occurs before the post-ready observation plus 15 seconds;
- `core-resumed` is absent from the first reconciled projection;
- Core classification is not `healthy`;
- `connectionAttempts`, successes or failures increase;
- connection trigger or its timestamps change;
- ownership/configuration hashes change unexpectedly;
- Core becomes inactive or invalid;
- duplicate Core objects appear;
- diagnostics expose private data;
- REST, variables, archives, authentication or commands regress;
- any MQTT publish or mower command occurs.

No second restart, explicit Connect or retry experiment is permitted.

## 13. Gate G: Mandatory Cleanup

Gate G is included in Gate-E and Gate-F authorization and executes after pass,
failure or ambiguity.

Required exactly:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0 unless the normal cleanup fails
```

Verify:

```text
MQTT feature:                disabled
lifecycle:                   Disabled
lifecycle timer:             stopped
MQTT/WebSocket:              inactive
WebSocket Active:            false
Authorization headers:       empty
MQTT username/password:      empty
owned topology:              retained
```

Then repeat the complete compatibility projection:

- installed module clean and valid;
- productive instance identities unchanged;
- 14/14 Device variable contracts unchanged;
- 5/5 Archive Control contracts unchanged;
- archive history queryable;
- OAuth and REST retained;
- command evidence unchanged;
- MQTT credential-free.

Direct emergency Core cleanup is permitted only if the normal disable and
Account `ApplyChanges()` fail. It must be separately recorded and may not
create, delete or reparent objects.

## 14. Evidence Closure

Each executed gate requires:

1. private machine-readable evidence;
2. a sanitized public SAEF report;
3. exact side-effect accounting;
4. source/installed commit and hash correlation;
5. separate MCP `transportError`, `executionError` and `truncated`
   evaluation;
6. updated synthetic fixture only when the bounded public contract changes.

Dated historical reports remain unchanged.

No credential, topic, endpoint, payload, Device ID, ObjectID, hostname,
private IP address or garden detail may enter public evidence.

## 15. Authorization Matrix

| Gate | Required authorization | Current state |
|---|---|---|
| A publication | exact Gate-A phrase | CLOSED |
| B disabled update | exact Gate-B phrase | CLOSED |
| C inactive staging | exact Gate-C phrase | CLOSED |
| D renewed persistence acceptance | exact acceptance text | NOT ACCEPTED |
| E temporary activation | exact Gate-E phrase | CLOSED |
| F active restart | exact Gate-F phrase | CLOSED |
| G cleanup | included with E/F | ARMED ONLY AFTER E |

One authorization never opens a later gate.

## 16. Side-Effect Budgets

| Operation | Maximum |
|---|---:|
| publication commits | 1 |
| publication pushes | 1 |
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| MQTT feature enables | 1 |
| activation `ApplyChanges()` | 1 |
| active service restarts | 1 |
| explicit MQTT Connect | 0 |
| cleanup disables | 1 |
| cleanup `ApplyChanges()` | 1 |
| direct emergency Core cleanup | 1 only on normal cleanup failure |
| MQTT publish operations | 0 |
| mower commands | 0 |
| Core create/delete/reparent | 0 |
| Archive Control mutations | 0 |

Read-only probes remain bounded in output, duration and polling count.

## 17. Architecture Decisions

### AD-NAV-569: Freeze the post-ready barrier candidate

**Decision:** Publish only the timerless apply-first barrier validated in step
165.

**Reason:** Starting the 15-second grace period before `KR_READY` would
reintroduce platform-order dependence.

### AD-NAV-570: Do not repeat the disabled restart

**Decision:** Reuse the successful disabled kernel-hook evidence from step 160
and verify disabled behavior offline plus through Gates B and C.

**Reason:** The corrected defect exists only in enabled Core preservation.
Another credential-free service restart adds side effects without testing the
changed branch.

### AD-NAV-571: Renew consumed persistence acceptance

**Decision:** Require a new explicit acceptance for the second active restart.

**Reason:** The first acceptance was bounded to one completed test and cannot
be silently reused.

### AD-NAV-572: Exclude token rotation from the proof window

**Decision:** Require at least 900 seconds of remaining token validity before
activation.

**Reason:** Deferred rotation is covered offline, while the live gate should
measure only startup ordering and Core adoption.

### AD-NAV-573: Capture the first reconciled projection

**Decision:** Evaluate `core-resumed` immediately after reconciliation.

**Reason:** The normal later health observation legitimately changes the
transition reason to `healthy`.

### AD-NAV-574: Compare causal timestamps across restart

**Decision:** In addition to counters, require unchanged connection trigger
and attempt timestamps.

**Reason:** This detects hidden Account reconnection even if another counter
projection is incomplete.

### AD-NAV-575: End every active outcome credential-free

**Decision:** Mandatory disable-and-clean cleanup follows pass, failure or
ambiguity.

**Reason:** The renewed acceptance covers one supervised experiment, not
persistent operation.

## 18. Gate Decision

| Contract | Decision |
|---|---|
| offline correction | PASS |
| frozen one-file candidate | PASS |
| standalone baseline | PASS |
| publication | NOT AUTHORIZED |
| Symcon update | NOT AUTHORIZED |
| renewed persistence acceptance | NOT GIVEN |
| MQTT activation | NOT AUTHORIZED |
| service restart | NOT AUTHORIZED |
| MQTT publish | PROHIBITED |
| mower commands | PROHIBITED |
| REST state authority | RETAINED |

## 19. Recommended Next Step

After explicit Gate-A authorization, execute publication only and create:

```text
167-native-mqtt-core-resume-ordering-correction-publication.md
```

Required authorization:

```text
Veröffentlichung der MQTT-Core-Resume-Ordering-Korrektur auf main freigegeben.
```

Step 167 must stop after remote source and hash verification. It must not
update Symcon, activate MQTT or restart the service.
