# Case Studies

This directory contains SAEF case studies.

A case study preserves engineering experience from a concrete IP-Symcon
project. It explains context, decisions, trade-offs, constraints, operational
evidence and lessons learned. It is not a generic template, a reusable code
skeleton or a polished reference implementation.

## Repository Artifact Boundaries

| Artifact | Purpose | Expected Content |
| --- | --- | --- |
| `templates/` | Starting points for new work | Reusable structures, checklists and adaptation guidance |
| `examples/` | Small demonstrations | Focused usage examples for one pattern or helper |
| `references/` | Complete reviewed implementations | End-to-end implementation guidance with code where practical |
| `case-studies/` | Engineering experience reports | Project context, decisions, trade-offs, evidence and lessons |

A case study may produce candidates for knowledge, templates, helpers or
references. It does not itself define a new public API or generic rule.

## When to Create One

Create a case study when real work produces reusable engineering insight, for
example when:

- a design decision has meaningful trade-offs;
- migration or rollback reveals a repeatable pattern;
- state or Diagnostics modeling matters more than final code shape;
- retries, archives, events, providers or module boundaries require careful
  handling; or
- a private integration should inform SAEF without exposing installation data.

Current examples include ControlLight, MediaCarousel, the MQTT Discovery
Exporter, Navimow, Open-Meteo and OwnTracks. Irrigation, HomeConnect or other
domains should receive their own case study only when the material documents
engineering experience rather than a private object tree.

## Recommended Structure

1. Title, date and status.
2. Project context and engineering question.
3. Constraints, assumptions and ownership.
4. Decisions and alternatives.
5. Runtime state and Diagnostics.
6. Safety, privacy and operational boundaries.
7. Verification and evidence.
8. Outcome and lessons.
9. Remaining gates and rollback or retention state.
10. Related standards, knowledge, references and ADRs.

Complete reusable implementations belong in `references/`; reusable starting
structures belong in `templates/`. Dated reports must remain historical when a
later step supersedes their current-state conclusion.

## Runtime Diagnostics

Where runtime metadata is relevant, explain how the case study composes:

- Registry for small structured metadata;
- Statistics for counters, timestamps and duration values;
- ErrorRingBuffer for bounded error or event history; and
- ConfigurationHash for deterministic configuration fingerprints.

If dedicated variables are used, explain which real domain state, user
interface or trigger requirement justifies them. Record the initialization
boundary: setup failures before Diagnostics exist may only be visible through
exceptions, `IPS_LogMessage()` or the Symcon log.

## Evidence and Privacy

Separate repository implementation, qualification, publication, live mutation,
observation and cleanup. A passed gate is evidence for its exact target and
source generation, not reusable authorization.

Do not include credentials, tokens, private IP addresses, hostnames, personal
ObjectIDs, private MQTT topics, exact local object trees or unique household
descriptions. Keep such evidence under `private/` or in ignored local overlays.

## Promotion Path

- Repeated setup structure may become a template.
- A focused demonstration may become an example.
- A complete reviewed implementation may become a reference.
- A recurring concept may become a knowledge article.
- Recurring infrastructure may justify a helper only after Reuse Before Extend.

The case study records the evidence for promotion; it does not bypass the
separate architecture and API review.
