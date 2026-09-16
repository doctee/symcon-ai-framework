# SAEF v0.6 Engineering Inventory

**Status:** Initial inventory; scope not frozen
**Inventory date:** 2026-09-16
**Published baseline:** `v0.5.0`
**Baseline revision:** `f02d36a8c404f949b8d4433db8f28fa2d52dd66b`

## Purpose

This inventory opens the post-v0.5 engineering intake without admitting any
candidate to a release. It separates repository work from private observation,
live mutation, publication and retention authority.

## Baseline

At this inventory boundary:

- `v0.5.0` is published and immutable;
- its annotated tag resolves to the exact protected-main merge revision;
- pull-request, post-merge and release-workflow checks passed;
- GitHub issue 1 is closed with its sanitized terminal report on `main`; and
- no post-v0.5 commit or changed path exists at the baseline revision.

These facts describe the starting point. They do not choose a v0.6 feature
scope or authorize any operation.

## Candidate Intake

### Open-Meteo calibration observation

Policy version 2.1 is active and the existing five-minute schedule is the only
collector driver. The remaining work is a read-only fachliches review after
the analysis backlog drains. Calibration-factor activation, manual collection,
source rollback and later evidence deletion remain separate gates.

### Shared Statistics reconciliation

Repository and retained private evidence showed that the helper activation was
already completed through the earliest effective owner during v0.4. The
currently activated v0.5 MQTT runtime package contains the same byte-identical
Statistics helper as the canonical source and both generated consumers.

The stale deferral is therefore closed without a new live gate, activation or
restart. See `project/SAEF_V0_6_SHARED_STATISTICS_RECONCILIATION.md` for the
evidence and remaining retention boundary.

### Natural-cycle and optional case-study work

The next natural irrigation cycle and optional Seestall legacy-pie cleanup may
produce useful engineering evidence. Observation, mutation and cleanup remain
distinct operations and require current target-specific evidence.

### Retention and workspace hygiene

Historical worktrees, branches, transfer packages, deployment state, backups
and private evidence require classification before any deletion. Cleanup must
use exact allowlists, preserve dirty recovery inputs and obtain separate
destructive authorization.

## Admission Rules

A candidate enters a future frozen scope only after it has:

1. a public engineering rationale rather than installation-specific demand;
2. current ownership, consumer and compatibility inventories;
3. deterministic repository tests and required platform qualification;
4. sanitized evidence suitable for the public repository;
5. explicit treatment of rollback and retained evidence; and
6. independent authorization for every live, restart, publication or cleanup
   operation.

## Non-Commitments

This inventory does not decide the next version number beyond the working v0.6
label, freeze a release scope, change the public helper API, activate a helper
or calibration factor, publish a module, mutate a live system, restart a
service or delete retained material. The Shared Statistics reconciliation
records a completed historical gate; it does not admit a new v0.6 feature.
