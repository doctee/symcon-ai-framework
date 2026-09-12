# SAEF Step 400: Mowing Analytics And HTML SDK Publication Readiness

- **Date:** 2026-09-12
- **Status:** Publication candidate verified; all mutation gates remain closed
- **Scope:** SAEF repository candidate only

## 1. Purpose

This step converts the offline result from steps 393 to 399 into a bounded
publication candidate. It verifies the current canonical SAEF base, freezes
the exact public fileset and separates repository publication from standalone
module publication, disabled Symcon rollout and live feature activation.

No commit, push, pull request, standalone publication, Symcon update, Archive
mutation, MQTT activation, OAuth action, restart or mower command belongs to
this step.

## 2. Canonical Base

The dedicated worktree and branch are:

```text
workstream: navimow-statistics-html-sdk
branch:     codex/navimow-statistics-html-sdk
HEAD:       4c6090f2969b1955238f679030150a912bceb75f
origin/main:4c6090f2969b1955238f679030150a912bceb75f
ahead/behind: 0/0
```

`origin/main` was refreshed immediately before the comparison. The primary
checkout was not used as a development or integration surface.

## 3. Allowed Change Scope

The candidate is restricted to:

```text
case-studies/navimow/
deployments/symcon/navimow-module.fileset.json
deployments/symcon/navimow-publication.json
dist/symcon/symcon-navimow-module/
```

No other SAEF case study, helper, standard, knowledge article, deployment
channel or distribution is changed. The generated distribution is derived
only through the generic Symcon module fileset and publication contracts.

## 4. X450 Physical Calibration

The mower used by this case study is a Navimow X450. The manufacturer specifies
a 430 mm cutting width, so its installation value is:

```text
CuttingWidthMeters = 0.43
```

This value is not made the generic module default. The fail-closed default
remains `0.0`, and geometric area output remains unavailable until a user sets
a verified model-specific width. `MetersPerLocalUnit` remains a separate
installation-specific calibration gate.

Manufacturer reference:
<https://de.navimow.com/products/navimow-x4-robot-lawn-mower>

## 5. Safety And Authority Review

- REST remains authoritative for mower state and all commands.
- MQTT remains receive-only and cannot issue a mower command.
- Mowing analytics and the HTML SDK presentation default to disabled.
- A missing cutting width produces no geometric area claim.
- Existing Pause, Resume and Dock contracts are unchanged.
- No Start or Stop command is introduced.
- Retention is bounded by revision, age, run count, zone count, subarea count,
  serialized bytes and coverage samples.
- Stored analytics contain bounded aggregates, not raw garden geometry or raw
  coordinate tracks.

## 6. Archive And Variable Continuity

Productive module code contains no Archive Control mutation. Existing variable
Idents are preserved, and disabling analytics does not delete newly introduced
statistics variables.

The current installation-owned 25-target Archive contract remains a mandatory
precondition and postcondition for the later disabled rollout. New analytics
variables may be added to standard logging only after they exist in Symcon and
their IDs, types, profiles and ownership have passed read-only verification.
That additive Archive operation is a separate live gate.

The following are deliberately not Archive targets:

- `LocalMap` HTML;
- HTML SDK transport state;
- serialized reducer attributes;
- raw coordinates, MQTT payloads and error text; and
- high-frequency timestamps that do not add analytical value.

## 7. Validation Evidence

The final candidate passes:

| Check | Result |
|---|---|
| Current `origin/main` comparison | PASS, `0/0` |
| Exact changed-path allowlist | PASS |
| Public privacy scan | PASS |
| Navimow functional checks | PASS |
| New analytics reducer and Device tests | PASS |
| HTML SDK CSP and no-external-runtime checks | PASS |
| PHPCS | PASS |
| PHPStan | PASS |
| Complete repository `composer check` | PASS |
| `git diff --check` | PASS |
| Deterministic publication check | PASS |

The isolated worktree intentionally contains no local `vendor/` directory. The
complete check therefore used the repository's general Composer vendor
resolver with the canonical dependency directory only after byte-identical
`composer.lock` files had been proven. All source, configuration and generated
artifact paths remained rooted in this worktree. No Open-Meteo-specific test
path or source checkout was substituted for Navimow validation.

The final standalone candidate identity is:

```text
fileCount:         47
filesetSha256:      af05215075f633f87ab1a7cda5d51a85d1a15db135214089e656af3284748aa6
publicationSha256: f356fdbe03de8e4c3233227f1fdb6446dc826d07a95884582ba67067f20c0d7e
```

No private paths, credentials, tokens, device identifiers, MQTT topics,
ObjectIDs, hostnames, garden coordinates or private geometry are part of the
public candidate.

## 8. Publication Sequence

The next gates remain separate and ordered:

1. **P1, SAEF branch publication:** stage the exact allowlist, create one
   Conventional Commit, push the topic branch and open a pull request.
2. **P2, SAEF review and merge:** review exact delta, require green checks and
   merge only after a separate approval.
3. **S1, standalone publication:** publish the frozen 47-file candidate through
   the generic pull-request publisher and verify metadata before merge.
4. **L1, disabled Symcon rollout:** update once with MQTT, analytics and the
   HTML SDK presentation disabled; prove existing IDs and all 25 Archive
   contracts before and after the update.
5. **L2, presentation and analytics activation:** configure the verified X450
   width, coordinate scale and local time zone, then validate browser and app
   rendering without enabling MQTT.
6. **L3, Archive extension:** add the stable new analytics variables to
   installation-owned standard logging through one hash-bound additive gate.
7. **L4, MQTT operation:** activate receive-only MQTT separately under its
   continuous-operation and cleanup contracts.

Failure or ambiguity at one gate grants no authority for a retry or for the
next gate.

## 9. Rollback And Retention

- Before L1, retain the currently installed standalone commit and a read-only
  projection of variable and Archive contracts.
- Configuration-first rollback disables analytics and the HTML SDK surface
  without deleting variables or Archive history.
- Standalone rollback uses the retained preceding module commit.
- Credential-bearing MQTT cleanup remains independent of visualization or
  analytics rollback.
- Private evidence is retained until L1 to L4 are closed or explicitly
  abandoned; it is never copied into public SAEF artifacts.

## 10. Gate Result

| Gate | Status |
|---|---|
| Publication readiness | PASS |
| X450 cutting-width calibration | PASS, `0.43 m` |
| SAEF commit, push and pull request | CLOSED pending P1 approval |
| SAEF review and merge | CLOSED |
| Standalone publication | CLOSED |
| Disabled Symcon rollout | CLOSED |
| Analytics and HTML SDK activation | CLOSED |
| New analytics Archive logging | CLOSED |
| Receive-only MQTT activation | CLOSED |
