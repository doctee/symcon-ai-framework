# SAEF v0.5 Engineering Inventory

**Status:** Initial inventory; scope not frozen
**Inventory date:** 2026-08-24
**Published baseline:** `v0.4.0`
**Baseline revision:** `de8a59d9f8d30e38d0fa18058057c620446f12c0`

## Purpose

This inventory opens the development boundary after the published v0.4
release. It records confirmed public intake, possible evidence sources and
explicit non-commitments before a later version and scope decision.

An inventory entry is not an implementation promise. Work enters a frozen
release scope only after its architecture, ownership, compatibility impact,
verification and publication boundaries are understood.

## Baseline

At inventory time:

- `v0.4.0` is the latest published SAEF release;
- its annotated tag resolves to
  `de8a59d9f8d30e38d0fa18058057c620446f12c0`;
- the tag workflow and exact-revision CI passed;
- `origin/main` has no commit or changed path after the tag;
- no pull request is open; and
- GitHub issue #1 is the only open public engineering issue.

The public helper contract therefore starts this development line with 30
functions and no post-release API delta.

## Confirmed Public Candidate

### V05-001: Rapid MQTT command supersession

GitHub issue #1 proposes an implementation-private latest-command-wins model
for rapid, superseding MQTT commands. It is admitted for architecture and test
work, not yet for release implementation.

The design must:

- preserve bounded execution and semaphore ownership;
- keep authoritative device feedback as the confirmation source;
- distinguish superseded intermediate commands from genuine action or
  confirmation failures;
- preserve one-message-to-one-dispatch behavior for independent producers;
- compose existing Runtime Diagnostics responsibilities;
- avoid an unbounded queue or JSON structure; and
- add no public API unless recurring reuse is demonstrated.

Before implementation admission, the workstream must inventory the exporting
MQTT fileset, effective live owner, all known consumers and observation
constraints. It must define command identity, supersession timing, ownership,
failure classification, restart behavior and deterministic multi-command test
cases. Any supervised live test remains a separately authorized gate with
immediate compensation.

## Evidence Watchlist

The following repositories of experience may produce later v0.5 candidates,
but none is admitted by this inventory alone:

- recurring patterns proven by MediaCarousel, Open-Meteo, Navimow,
  ControlLight or MQTT case studies;
- a demonstrated gap in the current 30-function helper API;
- repeated module-publication or worktree-toolchain friction not covered by
  the existing generic contracts;
- a concrete standards-stability or compatibility problem that justifies a
  standards revision; and
- a bounded operational failure that can be generalized without exposing
  installation data.

Reuse Before Extend applies to every watchlist item. Case-study-local behavior
remains local until recurring reuse is demonstrated.

## Not Automatically in v0.5

The following work is explicitly separate from framework release scope:

- standalone-module publication or Module Control updates;
- live Symcon fileset activation, restart or device actions;
- private Navimow observations and other installation evidence;
- migration of retained private ControlLight or System Functions consumers;
- local worktree, deployment-fileset, rollback or backup cleanup;
- historical report items already closed by later evidence; and
- declaration of Stable 1.0 standards or a framework `v1.0.0` contract.

These operations may proceed through their own bounded gates. They enter v0.5
only when they produce a reviewed public framework change.

## Admission Gates

A candidate may enter a frozen v0.5 scope only when:

1. the problem and recurring engineering value are documented;
2. existing helpers, standards, ADRs and publication tooling were searched;
3. owners, exporters, consumers and compatibility effects are inventoried;
4. public API impact is explicit and a new API is justified if proposed;
5. deterministic offline verification is defined;
6. privacy, provenance and licensing boundaries are clear;
7. repository publication is separated from live or standalone publication;
8. rollback and observation requirements are defined for any later live gate;
   and
9. the candidate has an issue or engineering proposal suitable for review.

## Recommended Order

1. Complete the architecture review for V05-001 without live mutation.
2. Build deterministic rapid-command tests against the existing MQTT runtime.
3. Decide whether the solution remains implementation-private.
4. Review new evidence before admitting another candidate.
5. Freeze version and scope only after the admitted change set is coherent.

The current evidence supports beginning V05-001 as the first v0.5 workstream.
It does not yet justify a final v0.5 release scope or a framework version bump.
