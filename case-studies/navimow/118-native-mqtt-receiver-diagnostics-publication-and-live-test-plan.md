# 118 Native MQTT Receiver Diagnostics Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Publication, update and one-shot live test planned; no publication
or live mutation performed
**Date:** 2026-07-28
**Scope:** Publish the step-117 Receiver diagnostics, preserve the private
pilot and obtain one bounded Receiver-to-Account delivery result

## 1. Purpose

Step 117 added the missing Receiver ingress and handoff diagnostics offline.
This step defines the controlled path to:

1. publish exactly one productive file;
2. update the private Symcon module without replacing instances;
3. prove that the new Receiver wrapper is available and read-only;
4. run exactly one bounded receive-only MQTT session;
5. compare Receiver and Account counter deltas;
6. cleanly disconnect, remove credentials and disable the experiment;
7. classify the receive gap without changing REST authority.

This document is a plan. It performs none of those operations.

## 2. Fixed Safety Boundary

All later execution remains subject to:

- REST remains the only authority for public mower variables;
- MQTT remains receive-only;
- no MQTT publish path is introduced or called;
- no module mower command is sent;
- no automatic connection retry occurs;
- no Core instance is created, replaced or deleted;
- the existing Account, Configurator, Device and Receiver are retained;
- the adopted native MQTT and WebSocket chain is retained;
- existing variable ObjectIDs, Idents, profiles, actions and values are
  retained;
- Archive Control logging and aggregation remain unchanged;
- no credential, endpoint, topic, payload, device identity or private ObjectID
  is copied into a public SAEF artifact;
- no tag is created;
- `MC_ReloadModule()` is not used.

Failure, timeout, ambiguity or interruption permits mandatory cleanup, never a
second Connect call in the same session.

## 3. Separate Authorization Gates

Approval of this planning step authorizes no publication, Symcon update,
connection or mower activity.

### Gate A: One-File Publication

Required authorization:

```text
Veröffentlichung der Receiver-Diagnostik freigegeben.
```

This permits:

1. copying the exact Receiver candidate into the standalone repository;
2. one reviewed commit;
3. one fast-forward push to `symcon-navimow/main`;
4. remote blob verification.

It permits no Symcon update.

### Gate B: Symcon Update and Read-Only Verification

Required authorization after Gate A passes:

```text
Symcon-Update und read-only Receiver-Diagnoseprüfung freigegeben.
```

The user updates the module through Module Control.

Codex may then use bounded Symcon MCP probes to inspect:

- installed library commit where available;
- instance and variable compatibility;
- Archive Control compatibility;
- Receiver wrapper availability;
- one read-only Receiver diagnostic result;
- existing Account diagnostics;
- final disabled and credential-empty MQTT state.

It permits no Connect call.

### Gate C: One-Shot Live Receive Test

Required authorization after Gate B passes:

```text
Ein einmaliger Receiver-Diagnose-Live-Test mit automatischem Cleanup ist freigegeben.
```

This permits exactly:

1. one experimental MQTT shadow enable;
2. one Connect invocation;
3. bounded read-only observation;
4. one Disconnect invocation;
5. credential cleanup;
6. final experimental disable.

It permits no retry or Symcon restart.

### Optional Physical Stimulus

Mower activity is not a module mutation and is not included implicitly in Gate
C.

If no scheduled mowing run is available, the user may:

1. keep the mower and work area in sight;
2. keep the official app and physical stop available;
3. start normal mowing manually in the official app;
4. confirm visible normal mowing before the single MQTT Connect call;
5. supervise the mower for the complete bounded session;
6. stop or send the mower home through the official app after transport
   cleanup.

Codex sends no Start, Resume, Pause, Dock or Stop command for this evidence
run.

## 4. Current Standalone Baseline

Established local publication clone:

```text
private/navimow-publish-20260708
```

Verified planning-time state:

```text
branch:      main
local HEAD:  efb8343e50dbea612db26e49324130ed3d039e90
origin/main: efb8343e50dbea612db26e49324130ed3d039e90
subject:     feat: expose bounded MQTT diagnostics
worktree:    clean
```

The canonical distribution and standalone repository contain the same 30
productive files.

Before Gate A execution, require:

```text
git fetch origin
git switch main
git pull --ff-only origin main
```

Local and remote heads must still match. Any remote advancement stops
publication for reclassification.

## 5. Exact Publication Delta

Canonical source:

```text
case-studies/navimow/distribution/
```

Exactly one productive file differs from standalone `main`:

```text
NavimowMqttReceiver/module.php
```

Planning-time hashes:

```text
candidate:
eb670775363010fc1b346e3bcfbe1d44b78a481305cf4aafc5190d4f062de726

standalone baseline:
bddb53b0b75dec11acff98de79c8bef53d9e5dc6b5dcad3400acb56604be95eb
```

The candidate change contains only:

- one private `ReceiveDiagnostics` attribute;
- the fixed `GetReceiveDiagnostics()` projection;
- bounded ingress and terminal-result accounting;
- fixed Receiver and Account result allowlists;
- malformed-state recovery and saturating counters.

The other 29 productive files must remain byte-equal.

Excluded from publication:

```text
case-study reports
tests
fixtures
tools
private evidence
.DS_Store
```

Any second productive file difference stops Gate A.

## 6. Candidate Validation

Before copying or staging, run:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
git diff --check -- case-studies/navimow
```

Require:

- PHP syntax success;
- all REST and MQTT fixture regressions;
- exact Receiver diagnostics projection;
- local-rejection and Account-handoff regressions;
- malformed and oversized diagnostic-state recovery;
- diagnostic privacy and output-size bounds;
- strict distribution validation;
- PHPCS;
- PHPStan;
- no MQTT publish implementation;
- no automatic instance creation or deletion;
- no `MC_ReloadModule()`;
- no new Device variable, profile, action or timer;
- no private installation data.

Run the official Symcon Module Validator against the complete candidate
library. A validator-page or cookie rendering failure must be classified
separately from an actual candidate schema failure.

Any real validation failure blocks publication.

## 7. Publication Procedure

After Gate A authorization:

1. revalidate the clean fast-forward standalone baseline;
2. recheck the candidate hash;
3. copy only `NavimowMqttReceiver/module.php`;
4. require `git status --short` to list exactly that file;
5. run PHP syntax, PHPCS, PHPStan and privacy checks again;
6. compare the complete 30-file manifest;
7. require 29 files to remain byte-equal;
8. stage only the Receiver file;
9. inspect the staged patch;
10. commit with:

```text
feat: expose bounded MQTT Receiver diagnostics
```

11. push `main` once;
12. verify the remote head;
13. fetch the remote Receiver blob;
14. require byte equality and the candidate SHA-256.

No tag is created.

## 8. Private Pre-Update Baseline

After publication and before Module Control update, capture a repeatable private
baseline under:

```text
private/navimow-capture/output/native-mqtt-receiver-diagnostics/
```

Capture:

- Account, Configurator, Device and Receiver instance IDs and module GUIDs;
- parent relationships and instance statuses;
- all Device variable IDs and metadata;
- Archive Control logging and aggregation for every logged variable;
- Account authentication booleans and connection state;
- MQTT feature-enabled boolean;
- Receiver selection and symmetric pairing boolean;
- native chain existence and Core status;
- WebSocket active boolean;
- credential-presence booleans only;
- exact-subscription equality and subscription count;
- Account bounded diagnostics;
- whether `NAVMQTTRX_GetReceiveDiagnostics` exists before update.

Repeat the baseline immediately. Both records must be structurally equal apart
from timestamps and expected current values.

Never capture raw:

- token or client secret;
- Authorization header;
- endpoint or hostname;
- MQTT username or password;
- client ID;
- topic;
- device ID;
- REST or MQTT payload.

## 9. Symcon Update Contract

The user performs the Module Control update manually.

Do not use:

```text
MC_ReloadModule()
```

After update, require:

- the same four productive module instances;
- unchanged instance IDs and parent relationships;
- unchanged Device variable IDs, Idents, types, profiles and actions;
- unchanged Archive Control logging and aggregation;
- Account authentication still usable;
- MQTT feature still disabled;
- WebSocket inactive;
- credential slots empty;
- Receiver selection and ownership unchanged;
- no new visible variable or action;
- `NAVMQTTRX_GetReceiveDiagnostics` available and callable.

Any identity, archive, authentication or topology drift blocks Gate C.

## 10. Read-Only Receiver Verification

While MQTT remains disabled, call the wrapper exactly once:

```text
NAVMQTTRX_GetReceiveDiagnostics(ReceiverInstanceID)
```

Validate:

- valid JSON;
- `formatVersion = 1`;
- exact fixed key set;
- all counters and timestamps are nonnegative bounded integers;
- `lastResult` is on the fixed allowlist;
- output is below 1024 bytes;
- no prohibited private field or string exists.

Historical counters may be zero because the attribute is new. No exact absolute
counter value is required.

Call `NAVAC_GetMqttDiagnostics()` once and retain only its existing bounded
projection.

Read both methods again only when required to prove read-only behavior. Their
results must not change due solely to diagnostic reads.

Evaluate Symcon MCP:

- transport success separately from PHP execution success;
- `truncated` explicitly;
- bounded output size;
- no source or private installation metadata in public documentation.

## 11. Frozen Live Harness

Before Gate C, prepare and review a private harness that:

- contains exactly one `NAVAC_ConnectMqttShadow` call;
- contains no MQTT publish;
- contains no mower command;
- contains no retry loop around Connect;
- reads both Receiver and Account diagnostics at every observation;
- records only fixed counters, codes, timestamps, booleans and Core statuses;
- has an absolute monotonic deadline;
- always executes Disconnect, credential cleanup and final disable;
- has an independently callable emergency-cleanup path;
- writes only below `private/`.

Freeze deterministic hashes for:

- enable preflight;
- one-shot Connect;
- observation;
- normal cleanup;
- emergency cleanup;
- orchestration source.

No live-session source edit is allowed after enable.

## 12. Physical-State Choice

Select exactly one predeclared option before Connect.

### Option A: Existing Scheduled Run

Preferred when the mower is already visibly mowing under its normal schedule.

Record only:

```text
physicalPhase = scheduled-running
supervised = true
```

### Option B: Manual Official-App Start

Allowed when no scheduled run is available.

The user starts normal mowing in the official app and confirms:

```text
physicalPhase = manually-started-running
supervised = true
```

The single Connect call occurs only after this confirmation.

Do not record garden geometry, zone, device identity or location.

If normal mowing cannot be confirmed, do not Connect.

## 13. Absolute Live Deadline

Use the established outer limit:

```text
hard active deadline: 180 seconds
observation cutoff:    165 seconds
cleanup reserve:        15 seconds
maximum poll interval:   5 seconds
```

The harness should terminate observation early once a conclusive result is
captured.

Success may therefore trigger immediate cleanup when:

```text
Receiver forwarded delta > 0
Account received delta > 0
Account accepted delta > 0
```

Local Receiver rejection may also trigger early cleanup after the relevant
counter and terminal code are observed.

No evidence condition extends the absolute deadline.

## 14. Live Execution Order

After Gate C authorization:

1. confirm supervision and physical-state option;
2. verify the frozen harness hashes;
3. capture Receiver and Account diagnostic baselines;
4. confirm experimental MQTT disabled;
5. confirm WebSocket inactive and credentials empty;
6. enable the retained adopted MQTT shadow once;
7. prove the lifecycle is `Ready` without broker activation;
8. invoke Connect exactly once;
9. observe native MQTT and WebSocket Core statuses;
10. observe Receiver and Account diagnostic deltas;
11. stop early on conclusive evidence or at the observation cutoff;
12. invoke Disconnect once;
13. clear WebSocket authorization and MQTT credentials;
14. disable the experimental MQTT shadow;
15. prove both Core transports inactive or safely disconnected;
16. prove credential slots empty;
17. capture final Receiver and Account diagnostics;
18. recheck variable and archive compatibility;
19. let the user end physical mowing through the official app when needed.

Cleanup is mandatory even when enable, Connect, observation or diagnostic
readback fails.

## 15. Delta Interpretation

Use values relative to the immediate pre-Connect baseline.

| Receiver result | Account result | Classification |
|---|---|---|
| `receiveCalls = 0` | `received = 0` | no child delivery or no broker publication |
| `receiveCalls > 0`, `forwarded = 0` | `received = 0` | Receiver-local rejection identified |
| `forwarded > 0` | `received = 0` | unexpected accounting or wrapper boundary defect |
| `forwarded > 0` | `received > 0`, `accepted = 0` | Account-side rejection |
| `forwarded > 0` | `accepted > 0` | productive native receive path proven |

Additional consistency requirements:

- `receiveCalls` must not decrease;
- `forwarded` must not exceed `receiveCalls`;
- Account `received` must not exceed Receiver `forwarded` for the session;
- every changed rejection counter must agree with `lastResult`;
- diagnostic reads alone must not alter counters.

## 16. Pass Criteria

### Publication Pass

- exactly one productive file committed and pushed;
- remote Receiver blob equals the canonical candidate;
- no tag created.

### Update Pass

- all instance and variable identities retained;
- all logging and aggregation retained;
- Receiver wrapper available and privacy-bounded;
- MQTT still disabled and credentials empty.

### Live Safety Pass

- exactly one Connect call;
- no retry or publish;
- cleanup begins before 165 seconds;
- cleanup finishes before 180 seconds;
- WebSocket inactive afterward;
- credential slots empty afterward;
- experimental MQTT disabled afterward.

### Receive Evidence Pass

Preferred complete pass:

```text
Receiver receiveCalls delta > 0
Receiver forwarded delta > 0
Account received delta > 0
Account accepted delta > 0
```

A Receiver-local rejection is a conclusive diagnostic finding but not a
productive receive pass.

Zero Receiver calls remains inconclusive between no broker publication and no
native child delivery. It does not authorize a retry in the same session.

## 17. Stop Conditions

Stop before Connect when:

- publication or installed version is ambiguous;
- wrapper shape or privacy validation fails;
- instance, variable or archive drift appears;
- authentication is unusable;
- ownership or pairing is invalid;
- transport chain differs from the adopted chain;
- credentials are already present unexpectedly;
- supervision is unavailable;
- the selected physical running state is not confirmed.

Begin cleanup immediately after Connect when:

- any credential or private payload appears in bounded output;
- an unexpected Core or topology mutation occurs;
- the mower requires physical intervention;
- the official app or stop control is unavailable;
- the observation harness fails;
- the deadline reserve is threatened.

## 18. Evidence Closure

Private machine-readable evidence must record:

- publication commit and file hash;
- pre/post-update compatibility;
- frozen harness hashes;
- selected physical-state option;
- Connect invocation count;
- timing and deadline measurements;
- Receiver and Account before/after projections;
- native Core status observations;
- cleanup and final disabled-state proof;
- variable and archive regression result.

The sanitized public report may contain only:

- bounded counter deltas;
- fixed result codes;
- elapsed timing;
- boolean safety and compatibility findings;
- no installation identifiers or payload-derived content.

No new public MQTT fixture is justified unless a new sanitized payload shape is
actually observed and required for parser regression. Receiver counters alone
do not create a payload fixture.

## 19. Gate Decision

Planning gate:

```text
COMPLETE
```

Publication:

```text
AWAITING GATE A AUTHORIZATION
```

Symcon update:

```text
BLOCKED UNTIL PUBLICATION PASS
```

Live connection:

```text
BLOCKED UNTIL UPDATE AND READ-ONLY PASS
```

Manual official-app start:

```text
OPTIONAL USER-CONTROLLED STIMULUS FOR GATE C ONLY
```

## 20. Planned Successor Steps

After explicit authorization, execute and document separately:

```text
119-native-mqtt-receiver-diagnostics-publication.md
120-native-mqtt-receiver-diagnostics-symcon-update-report.md
121-native-mqtt-receiver-diagnostics-live-test-report.md
```

Separating these reports preserves the exact authorization and mutation
boundary at every stage.
