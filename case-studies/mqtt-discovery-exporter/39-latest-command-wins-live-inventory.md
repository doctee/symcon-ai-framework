# Latest-Command-Wins Live Inventory

**Status:** Read-only inventory passed; offline implementation admitted
**Inventory date:** 2026-08-24
**Architecture:** Report 38
**Live mutation:** None

## Purpose

This gate verifies the current private installation boundary required by the
V05-001 architecture review. It uses Symcon MCP only and does not execute an
owner script, publish MQTT, invoke a device action, create an object or change
configuration.

Exact ObjectIDs, names, topics, source, timestamps and installation hashes are
retained only in private local evidence. This report records sanitized counts,
contracts and decisions that are relevant to the public implementation.

## MCP Validation

The expected Symcon MCP tools were callable before live inspection. Owner
source was read directly. The bounded read-only aggregate probes completed
with:

- empty transport errors;
- empty PHP execution errors;
- no output truncation; and
- an explicit mutationAttempted false result.

No SSH, PowerShell, browser, Computer Use or temporary Symcon object was used.

## Current Owner And Consumer Inventory

| Contract | Fresh result |
| --- | --- |
| Active exporter owners | Two enabled scripts |
| Managed entities | Three |
| Command channels | Five |
| State channels | Five |
| Owned command and state events | Ten, all active |
| Command triggers | Update triggers |
| State triggers | Change triggers |
| Event action binding | Explicit automation action on all ten events |
| Command transports | Five ready MQTT Client Device instances |
| Unknown runtime-owner scripts | None |
| Physical runtime-path consumers | None |
| Event-time payload passed to runtime | No |

The installation-wide script scan found exactly the two known owner scripts
using the MQTT exporter runtime. Both rely on the global bootstrap and neither
contains a physical .saef-filesets runtime include. The later owner migration
therefore has a complete, finite consumer set.

Both owners still call the current three-argument
dispatchTriggeredVariable() contract. They read the trigger variable ID but do
not pass the event-time value. This confirms the race boundary identified in
report 38 and the need for a backward-compatible fourth invocation argument
during migration.

## Active Runtime Identity

Reflection found one active MQTT exporter fileset. Its runtime, core and
bootstrap SHA-256 values match the current generated repository files byte for
byte. System.Locals.ips.php contains exactly one token for that active fileset.

The active method has three required parameters. There is no deployed
arbitration behavior hidden outside the repository candidate.

The later implementation changes the exporter source and generated fileset.
The Core gains only the exporter-local confirmation upper-bound validation;
generation arbitration remains in the Runtime adapter. No shared SAEF helper,
unrelated global bootstrap function or standalone-module publication contract
changes.

## Diagnostics Inventory

Both owners have complete, valid diagnostics structures:

- Registry schema version 1;
- matching prepared and published configuration hashes;
- bounded command and state indexes matching the ten event channels;
- valid ErrorRingBuffer JSON within the existing capacity of 20;
- the existing nine Statistics variables; and
- no arbitration Registry or superseded-command statistic yet.

One ErrorRingBuffer is currently at capacity. Its retained entries consist of
action failures, confirmation timeouts and dispatch-lock timeouts. The second
buffer contains three retained failures. The latest retained failures predate
this inventory; no probe changed counters or timestamps.

Before any later supervised test, the gate must capture fresh counter and ring
buffer baselines. Superseded work must not add failure entries, while genuine
action, current-generation timeout, current-generation lock and publication
failures retain their existing diagnostics semantics.

## Timing Finding

The two live configurations use confirmation windows of five and nine seconds
with 100-millisecond polling. The current command dispatch semaphore waits a
fixed five seconds.

That fixed wait is incompatible with the active nine-second confirmation
window. A newer command can remain the current generation yet fail to acquire
the lock before the older invocation reaches its bounded confirmation result.
Classifying that as a genuine latest-command failure would violate the accepted
supersession contract.

The offline implementation must therefore apply this case-study-local bound:

- maximum confirmation timeout: 15,000 ms;
- command lock wait: maximum configured confirmation timeout plus 5,000 ms;
- maximum command lock wait: 20,000 ms.

The bound covers both active configurations and leaves the existing
five-second margin explicit. Configuration normalization must reject a
confirmation timeout above 15 seconds. Poll intervals retain their existing
positive and not-greater-than-timeout validation.

State-trigger lock behavior remains the existing one-millisecond coalescing
path.

## Initialization And Migration Boundary

The new runtime diagnostics consist only of:

- one small COMMAND_ARBITRATION_REGISTRY per owner; and
- one SUPERSEDED_COMMANDS integer statistic per owner.

They must be created through the existing Registry and Statistics helpers
during preparation, never lazily in frequent dispatch. Registry slots remain
bounded by the validated command index and contain only generation, target
hash and update timestamp metadata.

The later migration sequence must:

1. build and verify an inactive candidate fileset;
2. capture byte-exact active bootstrap and both owner sources;
3. activate a backward-compatible runtime through the existing restart gate;
4. initialize both diagnostics structures without MQTT publication;
5. gate command events while both owner entry points are changed to pass the
   event-time value;
6. verify every event binding and diagnostics object before re-enabling
   commands; and
7. perform no device action until a separately authorized supervised test.

The current runtime fileset, bootstrap and two exact owner sources form the
rollback baseline. Newly created diagnostics objects must be tracked by exact
ownership so a later rollback decision can either retain them inertly or
remove only those additions after consumer and observation checks.

## Observation Constraints

The current owner and event topology is active but has not dispatched recently.
This makes historical counters useful as a baseline, not as proof of current
end-to-end command health.

A later live gate requires:

- a fresh pre-action diagnostics snapshot;
- one explicitly selected reversible command channel;
- an exact latest-command scenario and immediate final-state compensation;
- authoritative device feedback rather than optimistic command state;
- post-action Registry, Statistics and ErrorRingBuffer comparison;
- confirmation that independent channels retain one-message-to-one-dispatch;
  and
- passive observation after compensation.

No such functional test is authorized by this inventory.

## Implementation Admission

V05-001 is admitted for deterministic offline implementation and tests with
these fixed boundaries:

- implementation remains private to the MQTT exporter runtime;
- no new SAEF helper or public global API;
- immutable event-time payload input;
- dedicated bounded arbitration Registry;
- one superseded-command counter;
- 15-second maximum confirmation and 20-second maximum lock wait;
- current active source behavior preserved outside supersession; and
- generated fileset changes verified but not activated.

Any Symcon object creation, owner-source update, fileset stage, restart, MQTT
message, device action, compensation or cleanup remains a separate explicit
gate.
