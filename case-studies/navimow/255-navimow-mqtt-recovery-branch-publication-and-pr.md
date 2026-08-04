# 255 Navimow MQTT Recovery Branch Publication and Pull Request

**Case study:** Navimow native IP-Symcon module

**Status:** Recovery branch published and draft pull request opened; PR merge,
standalone publication and all live gates remain closed

**Date:** 2026-08-04

**Scope:** Execute Gate II from steps 253 and 254 without merging or accessing
the standalone module or Symcon

## 1. Result

The integrated and validated Navimow recovery branch was pushed to the SAEF
remote and one draft pull request was opened against canonical `main`.

```text
repository:    doctee/symcon-ai-framework
head branch:   codex/navimow-mqtt-recovery-clean
base branch:   main
pull request:  #22
state:         open, draft
title:         feat(navimow): consolidate receive-only MQTT workstream
```

The pull request is available at:

```text
https://github.com/doctee/symcon-ai-framework/pull/22
```

No pull-request merge, direct main push, standalone publication, tag, release
or Symcon access was performed.

## 2. Fresh Publication Preflight

Immediately before the initial branch push:

```text
local HEAD:          38a85eb4deac1ded83a0cd9f732db66ac8dc6c0b
fetched origin/main: 7358fa5878869ff43ad30282f744bf78950c081a
origin/main ancestor of HEAD: yes
worktree:            clean
effective paths:     209
insertions:          70251
deletions:           12
```

The effective 209-path scope contained only Navimow case-study paths. Step 254
had already closed focused tests, the private pilot harness, complete
`make check`, privacy review, receive-only review and standalone candidate
refreeze.

## 3. Branch Publication

The branch was published with upstream tracking:

```text
local branch:  codex/navimow-mqtt-recovery-clean
remote branch: origin/codex/navimow-mqtt-recovery-clean
initial remote head: 38a85eb4deac1ded83a0cd9f732db66ac8dc6c0b
```

The first push created only the named branch. It did not change remote `main`.

This step-255 closure report adds one expected Navimow report to the same pull
request. The final reviewed PR scope is therefore:

```text
paths:      210
added:      196
modified:    14
deleted:      0
```

The final report commit is pushed only to the same head branch and verified
before closure.

## 4. Pull Request Contract

The draft pull request records:

- the recovered receive-only MQTT architecture;
- the reason for moving the workstream out of a mixed checkout;
- the 210-path final review scope;
- REST as the sole public device-state authority;
- absence of MQTT publish and MQTT mower-command routes;
- disabled-by-default MQTT transport;
- preserved 14-variable and 5-archive contracts;
- focused, private-harness and complete repository checks;
- separately closed downstream publication and live gates.

Maintainer edits are allowed. The PR remains draft until its review and checks
are explicitly assessed.

## 5. Final Scope

After adding this report, the branch diff against the unchanged base contains:

| Review bucket | Paths |
|---|---:|
| numbered SAEF reports | 163 |
| Navimow case-study index | 1 |
| installable distribution | 17 |
| sanitized fixtures | 13 |
| forum documentation | 2 |
| offline tests | 11 |
| case-study tools | 3 |
| **Total** | **210** |

All paths remain below `case-studies/navimow/`. Steps 1 through 255 remain
continuous and each report is indexed exactly once.

## 6. Preserved Safety Boundary

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT publish path:             absent
MQTT mower-command route:      absent
MQTT default:                  disabled
standalone repository changes: 0
Symcon mutations:              0
```

The GitHub branch and pull request do not authorize deployment or operation of
the module.

## 7. Mutation Counts

```text
remote branch creations:     1
branch pushes:               2
pull requests created:       1
pull requests merged:        0
direct main pushes:          0
standalone commits/pushes:   0
tags/releases:               0
Symcon reads or mutations:   0
MQTT activations:            0
mower commands:              0
```

The second branch push contains only this sanitized SAEF closure report, its
index entry and the prior gate-status update.

## 8. Stop Conditions Retained

Do not merge or proceed to standalone publication if:

- the remote PR head differs from the local closure commit;
- remote `main` changes without renewed ancestry and conflict review;
- PR checks fail or remain ambiguous;
- the effective PR scope differs from 210 Navimow-only paths;
- a deletion or unrelated path appears;
- REST authority or receive-only MQTT boundaries drift;
- private installation data appears;
- review requests a productive change that has not passed the complete gates.

Any productive correction creates a new candidate hash and requires focused
and complete tests again.

## 9. Architecture Decisions

### AD-NAV-975: Publish only the reviewed recovery branch

The current clean branch contains the preserved recovery commit, current-main
merge and documented closure. No other branch is an authorized source.

### AD-NAV-976: Open the pull request as draft

The 210-path scope is intentionally large and requires explicit review before a
merge decision.

### AD-NAV-977: Keep the PR Navimow-only

Every effective path belongs to the case study. Mainline changes are present
only as merge ancestry, not as PR delta.

### AD-NAV-978: Include the publication report in the PR

The canonical case-study sequence must contain its own branch-publication and
gate evidence before merge.

### AD-NAV-979: Allow exactly one closure push

The initial branch creation and one report-only closure push are sufficient.
Further pushes require renewed scope review.

### AD-NAV-980: Do not merge under Gate II

Branch publication and PR creation provide a review surface. They do not
authorize changing canonical `main`.

### AD-NAV-981: Keep standalone publication independent

The separate module repository remains unchanged until canonical SAEF main is
verified and its own Gate A is authorized.

### AD-NAV-982: Treat remote-head equality as mandatory evidence

Successful transport from `git push` is not enough; the PR head must equal the
local closure commit.

### AD-NAV-983: Preserve every live gate

GitHub publication does not authorize metadata validation, Symcon update,
credential retrieval, MQTT activation, restart or mower command.

## 10. Gate Status

| Gate | Status |
|---|---|
| Gate II branch push | PASS |
| pull request creation | PASS, draft PR #22 |
| final remote-head verification | PASS |
| PR checks | PASS, 2 of 2 successful |
| PR review and merge decision | PASS IN STEP 256 |
| canonical-main verification | CLOSED |
| standalone publication | CLOSED |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 11. Next Step

The closure push and its checks were verified. Step 256 now records the
pull-request review and conditional merge recommendation:

```text
256-navimow-mqtt-recovery-pr-review-and-merge-decision.md
```

That step may review the pull-request diff and checks and recommend a merge
decision. It must not merge the PR without separate explicit authorization and
must not publish the standalone module or access Symcon.
