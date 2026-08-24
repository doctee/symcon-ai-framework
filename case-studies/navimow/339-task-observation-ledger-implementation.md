# 339 Task Observation Ledger Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Implemented and verified offline

**Date:** 2026-08-24

## 1. Result

The native Account now retains a bounded, privacy-safe task observation ledger
from accepted task telemetry. The feature adds no configuration switch, public
variable, action or device command.

`GetMqttTaskObservationDiagnostics()` returns a read-only projection with
explicit `mqtt-inference` authority and `correlated-zone-pass` semantics.

## 2. Runtime Integration

Task telemetry reaches the ledger only after topic, payload, identifier and
partial-state validation. The Account passes the already-protected SHA-256
correlations and accumulated semantic task fields to the reducer.

Normal MQTT cleanup does not clear the ledger. This allows later natural runs
to be compared after credentials and transient MQTT state have been removed.
The position diagnostic remains independent and ephemeral.

## 3. Inference Rules

- first task observation opens a pass;
- progress from at least 9000 to at most 1000 opens a new pass;
- changed known boundary or partition correlation opens a new pass;
- changed action, sub-action, start type or delay records a phase transition;
- changed pilot session records transport continuation without claiming a new
  mower task;
- completion requires near-complete progress or compatible percentage
  evidence.

These rules are deliberately conservative. They do not assign app zone names,
scheduled-run identities or percentages with an unproven denominator.

## 4. Bounds And Privacy

The ledger is limited to 32 passes, 64 transitions and 65,536 serialized bytes.
Its public diagnostic projection omits device correlation. Raw device IDs,
topics, manufacturer IDs, coordinates, payloads and opaque work-position data
are never stored.

## 5. Verification Result

Focused reducer, Account integration, bounded-storage, MQTT harness,
distribution, PHPCS and PHPStan checks pass. The complete repository check also
passes from the clean worktree using the canonical lock-matched toolset.
