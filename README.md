# Symcon AI Engineering Framework

The Symcon AI Engineering Framework (SAEF) is a structured engineering knowledge base for professional, AI-assisted IP-Symcon development.

SAEF is not a prompt collection. It combines engineering principles, architecture decisions, standards, reusable knowledge, reference implementations and agent instructions into a consistent development framework.

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
| `references/` | Complete reference implementations |
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
- `drafts/SYMCON_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `references/RI-001-idempotent-configuration-script.md`

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

The repository already contains the project foundation, first standards, initial engineering knowledge articles and the first reference implementation. The Symcon Engineering Standards are currently developed as a draft Reference Standard in `drafts/SYMCON_STANDARDS.md`.
