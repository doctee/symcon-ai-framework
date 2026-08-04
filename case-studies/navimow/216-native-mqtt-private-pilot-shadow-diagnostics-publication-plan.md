# 216 Native MQTT Private Pilot Shadow Diagnostics Publication Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Candidate frozen; publication, validation, Symcon update and all
pilot gates remain closed
**Date:** 2026-07-29
**Scope:** Plan deterministic one-file publication and disabled installation of
the version-2 MQTT-shadow diagnostics

## 1. Purpose

Step 215 completed the offline implementation and regression of the bounded
MQTT-hint projection.

This step:

1. verifies the standalone publication baseline;
2. freezes the exact productive delta;
3. excludes private and local artifacts;
4. defines publication and independent remote verification;
5. applies the established official-schema validation policy;
6. defines one disabled Symcon update and read-only version-2 check;
7. keeps inactive pilot initialization and MQTT activation as later gates.

This planning step performs no file copy into the standalone repository, Git
commit, push, browser validator run, Symcon access, MQTT activation, service
restart or mower action.

## 2. Fixed Architecture Boundary

The publication must preserve:

```text
REST: authoritative public device state
MQTT: receive-only diagnostic hint and REST reconciliation trigger
MQTT publication: prohibited
MQTT command path: absent
diagnostic variables: absent
coordinate retention: absent
feature default: disabled
```

The version-2 observation is read-only. It does not authorize a consumer to
write Device variables or make an automation decision from MQTT alone.

## 3. Standalone Baseline

The existing private standalone checkout is:

```text
repository:  doctee/symcon-navimow
branch:      main
commit:      8fdab84bd2a2190a6025cedd11f1ae6248369c0e
subject:     fix(mqtt): extend native core resume deadline
worktree:    clean
tracking:    main...origin/main
```

The current standalone Account file is:

```text
git blob SHA-1: c7d1dfeda3d6aa85841ff71859e81d2457398334
SHA-256:        6a4223b7480845f1113345bc4f3953e511916e725eb891c1c9d798539790e99f
```

This is a locally known baseline only. Immediately before publication, Gate A
must fetch `origin`, prune stale references and prove:

```text
local HEAD == origin/main
branch == main
worktree == clean
```

Remote drift or a dirty standalone worktree stops publication.

## 4. Frozen Candidate

The only productive candidate file is:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

Frozen candidate:

```text
git blob SHA-1: 2dce3f4651733e480dbeeec3fab713d80d0e3f01
SHA-256:        39fbc2183b0d5a119e2a4ba5cfdfcc81373b8a2f0b5be517a8c8cddb0cbbc069
```

Checksum-based comparison against the clean standalone checkout proves:

```text
content differences: NavimowAccount/module.php only
diff summary:         151 insertions, 1 deletion
all other files:      content-equal
```

Any candidate hash drift returns the process to step 215 review and complete
offline validation.

## 5. Local Artifact Finding

The SAEF distribution tree currently also contains local `.DS_Store` files.
They are not part of the productive candidate and must not be copied or
published.

The publication may not use an unrestricted directory synchronization.

It must copy exactly:

```text
distribution/NavimowAccount/module.php
  -> standalone/NavimowAccount/module.php
```

This one-file rule also prevents publication of:

- `private/`;
- pilot snapshots or state files;
- capture evidence;
- OAuth or MQTT data;
- local filesystem metadata;
- tests and fixtures;
- SAEF case-study documents;
- unrelated distribution drift.

No deletion of the local `.DS_Store` files is authorized by this plan.

## 6. Gate A: Standalone Publication

Gate A requires separate explicit authorization.

Recommended authorization wording:

```text
Veröffentlichung der MQTT-Shadow-Diagnostik v2 auf main freigegeben.
```

Gate A permits only:

1. fetch and verify the clean standalone `main`;
2. re-run the complete SAEF Navimow offline gate;
3. revalidate the frozen source hash;
4. copy the single Account module file;
5. prove standalone `git status --short` contains exactly that file;
6. verify candidate SHA-256 and Git blob hash in the standalone checkout;
7. run PHP syntax, PHPCS, PHPStan and distribution validation;
8. run a credential, topic, identity and local-path privacy scan;
9. inspect the exact one-file diff;
10. commit with a Conventional Commit message;
11. push `main`;
12. independently fetch and verify the remote commit and file hashes;
13. write bounded machine-readable evidence below `private/`;
14. create the sanitized SAEF publication report.

Recommended commit message:

```text
feat(mqtt): expose bounded shadow diagnostics
```

Gate A permits no:

- module tag or release;
- Symcon update or reload;
- Module Validator claim before it is run;
- MQTT credential retrieval;
- MQTT Connect or publish;
- service restart;
- REST probe against the live mower;
- mower command.

### Gate-A stop conditions

Stop before commit or push if:

- `origin/main` no longer equals the verified baseline;
- the standalone worktree is dirty;
- any file other than `NavimowAccount/module.php` changes;
- the candidate hash differs;
- `.DS_Store`, `private/` or another unexpected artifact appears;
- any offline, syntax, PHPCS, PHPStan or distribution test fails;
- the diff contains a credential, device identity, topic or coordinate;
- remote verification cannot prove the pushed content.

## 7. Gate-A Validation Contract

Before commit, the standalone candidate must satisfy:

```text
php -l NavimowAccount/module.php
PHPCS: PASS
PHPStan: PASS
distribution validation: PASS
exact source/standalone SHA-256 equality: PASS
exact source/standalone Git blob equality: PASS
privacy scan: PASS
changed file count: 1
```

The complete SAEF gate must also pass from the framework checkout:

```text
sh case-studies/navimow/tools/check-mqtt-shadow.sh
```

The private harness remains validated separately:

```text
sh private/navimow-capture/native-mqtt-private-pilot/validate.sh
```

Private harness files are evidence tooling, not standalone publication input.

## 8. Independent Remote Verification

After push, Gate A is not complete until a fresh fetch proves:

- remote `main` resolves to the recorded commit;
- the commit has one changed file;
- the changed path is `NavimowAccount/module.php`;
- remote file SHA-256 equals the frozen candidate;
- remote Git blob equals the frozen candidate;
- remote tree contains no `.DS_Store` or private artifact;
- the standalone checkout is clean after verification.

The publication report must record:

```text
baseline commit
published commit
commit subject
changed path
source and remote SHA-256
source and remote Git blob
validation results
privacy result
```

## 9. Gate B: Metadata Conformance

Gate B begins only after Gate A passes.

The preferred path is an official Symcon Module Validator run against the exact
published commit.

The established step-204 fallback remains valid if the browser UI reproduces
its known tool failure:

```text
official browser result
OR
exact unmodified official schemas executed with the validator-referenced
engine version
```

The fallback must record:

- exact published commit;
- hashes of all metadata inputs;
- exact official schema hashes;
- validation-engine version and hash;
- result for `library.json`;
- result for every `module.json`;
- distinction between browser tool failure and schema success.

A custom syntax-only validator is insufficient by itself.

No metadata file is expected to change in this publication. Any metadata drift
returns the process to offline review.

Gate B performs no Symcon mutation and grants no later gate automatically.

## 10. Gate C: Disabled Symcon Update

Gate C requires separate explicit authorization after Gates A and B pass.

Recommended authorization wording:

```text
Symcon-Update auf die MQTT-Shadow-Diagnostik v2 mit deaktiviertem MQTT
freigegeben.
```

Gate C permits:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
service restart:    0
```

Pre-update read-only evidence must prove:

- repository branch and installed commit;
- module clean and valid;
- MQTT feature disabled;
- lifecycle `Disabled`;
- WebSocket inactive;
- Authorization absent;
- MQTT username and password absent;
- REST authentication operational;
- 14 retained variables;
- 5 retained Archive Control logging entries;
- stable variable, archive, command, topology and subscription hashes.

Post-update evidence must prove:

- installed commit equals the Gate-A commit;
- repository remains clean and valid;
- all pre-update contracts remain equal;
- MQTT remains disabled and credential-free;
- `GetMqttDiagnostics()` returns `formatVersion = 2`;
- `shadow.observation.status = unavailable`;
- all observation values are `null`;
- two diagnostic reads do not mutate Account persistence;
- REST status remains authoritative and operational.

Gate C does not permit:

- `MC_ReloadModule()`;
- property mutation;
- `ApplyChanges()` for MQTT activation;
- credential retrieval;
- MQTT connection;
- restart;
- mower command.

## 11. Gate D: Inactive Preflight and Harness Initialization

Gate D is the deferred preflight originally planned after step 213.

It begins only after the exact published version-2 implementation is installed
and Gate C passes.

It requires separate read-only authorization and should:

1. run the frozen private Symcon projection twice at least 65 seconds apart;
2. prove equal disabled, credential-free contracts;
3. prove snapshot format version 2;
4. prove the MQTT hint is `unavailable`;
5. prove REST-authoritative state is present;
6. create the private pilot state file;
7. ingest both inactive projections;
8. verify `ready-for-acceptance`;
9. perform no activation, credential retrieval, restart or mower command.

The expected SAEF step after Gate C is:

```text
220-native-mqtt-private-pilot-inactive-preflight-and-harness-initialization.md
```

The number assumes separate publication, metadata and disabled-update reports.

## 12. Later Pilot Gates

Gate D does not authorize the pilot.

The remaining sequence stays:

```text
inactive preflight
  -> contextual persistence acceptance
  -> passive token-readiness check
  -> receive-only activation
  -> stable active baselines
  -> monitored 48-72-hour observation
  -> mandatory cleanup
  -> evidence closure
```

The step-212 operating policy remains authoritative for duration, mowing
cycles, credential rotation, stop conditions and cleanup.

## 13. Rollback

### Before push

If Gate A fails before commit, restore only the standalone Account file from
the clean standalone `HEAD`. Do not alter the SAEF candidate.

### After push but before Symcon update

Do not rewrite remote history. Correct the issue in SAEF, validate it and
publish a forward commit through a new gated step.

### After disabled Symcon update

If the version-2 diagnostic check fails while MQTT remains disabled:

1. collect bounded read-only diagnostics;
2. keep MQTT disabled and credential-free;
3. do not call `MC_ReloadModule()`;
4. stop before inactive preflight;
5. prepare a reviewed forward correction.

No rollback path may enable MQTT.

## 14. Evidence Closure

Each executed mutation gate needs:

- private machine-readable evidence;
- sanitized public SAEF report;
- exact commit and hash binding;
- explicit result for every authorized operation;
- explicit statement that MQTT remained disabled;
- explicit statement that no mower command occurred.

The diagnostic fixture remains synthetic. Live MQTT values, timestamps and
REST state belong only in bounded private pilot evidence.

## 15. Architecture Decisions

### AD-NAV-779: Publish exactly one file

**Decision:** Copy only `NavimowAccount/module.php` into the standalone
checkout.

**Reason:** Content comparison proves one productive delta, and the explicit
copy excludes local metadata and private evidence by construction.

### AD-NAV-780: Reject whole-directory synchronization

**Decision:** Do not use unrestricted `rsync`, recursive copy or archive
replacement for Gate A.

**Reason:** The SAEF distribution contains local `.DS_Store` files that are not
part of the module and must not reach the public repository.

### AD-NAV-781: Preserve the established validator fallback

**Decision:** Prefer the official UI but accept the exact-official-schema path
from step 204 when the known UI defect recurs.

**Reason:** Metadata conformance is normative; availability of one browser
presentation layer is not.

### AD-NAV-782: Use one supported Symcon update

**Decision:** Gate C permits one `MC_UpdateModule()` and zero
`MC_ReloadModule()` calls.

**Reason:** The established supported update path binds the installed tree to
the published commit without an unnecessary reload.

### AD-NAV-783: Verify diagnostics while MQTT is disabled

**Decision:** Prove version 2 and the empty `unavailable` projection before
inactive pilot initialization.

**Reason:** Schema compatibility and cleanup semantics can be tested without
credentials or broker contact.

### AD-NAV-784: Keep preflight after publication and installation

**Decision:** Initialize the private harness only against the exact installed
published candidate.

**Reason:** Pilot evidence must not begin from a local or unpublished module
variant.

### AD-NAV-785: Use forward correction after publication

**Decision:** Never rewrite public history to correct a published defect.

**Reason:** Deterministic evidence depends on immutable commit bindings.

## 16. Gate Matrix

| Gate | Mutation | Current decision |
|---|---|---|
| candidate freeze | none | PASS |
| one-file content boundary | none | PASS |
| local artifact exclusion design | none | PASS |
| A standalone publication | Git commit and push | CLOSED |
| B metadata conformance | none | CLOSED |
| C disabled Symcon update | one `MC_UpdateModule()` | CLOSED |
| D inactive preflight | private evidence only | CLOSED |
| persistence acceptance | none | NOT GIVEN |
| receive-only activation | property plus `ApplyChanges()` | CLOSED |
| 48-72-hour observation | none | CLOSED |
| mandatory cleanup | property plus `ApplyChanges()` | ARMED ONLY AFTER ACTIVATION |
| module tag or release | external side effect | CLOSED |
| MQTT publish | external side effect | PROHIBITED |
| mower command | physical side effect | PROHIBITED |

## 17. Next Step

After explicit Gate-A authorization, proceed with:

```text
217-native-mqtt-private-pilot-shadow-diagnostics-publication.md
```

That step may publish and remotely verify exactly the frozen one-file
productive delta. It must leave the Module Validator, Symcon update, pilot
initialization and MQTT activation closed.
