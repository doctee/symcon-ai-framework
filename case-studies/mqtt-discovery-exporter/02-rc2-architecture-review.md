# 02 V4.1-RC2 Architecture Review

**Case study:** IP-Symcon MQTT Discovery Exporter
**Status:** SAEF adoption review
**Date:** 2026-07-15
**Reviewed source:** Sanitized private V4.1-RC2 handover
**Decision:** Suitable as case-study evidence; not approved as a reference implementation

## 1. Review Objective

Determine which private RC2 decisions can be retained, which implementation
details must be adapted to existing SAEF building blocks and which behaviors
must be removed from the reusable core.

This review assesses the supplied handover. It is not an independent live-system
certification of the RC2 release candidate.

## 2. Accepted Design Decisions

The following decisions align with SAEF and should be preserved:

| Decision | SAEF assessment |
| --- | --- |
| IP-Symcon remains the action and state authority. | Preserves ownership boundaries and avoids duplicating device logic. |
| Device commands use `RequestAction()`. | Required by ADR-0001 and RS-001.8. |
| `SetValue()` is limited to exporter-owned state. | Correct separation of device and internal state. |
| Configuration is separate from reusable logic. | Aligns with RS-001.11. |
| Generated objects and events use deterministic Idents. | Aligns with ADR-0002 and idempotent configuration. |
| Command events use update triggers. | Correct for repeated identical MQTT commands. |
| State events use change triggers. | Avoids unnecessary synchronization runs. |
| Event action binding is explicit. | Required for generated script events on IP-Symcon 6.0+. |
| Discovery and runtime payloads use stable hashes. | Valid optimization when canonical inputs are used. |
| Runtime publication reads IP-Symcon state. | Avoids optimistic command echo as authoritative state. |
| A general retry queue was deferred. | Follows Reuse Before Extend until a recurring failure mode is evidenced. |
| Diagnostic histories are bounded. | Correct operational boundary, subject to helper-first refactoring. |

## 3. Required Adaptations

### AR-01 Replace local infrastructure with SAEF helpers

**Finding:** RC2 locally implements category, variable, instance and event
creation as well as configuration hashing, registry storage, statistics and
error history.

**Decision:** The SAEF implementation must compose existing canonical helpers:

- `SAEF_EnsureCategory()`;
- `SAEF_EnsureVariable()` and diagnostics Ensure functions;
- `SAEF_EnsureInstance()`;
- `SAEF_CreateConfigurationHash()`;
- Registry, Statistics and ErrorRingBuffer helpers.

No exporter-specific wrapper becomes a public helper unless reuse beyond this
implementation is demonstrated.

### AR-02 Add one canonical triggered-event helper

**Finding:** `helpers/object/EnsureEvent.php` currently supports cyclic script
events but not variable-triggered script events. RC2 needs both update and
change triggers repeatedly.

**Decision:** Propose `SAEF_EnsureTriggeredScriptEvent()` in the existing event
helper. Its contract must validate:

- parent and target script ownership;
- event Ident and compatible existing event type;
- trigger variable existence;
- supported trigger type;
- explicit Run Automation action binding;
- active, position and hidden state.

This is a recurring Symcon infrastructure pattern and is broader than the MQTT
exporter use case.

### AR-03 Normalize capability contracts before validation and runtime use

**Finding:** The handover documents optional separate state/action IDs, while
the RC2 validator still requires `powerID` and does not validate `stateID` as a
complete alternative. Partial brightness, color and color-temperature pairs can
also pass portions of validation and then be ignored by capability inference.

**Decision:** Normalize each raw entity into an explicit internal contract,
then validate exactly that contract. Every enabled capability must have both a
state and an action variable. Alias fields such as `powerID` are input
conveniences only.

### AR-04 Validate action semantics

**Finding:** RC2 validates variable existence and type but not whether action
variables expose an enabled standard or custom action.

**Decision:** Configuration validation must reject an action variable for which
`HasAction()` is false. State and action variable types must also be compatible
with the capability mapper.

### AR-05 Reject malformed MQTT payloads

**Finding:** Numeric command handlers cast strings to integers. Malformed input
can therefore become a valid zero command.

**Decision:** Parse external payloads strictly before range checks. Accepted
formats must be documented and covered by tests. Invalid input records one
bounded error and performs no `RequestAction()`.

### AR-06 Preserve failed command status

**Finding:** RC2 catches command exceptions inside the command handler and does
not rethrow or return a failed result. The outer run can subsequently record
success.

**Decision:** Command dispatch returns an explicit result or throws a classified
exception. The run records success only after command execution and confirmed
state publication succeed.

### AR-07 Replace fixed sleep with bounded confirmation

**Finding:** A fixed 300 ms sleep is followed by a state read. This is delayed
observation, not confirmation.

**Decision:** Compose `SAEF_WaitForVariable()` where feedback is expected. The
timeout and update/change mode are configuration. Timeout produces a classified
command-confirmation failure; it must not cause unbounded command retries.

### AR-08 Separate setup/reconciliation from event dispatch

**Finding:** RC2 ensures every command MQTT instance while scanning for a
triggered command. State-triggered runs can therefore reapply unrelated MQTT
instance configurations.

**Decision:** Separate responsibilities:

1. reconciliation ensures desired objects and discovery configuration;
2. command dispatch resolves only the triggering managed command variable;
3. state dispatch publishes only the affected entity where possible;
4. a controlled full-refresh path reconciles and republishes all entities.

The first SAEF version may retain a full runtime scan if tests show it is
bounded, but it must not reconfigure all command adapters on every state event.

### AR-09 Make cleanup ownership-exact

**Finding:** RC2 registers state event Idents but normal entity cleanup removes
only command instances and command events. State events can remain. The legacy
cleanup compensates through broad prefix matching only while it is enabled.

**Decision:** Registry reconciliation must track and remove every owned object
type explicitly, including state events. Removal must be derived from previous
managed-state entries, not from broad names or prefixes.

### AR-10 Move legacy migration out of the reusable core

**Finding:** RC2 contains installation-history-specific object patterns and
retained topics. Cleanup is enabled by default and recursively deletes matching
objects below the exporter.

**Decision:** Remove this logic from the generic exporter. If retained for the
private installation, it becomes a separate migration operation with:

- an explicit private manifest;
- default dry-run behavior;
- an activation token;
- exact expected object types and parents;
- a result report;
- no inclusion in the public reference implementation.

### AR-11 Align diagnostics with existing responsibilities

**Finding:** RC2 stores all statistics in one JSON variable and adds a second
general monitor ring beside the error ring.

**Decision:** Use typed Statistics variables for counters and timestamps.
Registry remains small reconciliation metadata. ErrorRingBuffer stores bounded
failure context. A separate monitor history is not introduced until its
operational requirement and privacy boundary are demonstrated.

### AR-12 Reduce routine log noise

**Finding:** RC2 logs normal discovery publication and cleanup activity at
informational level.

**Decision:** Default logs focus on errors and important warnings. Routine
counts and timestamps belong in Statistics. Optional debug output may expose
more detail temporarily.

## 4. External Contract Review

As reviewed on 2026-07-15:

- Home Assistant still supports the MQTT light default schema used by the RC2
  design, including explicit color-mode state and Kelvin color temperature.
- Retained state is a supported way to restore MQTT light state after
  subscription.
- Single-component MQTT Discovery remains supported.
- Home Assistant recommends device discovery when one device exposes multiple
  components; this is an optimization candidate, not a mandatory first change.
- IP-Symcon trigger type `0` means variable update and `1` means variable
  change.
- Generated events that execute the parent automation require the explicit Run
  Automation action binding from IP-Symcon 6.0 onward.

## 5. Artifact Classification

| RC2 content | Classification | Rationale |
| --- | --- | --- |
| Matter light scenario and evolution history | Case study | Useful evidence, but installation-specific. |
| Generic entity and capability model | Candidate reference content | Requires normalized contracts and tests. |
| MQTT payload and topic construction | Candidate reference content | Pure logic suitable for offline tests. |
| Existing diagnostics implementation | Replace | Duplicates SAEF helpers and responsibilities. |
| Object and event Ensure logic | Replace or canonical helper extension | Infrastructure must remain canonical. |
| Legacy object/topic cleanup | Private migration only | Encodes installation history and destructive behavior. |
| Fixed command settle sleep | Replace | Does not prove state confirmation. |
| General retry queue idea | Defer | No demonstrated need yet. |
| Availability/heartbeat | Future analysis | Explicitly outside initial scope. |
| New standards or ADR | Not required now | Existing SAEF rules already govern the findings. |
| Architectural pattern publication | Defer | Reuse beyond this implementation is not yet demonstrated. |

## 6. Review Decision

**No-Go for verbatim promotion.**

**Go for controlled SAEF adoption** through the gates in
`03-adoption-plan-and-gates.md`.
