# 130 Native MQTT Sibling Cross-Probe Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary publication, inactive staging and one-shot live test
planned; all execution gates closed
**Date:** 2026-07-28
**Scope:** Publish the frozen known-good probe temporarily, attach it as a
sibling to the retained MQTT Client, execute one bounded comparison and
restore verified `main`

## 1. Purpose

Step 129 implemented and validated the private sibling cross-probe harness.

This step defines the controlled path to:

1. publish the exact five-file known-good probe on a temporary branch;
2. update the private Symcon installation to that branch;
3. prove all productive contracts unchanged;
4. stage one inactive sibling probe on the retained MQTT Client;
5. execute one supervised receive-only comparison;
6. disconnect, disable and delete the probe automatically;
7. return Module Control immediately to standalone `main`;
8. prove variable, Archive Control and runtime restoration;
9. delete the temporary branch after complete closure.

This document performs none of those operations.

## 2. Fixed Safety Boundary

The following constraints apply to every later gate:

- REST remains the only authority for public Device variables;
- MQTT remains receive-only;
- no MQTT publish implementation or call is permitted;
- no Start, Pause, Resume, Dock or Stop command is permitted;
- the normal productive Connect is called at most once;
- the normal productive Disconnect is called at most once;
- no retry, reconnect experiment or Symcon restart is permitted;
- no Core instance is created, replaced, deleted or reparented;
- the productive Receiver is never reparented;
- only one temporary probe instance may be created;
- no Device variable, profile, action or timer is added;
- all existing variable and archive contracts are preserved;
- no tag or pull request is created;
- the experiment branch is never merged into `main`;
- `MC_ReloadModule()` is not used;
- private evidence remains below `private/`.

Failure, timeout, ambiguity or interruption permits cleanup and source
restoration, never another connection attempt.

## 3. Authorization Gates

Approval of this planning step authorizes no Git publication, Symcon update,
instance creation, broker connection or mower activity.

### Gate A: Temporary Probe Publication

Required authorization:

```text
Veröffentlichung des temporären MQTT-Sibling-Probe-Branches freigegeben.
```

This permits:

- creating one temporary branch from verified standalone `main`;
- adding exactly the five frozen probe files;
- committing and pushing only that branch;
- verifying remote commit and blobs;
- later deleting the branch after complete Symcon restoration.

It does not permit a Symcon update.

### Gate B: Symcon Update and Inactive Staging

Required authorization after Gate A passes:

```text
Symcon-Update und inaktives MQTT-Sibling-Probe-Staging freigegeben.
```

This permits:

- changing Module Control to the exact temporary branch once;
- repeated read-only compatibility checks;
- creating exactly one probe instance;
- connecting it as a child of the retained MQTT Client;
- inactive topology and wrapper verification;
- mandatory probe cleanup and return to `main` if Gate C does not follow.

It permits no credential retrieval, MQTT enablement or broker connection.

Gate-B authorization includes and requires rollback even if Gate C is never
opened.

### Gate C: One-Shot Live Cross-Probe

Required authorization after Gate B passes:

```text
Ein einmaliger MQTT-Sibling-Cross-Probe-Live-Test mit automatischem Cleanup und Rückkehr zu main ist freigegeben.
```

This permits exactly:

- one MQTT feature enable;
- one normal `NAVAC_ConnectMqttShadow()` invocation;
- bounded read-only observation of both children;
- one normal `NAVAC_DisconnectMqttShadow()` invocation;
- emergency transport cleanup only after normal cleanup failure;
- final MQTT disable;
- probe closure and deletion;
- immediate Module Control return to `main`;
- post-return compatibility verification.

It permits no retry.

### Physical Confirmation

Immediately before Gate C execution, the user must separately confirm:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

The mower may be running on schedule or may be started through the official
app. This confirmation does not authorize a module mower command.

## 4. Frozen Standalone Baseline

Local standalone clone:

```text
private/navimow-publish-20260708
```

Planning-time state:

```text
branch:      main
HEAD:        046529c518feefb15a51bd2f1c404401b3a7f474
origin/main: 046529c518feefb15a51bd2f1c404401b3a7f474
subject:     feat: expose bounded MQTT Receiver diagnostics
worktree:    clean
```

Before Gate A:

```text
git fetch --prune origin
git switch main
git pull --ff-only origin main
```

Require:

- local `main` equals `origin/main`;
- worktree clean;
- baseline commit exact;
- no local or remote branch with the selected name;
- current probe and harness hashes exact.

Any advancement or drift stops publication for revalidation.

## 5. Temporary Branch Contract

Selected branch:

```text
experiment/native-mqtt-sibling-cross-probe-20260728
```

Create it directly from verified `origin/main`.

Commit subject:

```text
test: add temporary MQTT sibling receive probe
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

No existing standalone file may change.

The branch contains no:

- private harness or evidence;
- credential;
- case-study report;
- productive source change;
- fixture;
- release metadata;
- tag.

## 6. Frozen Probe Hashes

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

These are the same known-good sources used by the successful step-94 native
receive probe.

## 7. Frozen Harness Hashes

```text
stage-inactive-sibling.php:
efee44fd8ddb052fa19ec0e32ac93a5d3600ed677dc307f13f3f5e05d21a4053

cleanup-inactive-sibling.php:
75e2f08e4bde866a1621260d1ce1abcd0d6a8c7a5004330f193cb769993e4803

live-one-shot.php:
3087b60f2d1fb02e3d20aedec47528c8c72e52da63e270909cd1da1fbe79701c

offline-test.php:
cccfdf938e2e1327232913b3d64491346c11ce8dbec625c2508e24f49fb62a23

validate.sh:
e34ce368f773649a01710e99cfc75240cd490b652b2b832fb87c51c86fea1c50
```

No source edit is permitted after the relevant execution gate opens.

## 8. Pre-Publication Validation

Re-run:

```text
private/navimow-capture/mqtt-sibling-cross-probe/validate.sh
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Require:

- all frozen hashes exact;
- PHP syntax and PHPCS pass;
- known-good probe regression pass;
- productive Receiver regression pass;
- four-outcome classifier pass;
- parent and implemented GUID equality;
- exactly one normal Connect call site;
- exactly one normal Disconnect call site;
- exactly one probe Arm, Close and Delete call site;
- zero MQTT publish;
- zero mower command;
- zero module reload;
- zero Core creation in the live harness;
- one probe Create and Connect in staging;
- private-material scan pass.

The existing publication-manifest helper from the original probe spike shall
be reused to prove:

- every standalone `main` file unchanged;
- no pre-existing target probe directory;
- exactly five reviewed probe files staged;
- every staged probe hash exact.

## 9. Publication Procedure

After Gate A:

1. fetch and revalidate `origin/main`;
2. require the exact baseline commit;
3. create the exact temporary branch;
4. stage the probe through the existing manifest gate;
5. require exactly five added files and zero modified/deleted files;
6. inspect the complete staged difference;
7. repeat syntax, PHPCS and metadata validation in the branch;
8. commit with the fixed subject;
9. push only the temporary branch;
10. fetch its remote reference;
11. require local and remote experiment commits equal;
12. require all five remote blobs to match the frozen hashes;
13. require `origin/main` unchanged;
14. record a private publication closure.

No force push is allowed.

## 10. Private Pre-Update Baseline

Before Module Control mutation, capture the established complete projection
twice.

Include:

- installed branch and commit;
- Account, Configurator, Device and Receiver identities;
- retained MQTT and WebSocket identities and connections;
- absence of probe instances;
- all 14 variable identities and metadata;
- all five Archive Control logging and aggregation contracts;
- archive history queryability;
- command evidence hash;
- Account authentication and token-usability booleans;
- Receiver binding and ownership validity;
- exact subscription-shape hash and count;
- WebSocket active boolean;
- credential-presence booleans;
- current bounded Receiver and Account diagnostics.

Never output raw configurations, ObjectIDs, identities, topics or values.

## 11. Module Control Update

After Gate B, invoke exactly once:

```text
MC_UpdateModuleRepositoryBranch(
  ModuleControl,
  "symcon-navimow",
  "experiment/native-mqtt-sibling-cross-probe-20260728"
)
```

Then verify in a new PHP execution:

- exact temporary branch and commit;
- repository clean and valid;
- probe wrappers available;
- productive wrappers available;
- all productive contract hashes unchanged;
- MQTT disabled and credential-empty;
- no probe instance yet.

Do not invoke:

```text
MC_ReloadModule()
```

Do not add `MC_UpdateModule()` when the branch operation has already installed
the target commit completely.

## 12. Inactive Sibling Staging

Execute the frozen staging source once:

```text
private/navimow-capture/mqtt-sibling-cross-probe/
  stage-inactive-sibling.php
```

It may:

- create one probe instance;
- configure its private expected Device ID in Symcon memory;
- connect it to the retained MQTT Client;
- apply and verify the probe.

It may not:

- change the Account configuration;
- retrieve credentials;
- activate the WebSocket;
- create or change Core instances;
- reparent the productive Receiver;
- create variables or logging.

After staging, repeat a read-only projection and require:

- exactly one probe;
- productive Receiver and probe share the retained MQTT parent;
- productive Receiver pairing unchanged;
- MQTT disabled;
- WebSocket inactive;
- credentials empty;
- all 14 variable and five archive contracts unchanged;
- probe report safe and inactive.

## 13. Gate-B Rollback

If Gate C does not immediately follow, execute:

```text
cleanup-inactive-sibling.php
```

Then:

1. prove the probe absent;
2. return Module Control to verified `main`;
3. prove temporary wrappers absent;
4. repeat the complete compatibility projection;
5. delete the temporary branch after evidence closure.

This rollback is mandatory and already included in Gate-B authorization.

## 14. Live Execution Contract

After Gate C authorization and physical confirmation, execute exactly once:

```text
private/navimow-capture/mqtt-sibling-cross-probe/
  live-one-shot.php
```

Use the bounded Symcon script-text channel and inspect separately:

- MCP transport success;
- `transportError`;
- `executionError`;
- `truncated`;
- decoded harness result.

The harness:

1. proves exact sibling topology;
2. proves disabled and credential-empty preconditions;
3. proves authentication usable;
4. captures both child baselines;
5. arms the known-good probe;
6. enables MQTT once;
7. invokes normal Connect once;
8. observes both children and native Core statuses every two seconds;
9. stops after first child ingress or before 165 seconds;
10. invokes normal Disconnect once in `finally`;
11. disables MQTT;
12. closes and reads bounded probe evidence;
13. uses emergency cleanup only after normal cleanup failure;
14. deletes the probe;
15. validates final runtime state.

## 15. Fixed Limits

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

No evidence condition extends the deadline.

## 16. Result Interpretation

Use deltas from the immediate pre-Connect baselines.

| Productive Receiver | Known-good probe | Classification | Interpretation |
|---:|---:|---|---|
| `> 0` | `> 0` | `both-received` | retained parent delivery works to both children |
| `0` | `> 0` | `probe-only` | parent delivery works; productive Receiver selection/invocation differs |
| `> 0` | `0` | `receiver-only` | productive path works; probe staging or arming is invalid |
| `0` | `0` | `neither-received` | gap remains before both children: retained Core, subscription or broker traffic |

Safety and hypothesis outcomes remain independent.

A positive result does not authorize MQTT to update public Device variables.

## 17. Runtime Cleanup Invariants

Before result interpretation, require:

- Disconnect called exactly once after Connect invocation;
- MQTT feature disabled;
- WebSocket inactive;
- authorization header empty;
- MQTT username and password empty;
- stable Client ID retained;
- exact subscriptions retained;
- productive Receiver retained and paired;
- probe evidence closed;
- probe instance deleted;
- cleanup before hard deadline;
- no emergency-cleanup failure.

Any failure blocks source rollback and branch deletion until runtime repair is
proven.

## 18. Immediate Return to Main

After runtime cleanup, regardless of result:

1. change Module Control to `main` exactly once;
2. verify commit `046529c5` or the revalidated exact Gate-A baseline;
3. do not use `MC_ReloadModule()`;
4. prove probe wrappers absent;
5. prove productive wrappers present;
6. prove probe instance absent;
7. prove MQTT disabled and credential-empty;
8. repeat the complete compatibility projection.

The temporary branch shall not remain installed overnight or across a Symcon
restart.

## 19. Post-Return Compatibility

Compare the repeated pre-update baseline with post-return evidence.

Require:

- same productive instance identities and connections;
- same 14 variable identities, types, profiles and actions;
- same five logging and aggregation contracts;
- archive history queryable;
- authentication usable;
- command evidence unchanged;
- Receiver pairing retained;
- exact subscription shape retained;
- no probe instance;
- no added variable, action or timer;
- MQTT disabled;
- WebSocket inactive;
- credentials empty;
- temporary module unavailable.

Current REST-owned Device values may legitimately advance during the test.
Identity and metadata contracts must not change.

## 20. Temporary Branch Deletion

Delete the branch only after:

- runtime cleanup passed;
- probe instance absent;
- Module Control returned to verified `main`;
- post-return compatibility passed;
- private evidence closure written.

Then:

1. delete the remote experiment branch;
2. fetch with prune;
3. verify remote branch absent;
4. switch the publication clone to clean `main`;
5. delete the local experiment branch;
6. verify `origin/main` unchanged.

The branch is never merged or tagged.

## 21. Evidence Closure

Private root:

```text
private/navimow-capture/output/
  native-mqtt-sibling-cross-probe/
```

Machine-readable evidence shall contain:

- authorization and physical-confirmation booleans;
- baseline and experiment commit hashes;
- five probe and five harness hashes;
- repeated pre-update projections;
- branch-update result;
- inactive staging result;
- both child baselines and bounded deltas;
- fixed classification;
- Core status classes and relative timings;
- cleanup and probe-deletion results;
- post-return projection;
- branch-deletion verification.

No public artifact may contain a credential, endpoint, topic, payload, client
ID, device ID, ObjectID or garden detail.

## 22. Abort Matrix

| Failure point | Required action |
|---|---|
| baseline commit or source drift | stop before branch creation |
| probe hash mismatch | discard branch, return clean `main` |
| remote `main` changes | stop and reclassify |
| post-update contract mismatch | return Module Control to `main` |
| staging failure | run inactive cleanup, return to `main` |
| physical mowing not confirmed | do not Connect; clean staging |
| ambiguous Connect result | one Disconnect and cleanup; no retry |
| MCP transport loss | allow local `finally` cleanup, then verify |
| probe deletion failure | repair before source rollback |
| runtime cleanup not proven | repair only; no branch deletion |
| post-return contract drift | investigate before closure |

## 23. Architecture Decisions

### AD-NAV-478: Republish exactly the proven five-file probe

**Decision:** Add the complete known-good probe directory without edits.

**Reason:** Omitting passive form or locale metadata would no longer reproduce
the previously validated module artifact.

### AD-NAV-479: Keep publication and staging separate

**Decision:** Gate A permits no Symcon update, and Gate B permits no broker
connection.

**Reason:** Remote source availability and live installation mutation require
separate evidence and consent.

### AD-NAV-480: Reuse retained Core instances

**Decision:** Add only a sibling child and create no temporary MQTT or
WebSocket Client.

**Reason:** The selected hypothesis concerns delivery from the retained MQTT
parent to different compatible children.

### AD-NAV-481: Bind Gate B to mandatory rollback

**Decision:** Staging authorization includes probe deletion and return to
`main`.

**Reason:** Cleanup must not depend on obtaining later live authorization.

### AD-NAV-482: Restore runtime before source

**Decision:** Delete the probe before Module Control returns to `main`.

**Reason:** The probe instance must not outlive the branch that provides its
module implementation.

## 24. Recommended Next Step

After Gate A authorization, create:

```text
131-native-mqtt-sibling-cross-probe-publication.md
```

That step should publish and remotely verify only the temporary five-file
branch, prove `origin/main` unchanged and stop before any Symcon operation.
