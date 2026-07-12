# 49 Pilot README Refresh and Second Tag Publication

**Case study:** Navimow native IP-Symcon module
**Status:** Published and remotely verified
**Date:** 2026-07-12
**Scope:** Refresh pilot guidance and publish `pilot-0.1.0.2`

## 1. Purpose

This step executes the conditional release decision from
`48-private-pilot-release-review-and-tag-decision.md`.

It updates the dedicated module README to match the completed recovery and
observation evidence, verifies that executable content remains unchanged and
publishes the second immutable private-pilot tag.

No productive PHP behavior or module metadata is changed.

## 2. Publication Target

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Publication branch:

```text
main
```

Previous executable commit:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

## 3. README Refresh

The canonical distribution and dedicated module repository README now record:

- recovery-hardened private-pilot status;
- restart-safe Dock verification without command replay;
- bounded token-refresh transport recovery;
- deterministic timeout and REST-failure evidence;
- supervised active-verification restart evidence;
- passive scheduled token-refresh evidence;
- the limited repeated-operation sample;
- unchanged command, OAuth, cloud API, privacy and Store boundaries;
- the current SAEF release-decision document.

The obsolete statement that timeout, restart and cloud read behavior still
needed pilot testing was removed.

## 4. Publication Boundary

The module repository change from `db36ea3` to the new publication commit is:

```text
M README.md
```

Explicit comparison confirmed no difference in:

- `library.json`;
- `NavimowAccount/`;
- `NavimowConfigurator/`;
- `NavimowDevice/`;
- `library/`.

The canonical case-study distribution and dedicated publish clone were
byte-equivalent before commit.

## 5. Pre-Publication Validation

The following checks passed:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
git diff --check
```

Results:

| Check | Result |
| --- | --- |
| deterministic pilot harness | 16 of 16 passed |
| REST client and authentication checks | passed |
| installable distribution structure | passed |
| productive content parity | passed |
| README parity | passed |
| whitespace validation | passed |
| publish change boundary | `README.md` only |

## 6. Publication Commit

Created and pushed dedicated module repository commit:

```text
937113e522f7a5323a8265b5b255855fcee7f19f
docs: refresh recovery-hardened pilot guidance
```

Commit scope:

```text
1 file changed, 23 insertions, 6 deletions
```

The commit contains documentation only. Its executable module tree is
identical to the directly tested `db36ea3` build.

## 7. Second Pilot Tag

Created and pushed annotated tag:

```text
pilot-0.1.0.2
```

Tag object:

```text
f5f20b4e31e902d0bd94ece6ed8922b82330d86d
```

Tag message:

```text
Private pilot 0.1.0.2: recovery-hardened REST MVP
```

Resolved tag commit:

```text
937113e522f7a5323a8265b5b255855fcee7f19f
```

## 8. Remote Verification

Direct remote reference verification returned:

| Reference | Remote object | Resolved commit |
| --- | --- | --- |
| `main` | `937113e522f7a5323a8265b5b255855fcee7f19f` | same |
| `pilot-0.1.0.1` | `21dc3eb0d2912ec3e957eb5e6ddfba88d290d0a2` | historical commit `692ea03` |
| `pilot-0.1.0.2` | `f5f20b4e31e902d0bd94ece6ed8922b82330d86d` | `937113e522f7a5323a8265b5b255855fcee7f19f` |

The dedicated publish clone is clean and synchronized with `origin/main`.
The published tag tree contains the expected 19 module distribution files.

The historical tag was not moved or modified.

## 9. Metadata State

`library.json` remains unchanged:

```text
version: 0.1
build: 0
date: 0
compatibility version: 6.2
```

The second pilot tag remains a controlled testing marker and does not use
public-release `v*` semantics.

## 10. Symcon Retest Decision

**Decision: no additional Symcon runtime or mower test required.**

The publication changes only `README.md`. All executable files are
byte-identical to the build already covered by:

- direct Symcon loading and authentication;
- read-only polling;
- supervised Dock transitions;
- active-verification service restart;
- passive scheduled token refresh.

Updating the module in Symcon is optional for repository synchronization but
cannot change runtime behavior at this commit.

## 11. Release Decision

**Decision: `pilot-0.1.0.2` PUBLISHED.**

The tag is an immutable, evidence-backed private-pilot snapshot containing the
recovery-hardened REST MVP and current operating guidance.

It approves continued controlled private-pilot use only.

It does not approve:

- broad public release;
- Symcon Store submission;
- public OAuth credential distribution;
- non-Dock commands;
- MQTT/WSS communication;
- removal of supervision requirements.

## 12. Architecture Decisions

### AD-NAV-125: Tag the tested executable tree with current guidance

**Decision:** Point `pilot-0.1.0.2` to a README-only commit whose productive
tree is identical to the tested hardening commit.

**Rationale:** The immutable snapshot must pair exact runtime evidence with
accurate operator guidance.

**Consequence:** The tag resolves to `937113e`, while executable evidence
remains anchored to byte-identical commit `db36ea3`.

### AD-NAV-126: Preserve historical pilot identity

**Decision:** Leave `pilot-0.1.0.1` unchanged.

**Rationale:** Pilot tags are immutable engineering checkpoints.

**Consequence:** The pre-hardening and recovery-hardened pilot states remain
independently traceable.

### AD-NAV-127: Avoid physical retest for documentation-only publication

**Decision:** Do not send another mower command or restart Symcon for this
README-only commit.

**Rationale:** No runtime path changed, so a physical test would add risk
without additional software evidence.

**Consequence:** Existing direct and supervised evidence remains valid.

## 13. Recommended Next Step

Create:

```text
50-second-pilot-case-study-consolidation.md
```

That step should consolidate steps 37 through 49, the deterministic harness,
canonical hardening changes and second pilot publication evidence into the
SAEF repository mainline as one traceable checkpoint.
