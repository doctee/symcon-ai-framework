# 33 Release Metadata and Tag Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Pilot metadata and tag policy decided; publication pending
**Date:** 2026-07-09
**Scope:** Version metadata, build/date handling, tag naming and publication
sequence for the private pilot

## 1. Purpose

This step defines how the current REST MVP should be identified in the
dedicated module repository after the private-pilot preparation in
`32-private-pilot-release-preparation.md`.

It answers:

- whether `library.json` should change now;
- which tag naming scheme should be used;
- whether the improved distribution README should be published;
- what should and should not be implied by a pilot tag.

No productive PHP behavior is changed in this step.

## 2. Current Metadata

Current `distribution/library.json`:

```json
{
  "version": "0.1",
  "build": 0,
  "date": 0
}
```

Current published module repository state:

| Item | Value |
| --- | --- |
| repository | `doctee/symcon-navimow` |
| branch | `main` |
| latest published code commit | `a6178dc feat: add long-running Dock verification` |
| local tag list | empty |

The private-pilot README update from step 32 exists in the SAEF distribution
workspace but has not yet been published to the dedicated module repository.

## 3. Release Identity Decision

### Decision

Keep `library.json` metadata unchanged for the current private pilot:

```text
version: 0.1
build: 0
date: 0
```

Use Git commits and optional pilot tags for traceability during the private
pilot.

### Rationale

The module is not yet a broad public release or Symcon Store candidate.
Changing version/build/date now could imply a formal release maturity that the
case study has explicitly not approved.

The current pilot still has open blockers:

- public OAuth credential/setup policy;
- Symcon Store compatibility review;
- restart-during-verification evidence;
- timeout behavior evidence;
- broader troubleshooting documentation.

### Consequence

Version metadata remains intentionally conservative. Release traceability is
provided by the Git commit and optional pilot tag, not by a public-style
version bump.

## 4. Tag Naming Policy

Recommended tag scheme for private pilot milestones:

| Purpose | Tag format | Example |
| --- | --- | --- |
| private pilot snapshot | `pilot-0.1.0.N` | `pilot-0.1.0.1` |
| public pre-release | `v0.1.0-rc.N` | `v0.1.0-rc.1` |
| public release | `v0.1.0` | `v0.1.0` |
| Symcon Store candidate | `vX.Y.Z-store-rc.N` if needed | `v0.1.0-store-rc.1` |

Rules:

- `pilot-*` tags are controlled-test markers, not public release claims.
- `v*` tags are reserved for public release semantics.
- Annotated tags are preferred over lightweight tags once the pilot is shared
  with anyone beyond the immediate test installation.
- Tags should point to a commit in the dedicated module repository, not to the
  SAEF case-study repository.

## 5. Build and Date Policy

For private pilot:

- keep `build` at `0`;
- keep `date` at `0`;
- use commit SHA and optional pilot tag for exact identification.

For public pre-release or release:

- define `build` as a monotonically increasing integer;
- set `date` to the release date in the format expected by Symcon module
  conventions;
- update `library.json` and publish that metadata change in the dedicated
  module repository;
- create a matching Git tag.

This avoids mixing pilot snapshots with formal release metadata.

## 6. Immediate Publication Plan

The next publication should contain documentation only:

```text
distribution/README.md
```

Recommended module repository commit message:

```text
docs: add private pilot usage guidance
```

After that commit is pushed, create an optional annotated tag:

```text
pilot-0.1.0.1
```

Suggested tag message:

```text
Private pilot 0.1.0.1: REST MVP with Dock verification
```

The tag should mean:

- OAuth, discovery, read-only status and Dock are pilot-tested;
- Dock verification handles `Running -> Docking -> Docked`;
- only Dock is enabled;
- the module is not public-release or store-ready.

## 7. What Not To Publish Yet

Do not publish these changes as part of the pilot tag:

- Start, Stop, Pause or Resume commands;
- MQTT/WSS support;
- location/map variables;
- Symcon Store metadata claims;
- public OAuth credential instructions that are not supportable;
- generated private captures;
- private local test scripts.

## 8. Verification Before Tagging

Before creating a pilot tag, run:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
git diff --check
```

Also verify:

- no `.DS_Store` files are copied into the dedicated module repository;
- `distribution/README.md` contains no private credentials or local paths;
- `library.json` is unchanged unless a formal release step explicitly changes
  it;
- the dedicated module repository working tree is clean before tagging.

## 9. Architecture Decisions

### AD-NAV-061: Use pilot tags instead of metadata bump for private pilot

**Decision:** Identify private pilot snapshots with Git tags rather than
changing `library.json` version/build/date.

**Rationale:** The module has strong private-pilot evidence but still has
public release blockers. A pilot tag gives traceability without overstating
release maturity.

**Consequence:** Symcon module metadata stays at `0.1` until a formal public
pre-release or release decision is made.

### AD-NAV-062: Publish README guidance before tagging

**Decision:** Publish the improved private-pilot README before creating a
pilot tag.

**Rationale:** The tag should identify not only the code state but also the
minimum user-facing safety and limitation guidance.

**Consequence:** The next dedicated module repository commit should be a docs
commit before any tag is created.

### AD-NAV-063: Reserve `v*` tags for public release semantics

**Decision:** Use `pilot-*` for private pilot snapshots and reserve `v*` for
public release or pre-release semantics.

**Rationale:** This prevents pilot snapshots from being mistaken for public
release artifacts.

**Consequence:** The first optional tag should be `pilot-0.1.0.1`, not
`v0.1.0`.

## 10. Recommended Next Step

Create:

```text
34-pilot-readme-publication-and-tag.md
```

That step should:

1. copy the updated distribution README to the dedicated module repository;
2. validate the module repository working tree;
3. commit `docs: add private pilot usage guidance`;
4. optionally create annotated tag `pilot-0.1.0.1`;
5. push commit and tag;
6. document the exact resulting commit and tag.
