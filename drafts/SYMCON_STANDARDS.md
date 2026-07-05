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

## 4. Planned Sections

The following sections will be developed in subsequent commits:

- Variable Lifecycle
- Script Architecture
- Event Architecture
- Data and Archive Management
- Error and Recovery Handling
- Logging and Diagnostics
- Configuration Management
- Reusability
- AI Engineering Compatibility
- References
