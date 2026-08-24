# SAEF v0.4 Scope

**Status:** Scope frozen; release preparation pending
**Target:** `v0.4.0`
**Baseline date:** 2026-08-24
**Baseline revision:** `a7f86d725b9487c3ca661363e325382210c8f59f`
**Scope-freeze base revision:** `46cc50cb7876ad1a65f055ef6a9b85abdf05b1e1`

## Version Decision

The next framework release from `main` will be `v0.4.0`.

The changes after `v0.3.0` are not a maintenance-only correction. They add a
manifest-driven module-publication platform, complete standalone module
distributions, safer object mutation, serialized Statistics updates,
worktree-isolated analysis tooling and substantial case-study evolution.
Publishing that combined scope as `v0.3.1` would understate its new framework
capabilities.

This document establishes the frozen release boundary. Work merged after the
scope-freeze base enters `v0.4.0` only when it is required to complete the
release gates below. New feature work requires an explicit scope reopening.

The freeze does not bump framework version constants, regenerate release
artifacts, create a tag, publish a release or authorize a live Symcon change.

## Current Baseline

- `v0.3.0` is published and immutable at release revision `e223b766`.
- The public helper contract contains 30 functions.
- `SAEF_ValidateMutableObject()` is the one public function added after
  `v0.3.0`.
- `SAEF_IncrementStatistic()` retains its signature and now serializes updates
  per variable.
- The generic standalone-module publisher and its hash-bound integration mode
  are established operational tooling, not public helper APIs.
- Current `main` passes the complete GitHub CI gate.
- Generated SAEF bundle and fileset framework versions remain `0.3.0` until a
  dedicated final release-preparation change.

## Included Scope

### Framework safety and diagnostics

- fail-closed mutable-object validation, including protection of ObjectID `0`;
- per-variable serialization for Statistics increments;
- shared helper presentation preservation and corresponding contract tests;
- explicit PHP 8.2 static-analysis boundaries; and
- regression coverage for public API and object-mutation safety.

### Workstream and publication platform

- clean-worktree coordination and shared-impact gates;
- lock-identical external Composer toolchain resolution through the existing
  `COMPOSER_VENDOR_DIR` contract;
- the manifest-driven generic standalone-module publisher;
- hash-bound pull-request integration with independent remote verification; and
- guarded deployment-retention planning and cleanup contracts.

### Module and case-study evolution

- the deterministic MediaCarousel module, standalone publication contract and
  validated client lifecycle, category, fullscreen and position behavior;
- the Open-Meteo Weather, Solar, Shared Location and DWD Nowcast distribution,
  publication and runtime hardening work;
- the Navimow standalone distribution, receive-only MQTT lifecycle,
  privacy-safe task ledger and bounded natural-zone evidence;
- the post-v0.3 ControlLight ownership, adapter, color and Statistics work; and
- MQTT discovery exporter subscription and ownership reconciliation.

### Engineering evidence

- sanitized reports that change the current support or migration statement;
- deterministic fixtures and executable regression expectations derived from
  those reports; and
- explicit separation of public framework claims from private live evidence.

## Excluded Scope

- rapid-command latest-command-wins behavior from GitHub issue #1, deferred to
  a post-v0.4 workstream;
- unfinished Navimow Zone 2 and Zone 3 natural-observation gates;
- bulk migration of retained private ControlLight or System Functions legacy
  consumers;
- local worktree, deployment-fileset or rollback cleanup;
- any new device command, restart or live activation;
- a general remote shell or unrestricted deployment channel;
- installation-specific credentials, ObjectIDs, topics, host data or payloads;
  and
- declaration of Stable 1.0 standards or a framework `v1.0.0` contract.

Excluded work may continue independently. It must not enter the release merely
because it finishes before the v0.4 tag; adding it requires an explicit scope
reopening and the appropriate verification gates.

## Deferred Work Decision

GitHub issue #1 remains open as a future engineering proposal and is not a
`v0.4.0` release blocker. Deliberately separated MQTT commands retain their
bounded, authoritative-feedback contract. Rapid superseding commands retain
the documented operational caveat.

Implementing latest-command-wins requires a separate architecture and runtime
workstream with deterministic concurrency tests and supervised live evidence.
Deferral must not weaken feedback predicates, remove serialization or hide
genuine action and confirmation failures.

## Release Gates

The feature scope and issue disposition are frozen. Before tagging `v0.4.0`,
the release-preparation workstream must:

1. reconcile every Unreleased entry against the frozen scope;
2. update current-status documentation while preserving dated evidence as
   historical snapshots;
3. audit all 30 public helper functions and every behavioral contract change;
4. verify third-party provenance, licensing and absence of private data;
5. update both framework-version constants to `0.4.0` in a dedicated release
   preparation change;
6. regenerate and byte-verify all deterministic bundles and filesets;
7. run focused publisher, module, helper and toolchain regressions;
8. run the complete `make check` gate from a clean checkout or worktree;
9. require successful GitHub CI on the exact release revision;
10. date the changelog section and verify release-note extraction; and
11. create and independently verify the annotated tag and GitHub Release.

Repository release does not authorize standalone-module publication, a Module
Control update, fileset activation, restart, device action or retention
cleanup. Those remain separate bounded operations.

## Immediate Next Boundary

Begin the dedicated `v0.4.0` release-preparation workstream. It must update the
framework versions, regenerate deterministic artifacts, complete the frozen
API, provenance and privacy audits, and pass the exact-revision release gates
before tag or GitHub Release authorization.
