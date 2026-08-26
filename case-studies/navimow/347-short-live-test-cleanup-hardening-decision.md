# 347 Short Live Test Cleanup Hardening Decision

**Case study:** Navimow native IP-Symcon module

**Status:** Required before another short receive-only activation

**Date:** 2026-08-26

## 1. Finding

The Zone 2 observation proved that a chat-dependent cleanup instruction is not
a sufficient safety mechanism. The module's longer private-pilot deadline did
not match the short natural-run evidence window, while the external heartbeat
could not be created and no temporary Symcon event was authorized.

The result was an active receive-only transport with credentials retained
overnight. The next-day mandatory cleanup succeeded, but delayed operator or
agent return must not be part of the future safety contract.

## 2. Decision

No further short receive-only live activation is permitted until its cleanup
deadline is armed before or atomically with activation and is independently
observable.

The preferred implementation order is:

1. reuse the existing module-owned pilot deadline and closure state machine;
2. add a bounded short-observation duration only if the existing metadata and
   scheduling components cannot express it by composition;
3. persist the absolute deadline before Core credentials are installed;
4. make credential removal the first closure operation;
5. retain task-ledger summaries while clearing transient position geometry;
6. expose a privacy-safe read-only deadline and closure projection;
7. verify restart reconstruction and late timer execution offline;
8. publish and install disabled before any renewed live activation.

A Codex automation may add observational checkpoints, but it must never be the
sole cleanup owner.

## 3. Failure Contract

Activation must fail closed when:

- the short deadline cannot be persisted;
- a previous session or closure remains unresolved;
- the transport is already active;
- credentials are present while the feature is disabled;
- the cleanup owner cannot be proven;
- the token horizon is below the established restart-free threshold.

There is no activation retry. An ambiguous cleanup result is followed by a
read-only postflight, never by a blind second `ApplyChanges`.

## 4. Offline Verification

Required tests include:

- deadline persisted before credential installation;
- automatic closure at the exact deadline;
- closure after process or kernel reconstruction;
- cleanup after a missed callback or delayed timer;
- credential-first cleanup when Core status is transitional;
- idempotent closure without a second Account `ApplyChanges`;
- retained bounded ledger and cleared transient geometry;
- unchanged REST authority, variables, archive identities and commands.

## 5. Next Step

Design the smallest composition of the existing pilot deadline, Registry,
Statistics and closure machinery. In parallel, the now sufficient Zone 1 and
Zone 3 evidence may feed an offline-only path-segmentation and zone-statistics
prototype. Neither track authorizes publication or another live activation.
