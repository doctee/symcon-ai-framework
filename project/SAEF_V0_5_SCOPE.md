# SAEF v0.5 Scope

**Status:** Published historical release scope
**Target:** `v0.5.0`
**Freeze date:** 2026-09-16
**Published baseline:** `v0.4.0`
**Scope-freeze base:** `b30dad4bee001d852029bb78b6fd94917e0eaccc`
**Release date:** 2026-09-16
**Release revision:** `f02d36a8c404f949b8d4433db8f28fa2d52dd66b`

## Version Decision

The release after `v0.4.0` was correctly classified as `v0.5.0`. The delta adds
reproducible workstream coordination, target-bound Channel-v8 deployment,
scope-bound one-click approval, secure Windows child execution and bounded
latest-command-wins behavior. Those capabilities are larger than a patch while
remaining compatible with the existing public helper API.

This document preserves the frozen historical release boundary. Work after the
release revision enters a later inventory and does not retroactively change
the v0.5 scope. Repository publication did not authorize a live operation,
standalone-module publication or cleanup.

## Included Scope

### Framework platform

- clean primary-checkout and dedicated-worktree guardrails;
- machine-verifiable private handovers;
- lock-identical Composer toolchain ownership;
- target-bound standalone-module Channel-v8 deployment;
- scope-bound one-use approval and crash reconciliation; and
- bounded, hash-pinned Windows PowerShell child processes.

### MQTT supersession

- immutable event-time command payloads;
- bounded Registry-backed generation arbitration;
- dedicated superseded diagnostics;
- deterministic rapid-command and genuine-failure regressions;
- atomic private claim-root installation and one-use owner migration; and
- sanitized terminal activation, migration and supervised command evidence.

### Case-study evolution

- OwnTracks target profile, scope-bound activation and recovery evidence;
- MediaCarousel adapter and reversible package-ownership migration;
- Navimow receive-only, map, analytics and physical-client evidence; and
- Open-Meteo bounded calibration processing, retention and refined policy.

## Excluded Scope

- additions or breaking changes to the public helper API;
- cross-root retention implementation or deletion;
- calibration-factor activation and manual collector execution;
- deferred Shared Statistics activation;
- optional case-study cleanup and later observation work;
- private installation identifiers or evidence;
- another live target, restart, command or provider action; and
- Stable 1.0 or `v1.0.0` status.

## Release Outcome

Pull request 130 merged the verified candidate as release revision
`f02d36a8c404f949b8d4433db8f28fa2d52dd66b`. Exact-revision post-merge CI
passed, GitHub issue 1 was closed only after its terminal report was present on
`main`, and the annotated `v0.5.0` tag plus non-draft, non-prerelease GitHub
Release were published on 2026-09-16.

The repository release did not authorize live Symcon work, standalone-module
publication, device action, service restart or retention cleanup. Subsequent
intake is governed by `project/SAEF_V0_6_INVENTORY.md`.
