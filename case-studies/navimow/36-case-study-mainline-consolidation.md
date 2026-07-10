# 36 Case Study Mainline Consolidation

**Case study:** Navimow native IP-Symcon module
**Status:** Passed
**Date:** 2026-07-10
**Scope:** Consolidate the evidence-backed private-pilot state in SAEF mainline

## 1. Purpose

This step consolidates the work completed after the first supervised Dock
implementation checkpoint.

It reviews and records the accepted transition evidence, long-running Dock
verification, direct Symcon test reports, release decisions, pilot metadata,
published documentation and canonical distribution changes as one traceable
SAEF mainline checkpoint.

No new productive behavior is introduced in this step.

## 2. Consolidation Boundary

The checkpoint is restricted to:

```text
case-studies/navimow/
```

It contains:

- SAEF steps `24` through `36`;
- the accepted Dock SUCCESS and Docking status fixtures;
- the timer-driven long-running Dock verification implementation;
- fixture-backed and static command-safety checks;
- private-pilot installation and safety documentation;
- the Navimow case-study index update;
- removal of two installation-specific local paths from case-study reports.

The checkpoint deliberately excludes:

- `private/` capture and publication workspaces;
- raw API captures;
- credentials, tokens and private device identifiers;
- unrelated framework files and working-tree changes;
- any change to the separately published module repository.

## 3. Evidence Chain Review

The consolidated documents form this evidence chain:

```text
transition plan
-> supervised private capture
-> sanitized fixtures
-> long-running verification design
-> implementation
-> direct Symcon already-docked test
-> direct Symcon Running-to-Docked test
-> stabilization decision
-> pilot preparation
-> immutable pilot publication
-> remote publication verification
```

The review confirmed that each implementation or release decision has a
preceding requirement, evidence or explicit safety rationale.

## 4. Canonical Distribution Review

The canonical distribution now contains the implementation published as
private pilot `pilot-0.1.0.1`:

- Dock remains the only enabled mower command;
- one user action sends exactly one Dock command;
- command transport has no retry path;
- verification uses read-only status calls;
- `Docking` is treated as progress;
- polling is bounded to 60-second intervals while returning;
- final `Docked` verification is bounded by a 15-minute deadline;
- non-Dock commands remain unavailable.

After excluding ignored local OS metadata, the canonical distribution and the
clean dedicated publish clone are identical.

## 5. Fixture and Contract Review

The two newly accepted fixtures are:

| Fixture | Contract evidence |
| --- | --- |
| `fixtures/rest/command-dock-success.json` | Real successful Dock response maps to `Accepted`. |
| `fixtures/rest/vehicle-status-docking.json` | Real `isDocking` state maps to `Docking` and represents progress. |

Both fixtures use SAEF placeholders for request, device and command
identifiers. No raw capture file is included in the checkpoint.

The fixture index and REST test suite reference the accepted filenames and
mapping expectations.

## 6. Validation Gate

The complete consolidation set was validated before commit.

Executed checks:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
php -l for all distribution and test PHP files
JSON decoding for all case-study JSON files
git diff --check
distribution-to-publish-clone comparison
private-data pattern scan
```

Results:

| Check | Result |
| --- | --- |
| REST, authentication, fixture and static safety checks | passed |
| Symcon distribution structure validator | passed |
| PHP syntax checks | passed |
| JSON decoding | 18 files passed |
| Whitespace check | passed |
| Canonical distribution parity with published pilot | passed |
| Private-data review | passed after local-path sanitization |

The privacy scan identified two local workspace paths in reports. They were
replaced with installation-independent wording before consolidation. A test
value named `ACCESS_PRIVATE_VALUE` remains intentionally present as a
non-secret placeholder used to verify debug-output sanitization.

## 7. Working Tree and Commit Boundary

Only files below `case-studies/navimow/` belong to this checkpoint.

The root `.gitignore` has an existing local modification and is explicitly not
part of this consolidation. Ignored `.DS_Store` files are also absent from the
checkpoint.

The Git commit containing this document is the canonical SAEF mainline
checkpoint for the published Navimow REST MVP private pilot.

## 8. Architecture Decisions

### AD-NAV-068: Consolidate only evidence-backed behavior

**Decision:** Include only the Dock command and long-running verification
behavior already supported by fixtures and supervised Symcon evidence.

**Rationale:** Mainline consolidation must preserve the proven MVP boundary and
must not silently expand physical command scope.

**Consequence:** Start, Stop, Pause and Resume remain outside the checkpoint.

### AD-NAV-069: Treat the case-study distribution as canonical source

**Decision:** Require byte-equivalent productive files between
`case-studies/navimow/distribution/` and the dedicated module publish clone.

**Rationale:** A separate publication repository is operationally necessary
for Symcon, but engineering decisions and review evidence remain owned by the
SAEF case study.

**Consequence:** Future productive changes must first be reviewed and tested in
the case-study distribution before publication.

### AD-NAV-070: Keep private operational work outside the checkpoint

**Decision:** Exclude capture workspaces, raw responses and local publication
paths from the mainline checkpoint.

**Rationale:** Reproducible engineering evidence requires sanitized fixtures
and generic procedures, not installation-specific operational data.

**Consequence:** The committed checkpoint can be reviewed and shared without
exposing the private test installation.

## 9. Gate Decision

**Decision: GO for SAEF mainline consolidation.**

The Navimow case study, canonical distribution and published private-pilot tag
now represent one consistent evidence-backed REST MVP state.

The consolidation does not change the release boundary established in steps
31 and 35: controlled private-pilot use is approved, while broad release and
additional mower commands remain blocked.

## 10. Recommended Next Step

Create:

```text
37-private-pilot-observation-plan.md
```

That step should define bounded pilot scenarios and evidence requirements for:

- verification timeout;
- Symcon restart during active Dock verification;
- temporary cloud read failures;
- token expiry during status polling;
- repeated normal operation without duplicate command delivery.
