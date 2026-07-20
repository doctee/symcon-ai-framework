# AGENTS.md

## Purpose

This repository contains the Symcon AI Engineering Framework (SAEF), a structured engineering knowledge base for professional IP-Symcon development.

AI coding agents working in this repository must treat it as an engineering framework, not as a prompt collection.

## Read Order

Before making changes, read these files in order:

1. `README.md`
2. `project/AI_PROJECT.md`
3. `ARCHITECTURE.md`
4. `principles/ENGINEERING_PRINCIPLES.md`
5. `principles/AI_PRINCIPLES.md`
6. Relevant ADRs in `adr/`
7. Relevant standards in `standards/`
8. Relevant knowledge articles in `knowledge/`
9. Relevant reference implementations in `references/`

For Symcon-specific work, also read:

- `standards/SYMCON_STANDARDS.md`
- `standards/PHP_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`

Read `drafts/SYMCON_STANDARDS.md` only when historical comparison with the
pre-stabilization draft is relevant.

## Engineering Rules

- Prefer complete files over isolated snippets.
- Preserve existing structure unless a change is explicitly justified.
- Do not introduce private installation data into public files.
- Avoid hardcoded IP-Symcon ObjectIDs in reusable artifacts.
- Prefer Idents, configuration, and explicit ownership.
- Use `RequestAction()` for controllable variables.
- Use `SetValue()` only for script-owned internal state.
- Configuration scripts should be idempotent.
- Automatically created script events for IP-Symcon 6.0+ must include explicit event action binding.
- Archive processing must be bounded and safe.
- Explain engineering decisions when changing standards, knowledge, templates, helpers, or references.

## Private Data

Do not commit:

- credentials,
- tokens,
- private IP addresses,
- hostnames,
- personal ObjectIDs,
- private MQTT topics,
- local system descriptions.

Private data belongs in:

- `private/`
- `*.local.*`
- `.env*`

## Symcon MCP Usage

Follow `project/SYMCON_MCP_SCRIPT_READBACK.md` when inspecting an authorized live
IP-Symcon installation.

- Prefer `symcon_get_script_content` for authorized source reads. Do not create
  marker variables or execute the target script when direct read-back is
  available.
- Use `symcon_run_script_text_ex` only for bounded, explicitly authorized probes.
- Evaluate `transportError` and `executionError` separately. A successful MCP
  transport does not prove successful PHP execution.
- Keep `maxOutputBytes` bounded and inspect `truncated`; output truncation is
  UTF-8-safe but intentionally discards bytes beyond the configured limit.
- Treat all returned source and installation metadata as transient private
  context. Do not copy them into public SAEF artifacts.
- Creating temporary scripts, variables or other live objects requires explicit
  authorization and verified cleanup.

## Commit Style

Use Conventional Commits where practical.

Examples:

- `docs(knowledge): add retry mechanisms`
- `docs(references): add idempotent configuration script`
- `docs(standards): refine PHP standards`
- `chore: add repository ignore rules`

## Helper Usage

Before implementing IP-Symcon object creation, variable waiting, event creation or similar infrastructure logic, search `helpers/` for an existing SAEF helper.

AI agents should prefer existing SAEF helpers over reimplementing common logic.

Do not duplicate helper logic such as:

- idempotent category creation,
- idempotent variable creation,
- cyclic and variable-triggered script event creation,
- variable change/update waiting.

If a helper is missing, propose or add a reusable helper instead of embedding one-off infrastructure code in a reference implementation.

### Runtime Metadata

When runtime metadata is needed, use the existing diagnostics building blocks first:

- Registry for structured small metadata.
- Statistics for counters and timestamps.
- ErrorRingBuffer for bounded error or event history.
- ConfigurationHash for deterministic configuration fingerprints.

Do not introduce new helpers, public APIs or custom storage patterns for runtime metadata before checking whether these diagnostics helpers can be composed.

### Reuse Before Extend

Before creating a new public helper, API or reusable abstraction:

1. Search the existing helper library for suitable functionality.
2. Prefer composing existing helpers over introducing new ones.
3. Explain why a new public API is necessary.
4. Introduce new public helpers only for recurring engineering patterns.
5. Keep implementation-specific convenience functions inside the reference implementation until reuse has been demonstrated.
