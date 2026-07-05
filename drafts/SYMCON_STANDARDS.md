# Symcon Engineering Standards

**Reference Standard:** RS-001  
**Status:** Draft 1.0  
**Target Location:** `standards/SYMCON_STANDARDS.md`

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

#### Exceptions

- Migration scripts
- One-time repair scripts
- Local private automations
- Diagnostics that intentionally operate on explicitly configured ObjectIDs

#### Related References

- `adr/ADR-0002-use-ident-over-object-id.md`
- `standards/SYMCON_STANDARDS.md`

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

#### Exceptions

Temporary runtime values that only exist during one script execution.

#### Related References

- `project/ENGINEERING_MODEL.md`
- `standards/PHP_STANDARDS.md`

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

When automatically creating events that execute scripts, set the event trigger, script assignment and event action binding explicitly.

For IP-Symcon 6.0 and newer, script-executing events shall include the required event action binding.

#### Rationale

A trigger alone does not fully define how an event executes its action. Explicit action binding makes generated events complete and reproducible.

#### Recommended Practice

For script-executing events, create or update all required parts:

- event object,
- trigger condition,
- script assignment,
- event action binding,
- activation state.

#### Exceptions

Manually configured user events are outside the scope of reusable setup scripts.

#### Related References

- Official IP-Symcon documentation: `IPS_SetEventAction()`
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

## 7. Planned Sections

The following sections will be developed in subsequent commits:

- Data and Archive Management
- Error and Recovery Handling
- Logging and Diagnostics
- Configuration Management
- Reusability
- AI Engineering Compatibility
- References
