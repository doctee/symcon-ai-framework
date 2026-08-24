# Latest-Command-Wins Architecture Review

**Status:** Architecture review passed; implementation not yet admitted
**Review date:** 2026-08-24
**Public intake:** GitHub issue #1
**Live mutation:** None

## Purpose

This review defines a bounded supersession model for rapid MQTT commands that
target the same exported entity capability. It closes the architecture gate
for V05-001 without changing the runtime, generated fileset or a live Symcon
installation.

The problem is narrower than general message queuing. When a producer quickly
sends different target values for one command channel, an invocation waiting
for the shared dispatch semaphore may later read the variable's newest value
instead of the value that triggered it. Intermediate invocations can then
duplicate the newest command or report a timeout after a newer target has
already superseded them.

## Existing Contract And Impact Inventory

| Responsibility | Current artifact or owner | Impact of a later implementation |
| --- | --- | --- |
| Pure MQTT normalization and payload rules | `candidate/MqttDiscoveryExporterCore.php` | Keep unchanged unless a pure, case-study-local target normalizer is demonstrably needed. |
| Dispatch, confirmation and diagnostics | `candidate/MqttDiscoveryExporterRuntime.php` | Primary implementation boundary. |
| Portable runtime export | `dist/symcon/saef-mqtt-discovery-exporter/` | Must be regenerated deterministically from the reviewed source. |
| Fileset contract | `deployments/symcon/mqtt-discovery-exporter.fileset.json` | Hash changes require the existing publication and activation gates. |
| Event ownership | Reconcile-created command events targeting the configured owner script | Owner entry must pass the event-time value into the runtime. Existing event identity remains stable. |
| Command producers | MQTT Client Device variables, including Home Assistant and other independent producers | Different channels and repeated equal messages retain one-message-to-one-dispatch behavior. |
| Device actions | Configured action variables and their existing `RequestAction()` paths | Remain the only device-facing action boundary. |
| Authoritative feedback | Configured state variables and bounded `SAEF_WaitForVariable()` confirmation | Remains authoritative; no optimistic command-state publication is introduced. |
| Runtime publication | Affected-entity MQTT publishers and retained state topics | Only the latest confirmed generation may publish the final affected entity state. |
| Runtime ownership | Active global fileset owner and `System.Locals` bootstrap | A fresh read-only owner, exporter and consumer inventory is mandatory before activation. |
| Verification | `tests/mqtt-discovery-exporter/` and case-study reports | Add deterministic overlap, classification and bounded-state regressions. |

Reports 36 and 37 provide dated, sanitized evidence for the command path,
authoritative feedback and known consumers. They are not proof of the current
live owner, active fileset hash, configuration or observation constraints.
Those facts require a fresh read-only Symcon MCP inventory in a later,
separately authorized gate.

## Reuse Decision

No existing helper should be extended.

- `Registry` already provides the required small structured storage.
- `Statistics` already provides bounded counters.
- `ErrorRingBuffer` already separates genuine runtime failures from successful
  or intentionally superseded work.
- `ConfigurationHash` already protects the prepared runtime configuration.
- Existing semaphore and wait behavior remain the concurrency and feedback
  foundations.

The arbitration rules are specific to this exporter's command lifecycle. They
therefore belong in private methods of `MqttDiscoveryExporterRuntime`, not in a
new global helper or public SAEF API.

The existing managed Registry must not also own rapidly changing arbitration
slots. Reconcile and publication update that Registry for topology and runtime
state. A dedicated small Registry variable gives command arbitration one clear
writer contract and avoids lost updates between unrelated responsibilities.

## Accepted Model

### Immutable Invocation Input

The owner captures the event-time command value and passes it to the runtime
with the triggering variable ID. For variable-triggered scripts, Symcon defines
`$_IPS['VALUE']` as the value at the trigger time. Reading `GetValue()` only
after semaphore acquisition is not an adequate invocation snapshot.

The later runtime change should add an optional, implementation-local argument
for the captured payload so the class transition can be staged safely. The
active owner must nevertheless be migrated atomically with the new behavior;
the compatibility default is not the target production path.

### Supersession Identity

A command channel is identified by:

```text
owner + entity key + command type
```

Different entities or capabilities never supersede one another. Repeated
messages with the same normalized target do not advance the generation and
continue to represent independent dispatches. A different normalized target
for the same channel advances its generation.

### Bounded Arbitration State

A dedicated diagnostics Registry stores one slot per configured command
channel:

```json
{
  "generation": 3,
  "targetHash": "sha256-of-normalized-target",
  "updatedAt": 1787568000
}
```

The Registry contains no raw MQTT payload, topic, discovery document, private
identifier or command queue. Its keys are derived only from the validated,
finite command index. Reconcile prunes slots that are no longer configured.
A short dedicated semaphore serializes Registry updates.

The payload remains only in the owning PHP invocation. No pending command is
replayed after restart. Stored generations are metadata, not durable work.

### Dispatch Sequence

1. Validate the triggering variable and immutable payload.
2. Normalize the target and register its channel generation under the short
   arbitration semaphore.
3. Acquire the existing bounded dispatch semaphore.
4. Re-read the channel slot before any device action.
5. Return `superseded` without an action when the invocation generation is no
   longer current.
6. For the current generation, execute exactly one existing `RequestAction()`
   and wait for authoritative feedback.
7. Re-read the generation before classifying a timeout and before publishing.
8. Publish the affected entity only when this invocation is both confirmed and
   still current.

State-trigger coalescing under the dispatch semaphore remains unchanged. The
latest confirmed command performs the forced affected-entity publication, so
an intermediate authoritative state is not exposed as the final command
result.

## Result Classification

| Situation | Classification | Diagnostics |
| --- | --- | --- |
| Generation changed before action | `superseded` | Increment `SUPERSEDED_COMMANDS`; no failure or error entry. |
| Generation changed while waiting and old target did not confirm | `superseded` | Increment superseded counter; no timeout failure. |
| Old target confirms after a newer target was registered | `superseded` | Do not publish old state; newest invocation remains responsible. |
| Dispatch lock expires and generation changed | `superseded` | No lock failure. |
| Dispatch lock expires for the current generation | Genuine `dispatch_lock` failure | Existing failure counter and ErrorRingBuffer. |
| Current target confirmation expires | Genuine `confirmation_timeout` | Existing failure counter and ErrorRingBuffer. |
| `RequestAction()` throws or is rejected | Genuine `action_failed` | Never hidden by a newer generation. |
| Current confirmed state cannot publish | Genuine `publish_failed` | Existing failure counter and ErrorRingBuffer. |

`SUPERSEDED_COMMANDS` is the only proposed Statistics addition. The existing
ConfigurationHash, execution, command, success, failure and publication
statistics retain their meanings. Supersession is an expected concurrency
outcome and must not pollute ErrorRingBuffer.

## Timing And Restart Boundaries

Execution remains bounded. Before implementation admission, configuration
validation must prove that confirmation timeout, polling interval and dispatch
lock budget form a compatible bound. The current fixed lock wait must not be
combined silently with a longer confirmation window.

Runtime Diagnostics begin only after their structure has been initialized.
The dedicated Registry and superseded counter must therefore be provisioned
before command events can enter the new runtime. Setup failures remain visible
through exceptions and the Symcon log. Activation must not mix an old owner
entry with the new runtime contract.

After a process restart, no Registry slot causes an action. A new event may
reuse or advance the stored generation, and reconcile may prune stale channels.

## Deterministic Verification Contract

A later implementation must add tests for:

1. one command preserving the existing action, confirmation and publication;
2. two and three different rapid targets on the same channel, with only the
   current generation acting after lock acquisition;
3. repeated equal targets preserving independent one-message-to-one-dispatch;
4. different entities and capabilities remaining independent;
5. old confirmation timeout becoming superseded after a newer target;
6. current confirmation timeout remaining a genuine failure;
7. action rejection or exception remaining a genuine failure even when a newer
   generation exists;
8. old confirmed state not being published after supersession;
9. lock timeout classification for old and current generations;
10. state-event coalescing followed by final authoritative publication;
11. bounded Registry size, deterministic pruning and invalid-JSON rejection;
12. restart metadata causing no replay;
13. incompatible timing configuration failing before runtime dispatch; and
14. owner migration passing the event-time payload rather than a later value.

The complete MQTT test suite, generated-fileset verification, PHPStan, PHPCS,
`git diff --check` and `make check` remain mandatory.

## Implementation Admission Gate

Runtime implementation is not admitted until the next workstream records:

- a fresh read-only Symcon MCP inventory of the effective owner, active fileset
  hash, owner entry contract, command events, confirmation timing and known
  consumers;
- current observation constraints and a rollback owner/fileset;
- the exact compatible dispatch and confirmation bound;
- an initialization and atomic owner-migration sequence; and
- confirmation that shared helpers, unrelated bootstrap consumers and
  generated publication contracts require no broader change.

Any fileset stage, activation, restart, MQTT command, device action or
compensation remains a separate explicit live gate.

## Decision

V05-001 passes architecture review with an implementation-private,
Registry-backed generation model. It adds no queue, helper or public API and
preserves authoritative feedback. The next permitted step is fresh read-only
live inventory plus deterministic offline implementation planning; this report
does not authorize runtime code or live mutation.
