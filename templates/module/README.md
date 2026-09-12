# IP-Symcon Module Template Concept

This directory describes how to start a new IP-Symcon module project with
SAEF. It is a concept template, not a PHP code skeleton.

## Purpose

A SAEF-aligned module project should make these responsibilities explicit
before implementation:

- which instances, variables, profiles, timers and actions it owns;
- which values are public configuration, domain state or internal metadata;
- which actions are user-controllable;
- which setup and migration behavior must be idempotent; and
- which publication and live-installation gates apply.

This makes the module reviewable by humans and AI agents before concrete module
code exists.

## Starting a Module Project

1. Define the module purpose, non-goals and supported IP-Symcon versions.
2. Identify owned instances, variables, profiles, timers, actions and external
   providers.
3. Separate public configuration from implementation logic and private local
   bindings.
4. Classify variables as domain state, command interfaces, intentionally
   visible diagnostics or internal metadata.
5. Define idempotent creation, migration, disable and deletion behavior.
6. Search existing SAEF helpers, module distributions and case studies before
   adding local infrastructure logic.
7. Define deterministic tests, static analysis and generated-artifact checks.
8. Keep publication, module-control update, live mutation, restart and cleanup
   outside the repository implementation gate.

The first committed artifact may be a design note, ADR or case-study plan. PHP
implementation should follow only after ownership and state responsibilities
are clear.

## Recommended Documentation

Document at least:

- purpose and non-goals;
- compatibility range;
- module and instance ownership;
- configuration fields and validation rules;
- variables, actions, timers and event behavior;
- runtime state and Diagnostics responsibilities;
- provider, network and credential boundaries;
- migration and backward compatibility;
- package, publication and installation model; and
- verification and rollback expectations.

Use an ADR when a decision affects reusable architecture, public behavior or
compatibility. Use a case study for concrete engineering experience. A complete
generic implementation belongs in `references/`, not in this template.

## Runtime Diagnostics

Use the existing Diagnostics responsibilities before adding dedicated metadata
variables:

- Registry for small structured metadata such as schema or migration markers;
- Statistics for counters, timestamps and duration values;
- ErrorRingBuffer for bounded recent errors or relevant events; and
- ConfigurationHash for deterministic normalized configuration fingerprints.

Diagnostics begin only after their structure has been initialized. Earlier
setup failures remain visible through exceptions, `IPS_LogMessage()` or the
Symcon log. Dedicated variables remain valid for real domain state or values
that must deliberately be visible to users, visualizations or triggers; record
that decision in the module design.

## Helper-First Boundary

Reuse SAEF helpers in configuration scripts, support tooling, references and
module distributions where their ownership model fits. Do not introduce a new
public helper API for module-specific convenience until recurring reuse has
been demonstrated.

Module lifecycle methods may require IP-Symcon-specific ownership rules that
differ from standalone scripts. In all cases:

- validate before side effects;
- prefer stable Idents over private ObjectIDs;
- use bounded state and archive processing;
- use `RequestAction()` for controllable variables; and
- use direct value writes only for state the implementation owns.

## Workstream and Deployment Boundary

Build from a current clean `origin/main` in a dedicated worktree. Keep the
Composer toolchain bound to the matching lockfile. Generated filesets and
publication hashes must remain deterministic.

The generic module publisher and restricted Channel-v8 deployment path are
operational contracts, not automatic privileges. A target must still receive
its own reviewed adapter/profile, Windows qualification, preflight, activation,
postflight and rollback evidence. Never infer authority for one module from a
successful gate for another.

## Review Checklist

- The module boundary and non-goals are documented.
- Public configuration is separated from private local values.
- Owned objects and variable roles are listed.
- Domain state is separated from runtime metadata.
- Diagnostics responsibilities and initialization boundaries are addressed.
- Idempotent setup, migration and disable behavior is described.
- Existing helpers, references and module patterns were reviewed.
- No private ObjectIDs, secrets, hostnames or topics are included.
- Deterministic tests and static-analysis entry points are defined.
- Publication, live installation, restart and cleanup remain separate gates.
