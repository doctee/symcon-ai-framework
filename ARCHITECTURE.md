# Architecture

**Status:** Draft 1.0

## 1. Purpose

This document describes the architecture of the Symcon AI Engineering Framework (SAEF).

It explains how the repository is organised, how the different artifact types relate to each other, and where new content belongs. It does not describe the engineering process itself; this is covered by `project/ENGINEERING_MODEL.md`.

---

## 2. Repository Structure

The repository is organised by engineering artifact type rather than by technology.

```text
Repository
│
├── project/          Project vision and engineering model
├── principles/       Engineering and AI principles
├── adr/              Architecture Decision Records
├── standards/        Engineering and coding standards
├── knowledge/        Reusable engineering knowledge
├── glossary/         Common terminology
├── templates/        Reusable document and code templates
├── helpers/          Reusable implementation building blocks
├── references/       Reference implementations
├── handbook/         User and contributor documentation
│
├── README.md
├── CONTRIBUTING.md
├── CODE_OF_CONDUCT.md
└── LICENSE
```

Directories that do not yet exist represent the planned target architecture and should be introduced only when required.

---

## 3. Artifact Relationships

The framework artifacts build upon each other.

```text
Project Charter
        │
        ▼
Engineering Principles
        │
        ▼
Architecture Decision Records
        │
        ▼
Standards
        │
        ▼
Templates
        │
        ▼
Helpers
        │
        ▼
Reference Implementations
        │
        ▼
Handbook
```

Knowledge supports every layer and is continuously expanded through practical engineering work.

---

## 4. Dependency Rules

- Principles define long-term engineering direction.
- ADRs capture significant architectural decisions.
- Standards translate principles and ADRs into concrete engineering rules.
- Templates and helpers implement standards in reusable form.
- Reference implementations demonstrate recommended engineering practice.
- The handbook explains the framework from a user's perspective.

Dependencies should always point from more stable artifacts to more specific artifacts.

---

## 5. Evolution

The repository architecture should remain intentionally simple.

New top-level directories or artifact categories should only be introduced when existing structures can no longer represent the required engineering knowledge without becoming unclear.

Maintaining a simple and understandable architecture is considered more valuable than introducing additional structural complexity.
