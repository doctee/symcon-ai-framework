# 185 Native MQTT Core Resume Health Observation Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Candidate frozen; publication and all live gates closed
**Date:** 2026-07-29
**Scope:** Plan one-file publication, disabled update, inactive staging,
temporary activation, one active restart and mandatory cleanup

## 1. Purpose

Step 184 reproduced the one-shot Core-health failure and implemented bounded
absolute observations at `+15/+30/+60/+90 s` after
`IPS_KERNELSTARTED`.

This step freezes that candidate and defines the separately authorized gates
for publication and live verification.

It performs no:

- standalone publication;
- Symcon mutation;
- MQTT activation or connection;
- credential retrieval;
- service restart;
- MQTT publish;
- mower command;
- tag or release.

## 2. Two Independent Time Axes

The live test must distinguish:

```text
Axis A: service start -> KR_READY -> IPS_KERNELSTARTED
Axis B: IPS_KERNELSTARTED -> bounded Core observation
```

Axis A may take several minutes. It does not consume the 90-second Core
observation window.

The live evidence from step 181 already showed:

```text
kernel epoch -> Account IPS_KERNELSTARTED observation: 197 seconds
observation -> first Core classification:              15 seconds
```

The new implementation starts Axis B only when the Account receives
`IPS_KERNELSTARTED`. A five-minute service start therefore permits:

```text
up to about 5 minutes to KR_READY
plus up to 90 seconds Core observation
```

Console or MCP reachability is not itself a lifecycle gate. The Account must
complete its bounded state machine autonomously while external control is
temporarily unavailable.

## 3. Fixed Architecture Boundary

Every execution gate must preserve:

- REST as authority for public mower variables;
- MQTT as receive-only acceleration evidence;
- no MQTT publish path;
- no mower command from MQTT lifecycle code;
- retained Account, Configurator, Device and Receiver instances;
- retained native MQTT and WebSocket Core instances;
- no automatic Core creation, deletion, replacement or reparenting;
- exact ownership and subscription contracts;
- all 14 Device variable identities and metadata;
- all five Archive Control logging contracts;
- queryable archive history;
- MQTT default-disabled;
- `MC_ReloadModule()` prohibited;
- no private installation data in public artifacts.

MQTT failure must not interrupt OAuth, REST polling or supported mower
commands.

## 4. Frozen Candidate

Canonical productive candidate:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Frozen hashes:

```text
NavimowAccount/module.php:
1bbc18327564bca52a9257f11485b4b8c9340e2f5f51e5066caa4fec253d79d7

tests/mqtt-transport-lifecycle.php:
49d18738dd07f7785649e4d3dfe492f48d1a817a2c0d414b9f807390f64ff938

tests/mqtt-fixtures.php:
fd8e8dab157c3a91afa4d16ea30862db21dda4ec19e1986e3c0f3d1287ade652

fixtures/mqtt/core-resume-bounded-health-observation.json:
a2d1d416954c16bd26b8d02bb197815fcfb38e808e21f654ead81e37d7f66431

fixtures/mqtt/bounded-diagnostics-shadow-active.json:
370e00a4a61c01be55ae812f33610a74205a265a551e546472b90c07076b3211
```

Only productive module files belong in the standalone repository. Tests,
fixtures, tools and SAEF reports remain in SAEF.

## 5. Standalone Baseline

Read-only local inspection established:

```text
repository:  private/navimow-publish-20260708
branch:      main
HEAD:        7d141f76cfa7a048c5bf7beb442fe5a9ee189e44
worktree:    clean
```

Published Account SHA-256:

```text
731e882aea21c5a6cd2d15b4a30e9857598c2876111d14d79e533a5843b9cdc5
```

Candidate delta:

```text
modified productive files: 1
NavimowAccount/module.php: +315 / -9
added productive files: 0
deleted productive files: 0
```

Gate A must fetch and revalidate `origin/main` immediately before mutation.
This read-only observation does not replace that check.

## 6. Last Verified Live Baseline

The last verified state after step 181 cleanup is:

```text
installed module:             main@7d141f76
MQTT feature:                 disabled
lifecycle:                    Disabled
MQTT/WebSocket:               inactive
WebSocket Active:             false
Authorization headers:        empty
MQTT username/password:       empty
Account authentication:       connected
REST state authority:         retained
Device variables:             retained
Archive logging:              retained
```

Gate B must establish the current state again through bounded read-only
evidence. Historical evidence alone is insufficient.

## 7. Gate A: Publication

Required explicit authorization:

```text
Veröffentlichung der MQTT-Core-Resume-Health-Observation auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone repository;
2. require clean local `main` equal to fetched `origin/main`;
3. recheck every frozen hash;
4. rerun the complete Navimow MQTT gate;
5. copy exactly `NavimowAccount/module.php`;
6. prove one modified and no added or deleted productive file;
7. run standalone syntax, JSON, metadata, privacy, PHPCS and PHPStan checks;
8. run the Symcon Module Validator;
9. inspect the complete staged diff;
10. create one commit and push one fast-forward `main`;
11. fetch and prove remote commit and Account blob equality;
12. close private and sanitized publication evidence.

Suggested commit:

```text
fix(mqtt): observe native core readiness after restart
```

Gate A permits no Symcon update, MQTT activation, restart, tag or release.

### Gate-A stop conditions

Stop before commit when:

- fetched `origin/main` differs from the expected baseline;
- the standalone worktree is dirty;
- any productive file except the Account module differs;
- a frozen hash changed;
- any offline or validator check fails;
- MQTT publish or mower-command behavior appears;
- private data is detected.

## 8. Gate B: Disabled Symcon Update

Required separately:

```text
Symcon-Update auf die MQTT-Core-Resume-Health-Observation mit deaktiviertem MQTT freigegeben.
```

Before update, require two equal read-only projections for:

- installed branch, commit and clean module state;
- MQTT disabled and credential-free;
- retained topology and instance identities;
- Account authentication connected;
- REST and command compatibility;
- Device variable and Archive Control contracts;
- stopped MQTT lifecycle timer.

Gate B permits exactly:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
```

Post-update verification must prove:

- installed commit equals the Gate-A commit;
- MQTT remains disabled;
- Core credential fields remain empty;
- no instance, variable or archive identity changed;
- REST remains operational;
- no MQTT connection attempt occurred.

An ambiguous update permits no second update.

## 9. Gate C: Inactive Staging

Required separately:

```text
Inaktives Staging der MQTT-Core-Resume-Health-Observation freigegeben.
```

This gate is read-only when the retained topology is valid.

It must prove:

- exact Receiver, MQTT and WebSocket module types;
- symmetric Account/Receiver pairing;
- retained parent chain;
- four exact device-scoped `Topic`/`QoS` subscriptions;
- no wildcard or duplicate subscription;
- valid ownership and transport hashes;
- MQTT disabled;
- WebSocket inactive;
- Authorization headers empty;
- MQTT username and password empty;
- lifecycle timer stopped;
- no credential or broker request.

No Core object may be created, deleted, reparented or configured.

## 10. Gate D: Renewed Persistence Acceptance

Prior acceptance was consumed by the completed step-180/181 sequence.

After Gates A through C pass, require:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.
```

Because external control may remain unavailable during startup, acceptance
must additionally acknowledge:

```text
Falls der Core bis +90 Sekunden nach IPS_KERNELSTARTED nicht gesund ist,
darf der vorhandene receive-only Lifecycle vor Wiedererreichbarkeit der
Konsole den bereits implementierten begrenzten Recovery-Pfad beginnen.
Es werden keine MQTT-Nachrichten veröffentlicht und keine Mäherbefehle
gesendet.
```

This acceptance permits neither activation nor restart by itself.

## 11. Gate E: Temporary Activation

Required separately:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den Core-Resume-Health-Observation-Test freigegeben.
```

### Preconditions

Require:

- Gates A through D passed;
- current kernel epoch reconciled;
- MQTT disabled and credential-free;
- valid ownership and topology;
- Account authentication connected;
- access token usable for at least 1200 seconds;
- no planned token refresh during the complete startup and observation window;
- complete variable/archive compatibility.

The 1200-second token threshold covers a slow service start, the 90-second
observation window and cleanup margin.

### Allowed activation

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
```

Expected activation:

```text
one initial Account connection
one healthy Core confirmation
Core status 102 / 102
WebSocket Active true
ShadowActive / healthy
```

Natural mower ingress is optional.

## 12. Active Baselines

Capture two equal active projections at least one normal lifecycle period
apart.

Each must include:

```text
kernel epoch and reconciliation markers
lifecycle state and transition reason
last Core classification
connection attempt/success/failure counters and timestamps
Core-resume observation counter
unexpected-disconnect and reconnect counters
lastReceivedAt
Receiver receive/forward/reject counters
MQTT/WebSocket status
WebSocket Active
credential-presence Booleans
ownership, topology and Core configuration hashes
token-valid-for-at-least-1200-seconds Boolean
variable/archive compatibility hashes
```

Required baseline:

```text
ShadowActive / healthy
102 / 102 / Active=true
connection timestamps frozen between projections
kernelCoreObservationCount = 0
no pending lifecycle attempt except normal health observation
```

Record baseline UTC timestamps. They bound later receive-counter timing.

## 13. Gate F: One External Restart

Required separately:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart zur Core-Resume-Health-Observation-Prüfung ist freigegeben.
```

Gate F permits:

- exactly one externally initiated Symcon service restart;
- no restart through Symcon PHP;
- no restart retry;
- no explicit MQTT Connect;
- no MQTT publish;
- no mower command.

Immediately before restart, record:

```text
restartArmedAtUtc
old kernel epoch
lastReceivedAt
all active baseline counters
```

The user performs the restart and reports completion. The new
`IPS_GetKernelStartTime()` value becomes the authoritative new service epoch.

## 14. Startup-Unavailability Handling

While console or MCP is unavailable:

- do not infer failure from transport unreachability;
- do not initiate a second restart;
- do not attempt fallback mutation through another channel;
- allow the published Account state machine to run;
- resume read-only inspection when MCP returns.

The first reachable probe must capture diagnostics before any operator cleanup.
It must not require the Core still to be in its decisive state because the
Account may already have adopted or entered bounded recovery.

The four-entry diagnostic timeline is the authoritative reconstruction for
Axis B.

## 15. Gate-F Pass Contract

Pass requires:

```text
new kernel epoch
kernelStartObservedAt >= new kernel epoch
kernelCoreObservationDeadlineAt =
    kernelStartObservedAt + 90
at least one bounded observation
final classification healthy
state ShadowActive
reason core-resumed
Core-resume observations +1
Account connection attempts delta 0
Account connection successes/failures delta 0/0
last connection trigger and timestamps unchanged
no native Core configuration mutation
ownership and topology hashes unchanged
```

Every observation must satisfy:

- ordinal in `1..4`;
- strictly increasing timestamp;
- offset equal to the measured observation offset;
- only privacy-safe presence Booleans;
- no credential value, topic, payload or device identity.

Healthy adoption may occur at `+15`, `+30`, `+60` or `+90 s`.

## 16. Deadline Adequacy Decision

The first healthy offset determines the recommendation:

| First healthy projection | Decision |
|---:|---|
| `+15 s` or `+30 s` | 90-second deadline has comfortable evidence |
| `+60 s` | pass, but review more conservative 180-second deadline |
| `+90 s` | pass is technically valid; increase deadline before broader pilot |
| no healthy projection by `+90 s` | Gate F fails; root cause/deadline analysis required |

Axis-A duration does not change this table. A five-minute kernel startup is
not evidence that the 90-second post-ready window is too short.

## 17. Failure and Recovery Accounting

If the deadline is reached unhealthy:

```text
classification: unhealthy-with-credentials
unexpectedDisconnects: +1
owned Core cleanup: once
first reconnect schedule: +60 seconds
```

If external control returns only after recovery began, capture:

- every connection-attempt, success and failure delta;
- reconnect-attempt and exhaustion deltas;
- last trigger and timestamps;
- current Core status and credential-presence Booleans;
- the preserved four-entry observation timeline.

Do not classify an automatic bounded reconnect as an explicit test action.
It is nevertheless a side effect and must be reported exactly.

Stop further active testing immediately after the first reachable decisive
projection. No manual Connect or restart retry is allowed.

## 18. Gate G: Mandatory Cleanup

Cleanup is mandatory after every Gate-F outcome.

Required mutation:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
```

Use direct Core cleanup only if normal Account cleanup fails and separately
record the emergency mutation.

Verify immediately and after at least 120 seconds:

```text
MQTT feature disabled
lifecycle Disabled
nextAttemptAt 0
WebSocket inactive
Authorization headers empty
MQTT username/password empty
no later connection attempt
REST authority retained
Device variables and Archive logging unchanged
```

The delayed check exceeds one normal 60-second lifecycle period.

## 19. Evidence Closure

Private machine-readable evidence must record:

- all explicit authorizations and gates;
- publication and installed commit hashes;
- two active baselines;
- `restartArmedAtUtc`;
- old and new kernel epochs;
- Axis-A duration;
- complete bounded Core observation timeline;
- Axis-B offsets and deadline;
- all Account and Receiver counter deltas;
- whether automatic recovery began before control returned;
- every cleanup mutation;
- immediate and delayed cleanup result;
- separate MCP transport, PHP execution and truncation fields.

The sanitized public report must contain no:

- ObjectID;
- credential value;
- private endpoint or hostname;
- private topic;
- payload;
- device identity;
- local IP address;
- garden detail.

Promote a sanitized fixture only when the live result adds a new regression
signature or changes a current expectation.

## 20. Stop Conditions

Stop the current gate when:

- a required explicit authorization is absent;
- installed or remote commit differs from the frozen candidate;
- MQTT is already active unexpectedly;
- token validity is below the threshold;
- topology, ownership, variable or archive contracts drift;
- any explicit MQTT publish or mower-command path appears;
- a second restart or explicit Connect would be required;
- cleanup cannot be proven;
- private data appears in public evidence.

Failure of one gate does not authorize the next gate.

## 21. Architecture Decisions

### AD-NAV-650: Start the observation clock at `IPS_KERNELSTARTED`

**Decision:** Measure the 90-second deadline only from the recorded post-ready
message.

**Reason:** Multi-minute kernel startup is independent of native Core
readiness after `KR_READY`.

### AD-NAV-651: Let the Account operate while external control is unavailable

**Decision:** Console or MCP unreachability must not trigger fallback actions.

**Reason:** The tested contract is autonomous restart reconciliation.

### AD-NAV-652: Account for autonomous recovery explicitly

**Decision:** Renewed acceptance must cover the possibility that bounded
receive-only recovery begins before the console returns.

**Reason:** Final unhealthy classification schedules the existing reconnect
after 60 seconds and cannot depend on operator availability.

### AD-NAV-653: Use the persisted timeline as restart evidence

**Decision:** Reconstruct post-ready Core readiness from the bounded
Account-owned timeline, not from the first late external snapshot.

**Reason:** A multi-minute startup can make the decisive transient state
unobservable externally.

### AD-NAV-654: Make deadline adequacy evidence-driven

**Decision:** Retain 90 seconds for this candidate and decide extension from
the first healthy live offset.

**Reason:** Total service-start duration does not measure post-ready native
Core delay.

## 22. Gate Decision

| Gate | Decision |
|---|---|
| candidate hash freeze | PASS |
| publication | CLOSED |
| disabled Symcon update | CLOSED |
| inactive staging | CLOSED |
| persistence acceptance | REQUIRED AGAIN |
| temporary activation | CLOSED |
| external restart | CLOSED |
| mandatory cleanup | ARMED BY PLAN |
| MQTT publish | PROHIBITED |
| mower command | PROHIBITED |
| REST state authority | RETAINED |

## 23. Recommended Next Step

Proceed only after explicit Gate-A authorization with:

```text
186-native-mqtt-core-resume-health-observation-publication.md
```

That step should publish and remotely verify exactly the frozen one-file
productive delta, close publication evidence and leave all Symcon and live
gates closed.
