# 320 Transport Incident Publication Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Exact local candidate frozen; publication, rollout and live gates
remain closed

**Date:** 2026-08-17

## 1. Decision

The transport-incident reducer from step 319 is ready for local Git
canonicalization and pull-request review.

The productive standalone delta is limited to exactly one file:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
    -> NavimowAccount/module.php
```

A complete comparison of all 31 distribution files against the current clean
standalone baseline found no other difference. This step performs no commit,
push, pull request, merge, standalone mutation, Symcon access, MQTT activation,
credential retrieval, OAuth action, restart or mower command.

## 2. SAEF Source Baseline

The isolated worktree was fetched and inspected on 2026-08-17:

```text
worktree:     private/worktrees/navimow-position-accounting-pilot-closure
branch:       codex/navimow-position-accounting-pilot-closure
HEAD:         9d48cc2e08d039de34b03316414a04ba3edbb2e4
origin/main:  b5b303f31b4458c8d43185d088c313cf3e53bb97
merge base:   9d48cc2e08d039de34b03316414a04ba3edbb2e4
ahead/behind: 0 / 1
```

`origin/main` is the merge commit for PR #43 and contains the worktree HEAD
without a tree delta. The candidate is therefore not divergent, but the merged
branch must not be reused directly for publication. Gate P1 must first
fast-forward to the exact current `origin/main`, create a new dedicated branch
and then retain the unchanged working candidate.

Including this report, the deliberate local candidate contains exactly these
12 paths:

```text
case-studies/navimow/README.md
case-studies/navimow/313-automatic-pilot-closure-disabled-symcon-rollout.md
case-studies/navimow/314-automatic-pilot-closure-l2-readiness-abort.md
case-studies/navimow/315-automatic-pilot-closure-l2-activation-safe-abort.md
case-studies/navimow/316-automatic-pilot-closure-l2-corrected-activation.md
case-studies/navimow/317-automatic-pilot-closure-live-result.md
case-studies/navimow/318-transport-incident-grace-and-recovery-design.md
case-studies/navimow/319-transport-incident-reducer-implementation.md
case-studies/navimow/320-transport-incident-publication-readiness.md
case-studies/navimow/distribution/NavimowAccount/module.php
case-studies/navimow/tests/mqtt-pilot-checkpoints.php
case-studies/navimow/tests/mqtt-transport-lifecycle.php
```

No path outside `case-studies/navimow/` may enter the candidate.

## 3. Standalone Baseline

The standalone publication clone and its remote were independently verified:

```text
repository:        doctee/symcon-navimow
branch:            main
local HEAD:        888325d8649160c5bae473f4f8a052cf86e703b6
local origin/main: 888325d8649160c5bae473f4f8a052cf86e703b6
remote main:       888325d8649160c5bae473f4f8a052cf86e703b6
worktree:          clean
distribution files: 31
```

The baseline is the automatic-closure release installed for step 317. A later
publication must fetch the standalone remote again and stop if its main commit
is no longer this exact value unless the complete-tree comparison and freeze
are deliberately repeated.

## 4. Productive Freeze

| Artifact | SHA-256 | Git blob |
|---|---|---|
| standalone Account baseline | `0b59e196c2c31ca0336c3485b7631b05bf5962cbe48bee4dfc9618ba5dc0564f` | `eb656eaac4fa618ba66412665b00387fb53058d9` |
| incident-reducer candidate | `32addd432fac80c0d1130dfb7829011142670a923d2ce1d954f7d047e0127e43` | `f7106189c0e015b1ef7d0b669d3a90a474494c1a` |

The one-file productive diff contains:

```text
modified paths: 1
added paths:    0
deleted paths:  0
insertions:     431
deletions:      9
```

The ordered standalone publication manifest is:

```text
32addd432fac80c0d1130dfb7829011142670a923d2ce1d954f7d047e0127e43  NavimowAccount/module.php
```

Manifest SHA-256:

```text
8b8af483f8d60eafa59fb2f28f18920ab8cb1fb961a289ee7f2ffe6123c9f9b4
```

Any productive byte change invalidates this freeze and requires renewed
focused and repository-wide validation.

## 5. Supporting Freeze

| Artifact | SHA-256 |
|---|---|
| MQTT pilot checkpoint tests | `a63d7524c85b23cb6c1a773500147307c7899d7933f94da2cd778cf7b5bcb4b1` |
| MQTT transport lifecycle tests | `f19cccd446105d7d4f19ce648e8a4bb53e15362085eb4f1b6c97f0f60760728c` |
| step 318 incident design | `253876f946e2ca4e25af7d2caa843bfb12866c1322d8c4e9c8bee83ea41552a9` |
| step 319 implementation report | `8174d3938fec780fe3cd7ce524696dd42f2028287792b1a433993ff2ac23ceae` |

Steps 313 through 317 retain the immutable rollout and live evidence that
motivated the change. The README and this report are reviewed as documentation
at Gate P1 instead of recursively hashing this report into itself.

## 6. Fixed Architecture Boundary

Every later gate must preserve:

```text
public device-state authority:        REST
MQTT direction:                       receive-only
MQTT default:                         disabled
MQTT publish and command paths:       absent
reconnect delays:                     60 / 300 / 900 seconds
maximum reconnect attempts:           3
pilot maximum duration:               259200 seconds
recoverable independent incidents:    1
maximum episodes per incident:        3
maximum incident duration:            1800 seconds
sustained-health incident reset:      900 seconds
terminal auth/config behavior:        fail closed
public variables and profiles:        unchanged
Archive identities and logging:       unchanged
automatic cleanup ordering:           credential first and idempotent
```

Episode evidence remains exact. Incident grouping changes only the bounded
pilot policy. It does not make MQTT authoritative, create a command path or
authorize permanent operation.

## 7. Verification Evidence

The following checks passed again in the isolated worktree:

- PHP syntax for the productive Account module;
- MQTT pilot checkpoint tests;
- MQTT transport lifecycle tests;
- MQTT position diagnostics;
- MQTT shadow diagnostics;
- REST pilot observation harness;
- Navimow distribution validation;
- repository-wide PHPStan and PHPCS; and
- complete `composer check` with the canonical dependency installation.

`git diff --check` also passed. The complete distribution comparison found
exactly the frozen Account file and no additions or deletions. No public
credential, token, topic, endpoint, device identity, coordinate, ObjectID,
hostname or installation metadata was added.

## 8. Publication Tool Decision

The canonical repository currently contains a deterministic Symcon module
fileset builder and an Open-Meteo-specific publisher, but no reviewed generic
publisher configured for Navimow. The Open-Meteo publisher must not be adapted
or invoked for this release.

Until a general controlled-fileset publisher is released independently, Gate
S1 must use the existing clean standalone clone with these equivalent guards:

1. exact expected remote commit;
2. clean standalone worktree;
3. exact one-path allowlist;
4. candidate and manifest hashes above;
5. complete 31-file tree comparison before commit;
6. one commit and one push only; and
7. remote commit, tree and blob read-back after push.

This is a release constraint, not a reason to extend framework tooling inside
the Navimow case study.

## 9. Reduced Gate Sequence

### Gate P1: Canonicalize and publish the SAEF candidate

One explicit authorization may cover:

1. fetch and prove the exact current `origin/main`;
2. fast-forward the existing worktree to that merge commit without changing
   the candidate;
3. create `codex/navimow-transport-incident-reducer` from that baseline;
4. verify the exact 12-path allowlist and all frozen hashes;
5. rerun focused plus complete offline validation;
6. stage and inspect exactly those 12 paths;
7. create one Conventional Commit;
8. push only the new dedicated branch; and
9. open one pull request against SAEF `main`.

Suggested commit:

```text
fix(navimow): reduce mqtt transport incidents
```

Gate P1 permits no merge, standalone mutation or Symcon access.

### Gate P2: Review and merge SAEF

After terminal green checks, one authorization may cover final diff review,
review-ready marking, merge by merge commit and canonical `origin/main`
verification. It permits no standalone or live mutation.

### Gate S1: Publish the exact standalone file

After P2, one hash-bound authorization may cover the guarded one-file
standalone publication, remote read-back and metadata conformance. No metadata
file is expected to change, but all published metadata inputs must still pass
the established schema-based fallback if the web validator is unavailable.

Gate S1 permits no Symcon access, tag, MQTT activation or mower command.

### Gate L1: Disabled Symcon rollout

One separate live authorization may cover:

1. bounded structured MCP preflight;
2. proof that MQTT and position diagnostics are disabled and Core credentials
   are absent;
3. exactly one supported `MC_UpdateModule()` call;
4. immediate read-only postflight; and
5. delayed read-only postflight.

The rollout must preserve Account status `102`, REST readiness, variable and
Archive contracts, and disabled credential-free MQTT. `MC_ReloadModule()` and
`IPS_ApplyChanges()` are not part of this gate.

### Gate L2: One bounded receive-only pilot

A later independent activation gate may authorize exactly one restart-free
pilot on the exact installed commit. It requires a fresh disabled preflight,
the 1200-second token horizon, explicit credential-persistence acceptance and
automatic closure ownership. A restart/Core-resume test would instead require
its separate 2400-second horizon and is not implied.

No failed or ambiguous activation is retried. The observer verifies native
closure; it does not replace it.

## 10. Rollback And Stop Conditions

Before standalone publication, rollback is abandonment of the candidate. After
publication but before live rollout, rollback is a reviewed standalone revert
commit, not history rewriting. After a disabled Symcon rollout, the previous
standalone commit remains the explicit rollback target and must be installed
through one separately authorized supported module update.

Every later gate stops before mutation if:

- source ancestry or a remote baseline differs from fresh preflight;
- any path outside the exact allowlist appears;
- a frozen hash or complete-tree comparison differs;
- MQTT publish or mower-command capability appears;
- REST authority, public variables, profiles or Archive contracts drift;
- validation fails or evidence is truncated or ambiguous;
- live MQTT is not disabled before Gate L1; or
- credential absence cannot be proved.

An ambiguous push or live result is resolved through fresh read-back and never
through a blind retry.

## 11. Architecture Decisions

### AD-NAV-1316: Canonicalize on a new branch after the merged predecessor

The current branch has already been merged through PR #43. A new branch from
the current canonical main avoids reusing a published branch while preserving
the isolated worktree and exact candidate.

### AD-NAV-1317: Publish only the productive Account delta

Tests and case-study evidence belong to SAEF. The standalone repository receives
only the byte-frozen Account module after complete-tree equality proves that no
other distribution artifact drifted.

### AD-NAV-1318: Keep repository and live trust boundaries separate

SAEF publication, standalone publication, disabled rollout and credential-
bearing pilot activation remain independently authorized. Grouping mechanical
substeps reduces approvals without collapsing different risk domains.

### AD-NAV-1319: Do not create release tooling inside the case study

The missing general publisher is framework work. This release uses an exact
manual publication contract rather than adding a one-off Navimow abstraction.

## 12. Gate State And Recommendation

| Gate | Status |
|---|---|
| exact SAEF scope | FROZEN |
| current main ancestry | PASS; one tree-equivalent merge commit behind |
| exact productive candidate | FROZEN |
| complete distribution comparison | PASS; one modified path |
| focused and complete offline checks | PASS |
| Gate P1 canonicalization, commit, push and PR | CLOSED |
| Gate P2 review and merge | CLOSED |
| Gate S1 standalone publication and metadata | CLOSED |
| Gate L1 disabled Symcon rollout | CLOSED |
| Gate L2 bounded live pilot | CLOSED |

The next useful action is Gate P1 as one grouped repository authorization. It
does not include merge, standalone publication or any Symcon operation.
