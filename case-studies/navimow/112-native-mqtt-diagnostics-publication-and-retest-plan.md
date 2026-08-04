# 112 Native MQTT Diagnostics Publication and Retest Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication, update and one-shot retest planned; no publication or
live mutation performed
**Date:** 2026-07-28
**Scope:** Publish step 111, update the private pilot safely and repeat Gate E
with observable receive evidence and an absolute cleanup deadline

## 1. Purpose

Step 110 proved one healthy native WSS/MQTT connection and complete cleanup,
but did not pass Gate E because:

- accepted Receiver evidence was not publicly observable;
- the declared 180-second connection deadline was exceeded.

Step 111 added the missing bounded diagnostic contract offline.

This step plans:

1. exact three-file standalone publication;
2. official metadata validation;
3. private pre-update compatibility capture;
4. user-controlled Module Control update;
5. read-only verification of the new diagnostic wrapper;
6. historical diagnostic inspection while MQTT remains disabled;
7. one separately authorized connection retest;
8. accepted-counter and `ShadowActive` proof;
9. absolute-deadline disconnect and credential cleanup;
10. final experimental disable and evidence closure.

This planning step performs none of those mutations.

## 2. Fixed Safety Boundary

The complete execution remains subject to:

- REST is the only authority for public mower variables;
- MQTT is receive-only;
- no MQTT publish path exists;
- no REST or MQTT mower command is called;
- the mower is not stimulated solely for test evidence;
- the existing Account, Configurator, Device and Receiver are retained;
- the adopted native MQTT and WebSocket chain is retained;
- all variable ObjectIDs, Idents, profiles and action contracts are retained;
- Archive Control logging and aggregation remain unchanged;
- no automatic Core instance creation, replacement or deletion occurs;
- no automatic connection retry occurs;
- no credential, endpoint, topic, payload or private identity is returned
  through MCP or written to Git;
- no tag is created;
- `MC_ReloadModule()` is not used.

Failure, timeout, ambiguity or process interruption permits cleanup, never a
second Connect invocation in the same session.

## 3. Current Live Baseline

Step 110 left the private installation in this safe state:

```text
experimental MQTT shadow: disabled
Receiver selection: retained
ownership: retained
WebSocket: inactive
authorization headers: empty
MQTT username: empty
MQTT password: empty
stable client ID: retained
exact subscriptions: retained
native chain instances: retained
```

The established 14 public variables and five archive logging contracts were
unchanged.

The installed standalone baseline is:

```text
6cc41d32df6cc2e528bdd4059dda3e006055241a
feat: add native MQTT shadow lifecycle
```

That release does not yet expose `NAVAC_GetMqttDiagnostics`.

## 4. Separate Authorization Gates

Approval of this plan authorizes no publication or live mutation.

### Gate A: Diagnostics publication

Required wording:

```text
Veröffentlichung der bounded MQTT-Diagnostik freigegeben.
```

This permits one reviewed commit and one fast-forward push to
`symcon-navimow/main`. It permits no Symcon update.

### Gate B: Module update and read-only verification

Required wording after remote verification:

```text
Symcon-Update und read-only Diagnoseprüfung freigegeben.
```

The user performs the Module Control update. Codex may then run only bounded
read-only probes. MQTT remains disabled.

### Gate C: One-shot receive retest

Required wording after Gate B passes:

```text
Ein einmaliger MQTT-Diagnose-Retest mit automatischem Cleanup ist freigegeben.
```

This permits exactly:

1. enable the retained owned MQTT shadow;
2. one Connect invocation;
3. bounded read-only observation;
4. one Disconnect invocation;
5. credential cleanup;
6. final disable.

It permits no retry and no restart.

### Gate D: Restart test

Restart remains a future separate gate and is not part of this plan's live
execution. It may be planned only after the retest passes completely.

## 5. Standalone Publication Baseline

Established local publish clone:

```text
private/navimow-publish-20260708
```

At planning time:

```text
local main:  6cc41d32df6cc2e528bdd4059dda3e006055241a
origin/main: 6cc41d32df6cc2e528bdd4059dda3e006055241a
worktree:    clean
```

The clone has the same 30-file productive manifest as the canonical
distribution.

Before execution, require:

```text
git fetch origin
git switch main
git pull --ff-only origin main
```

Local and remote heads must still match. Any remote advancement stops Gate A
for reclassification.

## 6. Exact Publication Delta

Canonical source:

```text
case-studies/navimow/distribution/
```

Exactly three productive files differ:

```text
NavimowAccount/module.php
NavimowAccount/form.json
NavimowAccount/locale.json
```

Classified content:

- bounded `GetMqttDiagnostics()` implementation;
- diagnostic normalization helpers;
- one diagnostic input-size bound;
- one read-only form button;
- one German locale entry.

The remaining 27 productive files must be byte-equal to standalone `main`.

Excluded from publication:

```text
case-study reports
tests
fixtures
tools
private evidence
.DS_Store
```

Any fourth productive file difference stops publication.

## 7. Candidate Validation

Before copying or staging:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
make check
git diff --check -- case-studies/navimow
```

Require:

- PHP syntax;
- JSON parsing;
- all REST and command regressions;
- all MQTT fixture, envelope, parser, Receiver and Account tests;
- exact bounded diagnostics fixture;
- poisoned-state privacy regression;
- strict distribution validation;
- PHPCS;
- PHPStan;
- no `IPS_CreateInstance`;
- no `IPS_DeleteInstance`;
- no `MC_ReloadModule`;
- no MQTT publish implementation;
- no new property, attribute, variable, profile, timer or Device action;
- no private installation data.

Record deterministic SHA-256 hashes for all three candidate files before
publication.

## 8. Official Module Validator

Run the official Symcon Module Validator against the complete candidate:

```text
library.json
4 x module.json
4 x locale.json
4 x form.json
```

The web validator has a previously documented rendering defect. Execution must
distinguish:

1. candidate schema failure;
2. browser or validator-page failure;
3. successful official result.

If the page still fails before evaluating the candidate:

- record the page failure separately;
- retrieve the current official schemas and validator dependency;
- validate all 13 JSON artifacts locally with equivalent schema semantics;
- record schema URLs and dependency hash privately;
- do not represent fallback success as successful web rendering.

Any actual schema failure blocks publication.

## 9. Publication Procedure

After Gate A authorization:

1. revalidate the clean fast-forward baseline;
2. copy only the three classified files;
3. require `git status --short` to list exactly those three files;
4. rerun syntax, JSON, validator, privacy and whitespace gates;
5. compare the complete 30-file manifest;
6. require 27 unchanged files to remain byte-equal;
7. stage only the three files;
8. inspect the staged patch;
9. commit with a Conventional Commit subject such as:

```text
feat: expose bounded MQTT diagnostics
```

10. push `main` once;
11. verify remote head;
12. fetch all three remote blobs and compare them byte-for-byte with the
    canonical source.

No tag is created.

## 10. Private Pre-Update Baseline

After publication but before Module Control update, capture:

```text
private/navimow-capture/output/native-mqtt-diagnostics/
  pre-update-baseline.json
  pre-update-repeat.json
```

Capture only private bounded evidence:

- installed commit and branch where available;
- Account, Configurator, Device and Receiver identities;
- native MQTT and WebSocket identities and parent order;
- productive instance statuses;
- configured-property hashes;
- all 14 variable identities and metadata;
- five archive logging and aggregation contracts;
- history queryability;
- command evidence hashes;
- REST connection and error state;
- MQTT shadow disabled;
- Receiver selection retained;
- WebSocket inactive;
- header, username and password absence;
- client-ID and subscription-presence booleans;
- exact subscription count and wildcard absence.

Do not return property values that contain endpoint, client ID, topic or
credential data.

The immediate repeat must prove deterministic identity, metadata and archive
hashes before the user updates the module.

## 11. Module Update Gate

After Gate B authorization:

1. user updates Navimow through Module Control from standalone `main`;
2. do not use `MC_ReloadModule()`;
3. wait for ApplyChanges completion;
4. rerun the complete compatibility probe before enabling MQTT.

Require:

- Account, Configurator, Device and Receiver identities unchanged;
- native MQTT and WebSocket identities and connections unchanged;
- all productive instance statuses expected for the disabled state;
- 14 variable identities and metadata unchanged;
- five archive contracts and history queryability unchanged;
- OAuth still connected;
- REST error state not worsened by the update;
- MQTT shadow still disabled;
- WebSocket inactive;
- authorization headers empty;
- MQTT username and password empty;
- no new instance or variable;
- no command evidence change.

Any mismatch blocks diagnostics and connection work.

## 12. Read-Only Wrapper Verification

Before re-enabling MQTT:

1. require `function_exists('NAVAC_GetMqttDiagnostics')`;
2. capture persistent and Core configuration hashes;
3. call the wrapper once;
4. parse the fixed format-version-1 schema;
5. require `featureEnabled = false`;
6. require `configurationStatus = disabled`;
7. require every key and value type to match the fixture contract;
8. require output below the size bound;
9. scan the returned JSON for forbidden private material;
10. require every persistent and Core hash unchanged.

Forbidden result material includes:

- topic fragments;
- device or account identity;
- ObjectIDs;
- endpoint or hostname;
- Authorization header;
- token;
- MQTT username, password or client ID;
- ownership registry or ownership hash;
- raw error or persistent JSON.

The wrapper check is read-only. Failure permits no enable or Connect action.

## 13. Historical Diagnostics Checkpoint

While MQTT remains disabled, retain the first diagnostic result as the
historical post-step-110 checkpoint.

Record privately:

```text
connectionAttempts
received
accepted
rejected
lastConnectionAttemptAt
lastReceivedAt
lastResult
current lifecycle state
error summary
```

Compare timestamps only with the private step-110 session window.

Possible findings:

| Finding | Classification |
|---|---|
| accepted count above zero and last receive inside the prior window | retrospective evidence for step-110 Receiver processing |
| no accepted count | prior receive remains inconclusive |
| malformed or unknown values | diagnostics/update gate fails |

Final disabling legitimately changed the current lifecycle state to
`Disabled`. A historical accepted result can support a retrospective finding,
but must not rewrite step 110 as if the evidence had been observable during
that session.

This checkpoint becomes the counter baseline for any new retest.

## 14. Retest Preflight

Before requesting Gate C authorization, prepare and statically validate the
complete private orchestration source, including cleanup. No source editing is
allowed after activation.

Immediately before mutation:

- recapture bounded diagnostics;
- require diagnostics equal to the historical checkpoint except for explainable
  passive timestamps;
- require Account connected and no reauthentication request;
- require MQTT shadow disabled;
- require Receiver selection and ownership retained;
- require WebSocket inactive;
- require authorization headers empty;
- require MQTT username and password empty;
- require client ID and exact subscriptions retained;
- capture all counter baselines;
- capture public-variable identity and archive hashes;
- capture command evidence hashes.

Then, under Gate C:

1. enable MQTT shadow;
2. ApplyChanges once;
3. require ownership validation `ready`;
4. require diagnostic lifecycle `Ready`;
5. require the transport still inactive and credentials still clean.

Failure disables MQTT again and permits no Connect call.

## 15. Absolute Deadline Contract

The retest harness establishes all deadlines before Connect:

```text
hard active deadline: 180 seconds
observation cutoff:    165 seconds
cleanup reserve:        15 seconds
poll interval:           5 seconds maximum
```

Required ordering:

1. load and validate observation and cleanup procedures;
2. record a monotonic session start;
3. derive the absolute observation cutoff and hard deadline;
4. invoke Connect exactly once;
5. poll diagnostics read-only;
6. stop observation immediately on complete acceptance or at the cutoff;
7. enter cleanup in a `finally` path;
8. finish Disconnect and readback before the hard deadline where technically
   possible;
9. record actual elapsed times without rounding.

The harness must calculate each wait from the absolute deadline. It must not
sum fixed sleeps as in step 110.

If cleanup begins after 165 seconds or finishes after 180 seconds, deadline
conformance fails even if transport and cleanup otherwise succeed.

## 16. One-Shot Connect

Execute:

```text
NAVAC_ConnectMqttShadow
```

exactly once.

Require:

- result `MQTT connection attempt started.`;
- `connectionAttempts` delta exactly `+1`;
- one credential endpoint path;
- one WebSocket activation maximum;
- no retry;
- WebSocket and native MQTT statuses reach healthy `102`;
- ownership remains valid;
- diagnostics remain schema-valid and private.

Any failure or ambiguity enters cleanup immediately.

## 17. Receive Acceptance

Gate-E receive acceptance requires one observation satisfying all:

```text
received delta >= 1
accepted delta >= 1
lastReceivedAt within the current session
lifecycle state = ShadowActive
lastResult = accepted
native MQTT status = 102
native WebSocket status = 102
```

Additionally require:

- rejected delta `0`;
- error count delta `0`;
- no unknown diagnostic code;
- no MQTT publish attempt;
- no mower action;
- no command evidence change.

Healthy Core status without the diagnostic deltas is not sufficient.

If no complete observation exists by 165 seconds, classify the receive result
as inconclusive and enter cleanup. Do not reconnect.

## 18. REST Authority

During the session:

- MQTT may update only private shadow, lifecycle, statistics and bounded
  reconciliation state;
- MQTT does not write a public Device variable directly;
- public Device changes must pass through the established REST path;
- reconciliation remains bounded and coalesced;
- command variables remain unchanged;
- variable metadata and archive configuration remain unchanged.

Public values may legitimately change through normal REST polling. Therefore
the gate compares:

- variable identity and metadata;
- archive logging and aggregation;
- command evidence;
- REST success and status-update timestamps;
- diagnostic reconciliation deltas.

It does not require mower-state values to remain frozen.

## 19. Mandatory Cleanup

Cleanup runs after pass, failure, timeout or ambiguity:

1. call `NAVAC_DisconnectMqttShadow` once while ownership is valid;
2. require `MQTT transport disconnected.`;
3. require WebSocket inactive;
4. require authorization headers empty;
5. require MQTT username empty;
6. require MQTT password empty;
7. require stable client ID retained;
8. require exact subscriptions retained;
9. require Receiver and both Core instances retained;
10. require ownership valid;
11. set `EnableMqttShadow = false`;
12. ApplyChanges once;
13. require diagnostic status `disabled`;
14. repeat credential-presence readback;
15. compare variables, command evidence and archive contract with the
    preflight baseline.

Cleanup never deletes the chain or rotates the local identity.

If supported cleanup cannot be proven, the operator must set the WebSocket
inactive through the Symcon UI and stop. No new connection is permitted.

## 20. Failure Matrix

| Failure | Required response |
|---|---|
| Standalone remote advanced | Stop publication and reclassify the delta. |
| More than three productive file differences | Stop publication. |
| Actual Module Validator schema error | Stop publication. |
| Module update identity/archive drift | Keep MQTT disabled; stop. |
| Diagnostic wrapper absent or malformed | Keep MQTT disabled; stop. |
| Diagnostic privacy scan fails | Roll back module version; do not enable. |
| Ownership invalid after enable | Disable; do not Connect. |
| Credential request fails | Accept automatic rollback, run cleanup, no retry. |
| Activation ambiguous | Run Disconnect once, disable, no retry. |
| No accepted evidence by cutoff | Disconnect, disable, classify inconclusive. |
| Rejected/error counters rise | Disconnect, disable, retain warning evidence. |
| Deadline exceeded | Disconnect/disable, classify gate nonconformant. |
| Public metadata/archive drift | Disconnect/disable and stop pilot progression. |
| Cleanup cannot be proven | Manually force WebSocket inactive; block all retests. |

No rollback deletes or recreates a productive variable or instance.

## 21. Private Evidence

Use:

```text
private/navimow-capture/output/native-mqtt-diagnostics/
  publication-preflight.json
  publication.json
  pre-update-baseline.json
  pre-update-repeat.json
  post-update-compatibility.json
  historical-diagnostics.json
  retest-preflight.json
  retest-session.json
  disconnect-cleanup.json
  post-test-disable.json
  post-retest-compatibility.json
  gate-closure.json
```

Every MCP envelope records separately:

```text
success
transportError
executionError
truncated
```

Private evidence may contain local ObjectIDs and hashes only below `private/`.
It must not retain tokens, credentials, complete topics, endpoint values or
raw MQTT payloads.

## 22. Public Reports

Execution should be split into:

```text
113-native-mqtt-diagnostics-publication.md
114-native-mqtt-diagnostics-symcon-retest-report.md
```

Step 113 closes only publication and remote byte verification.

Step 114 closes update compatibility, historical diagnostics, the separately
authorized one-shot retest, cleanup and the decision whether restart planning
may open.

Historical reports 106 through 111 remain unchanged.

## 23. Architecture Decisions

### AD-NAV-436: Publish only three Account files

**Decision:** Synchronize only the exact productive diagnostics delta.

**Reason:** The 30-file manifests already match, and broad synchronization
would make unrelated drift harder to detect.

**Consequence:** Any fourth file blocks Gate A.

### AD-NAV-437: Read history before reconnecting

**Decision:** Evaluate retained diagnostics while MQTT is still disabled.

**Reason:** Step 110 may already have produced accepted evidence, and a new
connection must not be used to discover what can be learned read-only.

**Consequence:** Historical counters also become the exact retest baseline.

### AD-NAV-438: Reserve cleanup time

**Decision:** End observation at 165 seconds and reserve 15 seconds inside the
hard 180-second active limit.

**Reason:** Cleanup must be part of the bounded session, not work scheduled
after its deadline.

**Consequence:** No fixed sleep may extend the active interval.

### AD-NAV-439: Require causal receive evidence

**Decision:** Require counter deltas, current-session timestamp and
`ShadowActive` in the same observation.

**Reason:** Core health, REST activity or historical counters alone cannot
prove current productive Receiver acceptance.

**Consequence:** Ambiguous transport sessions remain inconclusive without
retry.

### AD-NAV-440: End every session disabled

**Decision:** Disconnect, remove credentials and disable MQTT shadow after
every retest outcome.

**Reason:** Experimental transport must not remain enabled between supervised
gates.

**Consequence:** Restart persistence cannot be tested accidentally and remains
a separate authorization.

## 24. Gate Decision

| Planning item | Result |
|---|---|
| exact standalone baseline | VERIFIED locally |
| exact productive delta | three files |
| candidate offline gate | PASS before planning |
| publication | NOT PERFORMED |
| Module Validator execution | PENDING |
| Symcon update | NOT PERFORMED |
| historical diagnostics | NOT READ |
| one-shot retest | NOT PERFORMED |
| restart gate | BLOCKED |

**Planning gate: CLOSED.**

**Gate A publication: BLOCKED pending explicit authorization.**

## 25. Next Step

After explicit Gate A authorization, create:

```text
113-native-mqtt-diagnostics-publication.md
```

That step may publish and remotely verify only the exact three-file diagnostics
delta. It must stop before Module Control update or any live Symcon mutation.
