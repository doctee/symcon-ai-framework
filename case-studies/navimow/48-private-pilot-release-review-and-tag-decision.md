# 48 Private Pilot Release Review and Tag Decision

**Case study:** Navimow native IP-Symcon module
**Status:** Conditional GO for `pilot-0.1.0.2`
**Date:** 2026-07-12
**Scope:** Release review after recovery hardening and pilot observation closure

## 1. Purpose

This step performs the release review requested by
`47-passive-token-refresh-observation.md`.

It decides whether the tested recovery-hardening build may receive a second
immutable private-pilot tag. It also defines the exact publication boundary
without approving a broad public release, Symcon Store submission or command
expansion.

No productive PHP code, module metadata or remote Git reference is changed in
this step.

## 2. Release Candidate

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow.git
```

Reviewed executable commit:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

Proposed new private-pilot tag:

```text
pilot-0.1.0.2
```

The existing historical tag remains immutable:

```text
pilot-0.1.0.1 -> 692ea0350bb73e6581e4643a931837ae48b49ede
```

## 3. Remote Reference Verification

The dedicated repository references were queried directly during this review.

| Reference | Remote result |
| --- | --- |
| `main` | `db36ea37cb40298278307e88d65ae8c450603f18` |
| `pilot-0.1.0.1` | annotated tag object `21dc3eb0d2912ec3e957eb5e6ddfba88d290d0a2` |
| `pilot-0.1.0.2` | absent |

The local publish clone is clean and its `HEAD`, local `main` and
`origin/main` all identify `db36ea3`.

No existing tag must be moved or replaced.

## 4. Technical Evidence Review

The release candidate has the following evidence:

| Area | Evidence | Decision |
| --- | --- | --- |
| module structure | distribution validator and direct Symcon loading | PASS |
| OAuth authorization | supervised direct Symcon authentication | PASS |
| scheduled token refresh | deterministic coverage plus passive live observation | PASS |
| discovery and status | direct discovery, polling and status updates | PASS |
| Dock command | supervised single-command transitions | PASS |
| long return verification | Running, Docking and Docked live evidence | PASS |
| verification timeout | exact deterministic deadline evidence | PASS |
| restart recovery | deterministic reconstruction plus supervised live restart | PASS |
| temporary REST failure | bounded deterministic recovery and timeout evidence | PASS |
| command replay prevention | deterministic and live evidence | PASS |
| repeated operation | three successful transitions, one restart-stressed | PASS WITH LIMITATION |
| privacy boundary | sanitized reports and no retained private capture data | PASS |

The `OBS-01` through `OBS-05` matrix is technically complete. The documented
`OBS-05` sample limitation does not contain a duplicate-command, state-leak or
safety finding.

## 5. Regression Baseline

The current canonical distribution passes:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
```

Results:

| Check | Result |
| --- | --- |
| deterministic pilot harness | 16 of 16 passed |
| REST client and authentication checks | passed |
| installable distribution structure | passed |
| productive PHP syntax | passed |
| whitespace validation | passed |

These checks establish `db36ea3` as the executable baseline for the second
pilot snapshot.

## 6. Documentation Finding

The README currently published on module repository `main` still states that:

- timeout behavior needs additional pilot testing;
- restart during active verification needs additional pilot testing;
- cloud read failures need additional pilot testing.

Those statements were correct for `pilot-0.1.0.1`, but are outdated after
steps 37 through 47.

Creating `pilot-0.1.0.2` directly on `db36ea3` would therefore make the tag
technically correct but operationally misleading.

## 7. Required Pre-Tag Change

Before creating the new tag, update only the dedicated module repository
`README.md` to:

- identify the recovery-hardened private-pilot snapshot;
- record successful timeout and temporary-failure deterministic coverage;
- record successful supervised restart recovery;
- record passive scheduled token-refresh evidence;
- retain the repeated-operation sample limitation;
- retain all command, cloud API, OAuth, privacy and Store limitations;
- point the engineering boundary to the current case-study review.

No PHP file or `library.json` change is required.

The README commit becomes the tag target because a pilot tag identifies both
the tested executable tree and its applicable operating guidance.

## 8. Metadata Decision

Keep `library.json` unchanged:

```text
version: 0.1
build: 0
date: 0
compatibility version: 6.2
```

The `pilot-*` namespace remains the release identity for controlled testing.
Public-style `v*` tags remain reserved for a separately approved public
pre-release or release.

## 9. Tag Decision

**Decision: CONDITIONAL GO for `pilot-0.1.0.2`.**

The technical release gates are passed. The only prerequisite is publication
of the bounded README correction described in section 7.

After that documentation-only commit:

1. verify productive files are byte-identical to `db36ea3`;
2. rerun the three regression commands from section 5;
3. verify the publish clone is clean;
4. create annotated tag `pilot-0.1.0.2` on the README commit;
5. push `main` and the new tag;
6. verify both remote references and the resolved tag commit.

Suggested tag message:

```text
Private pilot 0.1.0.2: recovery-hardened REST MVP
```

No additional mower command or Symcon runtime test is required for the
documentation-only publication. The executable files remain identical to the
already tested build.

## 10. Approved Pilot Boundary

The second tag may represent:

- OAuth authorization and scheduled refresh;
- mower discovery;
- REST-polled read-only status;
- supervised Dock command;
- read-only long-running Dock verification;
- bounded verification timeout;
- restart recovery without command replay;
- bounded token-refresh transport recovery.

It does not approve:

- Start, Stop, Pause or Resume commands;
- command retries;
- unattended physical command testing;
- MQTT/WSS realtime communication;
- location or map data;
- public OAuth client-secret distribution;
- broad public release;
- Symcon Store submission.

## 11. Risk Review

Accepted for controlled private pilot:

- undocumented external Navimow cloud API;
- REST polling latency;
- one-mower direct live evidence;
- limited repeated-operation sample;
- installation-specific OAuth client configuration.

Still release-blocking for broader publication:

- supportable public OAuth setup and credential policy;
- compatibility and migration policy;
- public support and troubleshooting boundary;
- Symcon Store metadata and validator review;
- explicit response to upstream API changes;
- broader device and firmware coverage.

The second pilot tag does not lower these product-level gates.

## 12. Rollback and Traceability

If a pilot regression is found:

- do not move either pilot tag;
- record the affected commit and Symcon/module versions;
- disable command use if command safety is uncertain;
- retain read-only diagnostics without exposing credentials;
- publish a corrective commit and create a new immutable pilot tag only after
  the relevant evidence is repeated.

The previous `pilot-0.1.0.1` snapshot remains available as historical
evidence, not as an automatic rollback recommendation.

## 13. Architecture Decisions

### AD-NAV-121: Require current operating guidance before tagging

**Decision:** Do not tag the hardening commit while its published README still
describes already-closed test gaps.

**Rationale:** An immutable pilot snapshot includes the instructions and risk
statements used by its operators.

**Consequence:** One documentation-only commit is required before
`pilot-0.1.0.2` is created.

### AD-NAV-122: Preserve executable commit identity through byte comparison

**Decision:** Allow the tag to point to a later README commit only after all
productive files are verified byte-identical to `db36ea3`.

**Rationale:** This keeps runtime evidence attached to the exact executable
content while permitting accurate release documentation.

**Consequence:** No new physical mower test is needed for the README-only
commit.

### AD-NAV-123: Keep pilot metadata conservative

**Decision:** Retain `version 0.1`, `build 0` and `date 0` for the second
private-pilot tag.

**Rationale:** A second controlled pilot milestone does not establish public
release maturity.

**Consequence:** Git commit and `pilot-*` tag provide exact identity.

### AD-NAV-124: Separate pilot approval from public release approval

**Decision:** Approve only the controlled private-pilot tag boundary.

**Rationale:** Runtime hardening evidence does not resolve distribution,
support, Store or public credential-policy questions.

**Consequence:** A `v*` tag and broad publication require a separate review.

## 14. Recommended Next Step

Create:

```text
49-pilot-readme-refresh-and-second-tag-publication.md
```

That step should update the dedicated module README, verify executable parity,
create and push the documentation commit, create annotated tag
`pilot-0.1.0.2`, verify the remote references and document the exact resulting
commit and tag object.
