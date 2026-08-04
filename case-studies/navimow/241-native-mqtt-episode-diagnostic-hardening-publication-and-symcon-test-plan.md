# 241 Native MQTT Episode Diagnostic Hardening Publication and Symcon Test Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Candidate frozen; publication, metadata validation, Symcon update
and MQTT activation remain separately closed

**Date:** 2026-07-31

**Scope:** Plan deterministic one-file publication and one disabled,
credential-free Symcon update for the episode diagnostics v2 candidate

## 1. Purpose

Step 240 locally implemented and validated:

- owned native MQTT and WebSocket status observation;
- bounded Core-transition evidence;
- separate reconnect, Core-ready and recovery-confirmation timestamps;
- MQTT ingress and REST context at episode detection;
- pilot diagnostic projection format version 2;
- lossless projection of retained version-1 episodes as `legacy`;
- preserved kernelstart, recovery, REST, variable and archive contracts.

This step freezes the exact candidate and defines the later mutation gates. It
does not publish, update Symcon, activate MQTT or access credentials.

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
authentication/configuration retry: none
pilot episode threshold:            unchanged
Account variables:                  6
Device variables:                   8
Archive Control contracts:          5
```

The new status callback is diagnostic only. It must not become a second
recovery owner.

The process must not use:

```text
MC_ReloadModule()
```

Only the supported one-time Module Control update is permitted at its
separately authorized gate.

## 3. Offline Validation Baseline

The complete candidate gate passed:

```text
case-studies/navimow/tools/check-mqtt-shadow.sh
```

Result:

```text
fixtures and REST authentication:   PASS
MQTT parsing and ingestion:         PASS
shadow diagnostics/reconciliation:  PASS
pilot diagnostics v2:               PASS
transport lifecycle:                PASS
distribution validation:            PASS
PHPCS:                              PASS
PHPStan:                            PASS
```

The private migration-compatible pilot harness also passed:

```text
php private/navimow-capture/
  native-mqtt-private-pilot/offline-test.php
```

## 4. Frozen Candidate

Exactly one productive distribution file differs from the locally known
standalone baseline:

| Path | Candidate SHA-256 | Candidate Git blob |
| --- | --- | --- |
| `NavimowAccount/module.php` | `74d24fbce5efd85a89eaa4253d6ec958969cd372d3e6bd43f9247211f8e16e37` | `cfa3028861e7b6343bde41a36bc65c4fd7e19f82` |

Locally known standalone baseline:

```text
repository:  private/navimow-publish-20260708
remote:      doctee/symcon-navimow
branch:      main
commit:      793249ece1c0944192ea28dade7ecd2340a5135f
subject:     feat(mqtt): add native pilot checkpoints
worktree:    clean
```

Baseline Account file:

| Path | Baseline SHA-256 | Baseline Git blob |
| --- | --- | --- |
| `NavimowAccount/module.php` | `c7726fbc1f822a85e714b122c912a9306f5d201cd140df04e8d018f15d36a12e` | `12658d4badd989580ee68431d577115339cbdf05` |

Baseline-to-candidate summary:

```text
modified:    1
added:       0
deleted:     0
insertions:  659
deletions:   20
```

A complete recursive comparison found no other distribution difference.
Local `.git` and `.DS_Store` content is excluded from that comparison.

This is a planning-time local baseline. Gate A must fetch the remote again and
must not assume that `origin/main` is still unchanged.

## 5. Frozen Supporting Evidence

Supporting files are SAEF-only and are not copied to the standalone module:

| File | SHA-256 |
| --- | --- |
| `tests/mqtt-pilot-checkpoints.php` | `e9fa2f711adb36d77bb5a24f8e8381cfa04ccc5fc53050006150e6a9bae9254a` |
| `tests/mqtt-transport-lifecycle.php` | `84f2821dc45b63935805859ce7b66707d92e5242ae6bda289ab3f592be66cd10` |
| `tests/harness/SymconRuntime.php` | `72994e4878cc67b47460edf11a60715423ff1adbc0ba0c6aaa07bfacfa06b1bd` |
| private `PilotHarness.php` | `939cfd8fb9f31f69351c4bcae68afa52e237871a3d5586026e6050353b5f43c9` |
| private `symcon-readonly-probe.php` | `d5b1ee2f059f036c2038aba159be0087ed8c48bb482741e71fe7fca664feda32` |

Any candidate or test hash drift returns the process to implementation review
and the complete offline gate.

## 6. Publication Exclusions

Gate A may copy only:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> private/navimow-publish-20260708/NavimowAccount/module.php
```

Recursive synchronization and directory replacement are prohibited.

The standalone publication excludes:

- SAEF reports, tests, fixtures and tools;
- all `private/` evidence;
- OAuth and MQTT credentials or presence values;
- ObjectIDs and installation metadata;
- MQTT topics, payloads and device identities;
- local paths and filesystem metadata;
- `.DS_Store`;
- tags and releases.

## 7. Gate A: One-File Publication

Gate A requires separate explicit authorization.

Recommended wording:

```text
Veröffentlichung der MQTT-Episoden-Diagnosehärtung auf main freigegeben.
```

Gate A permits only:

1. fetch and prune the standalone remote;
2. prove clean local `main` equals fetched `origin/main`;
3. stop if the fetched baseline is not the reviewed commit;
4. rerun the complete Navimow MQTT offline gate;
5. verify all frozen source and supporting hashes;
6. copy exactly the frozen Account module;
7. prove exactly one modified path and no added or deleted path;
8. prove source and standalone SHA-256 and Git blob equality;
9. run PHP syntax, distribution, PHPCS and PHPStan checks;
10. run privacy and local-artifact scans;
11. inspect the complete one-file diff;
12. create one Conventional Commit;
13. push one fast-forward `main`;
14. fetch again and verify the remote commit and blob;
15. close private machine-readable and sanitized public evidence.

Suggested commit:

```text
feat(mqtt): harden episode diagnostics
```

Gate A permits no:

- tag or release;
- Symcon access, update or reload;
- MQTT activation, connection or credential retrieval;
- REST live request;
- service restart;
- mower command.

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
published commit. Because the candidate changes PHP only, existing metadata is
expected to remain unchanged, but that expectation does not replace
validation.

Required checks:

- `library.json`;
- every `module.json`;
- Account, Configurator, Device and Receiver form and locale JSON;
- exact published branch and commit;
- current official schemas or documented validator result;
- no private artifacts in the published tree.

If the browser validator reproduces its known tool failure, the established
freshly downloaded official-schema fallback may be used. The report must
distinguish validator-tool failure from successful schema conformance.

Gate B performs no Symcon mutation.

## 10. Gate C: Disabled Symcon Update

Gate C begins only after Gates A and B pass and requires separate explicit
authorization.

Recommended wording:

```text
Symcon-Update auf die MQTT-Episoden-Diagnosehärtung mit deaktiviertem MQTT freigegeben.
```

### 10.1 Fresh read-only preflight

Two bounded read-only snapshots must establish:

- exact installed branch and commit;
- module repository clean and valid;
- all four module instances present and compatible;
- MQTT feature disabled;
- MQTT and WebSocket inactive;
- WebSocket Authorization absent;
- MQTT username and password absent;
- lifecycle `Disabled`;
- no pending reconnect or Core-resume observation;
- REST connected and operational;
- reauthentication not required;
- 14 variable contracts unchanged;
- all 5 Archive Control contracts unchanged;
- retained pilot history closed;
- `GetMqttPilotDiagnostics()` still reports the installed v1 contract.

The preflight must separately evaluate MCP transport error, PHP execution
error and truncation.

### 10.2 Private rollback evidence

Before the update, retain privately:

- installed commit;
- previous Account source/blob binding;
- module, variable, archive, topology and subscription hashes;
- the complete retained v1 pilot diagnostic projection;
- operation counters initialized to zero.

The known previous public commit is not an automatic rollback instruction.
Any repository revert and second module update require a new explicit
authorization.

### 10.3 Authorized mutation

Gate C permits exactly:

```text
MC_UpdateModule(): 1
MC_ReloadModule(): 0
explicit ApplyChanges(): 0
service restart: 0
```

No update retry is allowed after an ambiguous result. The installed commit and
repository state must be read back first.

### 10.4 Immediate read-only verification

The post-update projection must prove:

- exact published target commit installed;
- module repository clean and valid;
- Account, Configurator, Device and Receiver status `102`;
- MQTT remains disabled and credential-free;
- native MQTT and WebSocket remain inactive;
- REST remains operational and authoritative;
- variable, archive, command, topology and subscription hashes unchanged;
- `GetMqttDiagnostics()` retains its established format and shape;
- `GetMqttPilotDiagnostics()` reports format version 2;
- `coreTransitions` is present and bounded to 32;
- `coreStatusEventDrops` is present and non-negative;
- `openEpisode` remains `null`;
- no pilot timer or active session starts.

### 10.5 Retained v1 evidence compatibility

The v2 projection must preserve every valid historical value from the two
closed v1 episodes:

```text
sequence and session sequence
detectedAt and recoveredAt
detection source
MQTT and WebSocket status
reconnect attempts used
duration and outcome
rotation overlap
kernel epoch classification
```

For migrated history:

```text
diagnosticCompleteness: legacy
new timestamps:         0 unless actually present
coreTransitions:        []
```

The update must not force an artificial pilot registry write merely to change
its stored format. Read-only projection compatibility is sufficient while
MQTT remains disabled. A later productive v2 write may canonicalize retained
storage only under a separately authorized activation.

### 10.6 Delayed verification

After at least 70 seconds, repeat the bounded projection and require:

- MQTT and WebSocket still inactive;
- credentials still absent;
- lifecycle still `Disabled`;
- no transition, drop, connection, reconnect or ingress counter advanced;
- retained pilot projection byte-stable;
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
- any variable identity or archive setting changes;
- REST becomes unavailable;
- MQTT activates or credentials appear;
- v1 episode fields disappear or receive fabricated timestamps;
- diagnostic reads mutate retained state;
- any private value appears in public diagnostics.

When the update itself completed but verification fails, do not improvise a
rollback. Keep MQTT disabled, preserve evidence and open a separately reviewed
recovery gate.

## 12. Evidence Closure

Every executed gate must produce:

- private machine-readable evidence below `private/`;
- one sanitized public SAEF report;
- exact authorization scope;
- candidate, baseline and resulting commit bindings;
- separate transport, execution and truncation results;
- exact mutation counts;
- immediate and delayed states;
- variable, archive, topology and subscription hashes;
- credential-presence booleans only;
- cleanup or stop outcome.

No public artifact may contain an ObjectID, credential, topic, payload,
hostname, device identity or installation path.

## 13. Architecture Decisions

### AD-NAV-881: Publish exactly one productive Account file

The recursive distribution comparison identifies only the Account module as a
candidate delta. Tests, private harnesses and SAEF reports are excluded.

### AD-NAV-882: Keep publication and metadata validation separate

A correct Git publication does not prove Symcon metadata conformance. Each
result receives its own evidence and gate.

### AD-NAV-883: Use one supported update without reload

The live mutation is exactly one `MC_UpdateModule()` call. No
`MC_ReloadModule()`, explicit `ApplyChanges()` or service restart is planned.

### AD-NAV-884: Verify v1 history through the v2 projection

The disabled update must prove lossless historical compatibility without
forcing a diagnostic storage write.

### AD-NAV-885: Preserve user-owned variables and archive logging

The update is accepted only when all 14 variables and five Archive Control
contracts retain their identities and settings.

### AD-NAV-886: Keep disabled verification credential-free

No MQTT credential request or Core activation is needed to verify format v2,
legacy migration and module compatibility.

### AD-NAV-887: Keep future activation outside this plan

Publication and disabled compatibility do not authorize inactive staging,
MQTT activation, restart testing or another private pilot.

## 14. Gate Status

| Gate | Status |
| --- | --- |
| candidate freeze | PASS |
| complete offline gate | PASS |
| private harness compatibility | PASS |
| Gate A publication | CLOSED |
| Gate B metadata conformance | CLOSED |
| Gate C disabled Symcon update | CLOSED |
| MQTT staging | CLOSED |
| MQTT activation | CLOSED |
| restart test | CLOSED |
| mower command | NOT PLANNED |

## 15. Next Step

After explicit Gate-A authorization, the next SAEF step is:

```text
242-native-mqtt-episode-diagnostic-hardening-publication.md
```

It must execute only the frozen one-file publication and remote verification.
It must not access Symcon or authorize a later gate.
