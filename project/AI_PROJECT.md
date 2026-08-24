# Symcon AI Engineering Framework – AI Project Charter

Version: 0.4 released; 0.5 inventory open
Status: Stable Draft
Scope: Public core, no private installation data

## 1. Purpose

The Symcon AI Engineering Framework is a structured engineering framework for professional, AI-assisted development with IP-Symcon.

It defines conventions, architecture decisions, reusable helpers, templates, documentation standards, and workflows for creating reliable, maintainable, and explainable IP-Symcon scripts and modules.

The framework is designed for both human developers and AI coding assistants.

## 2. Core Principles

### 2.1 Reliability before convenience

Generated code must be safe, understandable, and maintainable. Convenience is secondary to operational reliability.

### 2.2 Explain decisions

Technical recommendations must include the reason behind them. Rules without rationale are considered incomplete.

### 2.3 Prefer reusable patterns

Recurring solutions should become documented patterns, helpers, templates, or ADRs.

### 2.4 Separate public and private knowledge

Public framework files must not contain private installation data.

Private data includes:

- IP addresses
- credentials
- tokens
- personal object IDs
- site-specific MQTT topics
- VPN details
- private device names if sensitive

Private data belongs only in `private/` or `*.local.*` files and must not be committed.

### 2.5 Use stable references

Where possible, Symcon objects should be found by Ident, path, or configuration, not by hardcoded object IDs.

Hardcoded object IDs are allowed only when explicitly documented as installation-specific configuration.

## 3. Repository Layers

### 3.1 Public Core

Public and potentially GitHub-ready content:

- `principles/`
- `standards/`
- `adr/`
- `knowledge/`
- `templates/`
- `helpers/`
- `examples/`
- `references/`
- `case-studies/`
- `prompts/`
- `bundles/`
- `deployments/`
- `dist/`
- `tests/`

### 3.2 Project Layer

Project-specific but non-secret context:

- `project/`

### 3.3 Private Overlay

Local-only installation details:

- `private/`
- `*.local.*`
- `.env`
- secret files

These files are excluded from Git.

## 4. AI Assistant Operating Rules

AI assistants working in this repository must follow these rules:

1. Read this file first.
2. Read relevant standards and ADRs before changing code or documentation.
3. Do not move private data into public files.
4. Prefer complete scripts or complete files over isolated snippets.
5. Preserve existing logic unless a change is explicitly requested.
6. Explain relevant design decisions.
7. Use IP-Symcon documentation and established community practice when creating or modifying Symcon code.
8. Avoid unnecessary hardcoded object IDs.
9. Prefer `RequestAction()` over direct `SetValue()` when triggering device or module actions.
10. When automatically creating Symcon events that execute scripts, include the required event action binding for Symcon 6.0+.
11. Design helpers and templates for reuse.
12. Keep code readable, structured, and defensive.

## 5. Symcon Development Rules

### 5.1 Object handling

Objects should be created or found by Ident where possible.

Names are user-facing and may change. Idents are intended as stable technical references.

### 5.2 Events

Automatically created events must be deterministic, identifiable, and safe to recreate.

For script-executing events in Symcon 6.0 and newer, choose exactly one
execution contract: bind a parent automation through the event action or store
deliberate inline PHP source as the event script. Do not combine both contracts.

### 5.3 Variables

Variable profiles, types, idents, and archive behavior should be documented.

Variables created by scripts or modules should be clearly separated from user-facing configuration where appropriate.

### 5.4 Logging

Logging should be useful, not noisy.

Default behavior:

- log errors and important warnings,
- make debug logging optional,
- avoid logging every normal state transition unless explicitly required.

### 5.5 Archives

Archive operations must be performed carefully and preferably in bounded blocks.

Large calls to archive functions should be avoided.

### 5.6 Modules

Modules should be update-safe, migration-friendly, and compatible with the Symcon Store where possible.

Module-internal state should not depend on invisible assumptions.

## 6. Architecture Decision Records

Major technical decisions must be documented in `adr/`.

Each ADR should include:

- context
- decision
- rationale
- consequences
- alternatives considered
- status

## 7. Documentation Style

Documentation should be written as engineering documentation, not as a loose prompt collection.

Each important recommendation should include:

- goal
- motivation
- rule
- rationale
- examples
- exceptions
- related ADRs

## 8. Definition of Done

A change is considered complete when:

- it follows the relevant standards,
- private data is not leaked,
- important decisions are documented,
- code is readable and defensive,
- examples or tests are added where useful,
- the change is consistent with existing architecture.

## 9. Framework Evolution

### Phase 1 – Foundation (established)

- repository structure
- AI project charter
- coding standards
- first ADRs
- documentation conventions

### Phase 2 – Knowledge Transfer (established and evolving)

- reusable helper library
- Symcon script templates
- module templates
- known best practices
- examples from real automation scenarios

### Phase 3 – Engineering Platform (in progress)

- stable standards and knowledge base
- documented public/private split
- automated checks
- reusable test patterns
- deterministic helper bundles and deployment tooling
- release-quality reference implementations and case studies
