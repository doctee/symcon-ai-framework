# 254 Navimow MQTT Recovery Mainline Integration and Refreeze

**Case study:** Navimow native IP-Symcon module

**Status:** Local mainline integration, complete review, tests and candidate
refreeze passed; push, PR, standalone publication and all live gates remain
closed

**Date:** 2026-08-04

**Scope:** Execute Gate I from step 253 without remote publication or live
access

## 1. Result

The recovered receive-only MQTT workstream was integrated locally with the
freshly fetched canonical SAEF mainline. The merge completed without a
conflict. The complete effective Navimow scope was reviewed, all focused and
repository-wide checks passed, and the standalone publication candidate was
reproduced from the integrated clean worktree.

No branch was pushed, no pull request was opened, no standalone file was
changed, and Symcon was not accessed.

## 2. Integration Preconditions

The fresh preflight established:

```text
recovery branch:       codex/navimow-mqtt-recovery-clean
recovery commit:       f9eb640004fa8cb5645defa4097d71d6b122285c
step-253 commit:       5e0cd1eb90d598eebc3e3917bee73aaee905bb33
fetched origin/main:   7358fa5878869ff43ad30282f744bf78950c081a
merge base:            fd57c68617d09f7fceae03a2274d4a780073644d
worktree before merge: clean
mainline paths changed: 3
branch paths changed:   208
path overlap:           0
```

The 208 branch paths consisted of the recovered 207-path scope plus the newly
approved step-253 plan.

## 3. Local Merge

One normal merge of `origin/main` was performed:

```text
merge commit: b5d848503a1d537ce24d9bc6dc48bf9548861202
first parent: 5e0cd1eb90d598eebc3e3917bee73aaee905bb33
second parent: 7358fa5878869ff43ad30282f744bf78950c081a
strategy:     ort
conflicts:    0
```

No rebase, reset, checkout replacement, conflict resolver or history rewrite
was used.

Mutation counts:

```text
local documentation commits before merge: 1
local merge commits:                       1
branch pushes:                             0
pull requests:                             0
standalone publications:                   0
Symcon mutations:                          0
```

## 4. Effective Scope Review

Immediately after the merge and before adding this closure report, the branch
diff against integrated `origin/main` was:

| Review bucket | Paths |
|---|---:|
| numbered SAEF reports | 161 |
| Navimow case-study index | 1 |
| installable distribution | 17 |
| sanitized fixtures | 13 |
| forum documentation | 2 |
| offline tests | 11 |
| case-study tools | 3 |
| **Total** | **208** |

Git change classes were:

```text
added:     194
modified:   14
deleted:     0
```

Every effective path remained below `case-studies/navimow/`. No unrelated
workstream path entered the branch.

Adding this step-254 report produces the expected final review scope:

```text
paths:      209
added:      195
modified:    14
deleted:      0
```

## 5. Case-Study Sequence Review

The complete case study contains:

```text
numbered reports: 253 before this report
unique numbers:   253
missing numbers:  0
duplicate numbers: 0
README omissions: 0
README duplicates: 0
```

After adding this report, steps 1 through 254 remain continuous and indexed
exactly once.

## 6. Product Boundary Review

The integrated distribution preserves:

```text
public state authority:        REST
MQTT direction:                receive-only
MQTT publish function:         absent
MQTT mower-command route:      absent
feature default:               disabled
reconnect delays:              60 / 300 / 900 seconds
maximum reconnect attempts:    3
Account variables:             6
Device variables:              8
Archive Control contracts:     5
pilot summary maximum:         16384 bytes
```

The only `SendDataToParent()` calls found in the distribution belong to the
existing internal Configurator/Device-to-Account REST module chain. Neither the
Account MQTT lifecycle nor the MQTT Receiver exposes a publish path.

No shared helper, generated bundle, deployment channel or restart boundary was
changed by the Navimow workstream.

## 7. Validation Evidence

The focused Navimow MQTT gate passed:

```text
fixtures and REST authentication:   PASS
native envelope and parser:         PASS
receive-only payload handling:      PASS
Receiver diagnostics:               PASS
Account ingestion:                  PASS
shadow diagnostics/reconciliation:  PASS
pilot checkpoint and accounting:    PASS
transport lifecycle:                PASS
distribution structure:             PASS
PHPStan:                             PASS
```

Additional gates:

```text
private pilot harness offline test: PASS
complete make check:                PASS
git diff check:                     PASS
report sequence and index:          PASS
Navimow-only path ownership:        PASS
receive-only scan:                  PASS
privacy and local-artifact scan:    PASS
```

The privacy scan found only documented generic header terminology and explicit
synthetic test placeholders. It found no credential, private host, private IP
address, personal ObjectID, local absolute path, MQTT topic or device identity.

## 8. Standalone Baseline

The standalone module repository was fetched again and remained clean:

```text
branch:       main
HEAD:         79686e52f0bbaad77d37b9cd6e4b367797d96f2e
origin/main:  79686e52f0bbaad77d37b9cd6e4b367797d96f2e
subject:      feat(mqtt): harden episode diagnostics
```

No standalone file, commit, branch or tag was changed.

## 9. Refrozen Productive Candidate

A fresh recursive comparison between the integrated SAEF distribution and the
clean standalone baseline found exactly one difference:

| Path | Candidate SHA-256 | Candidate Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4` | `af1d4dd9094ca10a12f0ee264041ee47b7dc19cb` |

Standalone baseline:

| Path | Baseline SHA-256 | Baseline Git blob |
|---|---|---|
| `NavimowAccount/module.php` | `74d24fbce5efd85a89eaa4253d6ec958969cd372d3e6bd43f9247211f8e16e37` | `cfa3028861e7b6343bde41a36bc65c4fd7e19f82` |

Reproduced delta:

```text
modified:    1
added:       0
deleted:     0
insertions:  152
deletions:   0
```

The productive candidate hash and blob match the planning-time values from
step 252, but this result was independently reproduced after mainline
integration and is therefore the authoritative refreeze.

## 10. Refrozen Supporting Evidence

| File | SHA-256 |
|---|---|
| `251-native-mqtt-episode-accounting-and-bounded-projection-implementation.md` | `84629e2ae285824e4776819d125bf16475020f991cd37711fad0e56a6b2a3f61` |
| `252-native-mqtt-episode-accounting-publication-and-symcon-test-plan.md` | `be2a1cf21e6b22fd0bf474cd4cae88dbbcf52c4a032b03fb2d5de30d0dbd5066` |
| `253-navimow-mqtt-recovery-mainline-integration-plan.md` | `a502a93f3de44e050ad43b3254376980509647728d139da3061d422d87968448` |
| `tests/mqtt-pilot-checkpoints.php` | `b51897b672e8f1fe1131325a8a66a458edc1b7feb69410d149301fec69ac37d4` |
| `fixtures/mqtt/episode-accounting-reconciled.json` | `b803799f8cf27dd4838ec105027fda235cf9ecb6aeacffc64b090f21ce9232c2` |
| private `PilotHarness.php` | `c2c74a84d470ad13d76f96bc58844c78269bb9b3d1e452298b2b77a647ab722d` |
| private `offline-test.php` | `0ec4658b9c71ef6e06a059a9904baca8cdee7a686da326b53659530b249b75ff` |
| private `symcon-readonly-probe.php` | `cf710da3cdb83c05ee8c916c0059d016699100e2cb7aee7928d4c0fb76ccbf36` |

Any future drift requires the complete candidate and test gates to run again.

## 11. Gate Results

| Gate | Result |
|---|---|
| fresh current-main preflight | PASS |
| path-overlap review | PASS, zero overlap |
| local merge | PASS, no conflict |
| effective Navimow scope review | PASS |
| case-study sequence and index | PASS |
| focused Navimow checks | PASS |
| complete repository check | PASS |
| privacy and receive-only review | PASS |
| candidate refreeze | PASS |
| branch push | NOT EXECUTED |
| pull request | NOT CREATED |
| standalone publication | NOT EXECUTED |
| Symcon access | NOT PERFORMED |

## 12. Architecture Decisions

### AD-NAV-965: Accept the conflict-free normal merge

Both reviewed parents are preserved, no path overlaps and the complete
post-merge validation is green.

### AD-NAV-966: Explain the scope increase from 207 to 209

The recovered product scope remains 207 paths. Steps 253 and 254 add two
reviewed process reports without changing the product distribution.

### AD-NAV-967: Treat the integrated diff as Navimow-only

Every effective branch path against current `origin/main` remains under the
Navimow case-study boundary.

### AD-NAV-968: Preserve REST authority after integration

The current-main merge introduced no Navimow content and did not alter the
module's state-authority contract.

### AD-NAV-969: Reconfirm receive-only behavior by source and tests

Absence of a publish route and passing ingestion/lifecycle tests jointly prove
the intended transport direction.

### AD-NAV-970: Accept synthetic privacy markers

Explicit synthetic fixture values are regression inputs, not private data.
Actual credentials and installation metadata remain prohibited.

### AD-NAV-971: Refreeze from the integrated worktree

The reproduced one-file delta, hashes and blob identities supersede the
planning-time candidate binding.

### AD-NAV-972: Keep the standalone baseline unchanged

Candidate comparison required only fetch and read operations. Publication
remains a later explicit gate.

### AD-NAV-973: Require a separate remote branch-publication gate

Local integration does not authorize pushing the 209-path workstream or opening
a pull request.

### AD-NAV-974: Preserve every live boundary

No repository integration result authorizes metadata validation, Symcon update,
credential retrieval, MQTT activation, restart or mower command.

## 13. Current Gate Status

| Gate | Status |
|---|---|
| Gate I local mainline integration | PASS |
| post-merge scope review | PASS |
| post-merge tests | PASS |
| candidate refreeze | PASS |
| Gate II branch push and PR | READY, AUTHORIZATION REQUIRED |
| Gate III canonical-main verification | CLOSED |
| standalone publication | CLOSED |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 14. Next Step

After explicit Gate-II authorization, proceed with:

```text
255-navimow-mqtt-recovery-branch-publication-and-pr.md
```

That step may push only `codex/navimow-mqtt-recovery-clean` and open one pull
request against SAEF `main`. It must not merge the pull request, publish the
standalone module or access Symcon.
