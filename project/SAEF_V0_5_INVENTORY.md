# SAEF v0.5 Engineering Inventory

**Status:** Repository scope reconciled; release scope proposed, not frozen
**Inventory date:** 2026-09-12
**Published baseline:** `v0.4.0`
**Baseline revision:** `de8a59d9f8d30e38d0fa18058057c620446f12c0`
**Reconciled revision:** `d278226bf8f038e501ba1479b0bc2ea2ab867244`

## Purpose

This inventory reconciles the post-v0.4 development line after its initial
single-candidate intake. It distinguishes repository-integrated framework work,
case-study evidence, completed target-specific live gates and work that remains
outside the proposed v0.5 release.

An operational result does not grant authority for another publication, target,
live mutation, restart or cleanup. Those gates remain independent even when the
underlying reusable contract is proposed for v0.5.

## Baseline and API

At the reconciled revision:

- `v0.4.0` remains the latest published release;
- `origin/main` contains the complete post-v0.4 repository history through pull
  request 119;
- the public helper contract remains 30 functions and three constants;
- no post-v0.4 public helper signature was added or changed; and
- Runtime Diagnostics continues to compose ConfigurationHash, Registry,
  Statistics and ErrorRingBuffer without another storage API.

The release is therefore expected to be minor because of new operational
capabilities and case-study behavior, not because of a helper API break.

## Proposed v0.5 Scope

### V05-001: Rapid MQTT command supersession

The exporter-private latest-command-wins implementation is integrated. It uses
immutable event payloads, bounded generation arbitration, existing Registry and
Statistics responsibilities and deterministic concurrency tests. It adds no
public helper API.

The initial issue remains open because the private live-migration evidence has
not yet been reconciled into a complete, current postflight record. Repository
completion must not be presented as confirmed live adoption.

### V05-002: Reproducible isolated workstreams

The primary-checkout guard, fail-closed fast-forward synchronization, clean
workstream creation, lock-identical Composer toolchain reuse and verifiable
private handover contract are integrated. These rules make isolated worktrees
the only supported build, publication and deployment source.

### V05-003: Target-bound standalone-module deployment

Channel version 8 extends the existing five-verb restricted transport with
manifest-driven standalone-module packages and hash-pinned server-local target
profiles. It does not accept client-selected paths or commands. OwnTracks
completed the first separately gated profile installation and activation.

### V05-004: Scope-bound one-click approval

The one-use HMAC approval contract binds one canonical plan, target, adapter,
ordered operation set, baseline identities, expiry, nonce and opaque user and
host identities. Fresh preflight, lock ordering, postflight, rollback and crash
reconciliation remain distinct internal phases behind one conscious
**Jetzt anwenden** action.

### V05-005: Secure Windows child-process execution

The shared internal launcher pins Windows PowerShell 5.1 scripts by hash,
bounds arguments, environment, output and runtime, closes standard input and
uses a kill-on-close Job Object. It adds no remote verb or public API. Exact
Windows qualification, protected installation and first OwnTracks use are
complete for the current source generation.

## Case-Study Evidence in the Development Line

### OwnTracks

OwnTracks proves the Channel-v8 standalone target, recovery-capable adapter,
active-identity reseal and scope-bound approval composition. The first exact
one-click plan completed activation and independent live postflight without a
service restart, provider contact or expanded target authority.

### Navimow

Navimow progressed beyond the original v0.5 inventory through bounded
receive-only MQTT operation, revision-bound local maps, zone state and
statistics, mowing analytics and an HTML SDK map. The current standalone module
was published and rolled out through controlled gates; Safari controls, overlay
layout, station orientation and fixed-size legend behavior were corrected and
the final legend behavior was confirmed on a physical iPad.

These are substantial case-study additions. They do not create another generic
deployment authority or helper API.

### Open-Meteo and other case studies

Open-Meteo received a bounded nowcast presentation correction. Other case-study
work remains part of v0.5 only where it changed public repository artifacts;
installation-specific observations and private cleanup remain outside the
release contract.

## Explicit Deferrals

The proposed v0.5 scope does not include:

- a new public helper API;
- implementation or live use of cross-root standalone-module retention;
- extraction, integration or live activation of the MediaCarousel Channel-v8
  adapter;
- unverified MQTT exporter live-migration claims;
- service restarts, provider contact or device commands;
- local worktree, transfer-package, deployment-state or backup deletion; or
- a Stable 1.0 or `v1.0.0` declaration.

## Remaining Gates

1. Review and merge the repository reconciliation and scope decision.
2. Reconcile GitHub issue 1 with current repository and fresh live evidence.
3. Extract the MediaCarousel adapter from its historical recovery worktree into
   a current clean workstream before any integration decision.
4. Inventory and separately authorize retention cleanup for historical
   worktrees, branches and immutable deployment backups.
5. Freeze the exact v0.5 scope, audit the 30-function API, update framework
   versions and generated artifacts, and run release readiness.
6. Tag and publish v0.5 only through later explicit release gates.

The current evidence supports a coherent v0.5 release line. It does not yet
authorize the version bump, tag, GitHub release, live migration or cleanup.
