# 373 Local Map Runtime Correction Re-Review And Merge Decision

**Case study:** Navimow native IP-Symcon module

**Status:** Correction re-review passed; SAEF merge recommended; merge,
standalone publication and live gates remain closed

**Date:** 2026-08-28

## 1. Decision

PR #82 is recommended for merge into SAEF `main`. The single medium-severity
finding from step 371 is closed by the focused correction in step 372, the
complete validation sequence is green and the re-review found no new blocking
issue.

    pull request: doctee/symcon-ai-framework#82
    reviewed base: 016d78b028a8df261c0788133b86a5fdb9f411d2
    reviewed correction head: 198b3b452a4757183da635e49df6a1eb325eb8ef
    state: open, ready for review
    mergeability: MERGEABLE
    merge state: CLEAN
    changed files: 66
    commits: 18
    CI validate checks: 2 of 2 successful
    blocking findings: 0

This decision authorizes neither the merge itself nor any standalone-module or
live-system mutation.

## 2. Finding Closure

The re-review compared the exact correction range
`d0b1801b204fea53b3b51291ebdb95267d00c7ca..198b3b452a4757183da635e49df6a1eb325eb8ef`.
The change remains limited to the Device-owned local-map configuration gate,
focused tests, generated distribution copy and fileset metadata.

The correction closes every required item from step 371:

| Required behavior | Re-review result |
| --- | --- |
| reject an empty DeviceId | passed |
| reject malformed hidden-zone configuration | passed |
| reject unsupported theme | passed |
| reject malformed geometry even with a matching recomputed hash | passed |
| reject invalid zone binding and area semantics | passed |
| stop the local-map timer for invalid configuration | passed |
| clear and hide a previously rendered map | passed |
| perform no Account parent request | passed |
| preserve bounded retained path state for rollback | passed |
| preserve valid dark, light, restart and variable contracts | passed |

`RefreshLocalMap()` now applies the same complete configuration contract before
semaphore acquisition and parent communication. The correction composes the
existing package, zone-area and scene-projector validation instead of adding a
second geometry validator or new public helper.

## 3. Re-Review Boundaries

No change was found in these established contracts:

- REST remains authoritative for public mower and station state;
- MQTT remains strictly receive-only and cannot publish a mower command;
- OAuth and token handling are unchanged;
- established Dock, Pause and Resume behavior is unchanged;
- existing variable Idents, types, profiles and positions are unchanged;
- `EnableLocalMap` remains `false` by default;
- no private geometry, coordinates, credentials, hosts, ObjectIDs or capture
  output entered the public candidate;
- the generated Device source remains byte-identical to its canonical
  case-study distribution source.

The complete semantic validation is intentionally repeated before scheduling
or manual refresh. This adds bounded deterministic work for the accepted map
package, but prevents invalid configuration from reaching a timer or parent
read. With the current bounded package contract this is a non-blocking and
preferable fail-closed tradeoff.

## 4. Verification Evidence

The exact correction head passed a fresh complete local verification:

    complete Navimow offline suite: PASS
    focused lifecycle and restart regressions: PASS
    PHPCS: PASS
    PHPStan: PASS, no errors
    distribution validation: PASS
    generated fileset check: PASS
    git correction-range diff check: PASS

The general SAEF Composer resolver used the primary checkout's lock-identical
vendor toolchain. It only supplied tools; source and Git state remained owned
by the isolated Navimow worktree.

Frozen productive candidate:

    fileCount
    42

    filesetSha256
    a89dd1cce971342093cf70520f9d9e626106acbc0a2180dbeea192c78684c826

    publicationSha256
    c3e61fc36468728845db08ea462e2ec4ddd264b52d5534c9f29f48a0d3ef1633

GitHub checks on the correction head:

    validate branch workflow: success
    validate pull-request workflow: success

There is no independent GitHub review or comment on PR #82. The merge
recommendation is therefore based on the documented engineering re-review,
the reproduced finding closure and terminal automated checks.

## 5. Architecture Decision

### AD-NAV-373-01: Recommend SAEF merge after focused correction

**Decision:** Recommend merging PR #82 after the separately authorized merge
gate.

**Reason:** The only blocking review finding is closed by a minimal
compositional correction, all productive and generated copies agree, and no
new regression or boundary expansion was found.

### AD-NAV-373-02: Keep distribution and live release independent

**Decision:** Treat the SAEF merge as knowledge-base and candidate
consolidation only.

**Reason:** Merging SAEF does not publish `symcon-navimow`, run the official
Module Validator, update Symcon or enable the default-disabled private map.
Those actions retain their own evidence and approval gates.

## 6. Residual Risks

- the private cloud and MQTT protocols remain vendor-undocumented and can
  change independently of this local-map correction;
- the local-map runtime has not yet completed official Module Validator or
  disabled Symcon rollout evidence;
- private geometry import and live map activation remain installation-specific
  and explicitly closed;
- the PR contains a broad but coherent 66-file workstream, so the immutable
  merged commit must be verified against the reviewed PR head after merge.

None of these risks blocks consolidation of the default-disabled SAEF
candidate.

## 7. Gate Status

| Gate | Status |
| --- | --- |
| step-371 finding correction | passed |
| focused correction re-review | passed |
| complete offline validation | passed |
| terminal CI on correction head | passed |
| merge recommendation | passed |
| SAEF merge | closed, requires separate execution approval |
| canonical post-merge verification | closed |
| standalone publication | closed |
| official Module Validator | closed |
| Symcon update or local-map activation | closed |

## 8. Next Step

The next SAEF step should merge PR #82 by merge commit under a separate gate
and then verify the immutable merge result on canonical `origin/main`. It must
confirm the reviewed correction ancestry and public tree before any retention,
standalone publication or live rollout decision.
