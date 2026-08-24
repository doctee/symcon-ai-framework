# 338 Task Observation Ledger Offline Implementation Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Implemented offline

**Date:** 2026-08-24

## 1. Scope

Implement the design from step 337 without network access, Symcon mutation,
MQTT activation, OAuth action, restart or mower command.

## 2. Files

The implementation consists of:

1. `MqttTaskObservationLedger.php` as the bounded reducer, serializer,
   validator and read-only projector.
2. One synthetic, privacy-safe sequence fixture covering pass completion,
   progress wrap, phase change, transport-session continuation, area change
   and delay change.
3. A focused reducer test.
4. Account ingestion integration and persistence checks.
5. Inclusion in the existing Navimow MQTT offline check harness.

## 3. Failure Behaviour

- malformed persisted ledger data fails closed in the diagnostic projection;
- ingestion starts a fresh ledger if the prior private diagnostic attribute is
  malformed, without changing public variables;
- non-task MQTT observations leave the ledger byte-for-byte unchanged;
- size and entry bounds are enforced before persistence;
- raw identities are hashed before the ledger sees them.

## 4. Fixture Assertions

The fixture must prove:

- one completed pass followed by a progress-wrap pass;
- a transport-session change continues that pass;
- a changed area correlation opens another pass;
- stale 100-percent telemetry does not falsely complete the wrapped pass;
- phase and delay transitions are retained;
- serialization and restoration are deterministic;
- forbidden raw field names and identifiers are absent.

## 5. Validation Sequence

Run the focused reducer test, Account ingestion test, complete Navimow MQTT
offline harness, distribution validation, repository lint, PHPStan and the
full repository check. Publication readiness is evaluated only after all local
results pass from the clean worktree.
