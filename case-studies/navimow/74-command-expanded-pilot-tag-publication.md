# 74 Command-Expanded Pilot Tag Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Command-expanded pilot guidance and `pilot-0.1.0.3` published
**Date:** 2026-07-12
**Scope:** Publish a documentation-complete immutable Pause/Resume/Dock pilot checkpoint

## 1. Purpose

This step executes the tag preparation GO from
`73-resume-integration-review-and-stop-readiness.md`.

It:

- refreshes the module README to match completed Pause and Resume evidence;
- revalidates the unchanged executable distribution;
- publishes one documentation-only commit;
- creates and pushes annotated tag `pilot-0.1.0.3`;
- verifies the remote tag and historical tag immutability.

No productive PHP behavior, module metadata, Symcon configuration or mower
state is changed.

## 2. Publication Target

Repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Branch:

```text
main
```

Directly tested executable commit before the documentation refresh:

```text
64188f75527abcb49b0b27ce2b56ad2d34a403fd
feat: add bounded Resume command
```

## 3. README Refresh

The canonical and published README now records:

- evidence-backed command-expanded private-pilot status;
- enabled Pause, Resume and Dock scope;
- disabled Stop and Start scope;
- one direct private and one direct Symcon transition for both Pause and
  Resume;
- preservation of all eight public variable identities;
- preservation of all five operator-enabled archive logging streams;
- movement, supervision, one-write and bounded-verification rules;
- unofficial API, OAuth, Store and broad-release limitations;
- the current command integration decision in step 73.

An obsolete reference to the earlier Dock-only release decision was replaced.

## 4. Publication Boundary

The dedicated module repository delta is exactly:

```text
M README.md
```

Diff summary:

```text
1 file changed, 8 insertions, 6 deletions
```

Explicit comparison confirmed no change in:

- `library.json`;
- account, configurator or device PHP;
- module metadata, forms or locales;
- command and API libraries;
- variable registrations or profiles;
- runtime behavior.

The canonical README and publication clone were byte-equivalent before the
commit.

## 5. Validation Results

| Check | Result |
| --- | --- |
| productive PHP syntax | 8 of 8 passed |
| metadata/form/locale JSON parsing | 10 of 10 passed |
| REST client and authentication checks | passed |
| deterministic observation and command harness | 29 of 29 passed |
| distribution structure validation | passed |
| official Symcon schema gate | 10 of 10 passed |
| privacy scan | passed |
| whitespace validation | passed |
| publication boundary | README only |

The official schema fallback retained the validator's four official schemas
and declared AJV `6.10.2`, as documented in earlier publication steps. No
validator dependency entered the module repository.

## 6. Publication Commit

Created and pushed commit:

```text
5ff3a86204e5e6b3c7959dd54768550b32d6638a
docs: refresh command-expanded pilot guidance
```

The executable tree is identical to the directly tested Resume build at
`64188f7`.

## 7. Third Pilot Tag

Created and pushed annotated tag:

```text
pilot-0.1.0.3
```

Tag message:

```text
Private pilot 0.1.0.3: Pause, Resume and Dock
```

Tag object:

```text
b395c56a28d9681a5e95a0a1fa106bba551c457b
```

Resolved tag commit:

```text
5ff3a86204e5e6b3c7959dd54768550b32d6638a
```

The distinct tag-object and commit hashes confirm an annotated rather than
lightweight tag.

## 8. Remote Verification

Independent remote-reference verification confirmed:

| Check | Result |
| --- | --- |
| remote `main` | `5ff3a86` |
| `pilot-0.1.0.3` resolves to remote `main` | passed |
| tag object type | annotated tag |
| commit delta | README only |
| `pilot-0.1.0.1` object | unchanged |
| `pilot-0.1.0.2` object | unchanged |
| publication clone | clean and synchronized |

No historical tag was moved or recreated.

## 9. Metadata State

`library.json` remains:

```text
version: 0.1
build: 0
date: 0
compatibility version: 6.2
```

The new tag is a controlled private-pilot marker. It does not use public `v*`
release semantics and does not imply Store readiness.

## 10. Pilot Tag Meaning

`pilot-0.1.0.3` identifies:

- supervised OAuth and scheduled token refresh;
- discovery and REST-polled status;
- bounded, no-retry Dock, Pause and Resume commands;
- restart-safe read-only command verification;
- deterministic timeout, read-failure and token-recovery behavior;
- supervised live Dock, Pause and Resume evidence;
- stable public variables and preserved archive logging through update;
- current operator safety and privacy guidance.

It does not approve:

- Stop or Start;
- unattended mower operation;
- public OAuth credential distribution;
- broad model or firmware claims;
- MQTT/WSS or location/map support;
- Symcon Store submission;
- broad public release.

## 11. Symcon Retest Decision

**No additional Symcon update or physical command test is required.**

The tag-publication commit changes documentation only. Its executable tree is
identical to the build already covered by the compatibility, archive and
direct Resume verification in step 72. A further mower action would add
physical risk without increasing software evidence.

An optional Symcon module update would synchronize repository documentation
only and cannot alter runtime behavior.

## 12. Architecture Decisions

### AD-NAV-246: Tag the complete suspended-task lifecycle

**Decision:** Use one pilot marker for the independently verified Pause and
Resume pair together with Dock.

**Rationale:** The tag describes a coherent command set rather than an
intermediate implementation state.

**Consequence:** `pilot-0.1.0.3` is the current command-expanded private-pilot
checkpoint.

### AD-NAV-247: Keep the tag commit documentation-only

**Decision:** Point the tag to a README-only commit above the tested executable
commit.

**Rationale:** Immutable runtime evidence remains byte-identical while operator
guidance becomes complete.

**Consequence:** No runtime retest is required for the tag commit.

### AD-NAV-248: Preserve private-pilot metadata

**Decision:** Leave version, build and date unchanged.

**Rationale:** Public OAuth, complete command scope and Store gates remain
open.

**Consequence:** Traceability comes from the Git tag without overstating
release maturity.

### AD-NAV-249: Preserve every historical pilot marker

**Decision:** Create a new annotated tag and never move existing tags.

**Rationale:** Each pilot marker must remain an auditable engineering
checkpoint.

**Consequence:** Dock-only, recovery-hardened and command-expanded states remain
independently resolvable.

## 13. Decision

**README publication: PASS.**

**Annotated `pilot-0.1.0.3` publication: PASS.**

**Remote and historical-tag verification: PASS.**

The command-expanded private pilot now has an immutable,
documentation-complete checkpoint.

## 14. Recommended Next Step

Create `75-stop-support-and-semantics-analysis.md` for credential-free,
non-actuating review of current vendor, official-integration and maintained
community evidence. The step must decide whether Stop may advance to a
conditional capture plan or must be formally excluded. No Stop request is
authorized by this tag.
