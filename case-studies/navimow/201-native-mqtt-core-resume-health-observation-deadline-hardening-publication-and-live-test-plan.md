# 201 Native MQTT Core Resume Health Observation Deadline Hardening Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Candidate frozen; publication and all live gates closed
**Date:** 2026-07-29
**Scope:** Plan one-file publication, official validation, disabled update,
inactive staging, one threshold-gated restart and mandatory cleanup

## 1. Purpose

Step 200 implemented the six-point retained-Core observation horizon:

```text
+15, +30, +60, +90, +120, +180 seconds
```

This step freezes the candidate and defines all later operations as separate
authorization gates. It performs no:

- standalone publication;
- browser-based Module Validator run;
- Symcon mutation;
- MQTT activation or credential retrieval;
- service restart;
- MQTT publish;
- mower command;
- tag or release.

## 2. Fixed Architecture Boundary

Every gate must preserve:

- REST as authority for public mower variables;
- MQTT as optional receive-only acceleration evidence;
- no MQTT publish path;
- no mower command from MQTT lifecycle code;
- retained Account, Configurator, Device and Receiver instances;
- retained native MQTT and WebSocket Core instances;
- no automatic Core creation, deletion, replacement or reparenting;
- exact Account/Receiver pairing, ownership and subscription contracts;
- all 14 Device variable identities and metadata;
- all five Archive Control logging contracts;
- queryable accumulated archive history;
- MQTT default-disabled;
- `MC_ReloadModule()` prohibited;
- no private installation data in public artifacts.

MQTT failure must not interrupt OAuth, REST polling or supported REST commands.

## 3. Frozen Candidate

Canonical productive candidate:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Frozen hashes:

```text
NavimowAccount/module.php:
6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f

tests/mqtt-transport-lifecycle.php:
21a5d34d42a5bfdea2ddc95f47c461707e71cedaa7769541d9be70db1677bbcd

tests/mqtt-fixtures.php:
2cd749abf48b0811e1012f21d35778cb2f25263a6d6a64c22d0cf081ba03a153

fixtures/mqtt/core-resume-bounded-health-observation.json:
e9acb461a00e34e01fd2f0c8a55b5e53c3826b8b3ec57c79f4fad692cea8a71e
```

Only productive module artifacts belong in the standalone repository. Tests,
fixtures, tools and SAEF reports remain in SAEF.

Any hash drift before publication returns the process to offline review.

## 4. Standalone Baseline

Read-only inspection established:

```text
repository: private/navimow-publish-20260708
branch:     main
HEAD:       45c7bd509f95
worktree:   clean
```

The complete candidate delta against that baseline is:

```text
modified productive files: 1
NavimowAccount/module.php:  2 insertions / 2 deletions
added productive files:    0
deleted productive files:  0
```

The productive diff contains only:

```text
observation offsets: [15, 30, 60, 90]
                  -> [15, 30, 60, 90, 120, 180]

maximum entries:     4 -> 6
```

The publication gate must fetch and revalidate `origin/main` immediately
before mutation. This planning snapshot does not replace that check.

## 5. Last Verified Live Baseline

Step 198 closed the live sequence with:

```text
installed module:             main@45c7bd50
MQTT feature:                 disabled
lifecycle:                    Disabled
MQTT/WebSocket:               inactive
WebSocket Active:             false
Authorization headers:        empty
MQTT username/password:       empty
Account authentication:       connected
REST state authority:         retained
Device variables:             14/14 retained
Archive logging:              5/5 retained
archive history:              queryable
```

Every Symcon gate must establish the current state again through bounded
read-only evidence. Historical evidence alone is insufficient.

## 6. Two Independent Time Axes

The live test distinguishes:

```text
Axis A: service start -> KR_READY -> IPS_KERNELSTARTED
Axis B: Account IPS_KERNELSTARTED observation -> Core-health deadline
```

Axis A may take several minutes and does not consume Axis B.

The new Axis-B contract is:

```text
kernelStartObservedAt
  +15 / +30 / +60 / +90 / +120 / +180 seconds
```

Console or MCP unavailability is not itself a lifecycle failure. Allow up to
five minutes for service and console availability without a fallback mutation
or restart retry.

## 7. Conservative Token Budget

The extended test uses:

| Segment | Budget |
|---|---:|
| delayed lifecycle connection and Core health | 90 s |
| two active baselines | 120 s |
| operator handoff before restart | 180 s |
| service startup and console unavailability | 300 s |
| post-ready Core observation | 180 s |
| first reachable decisive projection | 300 s |
| immediate and delayed cleanup | 180 s |
| scheduling and transport reserve | 300 s |
| **bounded total** | **1650 s** |

Frozen thresholds remain:

```text
before activation: >= 2400 seconds
at restart arm:    >= 1800 seconds
```

The activation gate retains 750 seconds beyond the full conservative budget.
The restart-arm threshold is evaluated after activation and baseline work, so
it remains sufficient for the remaining restart, observation and cleanup
sequence.

Both thresholds are readiness gates, not claims about token lifetime.

## 8. Gate A: Standalone Publication

Required explicit authorization:

```text
Veröffentlichung der MQTT-Core-Resume-Deadline-Härtung auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone repository;
2. require clean local `main` equal to fetched `origin/main`;
3. verify the frozen hashes and exact productive diff;
4. rerun the complete Navimow MQTT offline gate;
5. copy exactly `NavimowAccount/module.php`;
6. prove one modified and no added or deleted productive file;
7. run standalone syntax, JSON, metadata, privacy, PHPCS and PHPStan checks;
8. inspect the complete staged diff;
9. create one commit and push one fast-forward `main`;
10. fetch and prove remote commit and Account blob equality;
11. close private and sanitized publication evidence.

Suggested commit:

```text
fix(mqtt): extend native core resume deadline
```

Gate A permits no Symcon update, MQTT activation, restart, tag or release.

### Gate-A stop conditions

Stop before commit when:

- fetched `origin/main` differs from the expected baseline;
- the standalone worktree is dirty;
- any productive file except the Account module differs;
- a frozen hash changed;
- any offline check fails;
- MQTT publish or mower-command behavior appears;
- private data is detected.

An ambiguous push result must be resolved by fetch and hash comparison, not by
blindly pushing again.

## 9. Gate B: Official Module Validator

After Gate A publication, run the official Symcon Module Validator against the
exact published commit.

Required evidence:

- validated repository URL, branch and commit;
- library and all four module metadata results;
- validator success or complete error text;
- confirmation that the candidate Account blob equals the frozen hash;
- separate classification of browser transport/UI errors and real validator
  schema errors.

A cookie banner, mini-browser layout problem, timeout or missing result is a
browser execution failure, not proof of an invalid module. Retry through a
clean browser session when necessary, but do not proceed to Symcon until a
real successful validator result exists.

Any actual validator error blocks Gate C. Correct it in SAEF first and publish
a new reviewed commit; do not rewrite published history.

## 10. Gate C: Disabled Symcon Update

Required separately:

```text
Symcon-Update auf die MQTT-Core-Resume-Deadline-Härtung mit deaktiviertem MQTT freigegeben.
```

Before update, capture two equal read-only projections for:

- installed branch, commit and clean module state;
- MQTT disabled and credential-free;
- retained topology and instance identities;
- Account authentication connected;
- REST and command compatibility;
- Device variable and Archive Control contracts;
- stopped MQTT lifecycle timer.

Gate C permits exactly:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
```

Post-update verification must prove:

- installed commit equals the Gate-A commit;
- MQTT remains disabled;
- lifecycle remains `Disabled`;
- Core credential fields remain empty;
- no instance, variable or archive identity changed;
- REST remains operational;
- no MQTT connection attempt occurred.

An ambiguous update permits no second update until the installed commit is
read back.

## 11. Gate D: Inactive Staging

Required separately:

```text
Inaktives Staging der MQTT-Core-Resume-Deadline-Härtung freigegeben.
```

This gate is read-only when the retained topology is valid.

It must prove twice over more than one normal lifecycle interval:

- exact Receiver, MQTT and WebSocket module types;
- symmetric Account/Receiver pairing;
- retained parent chain;
- four exact device-scoped `Topic`/`QoS` subscriptions;
- no wildcard or duplicate subscription;
- valid ownership, topology and configuration hashes;
- MQTT disabled;
- WebSocket inactive;
- Authorization headers empty;
- MQTT username and password empty;
- lifecycle timer stopped;
- stable connection counters;
- no credential or broker request;
- variables 14/14 and Archive logging 5/5 unchanged.

No Core object may be created, deleted, reparented or configured.

## 12. Gate E: Renewed Persistence and Recovery Acceptance

Prior acceptance was consumed by the completed step 197/198 activation and
restart sequence.

After Gates A through D pass, require:

```text
Ich akzeptiere für einen weiteren einmaligen beaufsichtigten Neustarttest,
dass Authorization- und MQTT-Zugangsdaten während des aktivierten nativen
Transports in den eigenen IP-Symcon-Core-Instanzen gespeichert sind und der
Core sie beim Neustart vor der Account-Reconciliation wiederverwenden kann.
Nach dem Test wird MQTT unabhängig vom Ergebnis deaktiviert und bereinigt.

Falls der Core bis +180 Sekunden nach IPS_KERNELSTARTED nicht gesund ist,
darf der vorhandene receive-only Lifecycle vor Wiedererreichbarkeit der
Konsole den bereits implementierten begrenzten Recovery-Pfad beginnen.
Es werden keine MQTT-Nachrichten veröffentlicht und keine Mäherbefehle
gesendet.
```

This contextual acceptance permits neither activation nor restart by itself.

## 13. Gate F: Passive Token Readiness

Before activation, observe the existing token state read-only.

Require:

```text
token remaining >= 2400 seconds
ConnectionState = Connected
ReauthRequired = false
normal REST continuity
MQTT disabled and credential-free
```

If the horizon is below 2400 seconds, do not invoke a manual refresh. Use the
established bounded passive token-refresh observation:

- poll no more than once per 60 seconds;
- stop when expiry moves forward;
- require a new horizon of at least 3000 seconds;
- require continuous REST authentication;
- obtain user confirmation that no manual authentication action occurred.

No OAuth authorization, manual token refresh, `ApplyChanges()`, MQTT action or
mower command belongs to this gate.

## 14. Gate G: Temporary Activation

Required separately:

```text
Temporäre Aktivierung des receive-only MQTT-Transports für den 180-Sekunden-Core-Resume-Test freigegeben.
```

Immediate preconditions:

- Gates A through F passed;
- current kernel epoch reconciled;
- token remaining at least 2400 seconds;
- MQTT disabled and credential-free;
- valid ownership and topology;
- Account connected and no reauthentication required;
- complete variable/archive/command compatibility.

Execute exactly:

```text
EnableMqttShadow -> true: 1
Account ApplyChanges:      1
explicit MQTT Connect:     0
```

Expected activation:

```text
one initial Account connection
one healthy Core confirmation
MQTT/WebSocket 102/102
WebSocket Active true
ShadowActive / healthy
```

Natural receive-only ingress is optional and must not be manufactured.

## 15. Active Baselines and Restart Arm

Capture two equal active projections at least 65 seconds apart.

Each must include:

- kernel epoch and reconciliation markers;
- lifecycle state, reason and classification;
- connection attempt, success and failure counters;
- Core-resume, disconnect and reconnect counters;
- Receiver counters and `lastReceivedAt`;
- MQTT/WebSocket statuses and WebSocket active state;
- credential-presence Booleans;
- ownership, topology and configuration hashes;
- token remaining as an integer;
- variable and archive compatibility hashes.

Required baseline:

```text
ShadowActive / healthy
102 / 102 / Active=true
connection timestamps stable between projections
kernelCoreObservationCount = 0
no pending reconnect
```

Immediately before requesting restart authorization require:

```text
token remaining >= 1800 seconds
restartArmedAtUtc recorded
old kernel epoch recorded
all active counters and hashes recorded
```

If the restart-arm token horizon is below 1800 seconds, do not restart.
Perform mandatory normal Account cleanup and return to passive readiness.

## 16. Gate H: One External Restart

Required separately:

```text
Ein einmaliger beaufsichtigter Symcon-Neustart für den 180-Sekunden-Core-Resume-Test ist freigegeben.
```

Gate H permits:

- exactly one externally initiated Symcon service restart;
- no restart through Symcon PHP;
- no restart retry;
- no explicit MQTT Connect;
- no MQTT publish;
- no mower command.

The user performs the restart and reports completion. The changed
`IPS_GetKernelStartTime()` value is the authoritative new epoch.

While console or MCP is unavailable:

- do not infer failure from unreachability;
- do not initiate a second restart;
- do not mutate through another channel;
- allow the Account state machine to run autonomously;
- resume read-only inspection when control returns.

## 17. Gate-H Pass Contract

The first reachable projection and persisted timeline must prove:

```text
new kernel epoch
kernelStartObservedAt >= new kernel epoch
kernelCoreObservationDeadlineAt =
    kernelStartObservedAt + 180
at least one bounded observation
final classification healthy
state ShadowActive
reason core-resumed
Core-resume observations +1
Account connection attempts delta 0
Account connection successes/failures delta 0/0
last connection trigger and timestamps unchanged
ownership and topology hashes unchanged
```

Every observation must contain:

- ordinal in `1..6`;
- increasing `observedAt`;
- measured `offsetSeconds`;
- MQTT and WebSocket status;
- WebSocket active state;
- credential-presence Booleans;
- receive timestamp;
- canonical `healthy` Boolean.

Per-entry `classification` and `failedPredicates` are not part of the contract.
Use the top-level fields:

```text
lastKernelCoreClassification
lastKernelCoreFailedPredicates
```

Healthy adoption may occur at any of the six scheduled offsets. It must occur
immediately at the first healthy point, including the exact `+180 s`
boundary.

## 18. Deadline Adequacy Decision

| First healthy projection | Decision |
|---:|---|
| `+15 s` or `+30 s` | comfortable reserve |
| `+60 s` or `+90 s` | private pilot pass |
| `+120 s` | pass, monitor reduced reserve |
| `+180 s` | technical boundary pass; no broader activation |
| none by `+180 s` | Gate H fails; recovery and root-cause review required |

Axis-A duration does not change this table.

## 19. Failure and Autonomous Recovery

If the final observation remains unhealthy:

```text
classification:          unhealthy-with-credentials
unexpectedDisconnects:  +1
owned Core cleanup:      once
first reconnect delay:   60 seconds
maximum attempts:        3
```

Network or broker failures may use the established delays:

```text
60, 300, 900 seconds
```

Authentication or configuration errors remain non-retryable.

If external control returns after recovery began, capture every automatic
attempt, result and counter delta. Do not issue an explicit Connect, restart
retry or compensating mower action.

Stop active observation after the first reachable decisive projection and
proceed directly to cleanup.

## 20. Gate I: Mandatory Cleanup

Cleanup is mandatory after every Gate-G activation outcome, including:

- token-horizon stop before restart;
- successful restart adoption;
- final deadline failure;
- autonomous recovery;
- ambiguous external inspection.

Normal mutation:

```text
EnableMqttShadow -> false: 1
Account ApplyChanges:      1
explicit Disconnect:       0
```

Use direct Core cleanup only if normal Account cleanup fails and record that
emergency mutation separately.

Verify immediately and after at least 180 seconds:

```text
MQTT feature disabled
lifecycle Disabled
nextAttemptAt 0
reconnectAttempt 0
WebSocket inactive
Authorization headers empty
MQTT username/password empty
no later connection attempt
REST authority retained
variables 14/14 unchanged
Archive logging 5/5 unchanged
archive history queryable
```

The delayed check spans the full new observation horizon and more than one
normal 60-second lifecycle interval.

## 21. Evidence Closure

Private machine-readable evidence must record:

- every explicit authorization and gate;
- frozen candidate, publication and installed hashes;
- official Module Validator result;
- all token horizons as remaining seconds with timestamps;
- both active baselines;
- `restartArmedAtUtc`;
- old and new kernel epochs;
- Axis-A duration;
- complete six-entry-capable Core observation timeline;
- top-level classification and failed predicates;
- all Account and Receiver counter deltas;
- whether autonomous recovery began;
- every cleanup mutation;
- immediate and delayed cleanup results;
- separate MCP transport, PHP execution and truncation fields.

The sanitized public report contains no:

- ObjectID;
- token or credential value;
- private endpoint, hostname or IP address;
- private topic or payload;
- device identity;
- local path;
- garden detail.

Promote a sanitized fixture only when the live result adds a new regression
signature or changes the current expected behavior.

## 22. Stop Conditions

Stop the current gate when:

- required authorization is absent;
- local, remote or installed commit differs from the frozen candidate;
- the official Module Validator has no real successful result;
- MQTT is already active unexpectedly;
- token validity is below the applicable threshold;
- topology, ownership, variables or archive contracts drift;
- a second restart or explicit Connect would be required;
- MQTT publish or a mower-command path appears;
- normal cleanup cannot be proven;
- private data appears in public evidence.

Failure or ambiguity in one gate does not authorize the next gate.

## 23. Architecture Decisions

### AD-NAV-717: Preserve separate startup and Core-health axes

**Decision:** Start the 180-second deadline only at the Account's recorded
`IPS_KERNELSTARTED` observation.

**Reason:** Multi-minute service startup and post-ready child readiness are
independent phases.

### AD-NAV-718: Retain the 2400/1800-second token gates

**Decision:** Use the established activation and restart-arm thresholds with
the new 1650-second conservative total.

**Reason:** Both gates retain positive reserve and prevent pre-restart work
from consuming the restart budget unnoticed.

### AD-NAV-719: Make official validation block Symcon update

**Decision:** Require a real successful Module Validator result for the exact
published commit before Gate C.

**Reason:** Browser execution ambiguity is not module validity evidence.

### AD-NAV-720: Use only canonical diagnostic fields

**Decision:** Read per-entry `healthy` and top-level classification fields.

**Reason:** This matches the productive serializer and prevents another
evidence-probe schema error.

### AD-NAV-721: Permit exactly one external restart

**Decision:** No restart retry is allowed under the same acceptance.

**Reason:** Each credential-bearing restart is a separate security-sensitive
live operation.

### AD-NAV-722: Extend delayed cleanup verification to 180 seconds

**Decision:** Repeat cleanup checks after at least the complete new horizon.

**Reason:** The final check must outlast any stale observation timer and prove
credential-free closure.

## 24. Gate Matrix

| Gate | Mutation | Current decision |
|---|---|---|
| plan | none | PASS |
| A standalone publication | Git commit and push | CLOSED |
| B official Module Validator | none | CLOSED |
| C disabled Symcon update | one `MC_UpdateModule()` | CLOSED |
| D inactive staging | none | CLOSED |
| E persistence acceptance | none | NOT GIVEN |
| F passive token readiness | none | CLOSED |
| G temporary activation | property plus `ApplyChanges()` | CLOSED |
| active baselines/restart arm | none | CLOSED |
| H external restart | one external service operation | CLOSED |
| I mandatory cleanup | property plus `ApplyChanges()` | ARMED ONLY AFTER ACTIVATION |
| MQTT publish | external side effect | PROHIBITED |
| mower command | external side effect | PROHIBITED |

REST remains authoritative and MQTT remains receive-only.

## 25. Next Step

Proceed only after explicit Gate-A authorization with:

```text
202-native-mqtt-core-resume-health-observation-deadline-hardening-publication.md
```

Gate A publishes and remotely verifies exactly the frozen one-file productive
delta. It leaves the Module Validator, Symcon update, MQTT activation and
restart gates closed.
