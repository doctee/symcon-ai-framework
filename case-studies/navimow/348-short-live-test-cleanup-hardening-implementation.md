# 348 Short Live Test Cleanup Hardening Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Offline implemented and verified; publication and live gates closed

**Date:** 2026-08-26

## 1. Scope

This step implements the decision from step 347 by composing the existing
MQTT pilot deadline and closure state machine. It does not introduce a second
cleanup owner, a new command path or another live activation.

## 2. Decision

The Account module now owns one bounded
`MqttPilotMaximumDurationSeconds` property:

- default and maximum: 259200 seconds (72 hours);
- minimum: 300 seconds;
- values written outside the form constraints are clamped fail-safe;
- the effective value is copied into the pilot registry when a session starts;
- the absolute `hardStopAt` is persisted before MQTT credentials are fetched;
- later property changes do not move an active session's deadline.

The existing `MqttPilotDeadline` timer remains the only deadline scheduler.
The existing restart-resumable closure phases remain the only cleanup path:

1. request closure and stop lifecycle timers;
2. disconnect the owned transport and verify credential absence;
3. disable MQTT and position-diagnostic properties;
4. run exactly one owned `ApplyChanges`;
5. mark the closure complete.

## 3. Data Retention

The existing `ApplyChanges` composition is retained:

- transient MQTT shadow and local-position geometry are cleared;
- pilot-wide coordinate-free position accounting is preserved;
- the bounded task-observation ledger is preserved;
- no public variable is created, removed or re-identified.

Diagnostics add only privacy-safe duration fields:

- `configuredMaximumDurationSeconds`;
- `sessionMaximumDurationSeconds`.

No credential, topic, device identity or coordinate is exposed.

## 4. Offline Evidence

`mqtt-pilot-checkpoints.php` verifies:

- the unchanged 72-hour default;
- a 900-second short session;
- clamping to the 300-second minimum;
- persisted absolute deadline across restart and property change;
- exact and late deadline processing;
- restart-resumable idempotent closure;
- exactly one owned `ApplyChanges`;
- retained task ledger and unchanged public variables;
- disabled position diagnostics after closure.

The complete repository check must be executed with an explicitly resolved
Composer vendor directory when the isolated worktree intentionally has no
`vendor/` directory.

## 5. Architecture Consequences

- REST remains authoritative.
- MQTT remains receive-only.
- The new duration does not authorize activation by itself.
- Standalone publication, disabled Symcon rollout and any live retry remain
  separate gates.
- External schedulers may observe a test, but are no longer cleanup owners.

## 6. Next Gate

After SAEF integration, the candidate may be proposed for exact standalone
publication. Only after metadata validation and a disabled Symcon rollout may
a separately authorized short receive-only test use the bounded duration.
