# 368 Local Map Runtime Candidate Review And Publication Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Offline candidate review passed; commit, publication and live gates
remain closed

**Date:** 2026-08-27

## 1. Review Scope

This step reviews the complete step-367 local-map runtime candidate in the
isolated `codex/navimow-map-source-readiness` worktree. It includes the later
Dark-Skin presentation requirement without changing the established runtime
authority or safety boundaries.

The review performs no network, authentication, Symcon, MQTT or mower action.
It does not create a commit, push a branch, open a pull request or publish the
standalone module.

## 2. Candidate Result

The candidate is internally consistent and ready for a separately approved
local commit and SAEF pull-request publication candidate.

It remains additive and default-disabled:

- `EnableLocalMap=false` remains the activation boundary;
- `LocalMap` keeps its stable string type, `~HTMLBox` profile and position 100;
- existing Device variable Idents, types, profiles and positions are unchanged;
- REST remains authoritative for public mower and station state;
- MQTT remains receive-only supporting evidence for path and task data;
- no Start, Stop or new mower command is introduced;
- no private map geometry or installation data enters the public fileset.

## 3. Dark-Skin Presentation Review

The embedded SVG cannot reliably inherit the surrounding Symcon skin.
Therefore the Device owns one explicit validated `MapTheme` property:

| Value | Result |
| --- | --- |
| `dark` | default; charcoal background with light path and labels |
| `light` | explicit light presentation |
| another value | configuration fails closed and the map timer remains off |

The dark palette uses distinct blue, green, amber, pink, indigo and teal zone
families rather than one dominant hue. Excluded areas use low-opacity fills and
dashed neutral outlines. The three orange points remain visible diagnostic
evidence for captured coordinates outside the accepted zone polygons; they are
not mowing coverage or obstacle markers.

Station color remains independent of the theme and REST-authoritative:

| State | Color meaning |
| --- | --- |
| `docked` | green |
| `docking` | amber |
| `undocked` | slate |
| `unknown` | petrol |

The current Zone 4 label remains hidden by presentation configuration while
its geometry remains available for attribution and revision control.

## 4. Private Visual Evidence

A new create-once private preview was rendered from the previously accepted
private geometry and bounded Zone 1 track evidence. It contains 25 retained
points: 22 attributed to Zone 1 and three outside the accepted zone polygon.
No point intersects an excluded area.

The SVG and PNG are retained only below the ignored private capture output.
They are not part of the public candidate, publication manifest or case-study
evidence. Visual inspection confirms:

- complete dark viewport coverage in the SVG;
- readable path, labels and station marker;
- distinct zone boundaries and fills;
- restrained excluded-area presentation;
- unchanged geometry and diagnostic-point semantics.

## 5. Verification

The focused local-map contracts pass for renderer themes, Device lifecycle,
runtime reduction, restart and disable behavior, variable stability and
distribution inventory.

The complete Navimow offline check passes, including all existing REST,
commands, MQTT, diagnostics, pilot, recovery, geometry and map tests, PHPCS and
PHPStan.

The general SAEF Composer toolchain resolver was used with the primary
checkout's vendor directory. It verified identical `composer.lock` files
before invoking PHPCS and PHPStan. Only immutable analysis tools were shared;
source files, generated artifacts and Git state remained in the isolated
worktree.

The generic publication check passed without mutation:

    fileCount
    42

    filesetSha256
    2d36cb88a552d6b6b7568673d1ba1b32cf4407fe9e894dcdfb5db3d35e06844c

    publicationSha256
    79f3c71e407c84736155b5c9afedaed377cb68293f3463088958215128e794bb

Focused productive hashes:

    NavimowAccount/module.php
    19e4e500ac4e660dd3cb16ce621cdc894e8524e66db6b84b40703393be8981ce

    NavimowDevice/module.php
    2966da403a57d8d39c2c78875dae480f4194ddc24ea8322491187eea86fe31ae

    LocalMapSceneProjector.php
    8fd9f9974ab2d52344cf7f41e71e48a0ffe7a5d73c30910c82a3ab3e079a542d

    LocalMapSvgRenderer.php
    2dcb883935a502a61ddc0bd796af63932955694af9b62adbd3f5ed7b4f929993

    RevisionBoundedTrackStore.php
    c02ff045fc79ae09860d2edbc5eb8d939440d4845f3a1a2b05a9c117115efa9f

`git diff --check` passes. The public delta contains no credentials, tokens,
private hosts, personal ObjectIDs, coordinates, garden labels or private map
payloads.

## 6. Architecture Decisions

### AD-NAV-368-01: Keep presentation theme explicit

**Decision:** Use a validated Device property instead of attempting implicit
skin detection inside the embedded SVG.

**Reason:** Explicit configuration is deterministic across Symcon consoles and
can be verified offline.

### AD-NAV-368-02: Preserve semantic colors across themes

**Decision:** Theme changes alter contrast but not station-state or diagnostic
point meanings.

**Reason:** Presentation must not redefine REST authority or evidence quality.

### AD-NAV-368-03: Share only a lock-verified toolchain

**Decision:** Use the general SAEF vendor resolver for isolated-worktree
analysis.

**Reason:** This avoids copying dependencies into the worktree while proving
that the analyzer versions match its canonical lock file.

## 7. Gate Matrix

| Gate | Status |
| --- | --- |
| offline candidate review | passed |
| private Dark-Skin preview | passed |
| local commit | closed |
| SAEF branch push and pull request | closed |
| standalone publication | closed |
| official Module Validator | closed |
| Symcon disabled update | closed |
| private map import | closed |
| MQTT or local-map live activation | closed |
| visualization placement | closed |

## 8. Decision And Next Step

The candidate is ready for Gate P1: create one local Conventional Commit in the
isolated worktree, then read back its exact tree and hashes. Push, pull request,
standalone publication and every Symcon action require their own later gates.

The next SAEF step should be
`369-local-map-runtime-saef-publication.md` only after explicit approval of the
local commit and SAEF branch publication sequence.
