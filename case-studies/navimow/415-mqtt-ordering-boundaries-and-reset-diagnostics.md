# SAEF Step 415: MQTT Ordering Boundaries and Reset Diagnostics

- Date: 2026-10-22
- Route: isolated development, no publication or live mutation
- Base: ffa48f6790ebb04182c1e9545244e6deeccb6784

## Finding and correction

Step 414 protected source timestamps inside the reducers, including serialized
state and changing session numbers. The account lifecycle still replaced their
entire states during credential rotation, disconnect and ApplyChanges. Thus its
reconnect guarantee did not cover the full runtime path. Sampled counter resets
at approximately token-refresh intervals exposed this gap; sampling alone does
not establish the cause of every historical reset.

The existing bounded attributes now distinguish observations from ordering
boundaries. Clearing observations removes coordinates, tracks, field values,
field receipt times and segment counters, while retaining device-bound source
high-water marks and per-field source boundaries. Restart and token rotation
are not evidence of a new device-clock epoch. Old/equal positions remain
rejected; equal-time complementary shadow fields remain admissible, but known
fields cannot be replayed as fresh. Receipt-only messages do not erase known
source-time boundaries. Legacy fields inherit only their conservative source
boundary, never invented receipt freshness.

No new public reset action is introduced. A genuinely reset device clock needs
an independently justified future epoch policy; until then it fails closed.
Device hashes are retained privately. Existing single-device position ambiguity
protection remains, and a full bounded shadow device set rejects new identities
instead of evicting established ordering boundaries. No cross-device borrowing.

Malformed ordering storage is preserved and reported as ordering-state-invalid.
It cannot authorize ingestion through a fresh fallback state. Observation reset
failure does not block independent credential cleanup. This is not an automatic
repair, retention deletion or credential recovery feature.

## Diagnostics contract

GetMqttDiagnostics adds observationResets to its existing version-3 projection:

- segmentSequence: count of actual observation resets, not empty clear calls;
- lastResetAt and lastResetReason: apply-changes, credential-rotation or the
  deliberately generic transport-disconnect (no inferred cause);
- accountingStartedAt: first recorded reset under this accounting contract;
- segmentCounters and cumulativeCounters: received attempts (sampleSequence),
  older timestamps and all rejected timestamps, including equal-time replay.

Cumulative counters include the initial segment present at adoption plus every
subsequent closed segment and the current segment. They are not reconstructed
lifetime totals, and accountingStartedAt is not the first sample time.
Counters saturate at the existing diagnostic maximum. Existing pilot accounting
is retained independently. Repeated empty ApplyChanges does not double count.
Boundary-only storage is unavailable observation evidence, not a current sample.
The shadow tracked-device count includes retained boundary identities.

The existing module Statistics attribute is reused; generic variable helpers
would add an unnecessary new public surface. The projection is bounded and
read-only and exposes neither coordinates, identifiers nor credentials. It does
not claim transactional durability across an abrupt interruption between
attribute writes. A restore from an older backup or manual state edit can still
reset accounting and requires separate interpretation, not invented continuity.

## Regression scope and gates

Tests cover the actual credential-rotation path, disconnect, ApplyChanges,
repeated empty clears and restored module attributes. They reject old/equal
positions, preserve MqttLastPositionAt, clear geometry, accept newer positions,
preserve field boundaries and unknown ages, admit complementary fields, retain
receipt-only source boundaries and verify cumulative/segment separation.
Malformed storage remains blocked for ingestion without blocking credential
cleanup. Existing pilot cleanup tests now distinguish removable observations
from retained ordering identity. Diagnostic fixture and bounded-output tests
include the additive projection.

Canonical source remains distribution/. Generated fileset changes are derived
locally. No device commands, new Symcon API, timer, recovery budget, OAuth action
or live configuration change is introduced. Publication and Symcon adoption
remain separate approval gates.

Local verification passed: full make check (including the complete Navimow
suite, PHPStan, PHPCS, schemas, generated filesets and read-only publication
contract checks), focused malformed-storage/capacity regressions and git diff
--check. The isolated worktree used the repository resolver with an external,
byte-identical-lock Composer toolchain. No source was borrowed from that owner.
This is offline evidence, not live rotation or restart verification.

## Recovery remains a plan

The diagnostic-only silence policy from step 414 remains unchanged. Before
implementing recovery, observe at least one natural mowing period spanning a
normal credential rotation with reset reason/sequence and cumulative counters.
Require fresh authoritative REST motion and sustained missing newer positions,
exclude normal rotation/closure windows, then review a single reconnect inside
the existing bounded budget. Never infer successful recovery from Core status
alone. No automatic response to data silence is implemented here.
