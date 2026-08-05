# 287 Combined MQTT Transport and Position Harness Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Productive checkpoint bridge and private combined harness
implemented and validated offline; all external gates remain closed

**Date:** 2026-08-05

## 1. Productive Implementation

The Account module now appends five bounded position fields to each existing
native pilot checkpoint. The 16-KiB operational pilot summary projects the
same fields and remains bounded.

The checkpoint projection fails closed to false and zero values when position
diagnostics are disabled, unavailable, malformed or ambiguous. It never
projects coordinates.

## 2. Private Harness

The established private harness is extended rather than duplicated.

Backward compatibility remains:

```text
legacy policy:           NAV-MQTT-PRIVATE-PILOT-72H
legacy snapshot format:  2
position requirement:    false by default
```

The explicit combined mode uses:

```text
policy:                   NAV-MQTT-POSITION-PRIVATE-PILOT-72H
snapshot format:          3
position requirement:     true
CLI selector:             position
```

Old state files without position keys are migrated in memory to the legacy
transport-only mode.

## 3. Read-Only Probe

The private Symcon probe now calls `GetMqttPositionDiagnostics()` and reduces
the result to:

- latest local pose;
- cumulative summary;
- counters;
- retained-track count;
- SHA-256 hash of the retained track.

The full 512-sample track is not copied into harness state. Raw MCP responses
and private snapshots remain outside public Git.

Before live use, the private probe's expected commit must be rebound to the
exact published and installed standalone commit.

## 4. Automatic Reconstruction

When a later private snapshot contains multiple native five-hour checkpoints,
the harness processes every new current-session position checkpoint in
sequence. It reconstructs evidence windows and correlates each checkpoint time
with bounded REST archive transitions.

This permits unattended overnight operation without requiring Codex to wake
at every five-hour boundary.

## 5. Offline Tests

The private validation proves:

- unchanged transport-only pilot behavior;
- explicit combined-mode creation;
- inactive and active position contracts;
- two position-covered natural cycles;
- 48-hour passing readiness;
- mandatory immediate and delayed cleanup;
- rejection of missing position snapshots;
- rejection of more than 512 retained samples;
- reconstruction from two native position checkpoints;
- privacy scanning of all private harness source.

The public focused suite additionally proves:

- parser and position reducer behavior;
- cumulative summaries beyond ring eviction;
- native checkpoint projection;
- 32-checkpoint and 16-KiB summary bounds;
- Account ingestion without public-variable mutation;
- PHPCS, PHPStan and distribution validity.

## 6. Side-Effect Accounting

| Operation | Count |
|---|---:|
| Symcon reads | 0 |
| Symcon mutations | 0 |
| MQTT connections | 0 |
| credential requests | 0 |
| OAuth actions | 0 |
| mower commands | 0 |
| Git commits | 0 |
| pushes or pull requests | 0 |

## 7. Architecture Decisions

### AD-NAV-1212: Extend the proven harness conditionally

One state machine preserves transport policy and avoids divergent cleanup
implementations.

### AD-NAV-1213: Keep full tracks out of harness state

Checkpoint decisions need bounded progress and hashes, not repeated copies of
private route geometry.

### AD-NAV-1214: Reconstruct missed observations from native checkpoints

The Account timer is the durable checkpoint owner; Codex scheduling is not a
pilot continuity dependency.

## 8. Gate Status

| Gate | Status |
|---|---|
| productive combined candidate | PASS OFFLINE |
| private combined harness | PASS OFFLINE |
| legacy compatibility | PASS |
| unattended checkpoint reconstruction | PASS |
| publication plan | NEXT |
| local commit | CLOSED |
| publication | CLOSED |
| Symcon update | CLOSED |
| pilot activation | CLOSED |

## 9. Next Step

Freeze the exact productive and SAEF filesets and define a bounded publication
sequence. No live operation belongs to that plan.
