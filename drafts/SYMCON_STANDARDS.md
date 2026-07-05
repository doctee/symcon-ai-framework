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
- variables have expected types,
- profiles exist where required,
- parent objects are valid,
- archive instance IDs are valid,
- configured time ranges are plausible,
- required actions are available before using `RequestAction()`.

#### Anti-Patterns

- Validating configuration after switching devices.
- Assuming an ObjectID still points to the expected object.
- Creating objects below an invalid or unintended parent.

#### Exceptions

Read-only diagnostic scripts may perform partial validation if failure has no side effects.

#### Related References

- `standards/PHP_STANDARDS.md`
- `references/RI-001-idempotent-configuration-script.md`

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

#### Anti-Patterns

- Helper functions that read private global configuration without parameters.
- Generic helper names with installation-specific behaviour.
- Helpers that both calculate and switch devices without clear documentation.

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

## 13. References

This Reference Standard is supported by the following SAEF artifacts:

- `project/AI_PROJECT.md`
- `project/ENGINEERING_MODEL.md`
- `ARCHITECTURE.md`
- `AGENTS.md`
- `principles/ENGINEERING_PRINCIPLES.md`
- `principles/AI_PRINCIPLES.md`
- `adr/ADR-0001-use-requestaction.md`
- `adr/ADR-0002-use-ident-over-object-id.md`
- `adr/ADR-0003-private-overlay.md`
- `standards/DOCUMENTATION_STANDARDS.md`
- `standards/PHP_STANDARDS.md`
- `standards/TESTING_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `references/RI-001-idempotent-configuration-script.md`

It is also aligned with current official IP-Symcon documentation for variable actions, event actions, event creation and Archive Control behaviour.

## 14. Release Notes

This draft is complete enough for internal SAEF use and Codex-assisted development.

Before moving this document from `drafts/SYMCON_STANDARDS.md` to `standards/SYMCON_STANDARDS.md`, perform a final consistency review:

- align all rules to the final Reference Standard rule format,
- verify all references,
- check terminology against the glossary,
- ensure no private installation details are present,
- verify current Symcon API references.
