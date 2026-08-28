# 372 Local Map Runtime Fail-Closed Configuration Correction

**Case study:** Navimow native IP-Symcon module

**Status:** Review finding corrected and fully verified offline; PR update and
terminal CI verification approved; merge and live gates remain closed

**Date:** 2026-08-28

## 1. Scope

This step corrects the single medium-severity finding from step 371. It changes
only the Device-owned local-map configuration boundary and its focused tests.

It does not change REST mapping, MQTT lifecycle, authentication, established
mower commands, variable identities, archive contracts, geometry semantics or
renderer presentation.

## 2. Correction

`localMapConfigurationIsValid()` now fails closed unless all of these
conditions pass before map visibility or timer scheduling:

1. `EnableLocalMap=true`;
2. non-empty configured `DeviceId`;
3. bounded format-version-1 accepted package;
4. exact accepted geometry hash;
5. valid hidden-zone sequence list;
6. valid explicit dark or light theme;
7. valid zone-area bindings;
8. complete geometry and binding semantics accepted by the existing
   `LocalMapSceneProjector`.

The correction composes existing components. It introduces no public helper,
parallel geometry parser or new persistence format. Private empty path and
statistics factories remove duplicated validation input from the stored-render
path.

`RefreshLocalMap()` applies the same gate before entering its semaphore or
calling the Account parent. An invalid configuration therefore:

- stops the timer;
- clears and hides the presentation;
- performs no parent request;
- records no new MQTT or authentication activity;
- preserves bounded retained track state for rollback;
- returns a deterministic configuration error.

## 3. Regression Evidence

Focused lifecycle tests cover these synthetic invalid configurations:

| Case | Timer | Presentation | Parent requests |
| --- | ---: | --- | ---: |
| empty DeviceId | 0 | empty | 0 |
| malformed hidden-zone list | 0 | empty | 0 |
| unsupported theme | 0 | empty | 0 |
| malformed geometry with matching hash | 0 | empty | 0 |
| invalid zone binding | 0 | empty | 0 |

A separate restart/update regression begins with a valid rendered map and
retained path, changes to malformed presentation configuration and proves:

- previous SVG cleared immediately;
- timer remains zero;
- manual refresh performs no parent request;
- retained bounded track state remains byte-identical.

Valid dark, light, active, idle, restart, disable, stale-REST, inactive-MQTT
and existing-variable contracts continue to pass.

## 4. Complete Verification

The complete Navimow offline suite passes, including REST, commands, MQTT,
diagnostics, pilot, recovery, geometry, rendering, runtime lifecycle,
distribution validation, PHPCS and PHPStan.

The general SAEF Composer resolver used the primary checkout's lock-identical
vendor toolchain. No source or Git state crossed the isolated worktree
boundary.

The generic fileset and publication checks pass without publication mutation:

    fileCount
    42

    filesetSha256
    a89dd1cce971342093cf70520f9d9e626106acbc0a2180dbeea192c78684c826

    publicationSha256
    c3e61fc36468728845db08ea462e2ec4ddd264b52d5534c9f29f48a0d3ef1633

Focused hashes:

    NavimowDevice/module.php
    7d92029103ab59dd900ded56a0d329e0fb23744833a17fb8e4d33347ef8c627a

    local-map-device-lifecycle.php
    2b9902c96fcf016dcb3d693d44af50574353103696ecb23e5eb10a590c1a1df7

    local-map-restart-and-disable.php
    3e92a60885d55c3bdc416ef4736db7f167708509baf56bc2fcada3f0fb2325a2

The generated standalone Device source is byte-identical to the canonical
case-study distribution source.

## 5. Architecture Decisions

### AD-NAV-372-01: Compose semantic validation at activation

**Decision:** Reuse `LocalMapSceneProjector` and existing package helpers before
timer scheduling rather than reimplementing geometry validation.

**Reason:** One validation owner prevents drift between configuration and
runtime rendering.

### AD-NAV-372-02: Guard manual refresh with the same contract

**Decision:** Reject an invalid manual refresh before semaphore acquisition or
parent communication.

**Reason:** A stopped timer alone would not make the public refresh action fail
closed.

### AD-NAV-372-03: Preserve bounded history while clearing presentation

**Decision:** Invalid configuration clears the visible SVG but does not delete
validated retained track state.

**Reason:** Configuration rollback should recover without losing bounded local
history, while stale presentation must not remain visible.

## 6. Gate Status

| Gate | Status |
| --- | --- |
| step-371 finding correction | passed offline |
| focused regression tests | passed |
| complete offline validation | passed |
| PR correction commit and push | approved, pending execution |
| terminal PR checks | pending correction push |
| merge | closed |
| standalone publication | closed |
| Module Validator and Symcon rollout | closed |

## 7. Next Step

Publish one normal correction commit to PR #82 and verify local, tracking,
remote and PR-head equality plus terminal CI. If those checks pass and no new
review finding appears, the subsequent gate may decide the SAEF merge. No
standalone or live action follows implicitly.
