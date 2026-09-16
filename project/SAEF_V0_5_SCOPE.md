# SAEF v0.5 Scope

**Status:** Frozen release-candidate scope
**Target:** `v0.5.0`
**Freeze date:** 2026-09-16
**Published baseline:** `v0.4.0`
**Scope-freeze base:** `b30dad4bee001d852029bb78b6fd94917e0eaccc`

## Version Decision

The release after `v0.4.0` is classified as `v0.5.0`. The delta adds
reproducible workstream coordination, target-bound Channel-v8 deployment,
scope-bound one-click approval, secure Windows child execution and bounded
latest-command-wins behavior. Those capabilities are larger than a patch while
remaining compatible with the existing public helper API.

This freeze admits only release-preparation corrections required to verify the
scope below. New feature work requires an explicit reopening. The freeze does
not itself authorize merge, issue mutation, tagging, release publication, live
operation or cleanup.

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

## Release Gates

Before publication, the release candidate must:

1. reconcile every changelog entry with this frozen scope;
2. confirm the unchanged public API contract;
3. update both canonical framework-version constants to `0.5.0`;
4. regenerate deterministic bundles and filesets twice with identical output;
5. pass focused and complete repository checks;
6. pass pull-request CI on the exact candidate;
7. merge only through explicit protected-main authorization;
8. close GitHub issue 1 only after its terminal report exists on `main`;
9. pass post-merge CI on the exact release revision; and
10. create and independently verify the annotated tag and GitHub Release under
    a separate publication authorization.

Repository release does not authorize live Symcon work, standalone-module
publication, device action, service restart or retention cleanup.
