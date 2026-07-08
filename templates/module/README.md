# IP-Symcon Module Template Concept

This directory describes how to start a new IP-Symcon module project with SAEF.

It is a concept template, not a PHP code skeleton. It defines the engineering
shape that a module project should follow before implementation begins.

## Purpose

A SAEF-aligned module project should make its responsibilities explicit from the
start:

- which Symcon objects, instances, variables and profiles the module owns,
- which values are public configuration,
- which values are domain state,
- which values are internal runtime diagnostics,
- which actions are user-controllable,
- which behavior must be idempotent and safe to repeat.

This keeps the module reviewable by humans and AI agents before concrete module
code exists.

## Starting a Module Project

Start with design, not code:

1. Define the module purpose and operational boundary.
2. Identify owned objects, variables, profiles, timers and actions.
3. Separate public configuration from implementation logic.
4. Decide which variables are domain state, command interfaces or internal
   diagnostics.
5. Plan idempotent setup behavior for created objects and profiles.
6. Reuse existing SAEF helpers and patterns where they apply to scripts,
   references, setup tooling or examples.
7. Document private installation values as external configuration or local
   overlays, never as committed defaults.
8. Define verification before adding implementation code.

The first committed artifact may be a README, design note, ADR or reference
plan. A PHP implementation should follow only after ownership, configuration and
diagnostics responsibilities are clear.

## Recommended Project Documentation

A new module project should document:

- module purpose and non-goals,
- expected IP-Symcon version range,
- owned variables and their roles,
- configuration fields and validation rules,
- user actions and action semantics,
- timers or event behavior,
- diagnostics structure,
- migration and compatibility notes,
- verification steps.

Use ADRs when a design decision affects public behavior, compatibility or a
new reusable pattern.

## Runtime Diagnostics

Runtime metadata should use existing diagnostics responsibilities before adding
dedicated variables:

- Registry for small structured metadata such as version, migration markers or
  configuration fingerprints,
- Statistics for counters, timestamps and duration values,
- ErrorRingBuffer for bounded recent errors or relevant events,
- ConfigurationHash for deterministic fingerprints of normalized
  configuration.

Diagnostics begin after the diagnostics structure has been initialized. Early
setup or Ensure failures may only be visible through exceptions, `IPS_LogMessage()`
or the Symcon log. Once diagnostics exist, runtime failures should be modeled in
ErrorRingBuffer, Statistics and Registry where appropriate.

Dedicated variables remain allowed when they represent real domain state or
must intentionally be visible for user interfaces, visualisation or trigger
logic. Document that decision in the module design.

## Helper-First Guidance

For setup scripts, examples or reference implementations around the module,
reuse existing SAEF helpers before adding local infrastructure logic:

- object and variable creation through existing Ensure helpers,
- explicit validation before side effects,
- stable Idents instead of private ObjectIDs,
- bounded archive or diagnostic processing,
- `RequestAction()` for controllable variables,
- `SetValue()` only for script-owned internal state.

Module internals may have IP-Symcon-specific lifecycle requirements that are
not identical to standalone scripts. Do not add new SAEF helpers or public APIs
for module-specific convenience until a recurring pattern has been demonstrated.

## Review Checklist

Before implementation starts:

- The module boundary is documented.
- Public configuration is separated from private local values.
- Owned objects and variable roles are listed.
- Domain state is separated from runtime diagnostics.
- Diagnostics responsibilities have been checked.
- Dedicated diagnostic variables are justified, if present.
- Idempotent setup behavior is described.
- Existing SAEF helpers and references have been reviewed.
- No private ObjectIDs, secrets, hostnames or local topics are included.
- Verification steps are defined.
