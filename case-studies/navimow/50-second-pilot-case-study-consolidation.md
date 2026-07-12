# 50 Second Pilot Case Study Consolidation

**Case study:** Navimow native IP-Symcon module
**Status:** Passed
**Date:** 2026-07-12
**Scope:** Consolidate recovery hardening, observation evidence and second pilot publication

## 1. Purpose

This step consolidates the Navimow work completed after the first private-pilot
mainline checkpoint in `36-case-study-mainline-consolidation.md`.

It records the deterministic observation harness, recovery hardening, direct
Symcon evidence, completed pilot observation matrix and publication of
`pilot-0.1.0.2` as one traceable SAEF mainline checkpoint.

No new productive behavior, external API call or mower command is introduced
by this consolidation step.

## 2. Consolidation Boundary

The checkpoint is restricted to:

```text
case-studies/navimow/
```

It includes:

- SAEF steps `37` through `50`;
- the deterministic pilot observation harness;
- the fake clock and bounded Symcon runtime double;
- recovery hardening in the canonical Account and Device modules;
- updated recovery-hardened pilot README guidance;
- the Navimow case-study index update.

It excludes:

- `private/` capture and publication workspaces;
- raw API responses and OAuth callback material;
- access tokens, refresh tokens and client secrets;
- private device, account, host and Symcon object identifiers;
- the Git history of the separately published module repository;
- unrelated framework files or changes;
- new mower commands, MQTT/WSS or map functionality.

## 3. Evidence Chain

The consolidated engineering chain is:

```text
private-pilot observation plan
-> non-actuating harness design
-> first deterministic findings
-> recovery-hardening design
-> productive implementation
-> 16 green deterministic scenarios
-> dedicated module publication
-> direct read-only Symcon smoke test
-> normal supervised Dock transition
-> supervised restart during active verification
-> consolidated observation review
-> passive scheduled token-refresh observation
-> second-tag release decision
-> README refresh and immutable pilot publication
```

Each productive change is preceded by a design decision and followed by
deterministic or direct evidence appropriate to its risk boundary.

## 4. Consolidated Productive Changes

### Account recovery

The canonical `NavimowAccount` module now provides:

- injectable current time for deterministic verification;
- injectable API client creation for scripted transport tests;
- persistent token-refresh retry count;
- transport-only refresh retry at 60-second intervals;
- a maximum of five failed refresh attempts;
- retry-state reconstruction after Symcon restart;
- retry reset after success, authentication rejection or account reset;
- no retry for authorization-code exchange;
- sanitized error classification without token output.

### Device verification

The canonical `NavimowDevice` module now provides:

- structured internal status-read results while preserving the public method;
- current-read evidence instead of stale variable inference;
- explicit internal `WaitingRead` command state;
- deadline precedence over intermediate progress;
- one final read at the exact verification deadline;
- deadline-aligned timer scheduling;
- persisted active-verification state across restart;
- no Dock command replay after restart, timeout or ambiguous reads.

These changes remain inside the existing Account and Device ownership
boundaries. No new public helper or framework abstraction was introduced.

## 5. Harness Consolidation

The committed case-study harness consists of:

```text
tests/harness/FakeClock.php
tests/harness/SymconRuntime.php
tests/pilot-observation-harness.php
```

It is deliberately:

- CLI-only;
- non-actuating;
- independent of network access;
- based on scripted Account responses;
- capable of reconstructing module state after restart;
- able to count read and command calls;
- limited to the Navimow case study.

The harness is not promoted to a reusable SAEF helper because reuse outside
this implementation has not yet been demonstrated.

## 6. Observation Matrix Closure

| Scenario | Consolidated evidence | Decision |
| --- | --- | --- |
| `OBS-01` verification timeout | fake time, exact deadline, final read and no replay | PASS |
| `OBS-02` active restart | deterministic reconstruction and supervised live restart | PASS |
| `OBS-03` temporary read failures | scripted transient recovery, cadence and bounded timeout | PASS |
| `OBS-04` token expiry and refresh | deterministic lifecycle plus passive scheduled live refresh | PASS |
| `OBS-05` repeated Dock operation | three successful transitions without duplicate delivery | PASS WITH LIMITATION |

The `OBS-05` limitation records the intentionally small one-mower sample. It
does not record a duplicate-command, state-leak or safety finding.

Physical timeout, credential damage and deliberate productive cloud failure
were not induced. Their software behavior is covered by deterministic tests in
accordance with the safety plan.

## 7. Direct Symcon Evidence

The checkpoint includes sanitized reports for:

- loading all three module types;
- continued Account and Device instance operation after update;
- successful OAuth and read-only polling;
- a read-only hardening smoke test;
- a normal supervised Dock transition;
- a supervised Symcon restart during active Dock verification;
- automatic post-restart transition from Pending to Verified;
- passive scheduled token-expiry movement;
- continued polling with zero accumulated REST errors;
- absence of manual authentication action during the passive observation.

Temporary test scripts were deleted after each observation. No private Symcon
object identifier or raw runtime payload is retained in the public reports.

## 8. Publication Alignment

The dedicated module repository published:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

The documentation-only release commit is:

```text
937113e522f7a5323a8265b5b255855fcee7f19f
docs: refresh recovery-hardened pilot guidance
```

The second annotated pilot tag is:

```text
pilot-0.1.0.2
```

It resolves remotely to `937113e`. The executable tree at that commit is
byte-identical to the directly tested hardening commit `db36ea3`.

The previous `pilot-0.1.0.1` tag remains immutable and continues to identify
the pre-hardening pilot snapshot.

## 9. Metadata and Release Boundary

Module metadata remains:

```text
version: 0.1
build: 0
date: 0
compatibility version: 6.2
```

`pilot-0.1.0.2` approves controlled private-pilot use of:

- OAuth authentication and scheduled token refresh;
- discovery and REST-polled status;
- supervised Dock command;
- bounded long-running verification;
- restart recovery without command replay;
- bounded token-refresh transport recovery.

It does not approve:

- broad public release;
- Symcon Store submission;
- public OAuth client-secret distribution;
- Start, Stop, Pause or Resume commands;
- command retry;
- MQTT/WSS communication;
- map or location processing;
- removal of physical supervision requirements.

## 10. Validation Gate

The consolidation set must pass before commit:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
php -l for all Navimow PHP files
JSON decoding for all Navimow JSON files
git diff --check
canonical distribution comparison with the clean publish clone
private-data pattern scan
```

Results:

| Check | Result |
| --- | --- |
| deterministic pilot harness | 16 of 16 passed |
| REST and authentication checks | passed |
| distribution validator | passed |
| PHP syntax | passed |
| JSON syntax | 18 files passed |
| canonical-to-published parity | passed |
| whitespace and privacy review | passed |
| publish clone | clean and synchronized |

The privacy scan matched the synthetic test string `ACCESS_PRIVATE_VALUE` in
the REST test suite. It is deliberately used to prove that authorization data
is removed from debug output and is not a credential or captured token.

## 11. Commit Boundary

Only reviewed paths below `case-studies/navimow/` belong to this checkpoint.

The commit must contain:

- documents `37` through `50`;
- harness files;
- the two reviewed productive hardening files;
- the recovery-hardened distribution README;
- the case-study index.

The commit must not contain any file from `private/` or any unrelated
repository change.

The Git commit containing this document is the canonical SAEF mainline
checkpoint for the recovery-hardened second Navimow private pilot.

## 12. Architecture Decisions

### AD-NAV-128: Consolidate the complete evidence chain

**Decision:** Commit design, implementation, deterministic tests, live reports
and publication decisions together as one case-study checkpoint.

**Rationale:** Reviewers must be able to trace each hardening behavior from
identified risk through implementation to evidence and release decision.

**Consequence:** Steps 37 through 50 form one coherent mainline unit.

### AD-NAV-129: Keep the observation harness case-study local

**Decision:** Retain fake time and runtime doubles under the Navimow tests.

**Rationale:** The seam is proven for one module but not yet as a recurring
framework pattern.

**Consequence:** SAEF avoids a premature public helper API.

### AD-NAV-130: Preserve canonical-to-published executable parity

**Decision:** Require the canonical distribution to match the executable tree
represented by `pilot-0.1.0.2`.

**Rationale:** The case study owns engineering truth while the dedicated
repository supplies the Symcon installation root.

**Consequence:** Future runtime changes must begin in the case-study
distribution and repeat the appropriate gates before publication.

### AD-NAV-131: Freeze command scope after second pilot publication

**Decision:** Do not treat consolidation as approval for another mower
command.

**Rationale:** Each physical command needs its own API evidence, state model,
safety analysis and supervised test plan.

**Consequence:** Dock remains the only controllable mower action.

## 13. Gate Decision

**Decision: GO for SAEF mainline consolidation.**

The recovery-hardened implementation, deterministic harness, direct Symcon
evidence, completed observation matrix and immutable second pilot tag form a
consistent engineering checkpoint.

Controlled private-pilot use may continue within the published safety and
scope boundaries. Broader release remains a separate decision.

## 14. Recommended Next Step

After the mainline checkpoint is committed, create:

```text
51-post-pilot-roadmap-decision.md
```

That step should compare the next legitimate work tracks without starting
implementation:

- continued passive pilot observation;
- public-release and OAuth distribution readiness;
- Symcon Store readiness;
- MQTT/WSS research;
- analysis of a second mower command under a new safety contract.
