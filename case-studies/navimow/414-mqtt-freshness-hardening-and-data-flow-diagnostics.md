# SAEF Step 414: MQTT Freshness and Data Flow Diagnostics

- Date: 2026-10-21
- Scope: isolated local implementation; no publication or live mutation
- Baseline: SAEF c817c92e450afc86fbaf255ae3c5a458acc5e1b7
- Upstream comparison: NavimowHA 1.1.1, commit
  37fb1d46e3f6f915ac1848cbbd7c7a986622fc86

## Decision and ownership

No Home Assistant installation or update is required. HA is comparison material,
not a runtime dependency. The canonical source remains distribution/; historical
candidate prototypes are not publication inputs. REST remains authoritative for
public device state. MQTT remains receive-only.

Route: isolated development under ADR-0012. Only Navimow source, tests, generated
fileset and this engineering record change. Existing Idents, variable profiles,
archive settings, credentials, timers and recovery budgets are preserved.

## Position ordering

Only a strictly increasing source timestamp may replace the latest position,
extend the track, update distance/bounds or advance MqttLastPositionAt.
An equal timestamp is treated conservatively as a duplicate/conflicting replay.
Older timestamps increment the existing out-of-order counter. Rejections have
an additive rejectedTimestampCount and contribute to droppedSampleCount.
New timestamps with unchanged coordinates remain valid observations.

The source high-water mark survives serialization and reconnect session changes.
A transport session change does not establish a new device clock epoch. If the
device clock genuinely resets, position stays stale until source time catches
up; automatic epoch reset is deliberately not implemented. A future solution
requires independently established epoch evidence, not a reconnect alone.
Previously recorded track/history is retained, not retrospectively rewritten.
The change does not prove absolute source-clock accuracy or detect every delayed
but monotonically newer packet.

## Field freshness

The bounded shadow accumulator adds fieldReceivedAt and fieldSourceTimestamp.
Only fields actually present and admitted in a patch gain new evidence.
Equal-time complementary fields are allowed, but a field already observed at
that timestamp cannot change or become fresh again. The existing global
out-of-order gate remains unchanged.

Legacy snapshots without field timestamps are readable but their field ages
are unknown. An equal-time replay cannot establish freshness for a legacy field.
No migration invents timestamps. Existing restart behavior still clears the
semantic shadow cache; retained position history is separate.

The REST comparison requires fresh evidence for every present compared field.
Unknown age, future receipt time or age over 300 seconds yields stale, never a
false match/mismatch. Diagnostic shadow output adds an allowlisted
fieldAgeSeconds projection. Timestamp-free documented partial messages still
use receipt time: source age cannot be inferred where the protocol supplies none.

Historical task/pass accumulation is not converted into a per-field expiry
engine by this step. Its last-known values remain historical observations;
fieldAgeSeconds must be consulted when interpreting the current shadow.

## Data flow diagnostics

GetMqttDiagnostics adds dataFlow without changing formatVersion 3 or removing
existing fields. Existing Show MQTT Diagnostics exposes the projection. No
new live variable, timer or connection is created.

The bounded internal MqttRestObservation attribute stores at most 64 hashed
device keys, REST state/receipt time and the start of the expectation window.
Only the REST path writes it under the existing account lock. It is separate
from the MQTT lifecycle registry to avoid cross-lock read/modify/write loss.
It is cleared with adaptive polling/auth reset. No identifiers or coordinates
are returned by dataFlow. Existing small registry/diagnostic patterns are reused;
no new framework-wide helper or generic metadata store is introduced.

| Status | Meaning |
| --- | --- |
| disabled | MQTT or position diagnostics disabled |
| transport-unavailable | Core transport is not confirmed connected |
| rest-unknown | No unique, current REST evidence for the selected mower |
| not-expected | Current REST state is neither Running nor Docking |
| awaiting-data | First 120 seconds of a newly evidenced moving period |
| receiving | Accepted position within 120 seconds, at/after moving-period start |
| telemetry-missing | Moving mower with connected transport but no fresh position |

REST evidence expires after 300 seconds. A longer REST gap starts a new grace
window. Ambiguous devices do not borrow each other's evidence. Position age is
computed on demand; arbitrary MQTT packets cannot refresh it. The existing
MqttOperatingState continues to describe transport lifecycle, not data quality.
automaticRecoveryEnabled is always false in this projection.

## Recovery plan only

Before any automated response to data silence:

1. Observe the new diagnosis across normal mowing, return, dock, rain/pause,
   reconnect and token rotation. Establish actual silence distributions.
2. Require current authoritative per-device REST motion, stable ownership,
   valid credentials and sustained missing position evidence across more than
   one observation. Proposed starting threshold: 600 seconds, not yet admitted
   as a runtime policy.
3. Integrate any eventual single reconnect with the existing bounded episode/
   circuit breaker budget and credential-rotation exclusions. Never add a
   parallel retry loop, broaden subscriptions or publish a mower command.
4. Confirm recovery by newer accepted position evidence, not Core status alone.
   Failure must retain REST and enter the existing cooldown/stop policy.
5. Obtain separate implementation/publication/live approval after review.

No behavior in this plan is enabled by this change.

## Verification and promotion

Regression coverage includes partial updates, equal/conflicting timestamps,
legacy unknown ages, receipt-only fields, clock rollback, position replay across
restore/session changes, unchanged-coordinate updates, stale REST comparison,
module position timestamp preservation, transport/data separation, grace expiry,
dock silence, stale REST, ambiguous devices and diagnostic read-only behavior.

Passed locally: the complete Navimow offline suite, distribution validator,
PHPStan/PHPCS, generated-fileset and publication-contract checks, and the full
Composer check (exit 0). The external validation toolchain was reused only
through COMPOSER_VENDOR_DIR with verified identical composer.lock ownership;
all source/build inputs came from the isolated worktree.
Exact results are recorded in the private workstream record. Offline success is not proof of
live delivery or physical operation. Publication, merge and Symcon update remain
separate gates.

Official attribute contracts were checked via the Symcon llms index:
RegisterAttributeString in Create and WriteAttributeString outside Create
(available since Symcon 5.1). No internal API was added.
