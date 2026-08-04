# 156 Native MQTT Kernel Start Reconciliation Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication and live verification planned; all execution gates
closed
**Date:** 2026-07-28
**Scope:** Freeze and separately authorize publication, disabled installation,
kernel-hook verification, bounded active restart verification and cleanup

## 1. Purpose

Step 153 failed the original restart contract because native Core resumed
persisted active configuration without Account reconstruction.

Step 154 selected post-ready observe-and-adopt reconciliation. Step 155
implemented and verified:

- registration for `IPS_KERNELSTARTED`;
- mutation-free `MessageSink()` scheduling;
- 15-second kernel reconciliation;
- adoption of a healthy owned Core resume without reconnect;
- credential-free delayed reconstruction;
- cleanup and bounded recovery for an unhealthy owned transport;
- terminal authentication, ownership and configuration handling;
- separate `coreResumeObservations` diagnostics.

This step freezes the exact candidate and defines independently authorized
publication and live gates.

It performs no publication, Symcon mutation, service restart, MQTT activation,
REST request, broker connection or mower command.

## 2. Fixed Architecture Boundary

Every later gate must preserve:

- REST as the only authority for public mower variables;
- MQTT as a private receive-only acceleration hint;
- targeted and coalesced REST reconciliation after MQTT hints;
- no MQTT publish path;
- no Start, Pause, Resume, Dock or Stop command from MQTT recovery;
- retained Account, Configurator, Device and Receiver instances;
- retained native WebSocket and MQTT Core instances;
- explicit ownership and topology validation;
- no automatic Core create, delete or reparent operation;
- all 14 Device variable identities and metadata;
- all five Archive Control logging contracts;
- MQTT default-disabled;
- `MC_ReloadModule()` prohibited;
- no tag or release;
- no credential or installation data in public artifacts.

MQTT failure must not interrupt REST polling or command operation.

## 3. Credential Persistence Boundary

The retained native transport stores these values in owned IP-Symcon Core
instance configuration while active:

```text
WebSocket Authorization header
MQTT username
MQTT password
WebSocket Active state
```

Core can reuse that stored active configuration during service restart before
the Account receives and reconciles `IPS_KERNELSTARTED`.

The new implementation observes and adopts healthy continuity after
`KR_READY`. It cannot guarantee cleanup before Core-native reconnect.

Therefore:

- publication and disabled testing do not require acceptance of active
  credential persistence;
- active pilot testing requires an explicit bounded acceptance;
- passing the bounded test does not authorize indefinite active retention;
- final cleanup is mandatory unless a later step separately authorizes a
  persistent pilot.

## 4. Frozen Publication Candidate

Canonical productive candidate:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Candidate hash:

```text
544a594569c63aaf942e455fed6fdecc163d404710cb338876e91362ed06e440
```

Supporting SAEF-only evidence:

```text
tests/mqtt-transport-lifecycle.php:
853372c66cf2b07ac2e326d8e959314344f5eede739887478089d2a2ac5347ca

fixtures/mqtt/bounded-diagnostics-shadow-active.json:
e052e1174e872090bb94f77aa1c2a055a4614abe87b3857443157cc34383a0c6

tests/harness/SymconRuntime.php:
4e09af33db6f58d415797e9f9e03b3796d264375eb3df6f945a647e3a74e375e
```

Tests, fixtures, harnesses and SAEF reports are not copied to the standalone
module repository.

## 5. Standalone Baseline

Read-only planning inspection:

```text
repository:  private/navimow-publish-20260708
branch:      main
HEAD:        7c1747ccd23a8aff9ddc8170d04f5030be615064
origin/main: 7c1747ccd23a8aff9ddc8170d04f5030be615064
worktree:    clean
subject:     feat(mqtt): harden passive pilot recovery
```

Published Account hash:

```text
4127b75e2dd451141a771f5244f185e43a7b4d3a158e6ddc2f59b630e562e48b
```

Current productive delta:

```text
modified files: 1
added files:    0
deleted files:  0
insertions:     256
deletions:      2
```

Only:

```text
NavimowAccount/module.php
```

differs between the locally known standalone baseline and the canonical
distribution. Gate A must fetch and revalidate the actual remote state before
any mutation.

## 6. Gate A: Productive Publication

Required explicit authorization:

```text
Veröffentlichung der Kernelstart-Reconciliation auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone repository;
2. require clean local `main` equal to current `origin/main`;
3. require the frozen candidate and supporting evidence hashes;
4. run the complete Navimow offline gate;
5. copy exactly `NavimowAccount/module.php`;
6. prove one modified and no added or deleted productive file;
7. run standalone PHP syntax, JSON, privacy and metadata checks;
8. run the SAEF distribution validator, PHPCS and PHPStan;
9. run or attempt the official Symcon Module Validator;
10. inspect the complete staged diff;
11. commit and push one fast-forward `main` commit;
12. fetch and prove remote commit and blob equality;
13. close private machine-readable and sanitized public evidence.

Suggested commit:

```text
fix(mqtt): reconcile native transport after kernel start
```

Gate A permits no:

- Symcon update;
- MQTT activation;
- service restart;
- tag or release.

### Module Validator classification

No metadata file changes:

```text
library.json
4 x module.json
4 x form.json
4 x locale.json
```

The official Symcon Module Validator remains part of the publication record.

Classification rules:

- an actual validation error blocks publication;
- a browser, cookie, authentication or transport failure is
  `INCONCLUSIVE`, not a successful validation and not a module defect;
- unchanged metadata still requires local exact JSON and structure evidence;
- an inconclusive web run must be documented and must not be relabeled
  `PASS`.

### Gate-A stop conditions

Stop before commit when:

- remote `main` differs from the fetched local baseline;
- the standalone worktree is dirty;
- any productive file except the Account module differs;
- the candidate hash changed;
- offline regression, syntax, PHPCS, PHPStan or structure validation fails;
- the official validator reports an actual module error;
- a publish or mower-command path appears in MQTT code;
- private data is detected.

## 7. Gate B: Disabled Symcon Update

Required separately after verified publication:

```text
Symcon-Update auf die Kernelstart-Reconciliation mit deaktiviertem MQTT freigegeben.
```

Before update, execute the established bounded projection twice and require
stable evidence for:

- installed branch, commit and clean module state;
- Account, Configurator, Device and Receiver identities;
- retained WebSocket/MQTT/Receiver topology;
- MQTT feature disabled;
- WebSocket inactive;
- empty Authorization headers;
- empty MQTT username and password;
- Account authentication connected;
- REST-read and command compatibility;
- all 14 Device variable contracts;
- all five Archive Control logging contracts;
- queryable archive history.

Gate B permits:

1. exactly one `MC_UpdateModule()` operation;
2. no `MC_ReloadModule()`;
3. repeated read-only post-update projections;
4. module and wrapper availability checks;
5. bounded diagnostics inspection;
6. exact installed-commit prefix comparison without assuming a fixed short
   hash length.

The update must leave MQTT disabled and credential-free.

### Gate-B stop conditions

Stop before any restart when:

- Module Control update is ambiguous or unsuccessful;
- repository status is dirty or invalid;
- any productive instance identity or parent changes;
- any variable identity or metadata changes;
- any Archive Control setting changes;
- authentication, REST or command compatibility regresses;
- MQTT becomes active;
- any Core credential becomes present.

No second update is permitted after an ambiguous first result. Read-only
evidence must classify the installed state.

## 8. Gate C: Inactive Topology Staging

Required separately:

```text
Inaktives Staging der Kernelstart-Reconciliation freigegeben.
```

Gate C is read-only when the retained topology remains valid.

It must prove:

- exact Receiver, MQTT and WebSocket module types;
- symmetric Account/Receiver pairing;
- retained parent chain;
- four exact device-scoped `Topic`/`QoS` subscriptions;
- no wildcard or duplicate;
- WebSocket inactive;
- headers and MQTT credentials empty;
- feature disabled;
- no credential or broker request.

No Core instance may be created, deleted, reparented or reconfigured.

## 9. Gate D: Disabled Kernel-Hook Restart

Required separately:

```text
Ein beaufsichtigter Symcon-Neustart mit deaktiviertem MQTT zur Kernel-Hook-Prüfung ist freigegeben.
```

This is the first real restart and remains credential-free.

### Pre-restart evidence

Capture:

```text
kernel start time
MQTT feature
Core statuses
WebSocket Active
credential-presence booleans
kernel lifecycle diagnostics
connectionAttempts
coreResumeObservations
variable/archive contracts
```

Required precondition:

```text
MQTT disabled
WebSocket inactive
headers empty
MQTT username/password empty
```

The user performs exactly one external IP-Symcon service restart. No restart
is initiated from Symcon PHP and no mower action is required.

### Expected post-restart result

After `KR_READY` and the 15-second grace period:

```text
kernel start changed:             yes
kernelStartTime:                  new kernel start
kernelStartObservedAt:            present
kernelStartReconciledAt:          present
reconciliation delay:            at least 15 seconds
lifecycle:                        Disabled
connectionAttempts delta:         0
coreResumeObservations delta:     0
WebSocket Active:                 false
credentials present:             false
REST and public contracts:        retained
```

Duplicate reconciliation evidence for the same kernel epoch is prohibited.

### Gate-D stop conditions

Stop before active acceptance when:

- the new kernel epoch is not observed or reconciled;
- timestamps are impossible or out of order;
- any credential appears;
- MQTT or WebSocket becomes active;
- any Account connection attempt occurs;
- variables, archives, authentication, REST or command behavior regress.

Gate D failure permits no active MQTT test.

## 10. Gate E: Bounded Credential-Persistence Acceptance

Required only after Gate D passes.

Exact explicit acceptance:

```text
Ich akzeptiere für den einmaligen beaufsichtigten Neustarttest, dass
Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT deaktiviert und bereinigt.
```

This acceptance is:

- limited to one activation and one active service restart;
- receive-only;
- revocable before activation;
- not permission for indefinite active operation;
- not permission to expose credentials;
- not permission for MQTT publish or mower commands.

Without the exact acceptance, stop after Gate D with MQTT disabled.

## 11. Gate F: Temporary Receive-Only Activation

Required separately after Gate E:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Restarttest freigegeben.
```

Gate F permits:

- one `EnableMqttShadow=true` mutation;
- one Account `ApplyChanges()`;
- the existing five-second delayed connection;
- receive-only natural broker ingress;
- bounded diagnostics and REST reconciliation;
- immediate Disable fallback.

It permits no explicit second Connect after ambiguity.

Activation passes only when:

- ownership and topology validate;
- exactly one Account connection attempt starts;
- MQTT and WebSocket become healthy;
- Receiver forwarding is healthy when natural traffic exists;
- REST comparison remains authoritative;
- no private value appears in diagnostics;
- variable and archive contracts remain unchanged.

Capture the exact pre-restart baseline:

```text
kernel start time
connectionAttempts
connectionSuccesses
coreResumeObservations
received/accepted/rejected
Receiver calls/forwarded
lifecycle and transition reason
Core statuses
WebSocket Active
credential-presence booleans
```

Healthy Core without a fresh mower message remains
`transport-ready/data-pending`, not failure.

## 12. Gate G: Active Core-Resume Restart

Required separately after stable Gate F:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur Core-Resume-Prüfung ist freigegeben.
```

The user performs exactly one external service restart while the receive-only
transport is active.

No pre-restart cleanup occurs because this gate intentionally models the
unplanned-restart behavior accepted in Gate E.

### Expected sequence

```text
1. native Core loads persisted active configuration;
2. native transport may reconnect before Account reconciliation;
3. IPS_KERNELSTARTED is delivered after KR_READY;
4. Account schedules one 15-second kernel reconciliation;
5. owned healthy Core is classified core-resumed;
6. no forced disconnect or credential request occurs;
7. normal 60-second health observation resumes.
```

### Required evidence

```text
kernel start changed:                 yes
kernelStartTime:                      exact new epoch
kernelStartObservedAt:                present
kernelStartReconciledAt:              present
lastTransitionReason:                 core-resumed
coreResumeObservations delta:         exactly 1
connectionAttempts delta:             0
connectionSuccesses delta:            0
credential request evidence:          none
Core property mutation evidence:      none
duplicate topology:                   none
MQTT/WebSocket status:                102/102
WebSocket Active:                     true
Receiver ingress continuity:          observable when traffic exists
REST authority and compatibility:     retained
```

An unchanged `connectionSuccesses` value is expected because the resumed Core
connection is not a new Account-owned attempt.

### Gate-G stop conditions

Immediately proceed to cleanup when:

- no new kernel epoch is reconciled;
- `core-resumed` is absent after the bounded grace window;
- `connectionAttempts` increases;
- `coreResumeObservations` does not increase exactly once;
- a forced Account reconnect occurs;
- ownership or topology becomes invalid;
- duplicate Core objects appear;
- diagnostics expose a private value;
- REST, variables, archives, authentication or commands regress;
- MQTT publish or any mower command occurs.

No second restart, explicit Connect or retry experiment is permitted.

## 13. Gate H: Mandatory Cleanup and Evidence Closure

Gate H is included in the authorization of Gates F and G and executes after
pass, failure or ambiguity.

Required cleanup:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
WebSocket Active:          false
Authorization headers:     empty
MQTT username/password:    empty
MqttLifecycle timer:       stopped
lifecycle:                 Disabled
```

Then repeat the full compatibility projection and require:

- installed module remains clean and valid;
- productive instances unchanged;
- 14/14 variable contracts unchanged;
- 5/5 Archive Control contracts unchanged;
- history queryable;
- OAuth and REST retained;
- command evidence unchanged;
- MQTT credential-free.

Evidence closure must contain:

1. private machine-readable pre/post snapshots;
2. sanitized public SAEF report;
3. updated synthetic regression fixture only if the observed public contract
   differs from the frozen fixture;
4. exact side-effect accounting;
5. separate MCP `transportError`, `executionError` and `truncated`
   evaluation.

No credential, topic, endpoint, payload, device identity, ObjectID or garden
detail may enter public evidence.

## 14. Authorization Matrix

| Gate | Required authorization | Current state |
|---|---|---|
| A publication | exact Gate-A phrase | CLOSED |
| B disabled Symcon update | exact Gate-B phrase | CLOSED |
| C inactive staging | exact Gate-C phrase | CLOSED |
| D disabled restart | exact Gate-D phrase | CLOSED |
| E persistence acceptance | exact acceptance text | OPEN / NOT ACCEPTED |
| F temporary activation | exact Gate-F phrase | CLOSED |
| G active restart | exact Gate-G phrase | CLOSED |
| H cleanup | included with F/G | PREAUTHORIZED ONLY WITH F/G |

One authorization never opens a later gate.

## 15. Side-Effect Budgets

| Operation | Maximum |
|---|---:|
| publication commits | 1 |
| publication pushes | 1 |
| Module Control updates | 1 |
| `MC_ReloadModule()` | 0 |
| disabled service restarts | 1 |
| MQTT feature enables | 1 |
| Account activation `ApplyChanges()` | 1 |
| active service restarts | 1 |
| explicit MQTT Connect calls | 0 |
| cleanup disables | 1 |
| cleanup `ApplyChanges()` | 1 |
| MQTT publish operations | 0 |
| mower commands | 0 |
| Core create/delete/reparent | 0 |
| Archive Control mutations | 0 |

Read-only probes must remain bounded in output and duration.

## 16. Architecture Decisions

### AD-NAV-535: Verify the hook while disabled first

**Decision:** A credential-free real restart must prove message registration
and epoch reconciliation before active testing.

**Reason:** Failure of the platform hook can be detected without storing or
reusing broker credentials.

### AD-NAV-536: Require explicit bounded persistence acceptance

**Decision:** Source publication and disabled installation do not imply
acceptance of active Core credential storage.

**Reason:** This is a security and operational decision, not a technical side
effect that may be silently inherited.

### AD-NAV-537: Distinguish Core resume from Account connection

**Decision:** Active restart passes only with
`coreResumeObservations + 1` and `connectionAttempts + 0`.

**Reason:** These counters prove the selected ownership model and prevent the
old forced-reconstruction interpretation from returning unnoticed.

### AD-NAV-538: End the bounded test credential-free

**Decision:** Mandatory cleanup follows both pass and failure.

**Reason:** The acceptance covers one supervised test, not persistent
operation.

### AD-NAV-539: Preserve historical evidence

**Decision:** Step 153 remains a valid failed historical report.

**Reason:** The new implementation changes future behavior; it does not
reinterpret the earlier live result.

### AD-NAV-540: Keep publication independent from installation

**Decision:** Gate A publishes source only.

**Reason:** Remote integrity, installed compatibility and live transport
behavior are different evidence classes.

## 17. Current Gate State

| Contract | Decision |
|---|---|
| offline implementation | PASS |
| frozen one-file candidate | PASS |
| local standalone baseline | PASS |
| publication | NOT AUTHORIZED |
| Symcon mutation | NOT AUTHORIZED |
| service restart | NOT AUTHORIZED |
| credential persistence | NOT ACCEPTED |
| MQTT activation | NOT AUTHORIZED |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |
| REST state authority | RETAINED |

Current live state inherited from step 153:

```text
MQTT feature:         disabled
WebSocket:            inactive
Core credentials:     cleared
OAuth/REST operation: retained
```

## 18. Recommended Next Step

After explicit Gate-A authorization, execute only publication and create:

```text
157-native-mqtt-kernel-start-reconciliation-publication.md
```

Required authorization:

```text
Veröffentlichung der Kernelstart-Reconciliation auf main freigegeben.
```

Step 157 must stop after remote source verification. It must not update
Symcon, restart the service or activate MQTT.
