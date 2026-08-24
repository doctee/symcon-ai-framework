# SAEF v0.4 Repository Reconciliation

**Assessment date:** 2026-08-24
**Baseline revision:** `a7f86d725b9487c3ca661363e325382210c8f59f`
**Scope-freeze base revision:** `46cc50cb7876ad1a65f055ef6a9b85abdf05b1e1`
**Decision:** SCOPE FROZEN - RELEASE PREPARATION PENDING

## Purpose

This report reconciles the public repository after the work accumulated since
`v0.3.0`. It identifies the release delta, current public API, generated
artifact boundary, documentation drift and remaining release decisions.

The assessment is repository-only. It contains no installation inventory and
does not claim a fresh live Symcon verification.

## Published Baseline

| Item | Verified state |
| --- | --- |
| latest release | `v0.3.0` |
| annotated tag target | `e223b76673b495cecae3e2232ce148c5dabb6230` |
| GitHub Release | published 2026-07-23; neither draft nor prerelease |
| current development baseline | `a7f86d725b9487c3ca661363e325382210c8f59f` |
| commits after `v0.3.0` | 172 |
| paths changed after `v0.3.0` | 739 |
| open pull requests at assessment | 0 |
| open public engineering issues | 1 |
| latest baseline CI | PASS |

These counts are a dated repository snapshot. They are evidence for choosing a
minor release, not permanent framework constants.

## Architecture Reconciliation

The post-v0.3 work reuses established SAEF responsibilities:

- canonical helpers remain the public implementation source;
- generated bundles and module trees remain deterministic artifacts;
- module publication is owned by the accepted manifest-driven engine;
- case-study behavior remains local until recurring reuse is demonstrated;
- live evidence remains separate from repository publication; and
- worktree toolchain reuse uses the existing Composer environment contract.

No second publication engine, Diagnostics abstraction, Ensure implementation
or release mechanism is required for v0.4.

## Public API Reconciliation

The `v0.3.0` contract contained 29 public functions. The current contract test
contains 30.

The additive function is:

```text
SAEF_ValidateMutableObject(int $objectID, ?int $expectedObjectType = null): void
```

It enforces the existing fail-closed object-mutation rule and rejects the
Symcon root before a mutator runs. The release audit must verify every helper
and generated artifact that exports this function.

`SAEF_IncrementStatistic()` has no signature change, but its per-variable
semaphore is a public behavioral hardening and belongs in release notes.

No case-study class, deployment operation or publication CLI is classified as
a public SAEF helper API.

## Generated Artifact Boundary

Current builders still declare framework version `0.3.0`. This is correct
during development: changing the version would regenerate shared bundles and
filesets before the release scope is frozen.

The final v0.4 preparation must update both builder constants together,
regenerate every declared artifact, prove deterministic output and review all
exporting filesets affected by the helper changes. No generated artifact is
changed by this reconciliation.

## Documentation Reconciliation

| Document | Previous drift | Reconciled statement |
| --- | --- | --- |
| `README.md` | treated v0.3 as the next target | records v0.3 as published and v0.4 as development |
| `project/AI_PROJECT.md` | reported 0.2 released and 0.3 development | reports 0.3 released and 0.4 development |
| v0.3 scope and readiness | left tag and release pending | retained as historical records with publication outcome |
| MediaCarousel README | described an offline-only unpublished candidate | records publication and bounded live validation |
| Navimow README | stopped before task-ledger publication and rollout | records the installed ledger and remaining natural-zone gates |
| `CHANGELOG.md` | omitted several current support changes | includes reconciliation, helper and case-study status changes |

Historical dated reports remain unchanged. Current overview documents carry
the reconciled status.

## Release Boundary Decisions

### GitHub issue #1

Rapid superseding MQTT commands have one public engineering proposal for
latest-command-wins behavior. It is explicitly deferred from `v0.4.0` and
remains open for a post-v0.4 workstream.

The existing bounded feedback behavior remains part of the frozen release.
Deferral does not classify superseded commands as successful, weaken
authoritative feedback predicates or suppress genuine action and confirmation
failures.

### Navimow natural evidence

The bounded task ledger and first natural zone correlation are complete. Zone
2 and Zone 3 observations remain private operational evidence gates and are not
v0.4 release blockers.

### Repository retention

Local worktree and rollback retention is installation and workspace state. It
must be handled through private records and separate cleanup authorization; it
is not part of the public v0.4 artifact set.

## Readiness Matrix

| Gate | State | Required next action |
| --- | --- | --- |
| version decision | PASS | target `v0.4.0` |
| public repository reconciliation | PASS | maintain through release preparation |
| current-status documentation | PASS at scope freeze | verify final release diff and CI |
| public API inventory | PASS at baseline | repeat on frozen release revision |
| issue #1 disposition | PASS | deferred to a post-v0.4 workstream |
| scope freeze | PASS | admit only release-gate completion changes |
| framework version `0.4.0` | PENDING | dedicated release-preparation change |
| deterministic artifact regeneration | PENDING | run after version change |
| private-data and provenance review | PENDING | run on frozen candidate |
| clean-checkout `make check` | PASS at scope freeze | repeat on final release revision |
| exact-revision CI | PENDING | require after publication |
| annotated tag and GitHub Release | PENDING | final independent gate |

## Decision

The repository justifies a `v0.4.0` release line, and its feature scope is now
frozen. It is not tag-ready: version mutation, artifact regeneration, final
audits, exact-revision CI and publication remain separate release gates.
