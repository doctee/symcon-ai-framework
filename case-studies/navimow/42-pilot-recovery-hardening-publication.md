# 42 Pilot Recovery Hardening Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Published and remotely verified; Symcon smoke test pending
**Date:** 2026-07-10
**Scope:** Publish deterministic recovery hardening to the module repository

## 1. Purpose

This step publishes the locally verified recovery hardening from
`41-pilot-recovery-hardening-implementation.md` to the dedicated Navimow module
repository.

It verifies:

- the exact publication file boundary;
- canonical distribution parity;
- local module repository validation;
- the published branch commit;
- the remote productive file content;
- preservation of the historical pilot tag.

No Symcon runtime or mower command is used in this step.

## 2. Publication Target

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Publication branch:

```text
main
```

The established private-pilot workflow publishes reviewed commits directly to
`main`. No pull request was required for this controlled repository.

The GitHub CLI was not installed in the agent environment. This did not block
the established SSH-based direct publication flow.

## 3. Pre-Publication State

The dedicated publish clone was clean and synchronized with the previous
remote branch state:

```text
692ea0350bb73e6581e4643a931837ae48b49ede
```

That commit also remains the target of the historical annotated tag:

```text
pilot-0.1.0.1
```

The canonical distribution differed from the publish clone only in the two
expected hardening files.

## 4. Published File Boundary

Copied from the canonical case-study distribution:

```text
distribution/NavimowAccount/module.php
distribution/NavimowDevice/module.php
```

Changed in the dedicated module repository:

```text
NavimowAccount/module.php
NavimowDevice/module.php
```

Not published:

- CLI harness files;
- fake clock or Symcon runtime double;
- fixtures;
- SAEF case-study documents;
- private captures or publication helpers;
- local OS metadata;
- credentials or installation-specific identifiers.

## 5. Validation Before Commit

Canonical case-study checks:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
```

Results:

| Check | Result |
| --- | --- |
| deterministic recovery harness | 16 of 16 passed |
| REST/Auth/fixture/static checks | passed |
| distribution structure validator | passed |
| productive PHP syntax | passed |
| publish-clone whitespace check | passed |
| private-data scan | passed |

After copying, the dedicated publish clone was byte-equivalent to the
canonical distribution after excluding `.git` and ignored `.DS_Store` files.

## 6. Module Repository Commit

Created commit:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

Commit scope:

```text
2 files changed
144 insertions
47 deletions
```

The commit contains:

- structured current-read evidence;
- deadline precedence and terminal timeout;
- internal `WaitingRead` state;
- deadline-aligned read scheduling;
- persistent five-attempt token-refresh transport retry;
- approved internal testability seams.

## 7. Remote Push

The direct fast-forward push succeeded:

```text
692ea03..db36ea3  main -> main
```

Remote reference verification returned:

```text
refs/heads/main -> db36ea37cb40298278307e88d65ae8c450603f18
```

The local publish clone is clean and `HEAD`, local `main`, `origin/main` and
remote `main` identify the same commit.

## 8. Remote File Verification

The two productive files were read independently from GitHub `main` after the
push.

| File | Remote blob | Result |
| --- | --- | --- |
| `NavimowAccount/module.php` | `2d4800eda2730a177bfeddf963e2e16a0049f1ae` | hardening content present |
| `NavimowDevice/module.php` | `77ec5012123749195f62f0fbf208d6ec95e69a05` | hardening content present |

The account file contains the bounded refresh retry constants and persistent
retry attribute. The device file contains `WaitingRead`, structured current
read handling and deadline-aware scheduling.

## 9. Pilot Tag Decision

The existing annotated tag remains unchanged:

```text
pilot-0.1.0.1 -> 692ea0350bb73e6581e4643a931837ae48b49ede
```

It continues to identify the first published REST MVP pilot snapshot before
recovery hardening.

No tag is moved. No new tag is created in this step.

If the hardening build passes direct Symcon smoke and supervised restart
evidence, a later release decision may create a new immutable pilot tag such
as `pilot-0.1.0.2`.

## 10. Metadata State

`library.json` was not changed.

Metadata remains:

```text
version: 0.1
build: 0
date: 0
compatibility version: 6.2
```

The hardening commit is a controlled pilot update, not a broad public release
or Symcon Store release.

## 11. Publication Gate

**Decision: PASS.**

The reviewed productive hardening is now published on the dedicated module
repository `main` branch with deterministic local evidence and remote content
verification.

The publication does not by itself close the direct Symcon gate.

## 12. Direct Symcon Test Boundary

The next Symcon update must begin with read-only checks only:

1. update the Navimow module from repository `main`;
2. confirm all three module types remain available;
3. confirm existing account and device instances load;
4. inspect connection and reauthorization state;
5. run a read-only status refresh;
6. confirm no new error or secret-bearing log entry;
7. confirm no mower command was sent.

No supervised restart transition should start until this smoke test passes.

## 13. Architecture Decisions

### AD-NAV-097: Publish only canonical productive files

**Decision:** Copy only Account and Device module files from the SAEF
distribution.

**Rationale:** Harness and evidence files belong to the case study, not the
installable Symcon module root.

**Consequence:** The module repository remains minimal and loadable.

### AD-NAV-098: Use one direct hardening commit

**Decision:** Publish seams and all three recovery fixes in one commit.

**Rationale:** Step 41 proved them together with one deterministic harness.

**Consequence:** No remotely visible intermediate red-harness state exists.

### AD-NAV-099: Preserve the first pilot tag

**Decision:** Do not move or overwrite `pilot-0.1.0.1`.

**Rationale:** An immutable tag must preserve the exact historical snapshot it
originally identified.

**Consequence:** Hardening remains on `main` until a later tag decision.

### AD-NAV-100: Require read-only Symcon smoke before live restart

**Decision:** Test loader, authentication state and status refresh before any
new supervised mower transition.

**Rationale:** The publication changes module lifecycle, timers and internal
state restoration even though command payload behavior is unchanged.

**Consequence:** Physical testing remains blocked until the updated module is
known to load and poll correctly.

## 14. Recommended Next Step

Create:

```text
43-pilot-recovery-hardening-symcon-smoke-test-report.md
```

That step should record the module update and read-only direct Symcon checks.
Only a passed smoke report may reopen the supervised `OBS-02` restart test.
