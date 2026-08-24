# 337 Task Observation Ledger Design

**Case study:** Navimow native IP-Symcon module

**Status:** Design approved for bounded private diagnostics

**Date:** 2026-08-24

## 1. Objective

Retain privacy-safe MQTT task semantics across ordinary MQTT cleanup so that
natural runs can be compared without preserving raw payloads, device IDs,
topics, coordinates or manufacturer area identifiers.

The ledger is diagnostic evidence. REST remains authoritative for public mower
state and every MQTT path remains receive-only.

## 2. Semantic Boundary

The retained unit is a **correlated zone pass**, not a manufacturer-confirmed
app task or schedule run. A pass is inferred from bounded observations:

- first task telemetry for a device correlation;
- a change in an already-known boundary or partition correlation;
- task progress wrapping from at least 90 percent to at most 10 percent.

Transport-session changes do not automatically open a new pass. They are
recorded as transitions so an interrupted private pilot can continue observing
the same resumable zone pass. Action, sub-action, delay and start-type changes
are phases within a pass unless stronger boundary evidence exists.

Completion evidence is retained when current progress reaches at least 99
percent. A 100-percent percentage alone is accepted only if progress is absent
or also near completion; this avoids marking the next pass complete when a
percentage remains temporarily at 100 after task progress resets.

## 3. Data Contract

The ledger stores at most 32 pass summaries and 64 transitions within a hard
65,536-byte JSON limit. Oldest entries are removed deterministically.

Each pass contains:

- monotonic sequence and first/last MQTT pilot session sequence;
- first/last observation timestamp and optional completion timestamp;
- SHA-256 device, boundary and partition correlations;
- bounded partition count;
- first, last and maximum progress;
- first, last and maximum subtotal and weekly area candidates;
- latest phase codes and observation count.

The read-only projection omits the device correlation and exposes only bounded
pass and transition evidence. Hashes are correlation handles, not app zone
numbers and not stable public identifiers.

## 4. Privacy And Persistence

Forbidden persisted values are raw device IDs, MQTT topics, boundary IDs,
partition IDs, `mapWorkPosition`, raw payloads and coordinates. Position
diagnostics remain a separate bounded component with explicit gaps.

The ledger uses its own module attribute. It deliberately survives
`ApplyChanges()` and MQTT credential cleanup, while the transient shadow,
pending reconciliation and position sample buffer continue to be cleared.
No public variable or archive contract changes.

## 5. Architecture Decisions

| Decision | Rationale |
|---|---|
| dedicated reducer class | deterministic fixture tests without Symcon or network access |
| separate bounded attribute | SAEF Registry is for small metadata, not retained observation history |
| semantic summaries, not every frame | bounded storage and reduced privacy exposure |
| inference labels in API | prevents MQTT hints becoming authoritative state |
| no app-zone labels in module | operator labels and private mappings remain installation-local |
| no position/task row join | channels have different cadence and must preserve gaps |

## 6. Gates

Implementation may proceed offline. Standalone publication and a disabled
Symcon update require their normal independent evidence. A natural Zone 2 or
Zone 3 observation is not an implementation precondition and must not trigger
a mower command.
