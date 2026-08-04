# 175 Native MQTT Transient Readiness Correction Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Candidate frozen; publication and all live gates closed
**Date:** 2026-07-29
**Scope:** Plan publication, disabled update, inactive verification, temporary
activation, one Core-resume restart and mandatory cleanup

## 1. Purpose

Step 172 proved that the published Core-resume implementation could lose
restart precedence when semantic native-Core configuration was transiently
unavailable before `KR_READY`.

Step 173 reconstructed the exact failing path. Step 174 then:

- reproduced the failure with a red regression;
- replaced semantic pre-ready validation with a durable epoch barrier;
- prohibited pre-ready Core reads and mutations while that barrier owns
  startup;
- deferred authentication and configuration failure handling until the
  post-ready reconciliation;
- passed transient, negative, idempotency and complete Navimow validation.

This step freezes that candidate and defines the separately authorized gates
needed to publish and verify it. It performs no publication, Symcon mutation,
MQTT activation, broker connection, service restart or mower command.

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

MQTT failure must not interrupt OAuth, REST polling or supported commands.

## 3. Frozen Candidate

Canonical productive candidate:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Frozen hashes:

```text
NavimowAccount/module.php:
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5

tests/mqtt-transport-lifecycle.php:
a29e0fce4f48d8cdba09c0c9ed6f53d6715890c190bcbca8806c065e04278a6a

tests/mqtt-fixtures.php:
45984794c018911bcbd554e2a6f39b0f2b6380cadf900a1be16629ee3919383d

fixtures/mqtt/core-resume-transient-core-readiness.json:
3fcafc1934c4ba05ed20f7433a4148bdae88b8d57aea942a9acaed076d82657a

tools/check-mqtt-shadow.sh:
a59790d59371d0a17355ef44d1f192175653035f60d4aa8baa72bc1a524a942f
```

Only productive module files belong in the standalone repository. Tests,
fixtures, tools and SAEF reports remain in SAEF.

## 4. Standalone Baseline

Read-only local inspection established:

```text
repository:  private/navimow-publish-20260708
branch:      main
HEAD:        71a90f697031da017264d2a33555b9b6693d8776
origin/main: 71a90f697031da017264d2a33555b9b6693d8776
worktree:    clean
subject:     fix(mqtt): preserve core resume across startup ordering
```

Published Account hash:

```text
3ec8c72bdbe68be434b3990e094d8dd3270b2d1ef694ecda04d3102051e9a63b
```

Candidate delta against that baseline:

```text
modified productive files: 1
NavimowAccount/module.php:  +58 / -16
added productive files:     0
deleted productive files:   0
```

Gate A must fetch and revalidate the remote baseline immediately before any
mutation. The local read-only observation does not replace that check.

## 5. Current Live Baseline

The last verified live state from step 172 is:

```text
installed module:             main@71a90f69
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

Gate B must establish this baseline again through bounded read-only evidence
before updating. Historical evidence alone is insufficient.

## 6. Gate A: Publication

Required explicit authorization:

```text
Veröffentlichung der MQTT-Transient-Readiness-Korrektur auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone repository;
2. require clean local `main` equal to fetched `origin/main`;
3. recheck every frozen hash;
4. run `sh case-studies/navimow/tools/check-mqtt-shadow.sh`;
5. run the complete pilot observation harness;
6. copy exactly `NavimowAccount/module.php`;
7. prove one modified and no added or deleted productive file;
8. run standalone PHP syntax, JSON, metadata and privacy checks;
9. run distribution validation, PHPCS and PHPStan;
10. run or attempt the official Symcon Module Validator;
11. inspect the complete staged diff;
12. create one commit and push one fast-forward `main`;
13. fetch and prove remote commit and Account blob equality;
14. close private machine-readable and sanitized public evidence.

Suggested commit:

```text
fix(mqtt): defer core validation until kernel readiness
```

Gate A permits no Symcon update, MQTT activation, service restart, tag or
release.

### Module Validator classification

- an actual module validation error blocks publication;
- browser, cookie, authentication or transport failure is `INCONCLUSIVE`;
- `INCONCLUSIVE` is neither a pass nor a module defect;
- exact local JSON, structure and metadata checks remain mandatory.

### Gate-A stop conditions

Stop before commit when:

- fetched remote `main` differs from the expected clean baseline;
- the standalone worktree is dirty;
- any productive file except the Account module differs;
- a frozen hash changed;
- an offline, syntax, PHPCS, PHPStan or structure check fails;
- the official validator reports an actual module error;
- an MQTT publish or mower-command path appears;
- private data is detected.

## 7. Gate B: Disabled Symcon Update

Required separately after verified publication:

```text
Symcon-Update auf die MQTT-Transient-Readiness-Korrektur mit deaktiviertem MQTT freigegeben.
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

- installed commit equals the Gate-A commit;
- module and wrappers are available;
- MQTT remains disabled;
- Core credentials remain empty;
- no instance, variable or archive identity changed;
- the lifecycle timer remains stopped;
- REST remains operational;
- no MQTT connection attempt occurred.

An ambiguous update permits no second update. Read-only evidence must classify
the installed state.

## 8. Gate C: Inactive Staging

Required separately:

```text
Inaktives Staging der MQTT-Transient-Readiness-Korrektur freigegeben.
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

The acceptance from step 170 was consumed by the completed activation and
restart sequence in steps 171 and 172. It does not authorize another active
restart.

After Gates A through C pass, require this new explicit acceptance:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.
```

This acceptance permits neither activation nor restart by itself. It only
accepts the bounded storage behavior for the later separately authorized
gates.

## 10. Gate E: Temporary Activation

Required separately after Gate D:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Transient-Readiness-Restarttest freigegeben.
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
kernel start time and reconciliation markers
lifecycle state and transition reason
last kernel Core classification and timestamp
connection attempts, successes and failures
Core-resume observations
last connection trigger and timestamps
Receiver receive/forward/reject counters
MQTT/WebSocket status
WebSocket Active
credential-presence Booleans
ownership/configuration hashes
token-validity threshold Boolean
variable/archive compatibility hashes
```

Credential values must not be returned. Private configuration may be compared
only by hash inside the bounded probe.

Required baseline:

```text
lifecycle:                 ShadowActive / healthy
Core:                      102/102
WebSocket Active:          true
last connection trigger:  initial
token validity >= 900 s:  true
cleanup:                   armed
```

Capture two equal projections separated by more than one lifecycle period.
No restart authorization is requested until both pass.

## 12. Gate F: Corrected Active Restart

Required separately:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur Transient-Readiness-Core-Resume-Prüfung ist freigegeben.
```

The user performs exactly one external IP-Symcon service restart. No restart
is initiated from Symcon PHP.

No pre-restart cleanup occurs because this gate intentionally tests accepted
Core-native credential resume.

### Expected startup ordering

```text
1. Core loads retained active transport configuration.
2. Account ApplyChanges may run before IPS_KERNELSTARTED.
3. Durable epoch state gives kernel reconciliation precedence.
4. Account performs no semantic Core read or Core mutation before ready.
5. Account waits timerless in kernel-start-awaiting-ready.
6. IPS_KERNELSTARTED arrives after KR_READY.
7. Account starts one 15-second reconciliation grace period.
8. Healthy owned Core is classified healthy and adopted.
9. No Account credential request or connection attempt occurs.
10. Normal 60-second health observation resumes.
```

### Bounded observation

After MCP becomes available:

1. confirm the kernel timestamp changed;
2. poll only bounded sanitized Account diagnostics;
3. capture the awaiting-ready or scheduled state when observable;
4. stop polling immediately when reconciliation is present for the new epoch;
5. capture the first reconciled projection before the later health observation
   can replace transition reason `core-resumed`;
6. perform no explicit Connect, retry or additional `ApplyChanges()`.

Absence of an observable intermediate projection is not by itself a failure.
The final causal timestamps and counters must still prove the exact barrier
contract.

### Exact pass contract

The first reconciled projection must prove:

```text
kernelStartTime:                       exact new epoch
kernelStartObservedAt:                 present for new epoch
kernelStartReconciledAt:               observed + at least 15 seconds
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

The diagnostic sequence must additionally exclude the step-172 signature:

```text
ConfigurationError / healthy
classification: none
observation-to-reconciliation gap: 0
credential-cleanup-skipped after boot
```

Natural accepted ingress supports transport continuity but is not required
for the lifecycle pass. It never substitutes for classification, timestamp
and counter evidence.

### Immediate stop conditions

Proceed directly to cleanup when:

- no new kernel epoch is reconciled;
- reconciliation occurs less than 15 seconds after observation;
- `core-resumed` is absent from the first reconciled projection;
- Core classification is not `healthy`;
- any connection attempt, success or failure counter increases;
- connection trigger or its timestamps change;
- `credential-cleanup-skipped` appears after boot;
- ownership/configuration hashes change unexpectedly;
- Core becomes inactive or invalid;
- duplicate Core objects appear;
- diagnostics expose private data;
- REST, variables, archives, authentication or commands regress;
- any MQTT publish or mower command occurs.

No second restart, explicit Connect, second update or retry experiment is
permitted.

## 13. Gate G: Mandatory Cleanup

Gate G is included in Gate-E and Gate-F authorization and executes after pass,
failure or ambiguity.

Required normal cleanup:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0 unless normal cleanup fails
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

Direct emergency Core cleanup is permitted only if normal disable and Account
`ApplyChanges()` fail. It must be separately recorded and may not create,
delete or reparent objects.

## 14. Evidence Closure

Each executed gate requires:

1. private machine-readable evidence;
2. a sanitized public SAEF report;
3. exact side-effect accounting;
4. source, installed commit and hash correlation;
5. separate MCP `transportError`, `executionError` and `truncated`
   evaluation;
6. fixture reconciliation only when the public executable contract changes.

The active Gate-E/F sequence must produce one closure artifact even if Gate F
fails or is ambiguous. Cleanup evidence is mandatory.

Dated historical reports remain unchanged. No credential, topic, endpoint,
payload, Device ID, ObjectID, hostname, private IP address or garden detail
may enter public evidence.

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

### AD-NAV-607: Publish only the durable-barrier delta

**Decision:** Transfer only the Account module delta verified in step 174.

**Reason:** The failure and correction are isolated to startup precedence;
metadata, Device behavior and public contracts require no change.

### AD-NAV-608: Keep publication and installation independent

**Decision:** Require separate authorization and evidence for Git publication
and the disabled Symcon update.

**Reason:** A valid published candidate does not authorize mutation of the
live installation.

### AD-NAV-609: Do not repeat a disabled restart

**Decision:** Verify disabled behavior through Gates B and C without another
service restart.

**Reason:** The changed branch is only exercised when persisted active Core
state must survive early Account startup.

### AD-NAV-610: Renew consumed credential-persistence acceptance

**Decision:** Require new contextual acceptance before temporary activation.

**Reason:** The prior acceptance was bounded to one completed restart test and
cannot be reused implicitly.

### AD-NAV-611: Treat the first reconciled projection as decisive

**Decision:** Capture and evaluate reconciliation before the normal health
timer can replace `core-resumed`.

**Reason:** Later health does not repair an invalid or bypassed startup
barrier.

### AD-NAV-612: Prove absence of the prior failure signature

**Decision:** Check classification, causal delay, error ring and connection
counters together.

**Reason:** Healthy Core status alone already passed in step 172 and did not
prove successful Account adoption.

### AD-NAV-613: End every active outcome credential-free

**Decision:** Mandatory normal cleanup follows pass, failure or ambiguity.

**Reason:** The acceptance covers one supervised experiment, not persistent
MQTT operation.

## 18. Gate Decision

| Contract | Decision |
|---|---|
| offline correction | PASS |
| frozen one-file candidate | PASS |
| standalone local baseline | PASS |
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
176-native-mqtt-transient-readiness-correction-publication.md
```

Required authorization:

```text
Veröffentlichung der MQTT-Transient-Readiness-Korrektur auf main freigegeben.
```

Step 176 must stop after remote source and hash verification. It must not
update Symcon, activate MQTT or restart the service.
