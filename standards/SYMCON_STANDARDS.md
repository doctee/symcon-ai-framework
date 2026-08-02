# Symcon Engineering Standards

**Reference Standard:** RS-001  
**Status:** Stable Draft 1.0  

## 1. Purpose

This Reference Standard defines engineering practices for developing professional IP-Symcon solutions within the Symcon AI Engineering Framework (SAEF).

Unlike the official IP-Symcon documentation, which describes APIs and platform capabilities, this document defines how those capabilities should be applied to produce maintainable, reusable and reliable automation systems.

The recommendations in this document are based on:

1. official IP-Symcon documentation,
2. established engineering practices within the IP-Symcon community,
3. proven engineering experience incorporated into SAEF.

This document complements, but does not replace, the official IP-Symcon documentation.

---

## 2. Engineering Philosophy

### Rule RS-001.1 — Reliability before Convenience

#### Purpose

Ensure that automation behaves predictably under normal operation and under failure conditions.

#### Rule

Engineering decisions shall favour reliability, maintainability and operational safety over shorter or more convenient implementations.

#### Rationale

IP-Symcon automations often control real devices. Concise code is not valuable if its behaviour depends on hidden assumptions or if failure conditions are unclear.

#### Recommended Practice

- Prefer explicit configuration over implicit assumptions.
- Make side effects visible.
- Avoid hidden dependencies between scripts.
- Prefer deterministic behaviour over clever shortcuts.

#### Exceptions

Local one-time maintenance scripts may be simpler, but must not be reused as framework examples without review.

#### Related References

- `principles/ENGINEERING_PRINCIPLES.md`
- `standards/PHP_STANDARDS.md`

---

### Rule RS-001.2 — Deterministic Behaviour

#### Purpose

Make automation behaviour reproducible and easier to debug.

#### Rule

Scripts and helpers should produce the same result when executed repeatedly with the same inputs and the same external system state.

#### Rationale

Deterministic behaviour simplifies troubleshooting, testing, refactoring and AI-assisted review.

#### Recommended Practice

- Initialise required objects explicitly.
- Avoid relying on accidental execution order.
- Store persistent state intentionally.
- Document time-dependent or event-dependent behaviour.

#### Exceptions

Automations based on time, external devices, cloud APIs or asynchronous events may naturally produce different results. These dependencies should be explicit.

#### Related References

- `project/ENGINEERING_MODEL.md`
- `standards/TESTING_STANDARDS.md`

---

### Rule RS-001.3 — Explicit Ownership

#### Purpose

Prevent uncontrolled changes to shared objects and improve maintainability.

#### Rule

Every object, variable and event created by automation should have one clearly identifiable owner.

#### Rationale

Clear ownership simplifies maintenance, migration, cleanup and future refactoring. It also helps AI systems understand which component is responsible for which object.

#### Recommended Practice

- Create framework-owned objects below a dedicated parent object where practical.
- Use stable Idents for technical references.
- Separate user-facing configuration from internally managed state.
- Document which script, helper or module owns created objects.

#### Exceptions

Existing legacy installations may not follow this structure. Migration should be gradual and safe.

#### Related References

- `adr/ADR-0002-use-ident-over-object-id.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

## 3. Object Lifecycle

### Rule RS-001.4 — Stable Object Identification

#### Purpose

Ensure long-term stability across renaming, migration and reuse.

#### Rule

Reusable SAEF artifacts shall identify IP-Symcon objects by stable technical references whenever possible.

Object IDs shall only be used for installation-specific configuration, one-time maintenance tasks or cases where no stable alternative exists.

#### Rationale

Object names are user-facing and may change. Object IDs differ between installations. Idents below known parent objects provide a more stable engineering reference.

#### Recommended Practice

Use the following priority order:

1. Ident below a known parent object
2. Configured object path
3. Explicit configuration value
4. Hardcoded ObjectID only for local/private scripts

In PHP stored in IP-Symcon script objects, avoid bare five-digit decimal
literals for values that are not ObjectIDs. Integrity tools may heuristically
interpret every five-digit number in the valid ObjectID range as an object
reference. Express timeouts, limits and other non-ID values through a named
constant or variable and, where it improves clarity, a unit-preserving
calculation:

```php
$startupDelayMilliseconds = 10 * 1000;
IPS_Sleep($startupDelayMilliseconds);
```

Do not rewrite a domain value into an obscure calculation merely to satisfy a
tool. If a literal is clearer and cannot reasonably be confused in review,
configure the integrity tool's documented number or line exclusion with a
reason. Confirm that the value is not an ObjectID before excluding it.

#### Exceptions

- Migration scripts
- One-time repair scripts
- Local private automations
- Diagnostics that intentionally operate on explicitly configured ObjectIDs

#### Related References

- `adr/ADR-0002-use-ident-over-object-id.md`
- `standards/SYMCON_STANDARDS.md`
- [IPSymconIntegrityCheck configuration](https://github.com/demel42/IPSymconIntegrityCheck/blob/main/README.md#5-konfiguration)

---

### Rule RS-001.5 — Idempotent Object Creation

#### Purpose

Allow safe repeated execution of configuration and setup scripts.

#### Rule

Configuration scripts shall be idempotent.

Running a configuration script multiple times shall update or reuse existing objects instead of creating duplicates.

#### Rationale

Idempotency enables reproducible setup, safe reconfiguration and controlled migration. It also reduces the risk of duplicate variables, events or categories after repeated script execution.

#### Recommended Practice

- Search for existing objects by Ident before creating new ones.
- Create missing objects only when required.
- Update properties intentionally.
- Preserve user data unless a migration explicitly requires changes.
- Keep automatically created objects identifiable.

For managed objects that are visible in the normal object tree, the initial
display name, position and icon should normally be creation defaults rather
than continuously enforced configuration. Users may rename and reorder these
objects without losing the automation's technical identity. Reconciliation
shall continue to use the stable Ident, verified parent relationship and
recorded ownership metadata.

An implementation may continuously manage presentation properties only when
their exact value is functionally required or when the configuration explicitly
declares presentation as managed. This exception must be documented. Changing
an Ident, moving an owned object to another parent or changing its object type
is not a presentation change and may be rejected as ownership drift.

For compatibility, a reusable helper may retain an older presentation-managing
default. New templates, references and reusable implementations shall still
select the presentation policy explicitly instead of relying on that default.

#### Exceptions

One-time migration scripts may intentionally transform existing structures, but should document this clearly.

#### Related References

- `adr/ADR-0002-use-ident-over-object-id.md`
- `standards/PHP_STANDARDS.md`

---

### Rule RS-001.6 — Explicit Object Ownership

#### Purpose

Prevent reusable components from modifying unrelated object tree areas.

#### Rule

Reusable helpers, templates and reference implementations shall document which objects they create, modify or expect to exist.

#### Rationale

Unexpected object tree changes make installations harder to maintain and can cause conflicts between scripts, modules and manual user configuration.

#### Recommended Practice

Each reusable artifact should document:

- expected parent object,
- created objects,
- modified objects,
- required existing objects,
- cleanup or migration behaviour, if applicable.

Cleanup shall use the logical object tree returned by IP-Symcon APIs, not the
physical grouping shown by the management console. I/O, splitter and similar
instances can be displayed in type-specific console sections even when their
logical `ParentID` points to an application category.

Destructive cleanup shall proceed leaf-first:

1. Read and validate the current logical children and their ownership.
2. Delete owned leaf objects or deliberately reparent objects that must remain.
3. Verify that the logical parent has no remaining children.
4. Delete the now-empty parent category.
5. Read back the resulting tree and fail explicitly if it differs from the
   expected result.

A cleanup routine shall not delete a parent before its children and shall not
silently remove objects whose ownership has not been established.

#### Exceptions

None for reusable SAEF artifacts.

#### Related References

- `standards/DOCUMENTATION_STANDARDS.md`
- `project/ENGINEERING_MODEL.md`

---

## 4. Variable Lifecycle

### Rule RS-001.7 — Distinguish Status, Command and Internal Variables

#### Purpose

Avoid unclear ownership and unsafe value changes.

#### Rule

Automation shall distinguish between status variables, command variables and internally owned state variables.

#### Rationale

In IP-Symcon, some variables represent device or instance state, while others are script-owned internal data. Treating all variables as writable values can bypass actions, create inconsistent state or produce misleading visualisation results.

#### Recommended Practice

- Treat instance-owned variables as status or command interfaces.
- Treat script-created variables as internal state only when the script owns them.
- Document whether a variable is read-only, user-controlled, calculated or persistent.
- Use clear variable names and Idents that reflect the variable role.

#### Exceptions

Legacy scripts may use mixed variable roles. Such scripts should be refactored gradually.

#### Related References

- `standards/PHP_STANDARDS.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

### Rule RS-001.8 — Use RequestAction for Controllable Variables

#### Purpose

Preserve the action semantics of device and module variables.

#### Rule

Use `RequestAction()` when changing controllable variables that belong to an instance, module or user-facing action.

Use `SetValue()` only for variables owned by the script itself or for calculated/internal state without action semantics.

#### Rationale

`RequestAction()` executes the same variable action used by visualisations and delegates the change to the owning instance or action script. Directly writing the value bypasses that action layer and may leave the real device, module or visualised state inconsistent.

#### Recommended Practice

- Use `RequestAction()` for device control.
- Use `RequestAction()` for variables with a defined default action or custom action script.
- Use `SetValue*()` only for script-owned variables.
- Use `HasAction()` or equivalent checks where a generic helper must decide whether a variable has action semantics.
- Treat authoritative feedback as confirmation of the observed result at that
  point in time, not as proof of exclusive target ownership.
- Treat a device-availability value as diagnostic evidence, not as a general
  command gate. A device that has just regained power may still be reported as
  unavailable when an interactive command arrives. Unless the domain contract
  requires a separate safety interlock, issue the permitted command first,
  perform bounded authoritative confirmation and inspect the latest
  availability only if confirmation fails.
- Document multi-controller targets explicitly. A script-local or named
  semaphore serializes only callers that participate in the same lock; it does
  not exclude device modules, physical controls, MQTT clients or other
  automations.
- When an unexpected state follows a confirmed action, compare command deltas
  with target-feedback deltas before classifying the action handler or wait as
  defective. For supervised integration tests, capture the actual outbound
  command payload and returned state when that boundary is ambiguous.

#### Exceptions

- Internal calculated variables
- Script-owned cache or state variables
- Test doubles or simulations where the variable is intentionally not backed by a device action

#### Related References

- `adr/ADR-0001-use-requestaction.md`
- Official IP-Symcon documentation: `RequestAction()`
- Official IP-Symcon documentation: Variables and actions

---

### Rule RS-001.9 — Keep Internal State Explicit

#### Purpose

Make automation state understandable, maintainable and recoverable.

#### Rule

Persistent internal state shall be stored explicitly in clearly owned variables or configuration data.

Hidden state shall be avoided.

#### Rationale

Implicit state makes debugging difficult and increases the risk of inconsistent behaviour after restarts, script edits or migrations.

#### Recommended Practice

- Store persistent timestamps, counters, hashes and status values in clearly named variables.
- Use Idents for internal variables.
- Keep internal variables below a known parent object.
- Document which internal variables are part of the automation state.

#### Runtime Diagnostics Rule

Scripts shall not create arbitrary new individual variables for internal runtime
metadata when an existing diagnostics responsibility applies.

Use the established diagnostics responsibilities first:

- Registry for small structured metadata.
- Statistics for counters, runtimes and timestamps.
- ErrorRingBuffer for bounded event and error history.
- ConfigurationHash for stable configuration fingerprints.

Dedicated individual variables remain allowed when they represent real domain
state or must intentionally be visible for user interfaces, visualisation or
trigger logic.

Runtime diagnostics start after the diagnostics structure has been initialized
successfully. Setup or Ensure failures before Registry, Statistics or
ErrorRingBuffer variables exist cannot be captured fully in those diagnostics.
Those early failures shall remain visible through `IPS_LogMessage()`,
exceptions or the Symcon log. Once the diagnostics structure exists, runtime
failures should be modeled through ErrorRingBuffer, Statistics and Registry
metadata where appropriate.

#### Exceptions

Temporary runtime values that only exist during one script execution.

#### Related References

- `project/ENGINEERING_MODEL.md`
- `standards/PHP_STANDARDS.md`
- `knowledge/EK-004-internal-state-management.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`

---

### Rule RS-001.10 — Use Profiles Deliberately

#### Purpose

Keep user-facing variables understandable and consistent.

#### Rule

Variable profiles should be assigned intentionally and should match the semantic meaning of the variable.

#### Rationale

Profiles define presentation, allowed values and user expectations. A wrong or missing profile can make a correct automation misleading or hard to use.

#### Recommended Practice

- Use existing standard profiles where they fit.
- Create custom profiles only when required.
- Keep custom profile names stable.
- Document custom profiles used by reusable artifacts.

#### Exceptions

Purely internal variables may omit a profile if no user-facing presentation is required.

#### Related References

- `standards/DOCUMENTATION_STANDARDS.md`

---

## 5. Script Architecture

### Rule RS-001.11 — Separate Configuration from Logic

#### Purpose

Make scripts reusable, reviewable and safe to adapt.

#### Rule

Scripts shall keep installation-specific configuration separate from implementation logic.

#### Rationale

Separating configuration from logic makes scripts easier to review, migrate, reuse and adapt to different installations.

#### Recommended Practice

- Place configuration at the beginning of the script.
- Use meaningful configuration keys.
- Validate configuration before executing actions.
- Avoid hardcoded ObjectIDs inside reusable logic.

#### Exceptions

Small private one-off scripts may inline configuration, but should not become reference implementations without refactoring.

#### Related References

- `standards/PHP_STANDARDS.md`
- `adr/ADR-0003-private-overlay.md`

---

### Rule RS-001.12 — Prefer Complete Scripts over Snippets

#### Purpose

Improve reproducibility and reduce integration errors.

#### Rule

SAEF examples and reference implementations shall provide complete scripts or complete files whenever practical.

#### Rationale

Isolated snippets often omit configuration, dependencies, side effects and error handling. Complete scripts are easier to review, test and adapt.

#### Recommended Practice

A complete script should include:

- configuration,
- validation,
- main execution flow,
- helper functions,
- logging and error handling where required.

#### Exceptions

Documentation may use short excerpts to explain a specific concept if the surrounding context is clear.

#### Related References

- `standards/PHP_STANDARDS.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

### Rule RS-001.13 — Use State Machines for Complex Behaviour

#### Purpose

Represent complex automation behaviour explicitly.

#### Rule

Use a state machine when behaviour depends on multiple states, transitions, retries, timeouts or recovery paths.

#### Rationale

Complex automation implemented as nested conditions becomes difficult to reason about. A state machine makes transitions visible and reduces accidental behaviour.

#### Recommended Practice

Use a state machine when automation contains:

- several distinct operating states,
- timeout handling,
- retry or recovery logic,
- manual acknowledgement,
- external cloud or device availability.

#### Exceptions

Simple binary or stateless automation does not require a state machine.

#### Related References

- `knowledge/README.md`
- `standards/PHP_STANDARDS.md`

---

### Rule RS-001.14 — Make Retries Explicit

#### Purpose

Handle temporary failures without hiding permanent problems.

#### Rule

Retry logic shall be explicit, bounded and observable.

#### Rationale

Unbounded or silent retries can hide real problems, overload devices or make debugging difficult.

#### Recommended Practice

- Define retry count or timeout.
- Use a delay appropriate to the device or service.
- Log final failure.
- Store relevant retry state when the retry spans multiple script executions.

#### Exceptions

Immediate idempotent reads may be retried locally if failure has no side effects.

#### Related References

- `standards/PHP_STANDARDS.md`
- `knowledge/README.md`

---

## 6. Event Architecture

### Rule RS-001.15 — Create Events Deterministically

#### Purpose

Ensure that automation triggers are reproducible and maintainable.

#### Rule

Automatically created events shall be deterministic, identifiable and safe to recreate.

#### Rationale

Events are part of the automation architecture. Duplicate, unnamed or unmanaged events cause unexpected execution and make troubleshooting difficult.

#### Recommended Practice

- Search for existing events before creating new ones.
- Use stable names or Idents where available.
- Keep events below or near the owning script when practical.
- Document which events a setup script creates.

#### Exceptions

Temporary diagnostic events may be created manually but should not be part of reusable artifacts.

#### Related References

- `standards/DOCUMENTATION_STANDARDS.md`
- `project/ENGINEERING_MODEL.md`

---

### Rule RS-001.16 — Bind Script Events Explicitly

#### Purpose

Ensure script-executing events work correctly on supported IP-Symcon versions.

#### Rule

When automatically creating events, configure both the trigger or schedule and
the executed action explicitly.

Use exactly one of the following execution contracts:

1. To execute the event's parent automation, place the event below that
   automation and bind the Run Automation action with `IPS_SetEventAction()`.
   For IP-Symcon 6.0 and newer, this binding is required explicitly.
2. To execute PHP source stored directly on the event, use
   `IPS_SetEventScript()` with PHP code without PHP tags. This selects the
   Execute PHP Code action and is not a script-ID assignment.

Do not combine both contracts for the same event. Both functions select the
event action, so a later call would replace the previously selected action.

#### Rationale

A trigger alone does not fully define how an event executes its action. Explicit
selection of one execution contract makes generated events complete and
reproducible and avoids confusing inline event code with a target script ID.

#### Recommended Practice

For events that execute their parent automation, create or update all required
parts:

- event object,
- trigger condition,
- parent assignment to the target automation,
- Run Automation action binding,
- activation state.

For inline PHP events, replace the parent-automation action binding with one
explicit `IPS_SetEventScript()` call containing the reviewed PHP source.

#### Exceptions

Manually configured user events are outside the scope of reusable setup scripts.

#### Related References

- Official IP-Symcon documentation: `IPS_SetEventAction()`
- Official IP-Symcon documentation: `IPS_SetEventScript()`
- `standards/PHP_STANDARDS.md`

---

### Rule RS-001.17 — Keep Event Ownership Clear

#### Purpose

Avoid accidental removal or modification of user-created automation.

#### Rule

A setup or configuration script shall only modify events it owns.

#### Rationale

Events created manually by users may encode local operational knowledge. Automatically changing them from reusable code risks breaking private installations.

#### Recommended Practice

- Use naming or Idents to identify owned events.
- Keep owned events close to the owning script.
- Avoid scanning and modifying unrelated events.
- Document cleanup behaviour.

#### Exceptions

Migration scripts may modify existing events if this is their documented purpose.

#### Related References

- `adr/ADR-0002-use-ident-over-object-id.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

## 7. Data and Archive Management

### Rule RS-001.18 — Bound Archive Reads

#### Purpose

Prevent archive operations from exhausting memory or blocking automation.

#### Rule

Archive reads shall be bounded by time range, count or both.

#### Why is this rule important?

Archive data may grow for years. Reading large ranges without limits can make scripts slow, memory-intensive or unreliable.

#### Rationale

Bounded archive access keeps data processing predictable and reduces the operational risk of maintenance scripts.

#### Recommended Practice

- Limit archive reads to the required time range.
- Process large data sets in blocks.
- Prefer explicit start and end timestamps.
- Avoid reading entire multi-year archives unless this is the explicit maintenance task.

#### Anti-Patterns

- Reading all archived values of a frequently updated variable into memory.
- Using unbounded archive access inside regularly scheduled automation.

#### Exceptions

One-time diagnostic or migration scripts may process large ranges if they are explicitly designed for that purpose and include progress control.

#### Related References

- Official IP-Symcon documentation: Archive Control
- `standards/PHP_STANDARDS.md`

---

### Rule RS-001.19 — Preserve Archive Consistency

#### Purpose

Ensure that archive modifications do not leave derived data inconsistent.

#### Rule

Scripts that modify archived values shall consider aggregation and derived archive data.

#### Why is this rule important?

Changing raw archive values without updating or rebuilding affected aggregations can produce inconsistent charts, summaries or downstream calculations.

#### Rationale

Archive data is often used as the historical source of truth. Maintenance scripts must preserve the consistency of raw and aggregated data.

#### Recommended Practice

- Document whether a script modifies raw archive data.
- Reaggregate affected time ranges when required.
- Limit corrections to clearly defined periods.
- Store correction metadata when corrections must be traceable.

#### Anti-Patterns

- Modifying archive values without considering aggregation.
- Applying silent historical corrections without traceability.

#### Exceptions

Read-only analysis scripts do not need reaggregation.

#### Related References

- Official IP-Symcon documentation: Archive Control
- `standards/DOCUMENTATION_STANDARDS.md`

---

### Rule RS-001.20 — Treat Historical Corrections as Engineering Operations

#### Purpose

Make archive corrections reproducible and auditable.

#### Rule

Historical archive corrections should be implemented as explicit maintenance operations, not as hidden side effects of normal automation.

#### Why is this rule important?

Historical data corrections may affect energy calculations, consumption statistics, charts and downstream automation decisions.

#### Rationale

Separating correction logic from normal automation reduces the risk of accidental data changes and makes maintenance operations easier to review.

#### Recommended Practice

- Use a dedicated maintenance script.
- Include a dry-run or debug mode where practical.
- Log the affected variable, time range and correction amount.
- Store the timestamp of the last correction if repeated correction is possible.

#### Anti-Patterns

- Correcting historical archive data during normal scheduled automation without visibility.
- Reapplying the same correction because no correction state is stored.

#### Exceptions

Small calculated helper variables may be corrected directly if they are not used as historical source data.

#### Related References

- `standards/PHP_STANDARDS.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

## 8. Error and Recovery Handling

### Rule RS-001.21 — Classify Expected Failures

#### Purpose

Make automation robust against common operational problems.

#### Rule

Expected failures shall be classified and handled explicitly.

#### Why is this rule important?

Symcon systems commonly interact with devices, networks, cloud APIs and external services. These dependencies may fail temporarily without indicating a logic error.

#### Rationale

Classifying expected failures allows automation to distinguish between temporary unavailability, invalid configuration, device errors and programming mistakes.

#### Recommended Practice

Common failure classes include:

- configuration error,
- device offline,
- cloud or API unavailable,
- timeout,
- invalid response,
- actuator command failed,
- archive access failure.

When authoritative device feedback times out, a separately reported
availability value may refine the failure to `device_offline`. The command must
not be reclassified merely because availability was stale before dispatch. If
feedback succeeds, the command succeeds regardless of that earlier indicator.
Automatic callers may use the resulting failure class for their own bounded
retry policy; interactive command dispatch remains a separate responsibility.
At a Symcon action-script boundary, expected operational failures must not be
re-thrown merely to transport that classification. Symcon can report such an
exception as an additional uncaught ScriptEngine fatal even when the initiating
caller handles `RequestAction()`. Record the classification in bounded runtime
diagnostics and complete the action script normally; callers that require
confirmation must evaluate authoritative feedback or an explicit shared status
contract. Unexpected configuration and programming failures remain exceptions.

#### Anti-Patterns

- Treating every failure as a generic script error.
- Suppressing warnings without recording the cause.

#### Exceptions

Very small private scripts may use simpler error handling if failure has no operational consequence.

#### Related References

- `standards/PHP_STANDARDS.md`
- `knowledge/README.md`

---

### Rule RS-001.22 — Design Recovery Paths

#### Purpose

Allow automation to return to a safe and known state after failure.

#### Rule

Automations that control real devices or depend on external services shall define recovery behaviour for expected failures.

#### Why is this rule important?

A script that detects errors but cannot recover may leave devices, visualisation states or internal variables inconsistent.

#### Rationale

Explicit recovery paths make behaviour predictable after network outages, device restarts, API failures or Symcon restarts.

#### Recommended Practice

- Define safe fallback states.
- Store failure timestamps when relevant.
- Retry only when the operation is safe to repeat.
- Require manual acknowledgement for critical or ambiguous situations.
- Reset error indicators only after the underlying condition is resolved.

#### Anti-Patterns

- Automatically clearing an error flag without verifying recovery.
- Retrying actuator commands indefinitely.
- Continuing normal operation with stale cloud data.

#### Exceptions

Read-only monitoring scripts may only need error reporting instead of active recovery.

#### Related References

- `standards/PHP_STANDARDS.md`
- `knowledge/README.md`

---

### Rule RS-001.23 — Make Timeouts Explicit

#### Purpose

Avoid automation waiting indefinitely or acting on stale state.

#### Rule

Timeouts shall be explicit whenever automation waits for devices, variables, cloud data or external responses.

#### Why is this rule important?

Waiting without a timeout can block execution. Acting on stale data can produce unsafe or misleading automation behaviour.

#### Rationale

Explicit timeout handling makes scripts predictable under degraded conditions and supports safe recovery logic.

#### Recommended Practice

- Define timeout values in configuration.
- Use timestamps to detect stale data.
- Log timeout failures at an appropriate severity.
- Use shorter timeouts for synchronous interactions and longer windows for cloud availability checks.

#### Anti-Patterns

- Infinite waits.
- Assuming a variable update happened without checking `VariableUpdated` or equivalent metadata.
- Treating stale data as current.

#### Exceptions

Purely local calculations without external dependencies do not require timeout handling.

#### Related References

- `standards/PHP_STANDARDS.md`
- `knowledge/README.md`

---

## 9. Logging and Diagnostics

### Rule RS-001.24 — Log for Diagnosis, Not Noise

#### Purpose

Keep logs useful during real operation.

#### Rule

Default logging shall focus on errors, important warnings and relevant state changes.

#### Why is this rule important?

Excessive logging hides real problems and makes long-term operation harder to diagnose.

#### Rationale

Logs are operational diagnostics. They should explain relevant failures and decisions without flooding the Symcon log during normal operation.

#### Recommended Practice

- Log errors and important warnings by default.
- Make debug logging configurable.
- Include enough context to identify the affected object, script or device.
- Avoid logging every normal event trigger or state check.

#### Anti-Patterns

- Logging every successful scheduled execution.
- Logging every unchanged state.
- Using debug logs permanently as operational monitoring.

#### Exceptions

Temporary diagnostics may log more details if they are clearly disabled after analysis.

#### Related References

- `standards/PHP_STANDARDS.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

### Rule RS-001.25 — Separate Operational State from Diagnostic Detail

#### Purpose

Make automation status understandable without requiring log inspection.

#### Rule

Reusable automations should expose important operational state through owned variables, while detailed diagnostics remain in logs or diagnostic structures.

#### Why is this rule important?

Operators and visualisations need concise state information. Developers need detailed diagnostic context. Mixing both creates either noisy user interfaces or insufficient diagnostics.

#### Rationale

Separate state and diagnostics improve usability and maintainability.

#### Recommended Practice

- Use a concise status variable for user-facing state.
- Use an error or alarm variable for automation triggers.
- Use a message variable for the latest relevant diagnostic text when appropriate.
- Use structured diagnostic data for complex components.

#### Anti-Patterns

- Encoding all diagnostic detail in one user-facing status string.
- Requiring users to inspect logs for normal operational status.
- Exposing internal debug data as permanent user-facing variables.

#### Exceptions

Very small local scripts may only need log output.

#### Related References

- `standards/DOCUMENTATION_STANDARDS.md`
- `project/ENGINEERING_MODEL.md`

---

### Rule RS-001.26 — Preserve Diagnostic Context

#### Purpose

Make intermittent and historical failures analyzable.

#### Rule

Important failures should preserve enough context to understand what happened after the immediate execution has ended.

#### Why is this rule important?

Many automation failures are intermittent. Without context, later diagnosis becomes guesswork.

#### Rationale

Persisted context helps identify whether a failure was caused by configuration, device state, network availability, cloud response or timing.

#### Recommended Practice

Store or log relevant context such as:

- timestamp,
- affected object or variable,
- expected value,
- actual value,
- timeout duration,
- external response summary,
- retry count.

#### Anti-Patterns

- Logging only “failed” without context.
- Overwriting the last error before it can be inspected.
- Hiding repeated failures behind a single generic alarm flag.

#### Exceptions

Sensitive data such as credentials, tokens or private network details shall not be logged.

#### Related References

- `adr/ADR-0003-private-overlay.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

## 10. Configuration Management

### Rule RS-001.27 — Keep Public Configuration Explicit

#### Purpose

Make reusable artifacts portable across installations.

#### Rule

Reusable SAEF artifacts shall expose installation-specific values as explicit configuration instead of hiding them in implementation logic.

#### Why is this rule important?

IP-Symcon installations differ in object structure, device IDs, module instances and naming. Hidden configuration prevents safe reuse and makes AI-assisted review unreliable.

#### Rationale

Explicit configuration separates local installation knowledge from reusable engineering logic.

#### Recommended Practice

- Place configuration at the top of scripts.
- Use meaningful configuration names.
- Validate configuration before executing actions.
- Keep private values outside public framework files.
- Use local overlay files or local configuration sections for private installation details.

#### Anti-Patterns

- Scattered ObjectIDs throughout reusable logic.
- Private hostnames, MQTT topics or tokens in public files.
- Configuration values that are only described in comments but not validated.

#### Exceptions

One-time local scripts may be less structured but must not be promoted to reusable artifacts without refactoring.

#### Related References

- `adr/ADR-0003-private-overlay.md`
- `knowledge/EK-005-idempotent-configuration.md`

---

### Rule RS-001.28 — Validate Configuration Before Acting

#### Purpose

Prevent unsafe actions caused by invalid or incomplete configuration.

#### Rule

Scripts that control devices, create objects, modify archives or perform migrations shall validate required configuration before executing side effects.

#### Why is this rule important?

A wrong ObjectID, missing parent object, invalid profile or wrong variable type can cause data loss, duplicate objects or unintended device actions.

#### Rationale

Early validation moves failures to a controlled phase before external state is changed.

#### Recommended Practice

Validate at least:

- required ObjectIDs exist,
- every object-mutation target is strictly greater than zero; ObjectID `0` is
  the IP-Symcon root category and may be used only as an explicitly intended
  parent or read target,
- every result from `IPS_Create*()` is greater than zero, exists and has the
  expected object type before calling `IPS_SetName()`, `IPS_SetParent()`,
  `IPS_SetIdent()` or another object mutator,
- variables have expected types,
- profiles exist where required,
- parent objects are valid,
- archive instance IDs are valid,
- configured time ranges are plausible,
- required actions are available before using `RequestAction()`.

#### Anti-Patterns

- Validating configuration after switching devices.
- Assuming an ObjectID still points to the expected object.
- Passing a failed lookup, missing array element, `false`, `null` or unchecked
  `IPS_Create*()` result to an integer ObjectID parameter; weak coercion can
  turn it into the root ObjectID `0`.
- Creating objects below an invalid or unintended parent.

#### Exceptions

Read-only diagnostic scripts may perform partial validation if failure has no side effects.

#### Related References

- `standards/PHP_STANDARDS.md`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`

---

### Rule RS-001.29 — Separate Public Examples from Private Overlays

#### Purpose

Keep the framework publishable and safe to share.

#### Rule

Public SAEF artifacts shall not contain private installation data. Private data belongs in `private/`, `*.local.*` files or environment-specific configuration that is excluded from version control.

#### Why is this rule important?

Private installation data can reveal security-sensitive details and may remain permanently in Git history once committed.

#### Rationale

A strict public/private split allows SAEF to evolve as a reusable framework without exposing local systems.

#### Recommended Practice

Keep out of public artifacts:

- credentials,
- tokens,
- private IP addresses,
- hostnames,
- private MQTT topics,
- personal ObjectIDs,
- local system descriptions.

#### Anti-Patterns

- Adding real local IDs to public examples.
- Committing temporary credentials for testing.
- Planning to “clean up later” after private data has already entered Git history.

#### Exceptions

Anonymized examples may include synthetic IDs or placeholders if clearly marked.

#### Related References

- `adr/ADR-0003-private-overlay.md`
- `.gitignore`
- `AGENTS.md`

---

## 11. Reusability

### Rule RS-001.30 — Promote Repeated Patterns Deliberately

#### Purpose

Turn recurring project solutions into reusable framework assets.

#### Rule

Repeated solutions should be evaluated for promotion to engineering knowledge, helpers, templates or reference implementations.

#### Why is this rule important?

Copying similar logic between scripts increases maintenance effort and makes later improvements harder to apply consistently.

#### Rationale

SAEF grows by extracting reusable engineering knowledge from concrete Symcon work.

#### Recommended Practice

After completing a non-trivial automation task, ask whether it produced:

- a reusable helper,
- a reusable template,
- a reference implementation,
- an engineering knowledge article,
- a new ADR,
- a standard update.

#### Anti-Patterns

- Copying the same retry, archive or state logic into many scripts.
- Creating helpers before the pattern is understood.
- Promoting highly installation-specific code without abstraction.

#### Exceptions

Single-use private automation may remain local if reuse is unlikely.

#### Related References

- `project/ENGINEERING_MODEL.md`
- `knowledge/README.md`

---

### Rule RS-001.31 — Prefer Stable Interfaces for Helpers

#### Purpose

Make helper functions safe to reuse across projects.

#### Rule

Reusable helpers shall provide stable, explicit interfaces and avoid hidden dependencies on global installation state.

#### Why is this rule important?

A helper that silently depends on global variables, private ObjectIDs or specific object-tree layout is difficult to reuse and unsafe for AI-assisted modification.

#### Rationale

Explicit interfaces make helper behaviour reviewable, testable and portable.

#### Recommended Practice

- Pass required IDs or configuration explicitly.
- Document input and output.
- Avoid hidden use of unrelated global variables.
- Define failure behaviour.
- Keep side effects obvious.
- For globally loaded guarded helpers, document the owning autoload/fileset,
  its deterministic identity and every known consumer.
- Treat guard constants as collision protection, not as version selection. The
  first loaded definition owns the function for the lifetime of that PHP
  context.
- When changing a helper exported by more than one fileset, update the earliest
  global load owner first, inventory all consuming filesets, start a clean PHP
  process when required and verify the effective source with Reflection.
- Do not infer that selecting a later consumer fileset selects its guarded
  helper implementation.

#### Anti-Patterns

- Helper functions that read private global configuration without parameters.
- Generic helper names with installation-specific behaviour.
- Helpers that both calculate and switch devices without clear documentation.
- Multiple versioned filesets exporting the same global helper while relying on
  include order or guards to choose the effective version silently.

#### Exceptions

Very small local utility functions may be private and installation-specific.

#### Related References

- `standards/PHP_STANDARDS.md`
- `knowledge/EK-004-internal-state-management.md`

---

### Rule RS-001.32 — Reference Implementations Must Be Complete Enough to Learn From

#### Purpose

Ensure that reference implementations are useful for humans and AI systems.

#### Rule

Reference implementations shall show complete engineering patterns rather than isolated code fragments.

#### Why is this rule important?

Codex and other AI coding agents learn better from complete, coherent examples than from disconnected snippets.

#### Rationale

Complete examples preserve context: configuration, validation, ownership, side effects, error handling and constraints.

#### Recommended Practice

A reference implementation should include:

- scenario,
- complete code where practical,
- configuration guidance,
- design notes,
- constraints,
- review checklist,
- related standards and knowledge articles.

#### Anti-Patterns

- Publishing a short code fragment as a reference implementation.
- Omitting configuration and validation.
- Including private installation data.

#### Exceptions

A reference implementation may intentionally focus on one technique if the missing context is documented.

#### Related References

- `references/README.md`
- `references/RI-001-idempotent-configuration-script.md`

---

## 12. AI Engineering Compatibility

### Rule RS-001.33 — Optimise for Reviewable AI Output

#### Purpose

Make AI-generated or AI-modified Symcon code easier to verify.

#### Rule

SAEF artifacts shall prefer structures that make AI output explicit, deterministic and reviewable.

#### Why is this rule important?

AI coding agents can produce plausible but unsafe changes if ownership, configuration or side effects are unclear.

#### Rationale

Clear structure reduces ambiguity and makes it easier for humans to review AI-generated changes.

#### Recommended Practice

- Use complete files instead of snippets.
- Keep configuration explicit.
- Use stable Idents.
- Document side effects.
- Use descriptive names.
- Keep functions small and focused.

#### Anti-Patterns

- Asking AI to modify code with hidden installation assumptions.
- Relying on comments instead of explicit configuration.
- Allowing AI to infer private ObjectIDs or object-tree layout.

#### Exceptions

Exploratory local work may be less formal, but must be reviewed before becoming framework content.

#### Related References

- `AGENTS.md`
- `principles/AI_PRINCIPLES.md`
- `standards/DOCUMENTATION_STANDARDS.md`

---

### Rule RS-001.34 — Preserve Engineering Context for AI Agents

#### Purpose

Help AI systems make consistent engineering decisions.

#### Rule

Important engineering context shall be stored in repository artifacts rather than only in chat history or private memory.

#### Why is this rule important?

AI agents operating in Codex or similar environments rely primarily on repository content. Knowledge that only exists in conversation history cannot be reliably used.

#### Rationale

Repository-contained knowledge is versioned, reviewable and available to humans and AI agents.

#### Recommended Practice

Store durable context in:

- ADRs,
- standards,
- engineering knowledge articles,
- reference implementations,
- `AGENTS.md`,
- README files.

#### Anti-Patterns

- Relying on previous chat context for critical engineering rules.
- Leaving design decisions undocumented.
- Encoding important constraints only in local prompts.

#### Exceptions

Private installation details should remain outside public framework content.

#### Related References

- `project/ENGINEERING_MODEL.md`
- `AGENTS.md`

---

### Rule RS-001.35 — Treat AI as an Assistant, Not an Authority

#### Purpose

Maintain engineering responsibility and operational safety.

#### Rule

AI-generated Symcon code and documentation shall be reviewed against SAEF standards before being accepted.

#### Why is this rule important?

AI can generate convincing output that is incomplete, outdated or unsafe for real automation systems.

#### Rationale

Human engineering review ensures that generated work matches current Symcon behaviour, project standards and operational constraints.

#### Recommended Practice

Review AI output for:

- correct use of Symcon APIs,
- missing configuration validation,
- unintended side effects,
- private data leakage,
- unsafe device actions,
- missing error handling,
- consistency with standards and ADRs.

#### Anti-Patterns

- Running AI-generated device-control code without review.
- Accepting AI-created ObjectIDs or private configuration.
- Treating plausible explanations as verified facts.

#### Exceptions

None for reusable SAEF artifacts.

#### Related References

- `principles/AI_PRINCIPLES.md`
- `AGENTS.md`
- `standards/TESTING_STANDARDS.md`

---

### Rule RS-001.36 — Gate and Observe Live-System Changes

#### Purpose

Prevent repository-valid changes from causing unreviewed production effects.

#### Rule

Changes to a live IP-Symcon installation shall use a staged verification gate
with a private snapshot, deterministic rollback, explicit side-effect analysis
and bounded operational observation.

#### Rationale

Offline correctness does not prove compatibility with an existing object tree,
autoload order, archive ownership, links, event configuration or real-device
state. Live verification must therefore preserve installation invariants rather
than relying only on a successful script result.

#### Recommended Practice

- Complete repository and offline verification before live deployment.
- Read authorized live source directly and preserve a private recoverable
  backup before changing it.
- Change one independently reviewable caller or contract at a time.
- Predict device actions and notifications before any manual execution.
- Prefer the next regular scheduled execution when it provides a safer
  equivalent observation.
- Compare source identity, call distribution, object identity, values,
  metadata, tree structure, archive configuration, links and event progression
  as applicable.
- Use direct bounded MCP result channels and evaluate transport errors,
  execution errors and truncation separately.
- Create temporary live objects only with explicit authorization, then delete
  them immediately and verify their absence.
- Stop the cohort and use the prepared rollback when an invariant fails.

#### Exceptions

Read-only inspections that do not execute target scripts or mutate live state
do not require a rollback, but still require authorization and private-data
handling appropriate to their scope.

#### Related References

- `standards/TESTING_STANDARDS.md`
- `project/SYMCON_MCP_SCRIPT_READBACK.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `adr/ADR-0003-private-overlay.md`

---

### Rule RS-001.37 — Keep Managed Runtime Mirrors Non-Authoritative

#### Purpose

Restore Symcon console discoverability for a file-backed shared runtime without
creating a second executable source of truth.

#### Rule

When a file-backed runtime is projected into a Symcon script object for source
visibility or reference search, the projection shall be generated,
non-authoritative and outside every action and autoload path.

#### Rationale

Filesystem sources support deterministic deployment and normal PHP tooling,
while Symcon script objects support useful console inspection. A managed mirror
can provide both only when authority, execution and ownership remain explicit.

#### Recommended Practice

- Keep the deployed runtime file as the only executable source of truth.
- Generate a deterministic private reference index before
  `__halt_compiler()` and append the authoritative runtime bytes after it.
- Locate the owned script by stable Ident below an explicit parent.
- Treat name, position, icon, information text and visibility as creation
  defaults and preserve later user changes.
- Pin the authoritative runtime hash, skip identical content, verify direct
  readback and restore the exact previous content on failure.
- Keep ObjectIDs in private deployment input and evidence only.
- Do not bind the mirror to events, actions, wrappers or autoload.
- Treat undocumented console search functions as optional, feature-detected
  acceptance probes, never as production dependencies.
- Keep the first provisioner implementation local until a second independent
  use case demonstrates a stable public helper contract.

#### Exceptions

Generated Symcon bundles that are themselves the installed executable source
are deployment artifacts, not runtime mirrors, and follow ADR-0005 instead.

#### Related References

- `adr/ADR-0006-managed-symcon-runtime-mirrors.md`
- `knowledge/EK-007-managed-runtime-mirrors.md`
- `case-studies/control-light/31-managed-runtime-mirror-generator.md`

---

## 13. References

This stable draft standard is supported by the following SAEF artifacts:

- `project/AI_PROJECT.md`
- `project/ENGINEERING_MODEL.md`
- `ARCHITECTURE.md`
- `AGENTS.md`
- `principles/ENGINEERING_PRINCIPLES.md`
- `principles/AI_PRINCIPLES.md`
- `adr/ADR-0001-use-requestaction.md`
- `adr/ADR-0002-use-ident-over-object-id.md`
- `adr/ADR-0003-private-overlay.md`
- `adr/ADR-0005-generate-symcon-helper-bundles.md`
- `adr/ADR-0006-managed-symcon-runtime-mirrors.md`
- `standards/DOCUMENTATION_STANDARDS.md`
- `standards/PHP_STANDARDS.md`
- `standards/TESTING_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `knowledge/EK-006-runtime-diagnostics.md`
- `knowledge/EK-007-managed-runtime-mirrors.md`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
- `project/SYMCON_MCP_SCRIPT_READBACK.md`

It is also aligned with current official IP-Symcon documentation for variable actions, event actions, event creation and Archive Control behaviour.

## 14. Release Notes

This stable draft has been promoted from `drafts/SYMCON_STANDARDS.md` for internal SAEF use and Codex-assisted development. The draft source remains available in `drafts/` for historical comparison.

For v0.2.0, the standard explicitly anchors helper-first implementation, idempotent configuration and runtime diagnostics patterns demonstrated by RI-001 and RI-002. Diagnostics state should remain bounded, script-owned and separated by responsibility: configuration hashes for fingerprints, registry variables for small metadata, statistics variables for counters and timestamps, and error ring buffers for bounded error history.

The ObjectID guidance also distinguishes genuine object references from
five-digit timeout, limit and domain literals so that Symcon integrity tooling
does not turn readable non-ID values into unexplained false positives.

Live-system changes now use an explicit staged verification gate with private
rollback material, side-effect prediction, direct bounded MCP read-back and
operational observation of installation invariants.

File-backed shared runtimes may now use an optional managed Symcon mirror for
console discoverability. The runtime file remains authoritative; the generated
mirror is inert, privately indexed, presentation-preserving and rollback-safe.
