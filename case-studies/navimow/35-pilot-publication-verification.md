# 35 Pilot Publication Verification

**Case study:** Navimow native IP-Symcon module
**Status:** Passed
**Date:** 2026-07-10
**Scope:** Verify the published private-pilot branch, tag and repository content

## 1. Purpose

This step closes the publication gate defined in
`34-pilot-readme-publication-and-tag.md`.

It verifies that the dedicated module repository contains the intended REST
MVP snapshot, that the annotated pilot tag resolves to that snapshot and that
no unintended file was published.

This step does not change productive PHP behavior or module metadata.

## 2. Publication Target

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Verified references:

```text
refs/heads/main
refs/tags/pilot-0.1.0.1
```

## 3. Remote Reference Verification

The remote Git references were queried after the user completed both pushes.

| Reference | Remote object | Resolved commit | Result |
| --- | --- | --- | --- |
| `main` | `692ea0350bb73e6581e4643a931837ae48b49ede` | same | passed |
| `pilot-0.1.0.1` | annotated tag object `21dc3eb0d2912ec3e957eb5e6ddfba88d290d0a2` | `692ea0350bb73e6581e4643a931837ae48b49ede` | passed |

The branch and the resolved pilot tag therefore identify the same reviewed
private-pilot snapshot.

## 4. Published Content Verification

The GitHub repository content was read independently through both `main` and
`pilot-0.1.0.1`.

Results:

| Check | Result |
| --- | --- |
| Pilot README available on `main` | passed |
| Pilot README available on `pilot-0.1.0.1` | passed |
| README content identical on branch and tag | passed |
| README identifies the private-pilot / REST MVP boundary | passed |
| README documents supervised OAuth and safe Dock use | passed |
| README documents known limitations and privacy boundaries | passed |

The published Git tree contains only the expected distribution files:

- `library.json` and `README.md`;
- account, configurator and device module files;
- the five shared Navimow library files.

The tree contains 19 tracked files. No `.DS_Store`, fixture, capture, test,
credential or case-study file was published in the module repository.

## 5. Metadata Verification

The tagged `library.json` contains the planned pilot metadata:

```text
version: 0.1
build: 0
date: 0
compatibility version: 6.2
```

This matches `33-release-metadata-and-tag-plan.md`. The pilot tag did not
silently introduce public-release or Symcon Store version semantics.

## 6. Regression Checks

The canonical case-study source was checked again after publication:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
git diff --check
```

Results:

| Check | Result |
| --- | --- |
| REST client, authentication, fixture and static safety checks | passed |
| Installable distribution structure | passed |
| Framework and publish-clone whitespace checks | passed |
| Publish-clone working tree | clean |

The remote `main` commit equals the clean local publish-clone commit. This
provides a deterministic link between the validated local tree and the
published repository tree.

## 7. Symcon Retest Decision

No additional Symcon runtime test is required for this publication step.

The only change after the successfully tested implementation commit
`a6178dc` was the pilot README commit `692ea03`. The PHP modules, shared
libraries and `library.json` remained unchanged. Repeating the supervised
physical mower test would add risk without testing a changed runtime path.

Symcon installations may update to the current `main` branch, but the expected
runtime behavior remains the behavior already recorded in
`30-dock-transition-verification-live-test.md`.

## 8. Architecture Decisions

### AD-NAV-066: Verify the resolved tag commit

**Decision:** A published annotated tag is accepted only after both its tag
object and its resolved commit are verified remotely.

**Rationale:** Confirming only the tag name would not prove which immutable
module snapshot a pilot installation receives.

**Consequence:** `pilot-0.1.0.1` is traceably bound to the reviewed commit
`692ea03`.

### AD-NAV-067: Do not repeat physical tests for documentation-only publication

**Decision:** Do not send another mower command when publication changes only
the README and all executable files remain byte-identical to the tested
snapshot.

**Rationale:** Physical integration tests must be proportional to changed
runtime risk. An unnecessary mower command provides no additional evidence for
a documentation-only commit.

**Consequence:** The existing supervised transition evidence remains the
runtime release evidence for this pilot tag.

## 9. Gate Decision

**Decision: GO for controlled private-pilot use.**

The first Navimow pilot snapshot is now published, immutable through
`pilot-0.1.0.1`, documented and traceable to the validated implementation.

This decision does not approve:

- broad public release;
- Symcon Store submission;
- non-Dock commands;
- MQTT/WSS support;
- removal of supervision requirements.

## 10. Recommended Next Step

Create:

```text
36-case-study-mainline-consolidation.md
```

That step should review and commit the accumulated SAEF documents, accepted
fixtures, tests and canonical distribution changes as one traceable framework
checkpoint. After consolidation, define the private-pilot observation matrix
for timeout, restart recovery and cloud-error behavior.
