# 312 Automatic Pilot Closure Publication Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Exact local candidate frozen; publication and live gates remain
closed

**Date:** 2026-08-13

## 1. Decision

The automatic pilot-closure candidate from step 311 is ready for local Git
canonicalization and review.

The later standalone publication is limited to exactly one productive file:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
```

No other standalone file differs from the current verified
`symcon-navimow/main` baseline.

This step performs no commit, push, pull request, merge, standalone mutation,
Symcon access, MQTT activation, credential retrieval, OAuth action, restart or
mower action.

## 2. Source Baseline

```text
worktree:    private/worktrees/navimow-position-accounting-pilot-closure
branch:      codex/navimow-position-accounting-pilot-closure
HEAD:        a2ee1c727296fcf3a236b7fcf23085a96943c3e7
origin/main: a2ee1c727296fcf3a236b7fcf23085a96943c3e7
relationship: exact local equality before candidate changes
```

The step-311 implementation candidate contained seven Navimow paths. Including
this readiness report, the current deliberate candidate contains only:

```text
case-studies/navimow/README.md
case-studies/navimow/309-position-accounting-pilot-closure-and-recovery-review.md
case-studies/navimow/310-final-reconnect-exhaustion-and-automatic-closure-design.md
case-studies/navimow/311-automatic-pilot-closure-implementation.md
case-studies/navimow/312-automatic-pilot-closure-publication-readiness.md
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
```

No path outside `case-studies/navimow/` is modified.

## 3. Standalone Baseline

A fresh remote branch read on 2026-08-13 established:

```text
repository:        doctee/symcon-navimow
branch:            main
local HEAD:        50b365200e0c5c55990214c31f4a46f28b1406c7
local origin/main: 50b365200e0c5c55990214c31f4a46f28b1406c7
remote main:       50b365200e0c5c55990214c31f4a46f28b1406c7
worktree:          clean
```

A complete recursive comparison of the SAEF distribution with this standalone
tree found exactly one differing path and no additions or deletions.

## 4. Productive Freeze

| Artifact | SHA-256 | Git blob |
|---|---|---|
| standalone Account baseline | `f54dbbaa578f2b38309464c0521c18418978fb048de7c3ab8d49a2db5c6db3be` | `3ecedd9fcfcf586a270d7b094ed5c68161e47911` |
| automatic-closure candidate | `0b59e196c2c31ca0336c3485b7631b05bf5962cbe48bee4dfc9618ba5dc0564f` | `eb656eaac4fa618ba66412665b00387fb53058d9` |

The one-file diff contains:

```text
modified paths: 1
added paths:    0
deleted paths:  0
insertions:     446
deletions:      3
```

The ordered standalone publication manifest is:

```text
0b59e196c2c31ca0336c3485b7631b05bf5962cbe48bee4dfc9618ba5dc0564f  NavimowAccount/module.php
```

Manifest SHA-256:

```text
df414c072637c1c2c65dee2d17e6e197eebb6a1bcd6727b1fe9341bae6ea5267
```

Any productive byte change invalidates this freeze and returns the candidate to
step-311 review plus complete offline validation.

## 5. Supporting Freeze

| Artifact | SHA-256 |
|---|---|
| MQTT pilot checkpoint tests | `22097e3531dc4479b57a60e6ecb0a33e45c0a9b53da75d70e4530dbc2d7d7dc1` |
| MQTT transport lifecycle tests | `dfd5ed1626cdf599e9d970c6759037e5f7aeed5c66f2937e6686c4ef33046523` |
| step 309 closure review | `4485be05c6b5c5db393291a7db528ecd8e83e4bbb9b046146f2b834f5f44eba7` |
| step 310 closure design | `830a7e93a7a8b2fae89ab6844a3fb3153d330f173a69e0a3db1459b2c1808b40` |
| step 311 implementation report | `2897aca2bb2b185e55655178fc99da2e0623b9378d85e00e6ae5f339b5cb04e0` |

The README and this readiness report are checked as staged documentation at
Gate P1 rather than recursively hashing this report into itself.

## 6. Fixed Architecture Boundary

Every later gate must preserve:

```text
public device-state authority:       REST
MQTT direction:                      receive-only
MQTT default:                        disabled
MQTT publish path:                   absent
MQTT mower-command path:             absent
inner reconnect delays:              60 / 300 / 900 seconds
maximum reconnect attempts:          3
pilot maximum duration:              259200 seconds
recoverable episodes per session:    1
Account public variables:            unchanged
Device public variables:             unchanged
Archive logging identities:          unchanged
position coordinates after cleanup:  absent
```

Permanent MQTT operation, an outer half-open retry state and any change to
command integration remain out of scope.

## 7. Verification Evidence

The following checks passed in the isolated worktree:

- focused Navimow MQTT fixture, parser, ingestion, reconciliation, lifecycle,
  position and checkpoint suite;
- exact and delayed 72-hour deadline tests;
- restart-resume tests for `ClosureRequested`, `CredentialsCleared` and
  `PropertiesDisabled`;
- second-episode and reconnect-exhaustion closure tests;
- credential-first and one-`ApplyChanges()` assertions;
- distribution structure validation;
- PHPCS;
- PHPStan with the canonical dependency toolset;
- complete repository `composer check`; and
- `git diff --check`.

No public secret, token, topic, device identity, coordinate, ObjectID, hostname
or installation metadata is present in the candidate.

## 8. Reduced Approval Sequence

Mechanically coupled operations are grouped while publication and live-system
trust boundaries remain separate.

### Gate P1: SAEF candidate publication

One explicit authorization may cover:

1. fresh `origin/main` fetch and ancestry verification;
2. final exact-path and hash preflight;
3. focused plus complete offline checks;
4. stage exactly the eight documented paths;
5. inspect the complete staged diff and run `git diff --cached --check`;
6. create one Conventional Commit;
7. push the dedicated branch; and
8. open one pull request against SAEF `main`.

Suggested commit:

```text
fix(navimow): enforce automatic mqtt pilot closure
```

Gate P1 permits no merge, standalone mutation or Symcon access.

### Gate P2: SAEF review and merge

After terminal green checks, one authorization may cover review-ready marking,
merge by merge commit and canonical `origin/main` verification. It permits no
standalone or live mutation.

### Gate S1: Exact standalone publication and metadata conformance

One hash-bound authorization may cover:

1. fresh standalone fetch and exact clean-base proof at the then-current remote
   commit;
2. exact one-file copy from the canonical merged SAEF commit;
3. candidate and manifest hash verification;
4. syntax, distribution, privacy, receive-only and complete-tree checks;
5. one standalone commit and one fast-forward push;
6. remote tree and blob read-back; and
7. metadata conformance for all 13 published metadata files.

The generalized Symcon module fileset builder may generate and verify the
deterministic package. The Open-Meteo-specific publisher must not be reused for
Navimow. Until a generalized publisher has its own reviewed framework release,
the one-file copy remains guarded by the same expected-base, expected-hash and
exact-path preconditions.

Gate S1 permits no Symcon access, tag, MQTT activation or mower command.

### Gate L1: Disabled Symcon rollout

One explicit live authorization may cover:

1. bounded read-only preflight;
2. verification that MQTT and position diagnostics are disabled and the Core
   chain is credential-free;
3. exactly one supported `MC_UpdateModule()` call;
4. immediate read-only postflight; and
5. delayed read-only postflight.

It may not activate MQTT or position diagnostics. `MC_ReloadModule()` remains
prohibited.

### Gate L2: Bounded automatic-closure validation

A later separate activation gate may cover exactly one monitored receive-only
pilot on the exact installed commit. It requires fresh authentication readiness
and renewed persistence acceptance.

The native controller then owns mandatory closure on:

- the second distinct session episode;
- reconnect exhaustion; or
- the absolute 72-hour deadline.

The external observer remains a verification layer, not the sole cleanup owner.
Immediate and delayed credential-free postflight remain mandatory after native
closure. No synthetic network fault or mower command may be used to force a
stop condition.

## 9. Stop Conditions

Every later gate stops before mutation if:

- source ancestry or remote baseline differs from its fresh preflight;
- any path outside the exact allowlist appears;
- the productive hash or manifest differs;
- the full distribution differs from standalone in more than the documented
  Account file;
- MQTT publish or mower-command capability appears;
- REST authority, public variables, profiles or Archive contracts drift;
- a validation fails or evidence is truncated or ambiguous;
- live MQTT is not already disabled before Gate L1; or
- credential absence cannot be proved.

An ambiguous push or live result is resolved through fresh read-back. It never
causes a blind retry.

## 10. Gate Status

| Gate | Status |
|---|---|
| exact SAEF scope | FROZEN |
| exact one-file productive candidate | FROZEN |
| productive manifest hash | FROZEN |
| focused offline checks | PASS |
| complete repository check | PASS |
| Gate P1 commit, push and PR | CLOSED |
| Gate P2 review and merge | CLOSED |
| Gate S1 standalone publication and metadata | CLOSED |
| Gate L1 disabled Symcon rollout | CLOSED |
| Gate L2 bounded live validation | CLOSED |

## 11. Next Gate

The next useful authorization is Gate P1 for final preflight, one local commit,
branch push and pull-request creation. No standalone or live action belongs to
that gate.
