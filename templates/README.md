# Templates

This directory contains reusable SAEF starting points.

Templates are intentionally more abstract than examples or reference
implementations. They define structure, responsibilities and review checklists
for new artifacts, but they do not contain private installation data or a
complete scenario-specific solution.

## Repository Artifact Boundaries

| Artifact | Purpose | Expected Content |
| --- | --- | --- |
| `templates/` | Starting structures for new work | Minimal reusable scaffolding, checklists and adaptation guidance |
| `examples/` | Small practical demonstrations | Concrete, limited examples that show one pattern in use |
| `references/` | Complete reviewed implementations | End-to-end implementations with design notes, constraints and verification guidance |
| `case-studies/` | Engineering experience reports | Real project decisions, trade-offs and lessons without private installation data |

Use a template when starting a new artifact. Use an example to demonstrate one
small pattern. Use a reference when the artifact should teach a complete SAEF
engineering approach. Use a case study to preserve engineering evidence from a
concrete project.

## Template Rules

Templates must:

- prefer explicit structure over implicit assumptions;
- avoid private ObjectIDs, secrets, hostnames and local installation details;
- keep configuration separate from implementation logic;
- reuse existing SAEF helpers and operational contracts before adding another
  abstraction;
- document ownership, side effects and expected adaptation points;
- keep runtime metadata aligned with the Diagnostics responsibilities in
  `standards/SYMCON_STANDARDS.md`; and
- keep repository work, publication, installation, live mutation and cleanup
  as separate gates.

Templates may define expected files, sections, naming conventions and review
questions. They should introduce code only when a reusable skeleton is part of
the explicit template goal.

## Current Templates

| Template | Purpose |
| --- | --- |
| `ConfigurationScript.php` | Starting point for idempotent IP-Symcon configuration scripts |
| `module/` | Concept and checklist for starting SAEF-aligned IP-Symcon module projects |
| `workstream/` | Canonical private handover record and Markdown structure for isolated workstreams |

## Runtime Diagnostics

When a template needs runtime metadata, prefer the existing responsibilities:

- Registry for small structured metadata;
- Statistics for counters, timestamps and duration values;
- ErrorRingBuffer for bounded error or event history; and
- ConfigurationHash for deterministic configuration fingerprints.

Dedicated variables remain appropriate when they represent real domain state
or must intentionally be visible for a user interface, visualization or
trigger.
