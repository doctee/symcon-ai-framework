# SAEF v0.5 Engineering Inventory

**Status:** Published historical release inventory
**Inventory date:** 2026-09-16
**Published baseline:** `v0.4.0`
**Baseline revision:** `de8a59d9f8d30e38d0fa18058057c620446f12c0`
**Reconciled revision:** `f02d36a8c404f949b8d4433db8f28fa2d52dd66b`
**Release revision:** `f02d36a8c404f949b8d4433db8f28fa2d52dd66b`

## Purpose

This inventory freezes the public v0.5 release boundary after repository,
Windows qualification and independently gated operational work overtook the
initial 2026-09-12 intake. It records reusable framework changes and sanitized
case-study evidence without carrying forward installation identities or
granting another live, release or deletion authority.

## Baseline And API

At the release revision:

- `v0.5.0` is the latest published release;
- the annotated tag resolves to the exact protected-main merge revision;
- the public helper contract remains 30 functions and three constants;
- no post-v0.4 public helper signature or constant changed; and
- Runtime Diagnostics still composes ConfigurationHash, Registry, Statistics
  and ErrorRingBuffer without another public storage abstraction.

The target is a minor release because it adds operational framework
capabilities and case-study behavior without breaking the helper API.

## Frozen v0.5 Scope

### V05-001: Rapid MQTT command supersession

The exporter-private latest-command-wins implementation uses immutable event
payloads, bounded generation arbitration, existing Registry and Statistics
responsibilities and deterministic concurrency tests. The deterministic
runtime fileset was activated, exactly two owners and ten events were migrated
through a one-use transaction, and independent postflight confirmed the
resulting state.

A passive observation remained non-conclusive because no natural command
occurred. A separate supervised reversible scenario then confirmed one
superseded intermediate command, successful authoritative confirmation of the
newest command, unchanged failure diagnostics and restoration of the starting
device state. The temporary producer was absent afterward. No public helper
API was added.

### V05-002: Reproducible isolated workstreams

The primary-checkout guard, fail-closed fast-forward synchronization, clean
workstream creation, lock-identical Composer toolchain reuse and verifiable
private handover contract make isolated worktrees the supported build,
publication and deployment source.

### V05-003: Target-bound standalone-module deployment

Channel version 8 extends the existing five-verb restricted transport with
manifest-driven standalone-module packages and hash-pinned server-local target
profiles. OwnTracks completed the first profile installation and activation.
MediaCarousel later completed its reversible package-ownership migration and
independent read-only postflight without widening target authority.

### V05-004: Scope-bound one-click approval

The one-use HMAC approval contract binds one canonical plan, target, adapter,
ordered operation set, baseline identities, expiry, nonce and opaque user and
host identities. Fresh preflight, lock ordering, postflight, rollback and crash
reconciliation remain explicit internal phases behind one conscious apply
action.

### V05-005: Secure Windows child-process execution

The shared internal launcher pins Windows PowerShell 5.1 scripts by hash,
bounds arguments, environment, output and runtime, closes standard input and
uses a kill-on-close Job Object. Exact Windows qualification, protected
installation and target use are complete for the admitted source generations.

## Included Case-Study Evidence

### OwnTracks

OwnTracks proves the Channel-v8 standalone target, recovery-capable adapter,
active-identity reseal and scope-bound approval composition. Its exact plan
completed activation and independent live postflight without expanding target
authority.

### MediaCarousel

MediaCarousel proves the separately gated transition from a reviewed Module
Control checkout to an adapter-owned Channel-v8 package. The one targeted
reload, preserved configuration and reference set, transaction inspection and
independent read-only postflight completed without a service restart.

### Navimow

Navimow contributes bounded receive-only operation, revision-bounded local
maps, mowing analytics, the HTML SDK map and subsequent Safari and physical
iPad validation. These results remain case-study evidence, not generic device
or provider authority.

### Open-Meteo

Open-Meteo contributes bounded calibration backlog processing, fail-closed
snapshot limits, protected evidence retention and curtailment-aware policy
version 2.1. A separately authorized cleanup retained a bounded rollback set.
The refined policy was deployed and observed through its natural scheduled
cycle without activating a calibration factor. Its passive analysis backlog
does not block v0.5.

## Explicit Deferrals

The frozen v0.5 scope excludes:

- a new public helper API;
- implementation or live use of cross-root standalone-module retention;
- calibration-factor activation or manual Open-Meteo collector execution;
- Shared Statistics helper activation and its restart-dependent regressions;
- optional Seestall legacy-pie, irrigation-cycle and retained rollback cleanup;
- local worktree, branch, transfer-package, deployment-state or backup
  deletion;
- another live target, command, restart, provider or device action; and
- a Stable 1.0 or `v1.0.0` declaration.

## Release Outcome

The exact candidate passed full local and pull-request checks, merged through
PR 130, passed post-merge CI and carried the sanitized MQTT terminal report on
`main`. GitHub issue 1 was then closed. The annotated `v0.5.0` tag and GitHub
Release were published and independently verified on 2026-09-16.

Worktree, branch, backup and private-evidence retention remain later exact
allowlist deletion gates. This historical inventory grants no new live
mutation, publication or cleanup authority.
