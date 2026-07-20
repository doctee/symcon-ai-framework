# 14 Supervised Integration and Rollback Plan

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** G6
**Status:** FILESYSTEM AND RESTART ADAPTERS OFFLINE-VERIFIED; LIVE EXECUTION UNAUTHORIZED
**Date:** 2026-07-15

## 1. Purpose and Authorization Boundary

This plan turns the G6 scenarios into a controlled procedure. It does not
authorize creation of a live script, deployment of source, MQTT publication,
Home Assistant changes or physical device control.

Each later execution phase requires a separate recorded authorization. In
particular, on/off, brightness, RGB and color-temperature commands are separate
device-affecting checkpoints; authorization for one does not authorize the
next.

## 2. Deployment Decision

The repository candidate loads canonical helpers through repository-relative
paths. A copied standalone IP-Symcon script would not preserve that dependency
model.

The selected adapter is a deterministic filesystem fileset that preserves
repository-relative loading. It contains the candidate core/runtime and the
automatically resolved canonical helper closure as unchanged files.

An expanded single-file bundle was rejected for this candidate. The existing
bundle builder is deliberately limited to global helper functions, while the
exporter adds namespaced classes. Transforming both models into one file would
create unnecessary namespace rewriting and a second transformed implementation
surface.

Do not copy helper bodies into the exporter, fetch source from private script
ObjectIDs or introduce a private convenience wrapper. The fileset has passed
offline build, provenance, syntax, drift, conflict and fake-runtime load tests.
An isolated physical-runtime load and private installation mapping remain
separately gated.

The target installation already loads the minimal SAEF EnsureVariable bundle.
The new fileset must not be added beside it in the same PHP process. The
generated conflict preflight intentionally rejects that state. Activation must
replace the old private bootstrap include with the fileset bootstrap and then
start a fresh IP-Symcon process. The fileset contains the same canonical
Validation and EnsureVariable sources required by the existing migrated caller.

## 3. Private Pilot Boundary

The private pilot record must select:

- one exporter script owner;
- one MQTT server connection;
- one disposable virtual switch for non-physical command tests;
- at most one supervised physical light after the virtual tests pass;
- a unique pilot token used in every entity and runtime topic identity;
- exact state and action variables with verified types and `HasAction()`;
- one operator, one observer and one rollback window.

The pilot token must isolate discovery object IDs and runtime topics from every
existing integration. The normal Home Assistant discovery prefix may be used
only with unique pilot object IDs. Wildcard cleanup is prohibited.

## 4. Read-Only Preconditions

Before deployment or publication, record a private snapshot containing only
the minimum recovery evidence:

- IP-Symcon and Home Assistant versions;
- MQTT broker type, protocol compatibility and connection health;
- deployment artifact and candidate SHA-256 hashes;
- owner-script source backup and checksum;
- selected state/action variable type, action presence and current value;
- object-tree IDs, parents, Idents, types and child counts in the pilot scope;
- absence of the planned pilot discovery and runtime topics;
- absence of the planned Home Assistant entity `unique_id`;
- current diagnostics and error counts relevant to the pilot.

Credentials, hostnames, private topics, ObjectIDs and device names stay only in
the ignored private record.

Any collision, missing action, incompatible type, unexpected child or
unrecoverable source backup is a stop condition.

## 5. Staged Execution

### Phase A — Isolated runtime load

1. Snapshot the existing minimal-bundle file, private bootstrap include and its
   migrated caller before any change.
2. Deploy the reviewed fileset directory atomically without configuring an entity.
3. Verify every hash before activation.
4. Replace, do not append, the minimal-bundle include with the fileset bootstrap.
5. From an elevated PowerShell process outside the service, run the reviewed
   state-based restart coordinator. It must wait for confirmed `Stopped`,
   confirmed `Running`, ready runlevel `10103` and an advanced kernel start
   time. Fixed stop/start delays are prohibited.
6. Verify the previous seven helper exports plus both exporter classes.
7. Run diagnostics initialization twice.
8. Confirm the same objects and Registry IDs are reused.
9. Confirm that no MQTT publication or device action occurred.
10. Verify the existing migrated EnsureVariable caller remains structurally
    unchanged before allowing normal scheduling to resume.

### Phase B — Virtual entity

1. Configure one disposable virtual switch under the pilot namespace.
2. Run initial reconcile and capture exact publications.
3. Run reconcile again unchanged and verify zero repeated publications.
4. Verify Home Assistant identity and state.
5. Test repeated commands, invalid payload, timeout and publish-failure paths
   without a physical device.
6. Remove the virtual entity through cleanup and verify exact tombstones and
   removal of all owned adapters/events.

### Phase C — Supervised physical light

Proceed only after Phase B passes and the operator explicitly authorizes the
single selected device.

For each capability, record the pre-command state, authorize one command,
observe IP-Symcon feedback, verify Home Assistant state and then stop for the
next authorization. Never batch power, brightness, RGB or Kelvin commands.

### Phase D — Recovery and restart

Use the same isolated entity to verify controlled refresh, broker failure and
restart scenarios. No unrelated service or production entity may be changed to
create a failure. Where safe failure injection is unavailable, record the
scenario as not executed rather than simulating success.

## 6. Required Scenario Record

| # | Scenario | Required observation | Device command gate |
| --- | --- | --- | --- |
| 1 | Initial and repeated reconcile | Initial exact publications; second run idempotent | No |
| 2 | Discovery and identity stability | Same topic, `unique_id`, device identifier and entity registry entry | No |
| 3 | Home Assistant on/off | Typed action, observed confirmation, affected-entity publication | Separate authorization |
| 4 | Brightness, RGB and Kelvin | Range-correct value and confirmed observed state for each supported capability | One authorization each |
| 5 | Direct IP-Symcon state change | Only the affected entity publishes | Supervised local action |
| 6 | Repeated identical command | Update event processes every delivery without duplicate resources | Separate authorization |
| 7 | Invalid payload | `invalid_payload`, no action request, failure diagnostic | No physical action |
| 8 | Slow/missing feedback | Bounded timeout, no false success | Safe virtual target preferred |
| 9 | Entity removal | Exact tombstones and complete owned-resource cleanup | No |
| 10 | Symcon, broker and HA restart | Retained discovery/state reconstruct stable identity | Approved maintenance window |
| 11 | Publish failure and refresh | No partial hash commit; later controlled reconcile succeeds | Safe isolated failure only |

Each row records expected result, observed result, sanitized evidence reference,
PASS/FAIL/NOT EXECUTED and operator confirmation where applicable.

## 7. Immediate Stop Conditions

Stop and enter rollback if any of these occurs:

- an unexpected object, topic or Home Assistant entity appears;
- ownership preflight fails;
- a command targets a different action variable or device;
- a failure is counted or displayed as success;
- a wait or semaphore exceeds its documented bound;
- cleanup attempts prefix or wildcard deletion;
- the exporter cannot be disabled independently;
- source or artifact hashes differ from the approved snapshot.

## 8. Rollback

Rollback proceeds in this order:

1. stop new operator commands;
2. disable all Registry-recorded exporter events after exact ownership checks;
3. run cleanup with an empty desired entity set to publish exact retained
   tombstones and remove owned command/publisher adapters and events;
4. verify the pilot Home Assistant entities disappear and exact retained pilot
   topics are empty;
5. verify no unrelated entity, object or topic changed;
6. preserve diagnostics temporarily if needed for the sanitized failure report;
7. remove only the disposable owner-script diagnostics subtree after its
   ownership and evidence-retention decision are confirmed;
8. restore the previous owner-script source or remove the new disposable script;
9. restore the previous private bootstrap include and minimal bundle if fileset
   activation or compatibility verification failed;
10. restore the old clean function namespace through the same external,
    state-based restart coordinator and require its verified rollback outcome;
11. verify the existing migrated helper caller and its target are unchanged;
12. compare the final private snapshot with the pre-change snapshot.

If automated cleanup cannot pass exact ownership preflight, do not force or
broaden deletion. Stop, retain the Registry evidence and perform a separately
reviewed manual recovery using exact recorded IDs and topics.

## 9. Sanitized Integration Report

The public G6 report may contain:

- software versions and verification date;
- aggregate object, event, topic and publication counts;
- scenario outcomes and bounded timings;
- artifact hashes that disclose no private content;
- classified failure statuses and rollback result.

It must not contain credentials, hostnames, private IP addresses, ObjectIDs,
private topics, entity names, room names, screenshots with private labels or
raw broker payload captures from outside the isolated pilot.

## 10. G6 Entry Decision

The scenario and filesystem adapter are offline-ready. The corrected
restart/rollback mechanism has passed its Windows parser and non-activating
private preflight gates. The active runtime, migrated caller and complete
inactive staged fileset have also passed an immediate read-only drift
revalidation. Actual state-changing G6 entry remains blocked until the private
pilot mapping, current snapshot and explicit live authorization are all present
and reviewed.
