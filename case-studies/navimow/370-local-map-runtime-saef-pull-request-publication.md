# 370 Local Map Runtime SAEF Pull Request Publication

**Case study:** Navimow native IP-Symcon module

**Status:** Ready-for-review pull request published; initial identity and scope
verified; review, terminal checks and merge remain closed

**Date:** 2026-08-27

## 1. Result

Gate P2 passes.

    repository: doctee/symcon-ai-framework
    pull request: #82
    url: https://github.com/doctee/symcon-ai-framework/pull/82
    title: feat(navimow): integrate local map runtime
    base: main
    head: codex/navimow-map-source-readiness
    mode: ready for review
    initial head: 8432ad80f0cbc01df2ba39de60401386d41d35c6
    initial commits: 15
    initial paths: 63
    decision: PASS

The pull request was created exactly once after a fresh local, tracking, remote
and existing-PR preflight. It is open and not a draft.

## 2. Authorization Boundary

The user explicitly authorized the pull-request gate. This permits:

- fresh read-only Git and GitHub preflight;
- creation of one ready-for-review pull request;
- read-only verification of identity, scope and initial check state;
- this report and README entry;
- one documentation-only closure commit and normal fast-forward push;
- read-only verification of the resulting PR head.

It does not permit review approval, merge, auto-merge, force push, rebase,
squash, tag, release, branch deletion, worktree removal, standalone module
publication, Symcon access, map import, MQTT activation, authentication action,
restart or mower command.

## 3. Preflight

Immediately before PR creation:

    local head: 8432ad80f0cbc01df2ba39de60401386d41d35c6
    tracking head: 8432ad80f0cbc01df2ba39de60401386d41d35c6
    remote head: 8432ad80f0cbc01df2ba39de60401386d41d35c6
    origin/main: 016d78b028a8df261c0788133b86a5fdb9f411d2
    main ancestor: true
    branch commits: 15
    changed paths: 63
    outside approved surfaces: 0
    private paths: 0
    existing pull requests: 0
    worktree: clean

The allowed public surfaces are the Navimow case study, the two Navimow
deployment contracts and the generated Navimow standalone distribution.

## 4. Pull Request Contract

The published description records:

- privacy-safe source analysis and synthetic geometry fixtures;
- revision-bound scene projection and bounded track retention;
- default-disabled Account and Device runtime integration;
- REST-authoritative public mower and station state;
- receive-only MQTT path and task evidence without a command route;
- explicit Dark-Skin default with a validated light alternative;
- preserved variable identities, archive contracts and established commands;
- complete offline, PHPCS, PHPStan, distribution and generic publisher checks;
- separately closed standalone, validator, Symcon and activation gates.

No private geometry, coordinate, credential, host, ObjectID, capture output or
local system description is part of the PR.

## 5. Initial GitHub Readback

GitHub reported immediately after creation:

    state: OPEN
    draft: false
    mergeable: MERGEABLE
    merge state: UNSTABLE
    head: 8432ad80f0cbc01df2ba39de60401386d41d35c6
    base: 016d78b028a8df261c0788133b86a5fdb9f411d2
    commits: 15
    changed files: 63
    additions: 16856
    deletions: 14

`UNSTABLE` reflects the pending required check and is not interpreted as a
review failure.

## 6. Initial Check State

The initial readback observed:

    validate from branch publication: completed, success
    validate from PR publication: pending

This is an observation only. Terminal CI classification and engineering review
belong to a separate gate. Pending must not be treated as passed.

## 7. Closure Commit Contract

The documentation-only closure commit contains exactly:

    case-studies/navimow/README.md
    case-studies/navimow/370-local-map-runtime-saef-pull-request-publication.md

Its subject is:

    docs(navimow): record local map pull request publication

After the closure push, the expected PR scope is 16 commits and 64 changed
paths. The productive fileset and publication hashes remain unchanged:

    filesetSha256
    2d36cb88a552d6b6b7568673d1ba1b32cf4407fe9e894dcdfb5db3d35e06844c

    publicationSha256
    79f3c71e407c84736155b5c9afedaed377cb68293f3463088958215128e794bb

The resulting closure hash is read from Git after creation and is not embedded
recursively in this report.

## 8. Safety Result

This step creates one ready-for-review PR and one documentation-only closure
commit. It performs no merge, standalone publication, release, live-system
read, live-system mutation, authentication action, MQTT activation, restart or
device command.

## 9. Next Gate

The next SAEF step is the engineering review and terminal CI assessment of PR
#82. Merge remains separately authorized after that review passes.
