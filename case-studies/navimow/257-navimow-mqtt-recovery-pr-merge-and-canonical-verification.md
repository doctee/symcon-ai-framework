# 257 Navimow MQTT Recovery PR Merge and Canonical Verification

**Case study:** Navimow native IP-Symcon module

**Status:** Explicitly authorized SAEF-main merge executed through pull request
22 and independently verified; downstream publication and live gates remain
closed

**Date:** 2026-08-04

**Scope:** Take the fully reviewed Navimow recovery pull request out of draft,
merge it into canonical SAEF `main`, and verify the resulting remote mainline
without publishing the standalone module or accessing Symcon

## 1. Authorization

The user explicitly authorized the merge after step 256 completed its review
without blocking findings.

The authorization covers only:

1. adding this merge-execution report to PR 22;
2. one report-only push to its existing head branch;
3. final GitHub checks for the resulting head;
4. changing PR 22 from draft to ready;
5. merging PR 22 through the repository's established merge-commit method;
6. read-only verification of canonical remote `main`.

It does not authorize standalone publication, metadata validation, a Symcon
module update, credential access, MQTT activation, restart or mower command.

## 2. Authorized Pre-Merge Baseline

```text
repository:       doctee/symcon-ai-framework
pull request:     #22
base branch:      main
base commit:      7358fa5878869ff43ad30282f744bf78950c081a
reviewed head:    4decdcc1340462b796af4a3a679b7b67e9ecfe63
state:            open, draft
mergeability:     MERGEABLE
merge state:      CLEAN
changed paths:    211
required checks:  2 of 2 successful
blocking findings:0
```

Local head, remote branch head and PR head were equal. The remote base had not
changed since the step-256 review.

## 3. Final Pull-Request Scope

Adding this report produces the final expected PR scope:

| Review bucket | Paths |
|---|---:|
| numbered SAEF reports | 165 |
| Navimow case-study index | 1 |
| installable distribution | 17 |
| sanitized fixtures | 13 |
| forum documentation | 2 |
| offline tests | 11 |
| case-study tools | 3 |
| **Total** | **212** |

All paths remain below `case-studies/navimow/`. This closure changes no
productive PHP, metadata, fixture, test or tool file.

## 4. Merge Procedure

The bounded merge sequence is:

1. commit and push only this report, the index entry and the preceding gate
   closure;
2. verify exact local, remote-branch and PR-head equality;
3. require exactly 212 Navimow-only paths and no deletion;
4. require all final-head checks to complete successfully;
5. require GitHub to report `MERGEABLE` and `CLEAN`;
6. mark PR 22 ready for review;
7. merge PR 22 with a GitHub merge commit;
8. read back PR state and merge commit;
9. verify remote `main` equals the reported merge commit;
10. verify the complete PR head is an ancestor of remote `main`;
11. verify steps 1 through 257 and the README index from canonical main;
12. stop without deleting the recovery branch or worktree.

The source branch and worktree are retained until a later cleanup decision so
that rollback evidence and the exact reviewed head remain available.

## 5. Canonical Verification Contract

The merge passes only if all following statements hold simultaneously:

```text
PR state:                         MERGED
PR draft state:                   false
reported merge commit:            remote main head
final PR head ancestor of main:   yes
effective PR paths:               212 Navimow-only
numbered report continuity:       1 through 257
README index entries:             exactly once per report
REST public-state authority:      preserved
MQTT direction:                   receive-only
MQTT default:                     disabled
standalone repository mutations: 0
Symcon reads or mutations:        0
```

A transport-level merge success without these read-back checks is not enough.

## 6. Preserved Runtime Boundary

Mainline integration changes repository ownership only:

- REST remains the sole public mower-state authority;
- MQTT remains an optional receive-only hint and diagnostic source;
- no MQTT publish or mower-command route exists;
- MQTT remains disabled by default;
- the 14-variable and 5-archive contracts remain unchanged;
- prior recovered transport episodes remain a private-pilot risk;
- no general-availability or Store-readiness claim is made.

## 7. Failure and Stop Conditions

Stop before merge if:

- `origin/main` no longer equals the authorized base;
- the final PR head differs from the local report commit;
- the scope differs from 212 Navimow-only paths;
- any required check fails, is cancelled or remains ambiguous;
- mergeability is not `MERGEABLE/CLEAN`;
- a productive file changes after the step-256 review;
- privacy or receive-only boundaries drift.

Stop after merge and report a verification failure if:

- GitHub does not report the PR as merged;
- remote `main` differs from the reported merge commit;
- the final PR head is not an ancestor of remote `main`;
- report continuity or index uniqueness fails.

No failure permits a force push, direct-main correction, standalone
publication or Symcon fallback.

## 8. Architecture Decisions

### AD-NAV-994: Accept the explicit merge authorization narrowly

The authorization applies to PR 22 and canonical verification only. Every
downstream publication and live boundary remains independent.

### AD-NAV-995: Include the merge contract in the merged history

Step 257 belongs to the same reviewed case-study sequence and therefore enters
the PR before merge.

### AD-NAV-996: Revalidate the final report-only head

Even a documentation-only closure changes the commit identity. CI and scope
checks are repeated before the merge mutation.

### AD-NAV-997: Use the established merge-commit method

Recent SAEF pull requests preserve branch ancestry through GitHub merge
commits. PR 22 follows the same repository convention.

### AD-NAV-998: Verify remote main independently

The GitHub action result and the merge command are not sufficient evidence.
Remote refs, PR state and ancestry are read back separately.

### AD-NAV-999: Retain the recovery source after merge

Branch and worktree cleanup is deferred so the exact reviewed source remains
available until canonical and downstream evidence is settled.

### AD-NAV-1000: Do not promote MQTT through repository merge

Canonical ownership does not change the disabled-by-default, receive-only and
REST-authoritative runtime contract.

### AD-NAV-1001: Keep Gate A closed

The standalone module repository requires its own fileset comparison,
publication authorization and remote verification.

### AD-NAV-1002: Keep every live gate closed

No GitHub merge authorizes metadata UI use, Symcon update, stored credentials,
MQTT connection, restart or mower actuation.

### AD-NAV-1003: Record exact execution evidence privately

The immutable public contract avoids installation details. Exact final refs,
check runs and mutation counts are retained in private machine-readable
evidence after execution.

## 9. Gate Result

| Gate | Result |
|---|---|
| explicit PR merge authorization | PASS |
| final report-only head checks | PASS, 2 of 2 |
| PR ready-state change | PASS |
| PR 22 merge | PASS, merge commit `2ef7a22a` |
| canonical remote-main verification | PASS |
| recovery branch cleanup | DEFERRED |
| standalone publication | CLOSED |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 10. Next Step

After successful canonical verification, the next analysis-only artifact is:

```text
258-navimow-standalone-mqtt-publication-readiness-review.md
```

It should compare canonical SAEF distribution with the standalone module
repository and decide whether the bounded MQTT increment is ready for a
separately authorized publication. It must not publish or access Symcon.
