# Symcon AI Engineering Framework

The Symcon AI Engineering Framework (SAEF) is a structured engineering
knowledge base for professional, AI-assisted IP-Symcon development.

SAEF is not a prompt collection. It combines engineering principles,
architecture decisions, standards, reusable knowledge, reference
implementations, helper libraries, implementation prompts and agent
instructions into a consistent development framework.

## Goals

SAEF helps engineers and AI coding agents create Symcon solutions that are:

- reliable,
- maintainable,
- reusable,
- reviewable,
- safe for real installations,
- compatible with AI-assisted development.

## Repository Structure

| Path | Purpose |
| --- | --- |
| `project/` | Project charter and framework-level project documents |
| `principles/` | Engineering and AI principles |
| `adr/` | Architecture Decision Records |
| `standards/` | Stable engineering, documentation, PHP and testing standards |
| `drafts/` | Larger artifacts currently under development and review |
| `knowledge/` | Reusable engineering knowledge and design guidance |
| `helpers/` | Reusable PHP helper functions for IP-Symcon engineering patterns |
| `templates/` | Reusable script and artifact templates |
| `references/` | Complete reference implementations |
| `prompts/` | Reusable implementation prompts for SAEF development workflows |
| `glossary/` | Shared terminology |
| `private/` | Local-only private overlay, excluded from Git |

## Start Here

For humans:

1. `project/AI_PROJECT.md`
2. `ARCHITECTURE.md`
3. `principles/ENGINEERING_PRINCIPLES.md`
4. `standards/README.md`
5. `knowledge/README.md`
6. `references/README.md`

For AI coding agents:

1. `AGENTS.md`
2. `project/AI_PROJECT.md`
3. `ARCHITECTURE.md`
4. Relevant standards, ADRs, knowledge articles and reference implementations

## Current Core Artifacts

Important current artifacts include:

- `AGENTS.md`
- `ARCHITECTURE.md`
- `project/AI_PROJECT.md`
- `principles/ENGINEERING_PRINCIPLES.md`
- `principles/AI_PRINCIPLES.md`
- `standards/SYMCON_STANDARDS.md`
- `helpers/diagnostics/ConfigurationHash.php`
- `helpers/diagnostics/Registry.php`
- `helpers/diagnostics/Statistics.php`
- `helpers/diagnostics/ErrorRingBuffer.php`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `knowledge/EK-006-runtime-diagnostics.md`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
- `prompts/IMPLEMENT_REFERENCE.md`

## Private Data

Private installation data must never be committed.

This includes:

- credentials,
- tokens,
- private IP addresses,
- hostnames,
- personal IP-Symcon ObjectIDs,
- private MQTT topics,
- local system descriptions.

Private data belongs only in:

- `private/`
- `*.local.*`
- `.env*`

## Development Status

SAEF is currently in early development.

The repository contains the project foundation, initial engineering knowledge
articles, reusable helpers, templates and reference implementations.

`standards/SYMCON_STANDARDS.md` is the current stable draft Symcon Reference
Standard for SAEF (`Stable Draft 1.0`). The earlier draft remains available in
`drafts/SYMCON_STANDARDS.md` for comparison.

Version 0.2.0 development focuses on runtime diagnostics, internal state and
helper-first reference implementations. The diagnostics helper set currently
covers configuration hashes, registry metadata, statistics and bounded error
ring buffers. `RI-002` demonstrates how these helpers are composed.
