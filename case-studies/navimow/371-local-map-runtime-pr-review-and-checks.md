# 371 Local Map Runtime PR Review And Checks

**Case study:** Navimow native IP-Symcon module

**Status:** Terminal CI passed; engineering review found one blocking medium
configuration-lifecycle defect; merge remains closed

**Date:** 2026-08-27

## 1. Review Result

PR #82 is not yet recommended for merge. One medium-severity finding must be
corrected and covered by regression tests first.

    pull request: doctee/symcon-ai-framework#82
    reviewed head: f545999f8c2e135ade031d6422bb324b42256878
    reviewed base: 016d78b028a8df261c0788133b86a5fdb9f411d2
    state: open, ready for review
    mergeability: MERGEABLE
    merge state: CLEAN
    CI validate checks: 2 of 2 successful
    blocking findings: 1 medium

No security, credential, privacy, command-authority or public-variable
regression was found.

## 2. Finding

### Medium: Incomplete fail-closed map configuration gate

`NavimowDevice::ApplyChanges()` exposes the map and schedules its timer when
`localMapConfigurationIsValid()` returns true. The current validator checks the
master switch, top-level package shape, geometry hash and theme, but it does
not validate:

- a non-empty configured `DeviceId`;
- `HiddenZoneSequences`;
- complete geometry and binding semantics already enforced by the existing
  map projector and reducers.

Relevant source locations at the reviewed head:

    case-studies/navimow/distribution/NavimowDevice/module.php:128
    case-studies/navimow/distribution/NavimowDevice/module.php:853
    case-studies/navimow/distribution/NavimowDevice/module.php:894
    case-studies/navimow/distribution/NavimowDevice/module.php:985

The existing synthetic harness reproduced both immediately after
`ApplyChanges()`:

    blank DeviceId with otherwise accepted package
    LocalMapRefresh = 300000 ms

    malformed HiddenZoneSequences with DeviceId and accepted package
    LocalMapRefresh = 300000 ms

The first timer invocation later stops itself for an empty DeviceId. A malformed
presentation or deeper package configuration can nevertheless perform a
bounded parent evidence read before rendering fails, retain a stale prior map
and create recurring local error attempts. It does not start MQTT, refresh a
token, send a mower command or expose credentials, but it contradicts the
documented configuration-first rollback and no-invalid-timer contract.

### Required correction

Before enabling visibility or scheduling the timer, the configuration gate
must compose the existing validators to prove:

1. non-empty `DeviceId`;
2. accepted package byte, format and geometry-hash contract;
3. valid hidden-zone sequences and theme;
4. valid geometry, binding and zone-area semantics using existing map
   components rather than a duplicate validator.

Regression tests must prove timer zero, hidden/empty presentation and no parent
request for every invalid case. Existing valid dark, light, restart, retained
path and variable-stability behavior must remain unchanged.

## 3. Reviewed Boundaries

The following areas passed review:

- REST remains the sole public mower and station-state authority;
- MQTT remains receive-only and has no publish or mower-command route;
- `EnableLocalMap` still defaults to `false`;
- existing Device variable Idents, types, profiles and positions are stable;
- the additive `LocalMap` variable is bounded to one module-owned HTMLBox;
- SVG labels and presentation values are escaped and active content is absent;
- retained tracks are revision-, segment-, point-, byte- and time-bounded;
- duplicate points are rejected deterministically;
- map revision changes do not overlay paths across unaccepted geometry;
- dark and light themes preserve semantic station and diagnostic colors;
- the public PR contains no private geometry, coordinates, credentials, hosts,
  ObjectIDs or capture output;
- the generic fileset and publication inventories match the generated module.

## 4. Validation Evidence

The exact reviewed productive head passed:

    complete Navimow offline suite: PASS
    PHPCS: PASS
    PHPStan: PASS
    distribution validation: PASS
    generated fileset check: PASS
    generic publication check: PASS, no mutation
    git diff check: PASS

The isolated worktree used the general SAEF Composer resolver with the primary
checkout's vendor directory. The resolver verified identical `composer.lock`
files before invoking PHPCS and PHPStan; no source or Git state crossed the
worktree boundary.

Frozen productive candidate:

    fileCount
    42

    filesetSha256
    2d36cb88a552d6b6b7568673d1ba1b32cf4407fe9e894dcdfb5db3d35e06844c

    publicationSha256
    79f3c71e407c84736155b5c9afedaed377cb68293f3463088958215128e794bb

GitHub checks on the reviewed head:

    validate branch workflow: success
    validate pull-request workflow: success

Green CI does not waive the reproduced configuration-lifecycle finding.

## 5. Architecture Decision

### AD-NAV-371-01: Block merge on configuration-first correctness

**Decision:** Treat the premature timer as blocking even though it cannot send
a mower command or activate MQTT by itself.

**Reason:** A default-disabled feature must also fail closed when an operator
attempts to enable it with incomplete or malformed configuration. Repeated
error timers and stale presentation would make the first live rollout harder
to diagnose and contradict the approved runtime contract.

## 6. Gate Status

| Gate | Status |
| --- | --- |
| PR publication | passed |
| terminal CI on reviewed head | passed |
| engineering review | blocked by one medium finding |
| merge recommendation | blocked |
| merge | closed |
| standalone publication | closed |
| official Module Validator | closed |
| Symcon update or activation | closed |

## 7. Next Step

The next SAEF step should be
`372-local-map-runtime-fail-closed-configuration-correction.md`. It should make
the smallest compositional correction in `NavimowDevice`, add focused invalid
configuration tests, rebuild the fileset and rerun the full offline and PR
validation sequence. Productive correction requires separate approval.
