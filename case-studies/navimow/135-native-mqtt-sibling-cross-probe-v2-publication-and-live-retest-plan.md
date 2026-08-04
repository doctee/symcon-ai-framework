# 135 Native MQTT Sibling Cross-Probe V2 Publication and Live Retest Plan

**Case study:** Navimow native IP-Symcon module
**Status:** V2 publication and live retest planned; all execution gates closed
**Date:** 2026-07-28
**Scope:** Temporarily republish the known-good sibling probe, execute the
contract-corrected private V2 harness once and restore verified `main`

## 1. Purpose

Step 133 executed the first sibling cross-probe safely but did not enter its
observation interval because the frozen V1 harness rejected the valid
asynchronous Connect result.

Step 134:

- preserved V1 unchanged;
- created a corrected V2;
- coupled V2 to the productive Account return contract;
- passed all offline and safety regressions.

This step defines a new controlled path to:

1. republish the unchanged five-file probe on a new temporary branch;
2. install that branch without enabling MQTT;
3. stage exactly one inactive sibling probe;
4. execute the corrected private V2 harness exactly once;
5. compare productive Receiver and known-good sibling ingress;
6. clean the runtime automatically;
7. restore Module Control to verified `main`;
8. prove variable and archive continuity;
9. delete the temporary branch.

This document performs none of those operations.

## 2. Fixed Engineering Boundary

The following constraints apply to all later gates:

- REST remains the only authority for public Device variables;
- MQTT remains receive-only and disabled by default;
- no MQTT publish implementation or invocation is permitted;
- no Start, Pause, Resume, Dock or Stop command is permitted;
- Connect and Disconnect may each be invoked at most once;
- no retry or reconnect is permitted;
- no Symcon restart is permitted;
- no Core MQTT or WebSocket instance may be created or replaced;
- the retained Core chain and productive Receiver may not be reparented;
- exactly one temporary sibling probe may be created;
- no productive variable, profile, action, timer or archive setting may change;
- `MC_ReloadModule()` is prohibited;
- the temporary branch may never be merged or tagged;
- exact private evidence remains below `private/`.

Failure, ambiguity, timeout or interruption permits only deterministic cleanup
and restoration.

## 3. Authorization Gates

Approval of this plan authorizes no Git publication, Symcon mutation, broker
connection or mower activity.

### Gate A: Temporary V2 Probe Publication

Required authorization:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-V2-Branches freigegeben.
```

Gate A permits:

- creating one new temporary branch from verified standalone `main`;
- adding the exact five frozen probe files;
- committing and pushing only that branch;
- remote commit and blob verification;
- mandatory later branch deletion.

It permits no Symcon update.

### Gate B: Symcon Update and Inactive V2 Staging

Required authorization after Gate A passes:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-V2-Staging freigegeben.
```

Gate B permits:

- one Module Control branch update;
- repeated read-only compatibility projections;
- creation of exactly one inactive sibling probe;
- connection of that probe to the retained MQTT Client;
- mandatory inactive cleanup and return to `main` if Gate C does not follow.

It permits no credential retrieval, MQTT enablement or broker connection.

### Gate C: One-Shot V2 Live Retest

Required authorization after Gate B passes:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-V2-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

Gate C permits exactly:

- one MQTT feature enable;
- one normal `NAVAC_ConnectMqttShadow()` invocation;
- bounded read-only observation of both compatible children;
- one normal `NAVAC_DisconnectMqttShadow()` invocation;
- emergency cleanup only if normal cleanup fails;
- final MQTT disable;
- probe closure and deletion;
- immediate return to verified `main`;
- post-return compatibility verification;
- temporary local and remote branch deletion after evidence closure.

It permits no retry.

### Physical Confirmation

Immediately before Gate C execution, require:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

Scheduled mowing or an official-app start is acceptable. This confirmation
does not authorize a module mower command.

## 4. Verified Baseline

Standalone publication clone:

```text
private/navimow-publish-20260708
```

Planning-time state:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
worktree:    clean
```

Before Gate A, fetch and require:

- clean local `main`;
- local `main` equal to `origin/main`;
- the revalidated exact baseline commit;
- no local or remote V2 experiment branch;
- exact probe and V2 harness hashes;
- byte equality between canonical and standalone Account modules.

Any drift stops Gate A.

## 5. Temporary Branch Contract

Selected branch:

```text
experiment/native-mqtt-sibling-cross-probe-v2-20260728
```

Fixed commit subject:

```text
test: republish temporary MQTT sibling receive probe
```

The branch adds only:

```text
NavimowMqttReceiveProbe/
  MqttReceiveProbeReducer.php
  form.json
  locale.json
  module.json
  module.php
```

No existing standalone file changes. The private V2 harness, contract test,
manifest and evidence are never published.

## 6. Frozen Probe Sources

```text
module.php:
408f16cf2755b1d80b8527a6c1fb3a4dce4a7882fdcc4743122ad403304f1e1e

module.json:
d7d5de3e18f00579db87a7cf8eb5937df17faf6932ea48d9ed2cf723d30de600

MqttReceiveProbeReducer.php:
06ee2e2408e645ba5d18490c30c9ceb2b303d25c929cb1e8d7ac50f3b91a48c9

form.json:
8280311f8ee8195682f73f62e87409ac2e8c09aea2a71aebeac847efadcdbddc

locale.json:
7ad31ae4213a95e25d515c35006e9271864d6ea2fa99cdd60bed9bd2847b4b61
```

These are unchanged from the successful disposable native receive probe and
the first sibling publication.

## 7. Frozen Private V2 Sources

```text
stage-inactive-sibling.php:
efee44fd8ddb052fa19ec0e32ac93a5d3600ed677dc307f13f3f5e05d21a4053

cleanup-inactive-sibling.php:
75e2f08e4bde866a1621260d1ce1abcd0d6a8c7a5004330f193cb769993e4803

live-one-shot-v2.php:
7c2d01c1cee8d5faf3bf33fd5956283308659c0a1193062b19220270b77ccc3e

connect-contract-test.php:
be0769f4c673953b7bcccceb00812254ef40dd40efacecf247e221acd2cf42d9

offline-test.php:
cccfdf938e2e1327232913b3d64491346c11ce8dbec625c2508e24f49fb62a23

validate.sh:
1087f338b742c2f1ef6dd42fae5f1f8cdea08ec0248aa51c9005159091c04a42
```

Historical V1 remains frozen separately and is not executable under this plan.

## 8. V2 Contract Gate

Before every later gate, execute:

```text
private/navimow-capture/mqtt-sibling-cross-probe/validate.sh
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Require:

- V1 historical hash unchanged;
- V2 and contract-test hashes exact;
- productive Account method contains
  `MQTT connection attempt started.`;
- V2 accepts exactly that result;
- V2 does not accept the invalid V1 result;
- canonical and standalone Account module hashes equal;
- PHP syntax and PHPCS pass;
- all probe and Receiver regressions pass;
- exactly one Connect and Disconnect call site;
- exactly one probe Arm, Close and Delete call site;
- zero MQTT publish, mower command, module reload and Core creation call sites;
- privacy scan pass.

This gate specifically prevents recurrence of the step-133 early-abort defect.

## 9. Gate-A Publication Procedure

After Gate A authorization:

1. fetch and prune the standalone remote;
2. switch to and fast-forward `main`;
3. require the exact revalidated baseline;
4. create the exact V2 experiment branch;
5. copy only the frozen five-file probe directory;
6. require five added and zero modified/deleted files;
7. validate syntax, PHPCS and module metadata;
8. inspect the complete staged diff;
9. commit with the fixed subject;
10. push only the temporary branch;
11. fetch and compare local and remote experiment commits;
12. compare all five remote blob hashes;
13. prove `origin/main` unchanged;
14. write private and sanitized publication evidence.

No force push, merge, tag or pull request is permitted.

## 10. Gate-B Pre-Update Baseline

Before Module Control mutation, execute the established complete read-only
projection twice.

It must record only sanitized hashes and booleans for:

- installed repository branch, commit, cleanliness and validity;
- Account, Configurator, Device and Receiver topology;
- retained MQTT and WebSocket topology;
- absence of probe instances;
- all 14 variable identities and metadata;
- all five Archive Control logging contracts;
- archive history queryability;
- command evidence;
- authentication and token usability;
- Receiver pairing and ownership validation;
- exact subscription shape;
- WebSocket and credential-empty state;
- bounded Receiver and Account diagnostics.

The two baselines must agree.

## 11. Gate-B Module Update

Invoke exactly once:

```text
MC_UpdateModuleRepositoryBranch(
  ModuleControl,
  "symcon-navimow",
  "experiment/native-mqtt-sibling-cross-probe-v2-20260728"
)
```

Do not invoke `MC_ReloadModule()` or a redundant `MC_UpdateModule()`.

In a new PHP process require:

- exact temporary branch and commit;
- repository clean and valid;
- temporary probe wrappers present;
- productive wrappers present;
- all productive contract hashes unchanged;
- MQTT disabled and credential-empty;
- no probe instance.

## 12. Gate-B Inactive Staging

Execute the unchanged frozen staging source once.

It may only:

- create one probe instance;
- derive and configure the private expected Device ID inside Symcon;
- connect the probe to the retained MQTT parent;
- apply and verify its inactive state.

It may not retrieve credentials, activate the broker chain, create Core
instances, reparent the productive Receiver or change a variable.

After staging require:

- exactly one inactive probe;
- probe and productive Receiver share the retained MQTT parent;
- no productive topology change;
- MQTT disabled;
- WebSocket inactive;
- headers and credentials empty;
- all variable and archive contracts unchanged;
- probe counters zero and acceptance disabled.

If Gate C does not follow, cleanup and return to `main` are mandatory under
Gate-B authorization.

## 13. Gate-C V2 Execution

After Gate C authorization and physical confirmation, execute exactly once:

```text
private/navimow-capture/mqtt-sibling-cross-probe/
  live-one-shot-v2.php
```

Use bounded `symcon_run_script_text_ex` and evaluate separately:

- MCP transport success;
- `transportError`;
- `executionError`;
- `truncated`;
- decoded harness result.

The V2 harness must:

1. prove exact inactive sibling topology and authentication;
2. capture Receiver and probe baselines;
3. arm the receive-only probe once;
4. enable the owned MQTT shadow once;
5. invoke normal Connect once;
6. accept only `MQTT connection attempt started.`;
7. enter the observation loop;
8. sample both children and Core status every two seconds;
9. stop after first child ingress or before 165 seconds;
10. invoke normal Disconnect once from `finally`;
11. disable MQTT and clear ephemeral credentials;
12. close and read bounded probe evidence;
13. delete the probe;
14. verify the complete final runtime state.

Acceptance of the Connect return proves only that the asynchronous attempt
started. It does not itself prove transport readiness or message delivery.

## 14. Fixed Runtime Limits

```text
broker connection attempts: 1
Connect calls:              1
Disconnect calls:           1
retries:                    0
poll interval:              2 seconds
observation cutoff:         165 seconds
hard deadline:              180 seconds
cleanup reserve:            at least 15 seconds
MQTT publishes:             0
mower commands:             0
```

No condition extends the deadline.

## 15. Result Classification

Use deltas from immediate pre-Connect baselines:

| Productive Receiver | Known-good probe | Classification | Meaning |
|---:|---:|---|---|
| `> 0` | `> 0` | `both-received` | retained parent delivers to both children |
| `0` | `> 0` | `probe-only` | parent delivery works; productive child path differs |
| `> 0` | `0` | `receiver-only` | productive child works; probe staging or arming differs |
| `0` | `0` | `neither-received` | gap remains before both children |

Additionally require at least one observation sample. A result with zero
samples is `inconclusive` regardless of counters.

Safety outcome and message-delivery outcome remain independent.

## 16. Mandatory Runtime Cleanup

Before interpreting delivery, require:

- Disconnect called exactly once after Connect invocation;
- MQTT feature disabled;
- WebSocket inactive;
- authorization headers empty;
- MQTT username and password empty;
- stable Client ID and subscriptions retained;
- productive Receiver retained and paired;
- probe evidence closed;
- probe instance deleted;
- no publish or mower command;
- cleanup completed before the hard deadline.

If cleanup is incomplete, repair only the runtime. Do not proceed to source
restoration until the probe no longer depends on the temporary module.

## 17. Return to Main

After successful runtime cleanup, regardless of delivery classification:

1. update Module Control to `main` exactly once;
2. verify the revalidated exact main commit;
3. prove repository clean and valid;
4. prove temporary wrappers absent;
5. prove productive wrappers present;
6. prove the probe instance absent;
7. prove MQTT disabled and credential-empty;
8. repeat the complete compatibility projection.

The temporary branch must not remain installed across a Symcon restart or
overnight.

## 18. Post-Return Compatibility

Compare post-return evidence with the repeated pre-update baseline.

Require:

- identical productive instance topology hash;
- identical 14-variable identity and metadata hash;
- identical five-variable archive contract hash;
- archive history remains queryable;
- identical command-evidence hash;
- authentication remains usable;
- Receiver pairing and subscription shape retained;
- no temporary instance, variable, action or timer;
- MQTT disabled and WebSocket inactive;
- headers and credentials empty.

REST-owned current values may advance naturally during mowing. Their identities
and metadata may not change.

## 19. Temporary Branch Deletion

Only after runtime cleanup, return to `main`, compatibility verification and
evidence closure:

1. switch the publication clone to clean `main`;
2. delete the remote V2 branch;
3. fetch with prune;
4. verify the remote branch absent;
5. delete the local V2 branch;
6. prove local `main` equals unchanged `origin/main`.

The branch is never merged or tagged.

## 20. Evidence Closure

Private evidence root:

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe-v2/
```

Record:

- explicit authorization and physical-confirmation booleans;
- baseline and temporary commit hashes;
- frozen probe, V2 and contract-test hashes;
- repeated pre-update compatibility projections;
- branch update and inactive staging results;
- Connect result and observation-sample count;
- both child baselines, deltas and fixed classification;
- Core status classes and relative timings;
- all cleanup outcomes;
- post-return compatibility projection;
- local and remote branch-deletion verification.

No public report may contain credentials, endpoints, topics, payloads, Client
IDs, Device IDs, ObjectIDs or garden details.

## 21. Abort Matrix

| Failure | Required action |
|---|---|
| baseline or source drift | stop before branch creation |
| V2 contract regression fails | stop before publication |
| remote `main` advances | revalidate and revise plan |
| publication mismatch | delete branch; keep Symcon on `main` |
| post-update compatibility mismatch | return Module Control to `main` |
| inactive staging failure | clean probe; return to `main` |
| mowing not confirmed | do not Connect; clean staging |
| Connect result differs | disconnect and clean once; no retry |
| zero observation samples | classify inconclusive; clean once |
| MCP transport loss | allow `finally`; verify separately |
| probe deletion failure | repair runtime before source rollback |
| post-return drift | investigate before branch deletion |

## 22. Architecture Decisions

### AD-NAV-483: Give the corrected run a new identity

**Decision:** Use a V2 source, manifest, branch and evidence root.

**Reason:** Historical V1 execution and corrected V2 execution must remain
unambiguous.

### AD-NAV-484: Republish unchanged probe files

**Decision:** Publish the exact known-good five-file probe again.

**Reason:** The old temporary branch was correctly deleted and must not be
reconstructed from an unverified remote object.

### AD-NAV-485: Require semantic contract validation

**Decision:** The productive Connect return and V2 expectation are checked
before every execution gate.

**Reason:** Call-count validation alone did not detect the V1 control-flow
defect.

### AD-NAV-486: Require at least one observation

**Decision:** Zero observation samples can never support a delivery
classification.

**Reason:** This explicitly prevents the step-133 result from being mistaken
for `neither-received`.

### AD-NAV-487: Keep REST authoritative

**Decision:** A positive sibling result changes only the MQTT transport
diagnosis.

**Reason:** It does not validate MQTT persistence, reconciliation or authority
over public Device variables.

## 23. Gate Decision

| Gate | Status |
|---|---|
| V2 offline readiness | PASS |
| Gate A publication | CLOSED |
| Gate B Symcon staging | CLOSED |
| Gate C broker attempt | CLOSED |
| MQTT production enablement | BLOCKED |
| REST state authority | RETAINED |

## 24. Recommended Next Step

After explicit Gate-A authorization, create:

```text
136-native-mqtt-sibling-cross-probe-v2-publication.md
```

That step shall publish and remotely verify only the exact five-file temporary
probe branch, prove `origin/main` unchanged and stop before any Symcon action.
