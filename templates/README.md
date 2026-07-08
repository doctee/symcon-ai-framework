# Templates

This directory contains reusable SAEF starting points.

Templates are intentionally more abstract than examples or reference
implementations. They define structure, responsibilities and review checklists
for new artifacts, but they should not contain private installation data or
complete scenario-specific solutions.

## Repository Artifact Boundaries

SAEF distinguishes between templates, examples, references and case studies.

| Artifact | Purpose | Expected Content |
| --- | --- | --- |
| `templates/` | Starting structures for new work | Minimal reusable scaffolding, checklists and adaptation guidance |
| `examples/` | Small practical demonstrations | Concrete but limited examples that show one pattern in use |
| `references/` | Complete reviewed implementations | End-to-end implementations with design notes, constraints and verification guidance |
| `case-studies/` | Experience reports, when present | Real-world analysis of decisions, trade-offs and lessons learned without private data |

Use a template when starting a new artifact. Use an example when demonstrating a
small usage pattern. Use a reference implementation when the artifact should
teach a complete SAEF engineering approach. Use a case study only when the goal
is to explain a completed engineering decision or migration.

## Template Rules

Templates must follow the repository engineering model:

- prefer explicit structure over implicit assumptions,
- avoid private ObjectIDs, secrets, hostnames and local installation details,
- keep configuration separate from implementation logic,
- reuse existing SAEF helpers before introducing new abstractions,
- document ownership, side effects and expected adaptation points,
- keep runtime metadata aligned with the diagnostics responsibilities in
  `standards/SYMCON_STANDARDS.md`.

Templates may define expected files, sections, naming conventions and review
questions. They should introduce PHP code only when a reusable code skeleton is
explicitly part of the template goal.

## Current Templates

| Template | Purpose |
| --- | --- |
| `ConfigurationScript.php` | Starting point for idempotent IP-Symcon configuration scripts |
| `module/` | Concept and checklist for starting SAEF-aligned IP-Symcon module projects |

## Runtime Diagnostics

When a template needs runtime metadata, prefer the existing diagnostics
responsibilities:

- Registry for small structured metadata,
- Statistics for counters, timestamps and duration values,
- ErrorRingBuffer for bounded error or event history,
- ConfigurationHash for deterministic configuration fingerprints.

Dedicated variables remain appropriate when they represent real domain state or
must intentionally be visible for user interfaces, visualisation or trigger
logic.
