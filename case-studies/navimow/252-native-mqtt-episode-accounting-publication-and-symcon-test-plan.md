# 252 Native MQTT Episode Accounting Publication and Symcon Test Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Candidate frozen; publication, metadata validation, Symcon update
and MQTT activation remain separately closed

**Date:** 2026-08-03

**Scope:** Plan deterministic one-file publication and one disabled,
credential-free Symcon update for cumulative episode accounting and the bounded
pilot summary

## 1. Purpose

Step 251 locally implemented and validated:

- additive cumulative sequences in the detailed pilot projection;
- `NAVAC_GetMqttPilotSummary()` format version 1;
- a fixed encoded summary limit of 16384 bytes;
- all retained checkpoint coverage markers in reduced form;
- latest-only operational context without forensic arrays;
- distinct episode policy based on `episodeSequence`;
- diagnostic duplicate observations based on `unexpectedDisconnects`;
- fail-closed accounting for regression or impossible deltas;
- compatibility with inactive retained detailed history.

This step freezes the exact candidate and defines later mutation gates. It does
not publish, validate online metadata, access Symcon, activate MQTT or retrieve
credentials.

## 2. Fixed Architecture Boundary

Every later gate must preserve:

```text
public device-state authority:      REST
MQTT direction:                     receive-only
MQTT publish path:                  absent
feature default:                    disabled
disabled native credentials:        absent
reconnect delays:                   60 / 300 / 900 seconds
maximum reconnect attempts:         3
distinct episode source:            episodeSequence
disconnect observation source:      unexpectedDisconnects
summary format:                     version 1
summary maximum:                    16384 bytes
Account variables:                  6
Device variables:                   8
Archive Control contracts:          5
```

The process must not use:

```text
MC_ReloadModule()
```

Only one supported Module Control update is permitted at its separately
authorized gate.

## 3. Offline Validation Baseline

The focused candidate gate passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
fixtures and authentication:        PASS
MQTT parsing and ingestion:         PASS
shadow diagnostics/reconciliation:  PASS
pilot summary and accounting:       PASS
transport lifecycle:                PASS
distribution validation:            PASS
PHPStan:                             PASS
```

The private accounting harness passed:

```text
php private/navimow-capture/
  native-mqtt-private-pilot/offline-test.php
```

The complete repository gate also passed:

```text
make check
```

The synthetic maximum-width summary with 32 checkpoint markers encoded to 5835
bytes. A deliberately oversized projection was rejected.

## 4. Frozen Productive Candidate

Exactly one productive distribution file differs from the locally known
standalone baseline:

| Path | Candidate SHA-256 | Candidate Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |

Locally known standalone baseline:

```text
repository:  private/navimow-publish-20260708
remote:      doctee/symcon-navimow
branch:      main
commit:      79686e52f0bbaad77d37b9cd6e4b367797d96f2e
subject:     feat(mqtt): harden episode diagnostics
worktree:    clean
```

Baseline Account file:

| Path | Baseline SHA-256 | Baseline Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `74d24fbce5efd85a89eaa4253d6ec958969cd372d3e6bd43f9247211f8e16e37` | `cfa3028861e7b6343bde41a36bc65c4fd7e19f82` |

Baseline-to-candidate summary:

```text
modified:    1
added:       0
deleted:     0
insertions:  152
deletions:   0
```

A recursive comparison found no other distribution difference. Local `.git`
and `.DS_Store` content was excluded.

This is a planning-time local baseline. Gate A must fetch the remote again and
must not assume that `origin/main` is unchanged.

## 5. Frozen Supporting Evidence

Supporting files are SAEF-only and must not be copied to the standalone module:

| File | SHA-256 |
|---|---|
| `251-native-mqtt-episode-accounting-and-bounded-projection-implementation.md` | `84629e2ae285824e4776819d125bf16475020f991cd37711fad0e56a6b2a3f61` |
| `tests/mqtt-pilot-checkpoints.php` | `b51897b672e8f1fe1131325a8a66a458edc1b7feb69410d149301fec69ac37d4` |
| `fixtures/mqtt/episode-accounting-reconciled.json` | `b803799f8cf27dd4838ec105027fda235cf9ecb6aeacffc64b090f21ce9232c2` |
| private `PilotHarness.php` | `c2c74a84d470ad13d76f96bc58844c78269bb9b3d1e452298b2b77a647ab722d` |
| private `offline-test.php` | `0ec4658b9c71ef6e06a059a9904baca8cdee7a686da326b53659530b249b75ff` |
| private `symcon-readonly-probe.php` | `cf710da3cdb83c05ee8c916c0059d016699100e2cb7aee7928d4c0fb76ccbf36` |

Any candidate or supporting hash drift returns the process to implementation
review and the complete offline gate.

## 6. Publication Exclusions

Gate A may copy only:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> private/navimow-publish-20260708/NavimowAccount/module.php
```

Recursive synchronization and directory replacement are prohibited.

The standalone publication excludes:

- SAEF reports, tests, fixtures and private harnesses;
- every file below `private/` except the explicit local publication target;
- credentials, Authorization values and credential-presence evidence;
- ObjectIDs and installation metadata;
- MQTT topics, endpoints, payloads and device identities;
- local paths and filesystem metadata;
- `.DS_Store`;
- tags and releases.

## 7. Gate A: One-File Publication

Gate A requires separate explicit authorization.

Recommended wording:

```text
Veröffentlichung der MQTT-Episodenzählung und Pilotzusammenfassung auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone remote;
2. prove clean local `main` equals fetched `origin/main`;
3. stop if the fetched baseline is not the reviewed commit;
4. rerun the complete Navimow MQTT offline gate and private harness;
5. verify every frozen source and supporting hash;
6. copy exactly the frozen Account module;
7. prove exactly one modified path and no added or deleted path;
8. prove source and standalone SHA-256 and Git blob equality;
9. run PHP syntax, distribution, PHPCS and PHPStan checks;
10. run privacy and local-artifact scans;
11. inspect the complete one-file diff;
12. create one Conventional Commit;
13. push one fast-forward `main`;
14. fetch again and verify remote commit and blob;
15. close private machine-readable and sanitized public evidence.

Suggested commit:

```text
feat(mqtt): add bounded pilot episode summary
```

Gate A permits no tag, release, Symcon access, module update, MQTT activation,
credential request, service restart, REST live request or mower command.

### Gate-A stop conditions

Stop before commit or push if:

- fetched `origin/main` differs from the reviewed baseline;
- the standalone worktree is dirty;
- any second path differs;
- a frozen hash differs;
- validation or privacy scanning fails;
- a public MQTT writer or publish path appears;
- retry, authority, variable or archive contracts drift;
- the complete diff cannot be reviewed.

An ambiguous push result is resolved by fetch and hash comparison. It never
causes a blind second push.

## 8. Gate A Remote Verification

Publication passes only when a fresh fetch proves:

- remote `main` equals the recorded publication commit;
- the commit changes exactly `NavimowAccount/module.php`;
- the remote blob equals the frozen candidate blob;
- no private or local artifact entered the tree;
- the standalone worktree is clean;
- no tag or release was created.

The public report records only sanitized commit, path, hash and validation
facts. Exact local evidence remains below `private/`.

## 9. Gate B: Metadata Conformance

Gate B starts only after Gate A passes and requires separate authorization.

The official Symcon Module Validator is the preferred check against the exact
published commit. The candidate changes PHP only, but unchanged metadata still
requires validation.

Required inputs:

- `library.json`;
- all four `module.json` files;
- Account, Configurator, Device and Receiver form and locale JSON;
- exact published branch and commit;
- current official schemas or documented validator result;
- public repository privacy scan.

If the browser validator reproduces its known runtime or cookie-overlay
failure, the established freshly downloaded official-schema fallback may be
used. The report must distinguish validator-tool failure from schema
conformance.

Gate B performs no Symcon mutation.

## 10. Gate C: Disabled Symcon Update

Gate C begins only after Gates A and B pass and requires separate explicit
authorization.

Recommended wording:

```text
Symcon-Update auf die MQTT-Episodenzählung und Pilotzusammenfassung mit deaktiviertem MQTT freigegeben.
```

### 10.1 Transitional read-only preflight

The installed baseline does not yet expose
`NAVAC_GetMqttPilotSummary()`. The preflight therefore must not call that
wrapper and must not misclassify its absence as an installation failure.

Two bounded read-only baseline snapshots must establish through the existing
Account, module and Core contracts:

- exact installed branch and commit;
- module repository clean and valid;
- all four module instances present and compatible;
- MQTT feature disabled;
- MQTT and WebSocket inactive;
- Authorization, MQTT username and MQTT password absent;
- lifecycle `Disabled`;
- no pending reconnect or Core-resume observation;
- REST connected, operational and authoritative;
- reauthentication not required;
- 14 variable contracts unchanged;
- all 5 Archive Control contracts unchanged;
- command, topology and subscription hashes unchanged.

The existing detailed pilot endpoint is not used for routine preflight because
its retained forensic projection is known to exceed the 64-KiB operational
channel. Previously closed private step-248 and step-249 evidence remains the
forensic baseline; no historical array is silently compacted for this update.

Every MCP result must evaluate `transportError`, `executionError` and
`truncated` separately.

### 10.2 Private rollback evidence

Before the update, retain privately:

- installed commit and target publication commit;
- previous and candidate Account source/blob bindings;
- module, variable, archive, command, topology and subscription hashes;
- two stable disabled and credential-free snapshots;
- prior closed pilot evidence references;
- operation counters initialized to zero.

The previous public commit is not an automatic rollback instruction. Any
repository revert and second module update require a new explicit
authorization.

### 10.3 Authorized mutation

Gate C permits exactly:

```text
MC_UpdateModule():      1
MC_ReloadModule():      0
explicit ApplyChanges(): 0
service restart:        0
```

No update retry is allowed after an ambiguous result. Installed commit and
repository state must be read back first.

### 10.4 Immediate read-only verification

The post-update probe may now call
`NAVAC_GetMqttPilotSummary()` exactly as a read-only wrapper. It must prove:

- exact published target commit installed;
- module repository clean and valid;
- Account, Configurator, Device and Receiver status `102`;
- MQTT remains disabled and credential-free;
- MQTT and WebSocket remain inactive;
- lifecycle remains `Disabled`;
- REST remains operational and authoritative;
- all structure and archive hashes remain unchanged;
- summary format version is 1;
- encoded summary is no larger than 16384 bytes;
- all four cumulative sequences are non-negative integers;
- checkpoint markers are an array bounded to 32;
- latest and open context are bounded objects or `null`;
- full `episodes`, `rotations` and `coreTransitions` arrays are absent;
- summary operational counters agree with the established Account diagnostic
  counters;
- retained pilot state remains inactive with no open episode;
- no pilot timer or active session starts;
- no read mutates Registry, Statistics or public variables.

The detailed endpoint remains available for explicit forensic work, but it is
not transported through the routine bounded live probe.

### 10.5 Retained-history compatibility

The summary must preserve the cumulative sequence values and latest bounded
context represented by the previously closed step-249 evidence. Exact values
are compared privately and are not copied into a public installation report.

The update must not force a pilot Registry write merely to expose the new
projection. Two successive summary reads must therefore be byte-stable while
MQTT is disabled and no Account-owned MQTT counter changes.

### 10.6 Delayed verification

After at least 70 seconds, repeat the bounded probe and require:

- MQTT and WebSocket still inactive;
- credentials still absent;
- lifecycle still `Disabled`;
- no connection, reconnect, episode, rotation, ingress or Core-drop counter
  advanced;
- pilot cumulative sequences unchanged;
- summary remains within 16384 bytes and byte-stable;
- REST still operational;
- variable and archive hashes unchanged.

## 11. Gate-C Stop Conditions

Stop before or immediately after the single update if:

- preflight is not disabled and credential-free;
- the installed baseline is unexpected;
- target publication or metadata validation is incomplete;
- MCP transport, PHP execution or truncation is ambiguous;
- a second module update appears necessary;
- any module fails to load;
- the summary wrapper is missing after the update;
- summary format, size or accounting fields are invalid;
- any variable identity or archive setting changes;
- REST becomes unavailable;
- MQTT activates or credentials appear;
- retained sequence values regress or history becomes active;
- diagnostic reads mutate retained state;
- any private value appears in public diagnostics.

When the update completed but verification fails, do not improvise a rollback.
Keep MQTT disabled, preserve evidence and open a separately reviewed recovery
gate.

## 12. Evidence Closure

Every executed gate must produce:

- private machine-readable evidence below `private/`;
- one sanitized public SAEF report;
- exact authorization scope;
- candidate, baseline and resulting commit bindings;
- separate transport, execution and truncation results;
- exact mutation counts;
- immediate and delayed states;
- summary byte size and cumulative sequence validation;
- variable, archive, command, topology and subscription hashes;
- credential-presence booleans only;
- stop or cleanup outcome.

No public artifact may contain an ObjectID, credential, topic, payload,
hostname, device identity or installation path.

## 13. Architecture Decisions

### AD-NAV-945: Publish exactly one Account file

The recursive comparison proves that only the Account module differs from the
standalone baseline. SAEF tests and private tools remain outside publication.

### AD-NAV-946: Bind publication to source and Git-object hashes

Byte hash and blob equality prevent line-ending or copy drift between the
reviewed candidate and public repository.

### AD-NAV-947: Keep publication, metadata and update as separate gates

A valid local implementation does not authorize Git publication, and a valid
publication does not authorize a live module update.

### AD-NAV-948: Use a transitional pre-update probe

The installed baseline cannot expose a wrapper that does not yet exist. The
preflight uses only existing bounded contracts and never invokes the oversized
forensic endpoint through the routine channel.

### AD-NAV-949: Use the summary for routine post-update monitoring

The 16-KiB contract is the operational endpoint. The detailed endpoint remains
for separately bounded forensic analysis.

### AD-NAV-950: Verify sequence continuity without a storage write

Projection changes must not mutate retained pilot Registry data merely to
prove compatibility.

### AD-NAV-951: Use one supported update without reload

The only planned live mutation is one `MC_UpdateModule()` call. No
`MC_ReloadModule()`, explicit `ApplyChanges()` or restart is permitted.

### AD-NAV-952: Preserve user-owned variables and archive logging

All 14 variables and five Archive Control contracts must retain identity and
configuration across the update.

### AD-NAV-953: Keep disabled verification credential-free

The new projection can be verified without MQTT credentials, native transport
activation or an upstream connection.

### AD-NAV-954: Keep another pilot outside this plan

Publication and disabled compatibility do not authorize staging, activation,
restart testing or another private pilot.

## 14. Gate Status

| Gate | Status |
|---|---|
| candidate freeze | PASS |
| focused offline gate | PASS |
| complete repository gate | PASS |
| private harness accounting | PASS |
| Gate A publication | CLOSED |
| Gate B metadata conformance | CLOSED |
| Gate C disabled Symcon update | CLOSED |
| MQTT staging | CLOSED |
| MQTT activation | CLOSED |
| restart test | CLOSED |
| mower command | NOT PLANNED |

## 15. Next Step

After explicit Gate-A authorization, proceed with:

```text
253-native-mqtt-episode-accounting-publication.md
```

That step must execute only the frozen one-file publication and remote
verification. It must not access Symcon or authorize a later gate.
