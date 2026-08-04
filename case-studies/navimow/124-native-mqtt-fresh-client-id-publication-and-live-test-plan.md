# 124 Native MQTT Fresh-Client-ID Publication and Live Test Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Temporary publication, Symcon update and live experiment planned;
all execution gates closed
**Date:** 2026-07-28
**Scope:** Publish the step-123 diagnostic patch temporarily, execute one
supervised receive-only experiment and restore standalone `main`

## 1. Purpose

Step 123 implemented and validated the fresh-client-ID experiment entirely
offline.

This step defines the controlled path to:

1. publish the exact one-file patch on a temporary branch;
2. update the private Symcon installation to that branch;
3. verify compatibility without connecting;
4. run one supervised receive-only fresh-client-ID session;
5. restore the stable client ID and disable MQTT automatically;
6. return Module Control immediately to standalone `main`;
7. prove source, instance, variable and Archive Control restoration;
8. delete the temporary branch only after complete closure.

This document performs none of those operations.

## 2. Fixed Safety Boundary

The following constraints apply to every later gate:

- REST remains the only authority for public Device variables;
- MQTT remains receive-only;
- no MQTT publish implementation or call is permitted;
- no Start, Pause, Resume, Dock or Stop command is permitted;
- no normal `ConnectMqttShadow()` call is permitted;
- the fresh-ID Connect wrapper is called at most once;
- no retry, reconnect or Symcon restart is permitted;
- no Core instance is created, replaced, deleted or reparented;
- the retained Receiver, MQTT Client and WebSocket Client are used;
- no persistent MQTT ownership or client identity is rotated;
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
broker connection or mower activity.

### Gate A: Temporary Branch Publication

Required authorization:

```text
Veröffentlichung des temporären Fresh-Client-ID-Branches freigegeben.
```

This permits:

- creating one temporary branch from the verified standalone `main`;
- applying the frozen private patch;
- committing exactly one changed productive file;
- pushing that branch once;
- verifying its remote commit and blob;
- later deleting the branch after complete Symcon restoration.

It does not permit a Symcon update.

### Gate B: Temporary Symcon Update and Read-Only Verification

Required authorization after Gate A passes:

```text
Symcon-Update auf den temporären Fresh-Client-ID-Branch und read-only Prüfung freigegeben.
```

This permits:

- changing the Module Control branch to the exact experiment branch;
- one normal module update;
- bounded read-only compatibility and wrapper checks.

It permits no experimental Connect call.

Authorization for this temporary update also authorizes and requires the
rollback update to `main`, even if Gate C is never opened or later fails. A
temporary diagnostic installation must not be left as the steady state.

### Gate C: One-Shot Live Experiment

Required authorization after Gate B passes:

```text
Ein einmaliger Fresh-Client-ID-Live-Test mit automatischem Restore und Rückkehr zu main ist freigegeben.
```

This permits exactly:

- one temporary MQTT feature enable;
- one fresh-ID Connect-wrapper invocation;
- bounded read-only observation;
- one Restore-wrapper invocation;
- emergency cleanup only when normal Restore fails;
- final MQTT disable;
- immediate Module Control return to `main`;
- post-return read-only compatibility verification.

It permits no retry.

### Physical Confirmation

Immediately before Gate C execution, the user must separately confirm:

```text
Mäher mäht sichtbar und ist beaufsichtigt.
```

The mower may already be running on schedule or may be started through the
official app. This confirmation does not authorize a module mower command.

## 4. Frozen Publication Baseline

Local standalone clone:

```text
private/navimow-publish-20260708
```

Planning-time state:

```text
branch: main
HEAD:   046529c518feefb15a51bd2f1c404401b3a7f474
subject:
feat: expose bounded MQTT Receiver diagnostics
worktree: clean
```

Account-module SHA-256:

```text
df820993599dded7962ae2998345db9694d44146ad33793a893e75454322fc3a
```

That hash is byte-equal to the current canonical case-study distribution.

Before Gate A, run:

```text
git fetch origin
git switch main
git pull --ff-only origin main
```

Require:

- local `main` equals `origin/main`;
- the worktree is clean;
- the Account hash remains exact;
- no existing local or remote experiment branch uses the selected name.

Any advancement or drift stops publication for revalidation. The patch shall
not be force-applied to an unknown base.

## 5. Temporary Branch Contract

Selected branch name:

```text
experiment/native-mqtt-fresh-client-id-20260728
```

Create it directly from the verified `origin/main`.

Apply:

```text
private/navimow-capture/fresh-client-id-experiment/account-module.patch
```

Only this productive file may change:

```text
NavimowAccount/module.php
```

Expected candidate SHA-256:

```text
04a69a573af052551e6e8202d4dd1057eeac063ef01128b9b52f4f89cc8aba2c
```

Commit subject:

```text
test: add temporary MQTT fresh-client-ID experiment
```

The branch contains no:

- case-study report;
- private harness;
- fixture;
- credential;
- tag;
- release metadata;
- productive `main` change.

## 6. Pre-Publication Validation

Re-run:

```text
sh private/navimow-capture/fresh-client-id-experiment/validate.sh
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Require:

- deterministic patch application;
- candidate hash equality;
- synthetic positive, restoration and credential-failure paths;
- PHP syntax;
- PHPCS;
- PHPStan;
- all existing REST and MQTT regressions;
- strict distribution validation;
- exactly two temporary public Account methods;
- one live fresh-ID Connect call site;
- one live Restore call site;
- no MQTT publish;
- no mower command;
- no module reload;
- no Core instance creation or deletion;
- no private-material match.

After applying the patch in the standalone branch, repeat syntax, PHPCS and
PHPStan directly against the branch file.

Any failure closes Gate A.

## 7. Publication Procedure

After Gate A authorization:

1. fetch and revalidate `origin/main`;
2. create the exact temporary branch;
3. apply the frozen patch;
4. require exactly one modified productive file;
5. require the expected candidate hash;
6. inspect the complete staged patch;
7. commit with the fixed subject;
8. push only the temporary branch;
9. fetch its remote reference;
10. require local and remote experiment commits to match;
11. require the remote Account blob to match the candidate hash;
12. require `origin/main` to remain at its baseline commit;
13. record a private publication closure.

No force push is allowed.

## 8. Private Pre-Update Baseline

Before changing Module Control, capture the established full compatibility
projection.

Include:

- installed library branch and commit where available;
- Account, Configurator, Device and Receiver module identities;
- retained Receiver, MQTT Client and WebSocket Client identities;
- parent and connection relationships;
- all 14 existing variable IDs, Idents and metadata;
- all five Archive Control logging and aggregation contracts;
- archive history queryability;
- Account authentication and token-usability booleans;
- command evidence hash;
- MQTT enabled boolean;
- ownership-valid boolean;
- exact subscription-shape hash and count;
- WebSocket active boolean;
- authorization-header presence boolean;
- MQTT username/password presence booleans;
- stable-client-ID presence and shape booleans;
- current bounded Receiver and Account diagnostics;
- absence of both temporary wrapper functions.

Repeat the baseline immediately. Structural hashes must match apart from
timestamps and expected current Device values.

Never output raw configuration or identity values.

## 9. Temporary Module Control Update

The user changes the installed module branch from:

```text
main
```

to:

```text
experiment/native-mqtt-fresh-client-id-20260728
```

Then the user performs one normal Module Control update.

Do not call:

```text
MC_ReloadModule()
```

No Account, Device, Configurator, Receiver or Core transport instance is
recreated.

## 10. Gate-B Read-Only Verification

After the temporary update and while MQTT remains disabled, require:

- experiment commit installed;
- Account-module source or wrapper surface matches the frozen candidate;
- both temporary wrappers exist and are callable;
- productive wrappers remain available;
- same module instances, IDs, parents and connections;
- same 14 variable identities and metadata;
- same five Archive Control contracts;
- archive history remains queryable;
- authentication remains usable;
- Receiver pairing retained;
- ownership remains valid;
- exact subscriptions retained;
- WebSocket inactive;
- authorization header empty;
- MQTT username and password empty;
- stable Client ID retained;
- MQTT experiment disabled;
- no Device value changed due to the update;
- command evidence hash unchanged.

Invoke neither temporary wrapper during Gate B.

Any mismatch triggers immediate return to `main` and closes Gate C.

## 11. Frozen Live Source

The only approved live orchestration source is:

```text
private/navimow-capture/fresh-client-id-experiment/live-one-shot.php
```

Frozen SHA-256:

```text
4622a5f9ae5c9c01db745c5b22e67d11be0ebf9dfa650fecca2e966d529cb06b
```

Before Gate C, recheck:

- exact source hash;
- exactly one fresh-ID Connect call site;
- exactly one Restore call site;
- zero normal Connect calls;
- zero MQTT publish calls;
- zero mower-command calls;
- zero module reload calls;
- zero instance creation/deletion calls.

No source edit is permitted after the physical confirmation.

## 12. Live Execution Contract

After Gate C authorization and physical confirmation, execute the frozen
source once through the bounded Symcon script-text channel.

MCP evaluation must separate:

- transport error;
- PHP execution error;
- output truncation;
- decoded harness result.

The output limit must accommodate the bounded observation list without
returning source or private configuration.

The harness itself:

1. proves disabled and credential-empty baseline state;
2. proves Account authentication usable;
3. proves exact subscriptions and topology;
4. captures Receiver baseline counters;
5. enables MQTT once;
6. proves owned state `ready`;
7. invokes the fresh-ID Connect wrapper once;
8. verifies the Client ID changed without outputting either value;
9. observes Receiver deltas and Core status every two seconds;
10. stops immediately after first Receiver ingress;
11. otherwise stops before 165 seconds;
12. invokes Restore in `finally`;
13. uses direct emergency cleanup only if Restore fails;
14. disables MQTT;
15. reports final cleanup booleans.

## 13. Absolute Deadline

Fixed limits:

```text
hard deadline:       180 seconds
observation cutoff:  165 seconds
cleanup reserve:      15 seconds
poll interval:         2 seconds
```

No evidence condition extends the deadline.

The experiment may finish in only a few seconds when Receiver ingress occurs.

## 14. Cleanup Invariants

Before any result interpretation, require:

- Restore wrapper called exactly once after Connect invocation;
- WebSocket inactive;
- authorization header empty;
- MQTT username empty;
- MQTT password empty;
- original stable Client ID restored;
- MQTT feature disabled;
- no emergency-cleanup failure;
- cleanup before the hard deadline.

Emergency cleanup use is a safety warning but may still recover the
installation. It blocks further live testing until separately reviewed.

If final credential or active-state cleanup cannot be proven, stop all
publication and branch-deletion work until the installation is repaired.

## 15. Result Interpretation

Use deltas from the immediate pre-Connect Receiver baseline.

| Result | Interpretation | Next decision |
|---|---|---|
| `receiveDelta > 0` | fresh Client ID changed native delivery outcome | client-session hypothesis strongly supported |
| `receiveDelta > 0`, `lastResult = pairing-rejected` | expected transport success at Receiver plus temporary ownership rejection | same conclusion |
| `receiveDelta = 0`, Core healthy | fresh ID alone did not resolve delivery | investigate retained Core instance or sibling child |
| `receiveDelta = 0`, Core unhealthy | experiment inconclusive | no retry |
| Connect wrapper failure | experiment inconclusive; internal restore expected | no retry |
| Restore or emergency cleanup failure | safety failure | repair only |

A positive result does not:

- authorize MQTT as public state authority;
- authorize automatic reconnect;
- authorize permanent identity rotation;
- authorize merging the diagnostic methods.

## 16. Immediate Return to Main

After runtime cleanup, regardless of receive result:

1. select branch `main` in Module Control;
2. perform one normal module update;
3. do not use `MC_ReloadModule()`;
4. prove the temporary wrappers are absent;
5. prove productive wrappers remain available;
6. prove the installed Account source matches standalone `main`;
7. prove MQTT disabled and WebSocket inactive;
8. prove credentials empty and stable Client ID retained;
9. prove ownership valid;
10. repeat the complete compatibility projection.

The diagnostic branch shall not remain installed overnight or across a Symcon
restart.

## 17. Post-Return Compatibility Gate

Compare pre-update and post-return evidence.

Require:

- same Account, Configurator, Device and Receiver IDs;
- same retained Core transport IDs and connections;
- same 14 variable IDs, Idents, types, profiles and actions;
- same five logging and aggregation contracts;
- archive history still queryable;
- authentication usable;
- command evidence unchanged;
- no added variable, action or timer;
- exact subscription shape retained;
- stable-client-ID shape restored;
- ownership valid;
- MQTT disabled;
- WebSocket inactive;
- credentials empty;
- temporary wrappers unavailable.

Current Device values may legitimately advance due to REST polling. Identity,
metadata, action and archive contracts must not change.

## 18. Branch Deletion

Delete the temporary branch only after:

- runtime cleanup passed;
- Module Control returned to verified `main`;
- temporary wrappers are absent;
- post-return compatibility passed;
- private evidence closure was written.

Then:

1. delete the remote experiment branch;
2. fetch with prune;
3. verify the remote branch is absent;
4. delete the local experiment branch;
5. remain on clean local `main`;
6. verify `origin/main` was never changed.

The experiment commit may remain reachable only through private evidence
metadata and ordinary remote retention behavior. It must not be tagged or
merged.

## 19. Evidence Closure

Private machine-readable evidence shall contain:

- base and experiment commit hashes;
- source and candidate hashes;
- pre-update repeatable compatibility snapshots;
- post-update compatibility snapshot;
- wrapper-availability booleans;
- physical phase and supervision booleans;
- live harness hash;
- bounded live result;
- cleanup result;
- post-return compatibility snapshot;
- main-restoration comparison;
- branch-deletion verification.

The public report shall contain only:

- authorization text;
- fixed source and commit identifiers;
- relative timings;
- Core status codes;
- Receiver deltas and fixed result codes;
- safety and cleanup classification;
- compatibility counts and pass/fail results;
- hypothesis update.

No public artifact may contain a private endpoint, topic, credential, client
ID, device ID, ObjectID, payload or garden detail.

## 20. Abort Matrix

| Failure point | Required action |
|---|---|
| base commit or hash drift | stop before branch creation |
| candidate hash mismatch | discard branch, return clean `main` |
| remote `main` changes during publication | stop and reclassify |
| temporary update compatibility failure | return Module Control to `main` |
| physical mowing not confirmed | do not Connect |
| wrapper result ambiguous | Restore and disable; no retry |
| MCP transport loss | allow local `finally` cleanup to finish, then verify |
| cleanup not proven | emergency repair, no branch deletion yet |
| post-return source mismatch | repair Module Control before closure |
| variable/archive drift | stop and investigate before branch deletion |

## 21. Architecture Decisions

### AD-NAV-453: Use a temporary branch, never main

**Decision:** Publish the one-file experiment only on a dated diagnostic
branch.

**Reason:** The methods are evidence tooling, not productive functionality.

### AD-NAV-454: Couple temporary installation with mandatory rollback

**Decision:** Gate-B authorization includes required return to `main`.

**Reason:** Cleanup must not depend on obtaining another approval after a
temporary module mutation.

### AD-NAV-455: Keep runtime cleanup before source rollback

**Decision:** Restore stable transport state before changing the installed
module source.

**Reason:** Only the temporary branch exposes the specialized Restore wrapper.

### AD-NAV-456: Delete the branch only after Symcon closure

**Decision:** Retain the remote branch until source and compatibility
restoration are proven.

**Reason:** Premature deletion could complicate deterministic repair or
read-back.

### AD-NAV-457: Treat safety and hypothesis outcomes separately

**Decision:** Report cleanup success independently from Receiver ingress.

**Reason:** A zero-ingress experiment can be operationally safe, while a
positive-ingress experiment can still fail cleanup.

## 22. Gate Decision

Planning:

```text
COMPLETE
```

Temporary branch publication:

```text
NOT AUTHORIZED
```

Temporary Symcon update:

```text
NOT AUTHORIZED
```

Live fresh-client-ID connection:

```text
NOT AUTHORIZED
```

Module `main`, REST authority and mower commands:

```text
UNCHANGED
```

## 23. Recommended Next Step

After explicit Gate-A authorization, create:

```text
125-native-mqtt-fresh-client-id-experiment-publication.md
```

That step should:

- publish only the frozen Account candidate to the exact temporary branch;
- verify remote byte equality;
- prove `origin/main` unchanged;
- record the private publication closure;
- stop before any Symcon update or broker connection.
