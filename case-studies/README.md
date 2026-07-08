# Case Studies

This directory contains SAEF case studies.

A case study documents engineering experience from a concrete IP-Symcon project.
It explains context, decisions, trade-offs, constraints and lessons learned. It
is not a generic template, not a reusable code skeleton and not a polished
reference implementation.

Case studies help SAEF evolve from real engineering work without turning private
installations into public artifacts.

## Repository Artifact Boundaries

SAEF uses different artifact types for different purposes.

| Artifact | Purpose | What It Should Contain |
| --- | --- | --- |
| `templates/` | Starting points for new work | Reusable structures, checklists and adaptation guidance |
| `examples/` | Small demonstrations | Focused usage examples for one pattern or helper |
| `references/` | Complete reviewed implementations | End-to-end implementation guidance with code where practical |
| `case-studies/` | Engineering experience reports | Project context, decisions, trade-offs and lessons learned |

A case study may mention patterns that later become knowledge articles,
templates, helpers or reference implementations. It should not itself define a
new reusable API or prescribe a generic solution.

## When to Create a Case Study

Create a case study when a real project produced reusable engineering insight.

Good candidates include projects where:

- a design decision had meaningful trade-offs,
- a migration revealed a repeatable pattern,
- runtime diagnostics changed how the system is maintained,
- state modeling was more important than the final code shape,
- an integration required careful handling of retries, archives, events or
  module boundaries,
- a local solution should inform SAEF without exposing private installation
  data.

Case studies are especially useful for larger automation topics such as:

- Navimow integrations with state, polling, cloud behavior or diagnostics,
- irrigation and watering systems with safety constraints and domain state,
- HomeConnect appliances with controllable actions, state synchronization or
  delayed feedback,
- Home Assistant exporter work with mapping, ownership, compatibility and
  migration decisions.

These project names describe engineering domains where SAEF can learn from
experience. They are not generic templates and must not include private
ObjectIDs, credentials, hostnames, private topics or installation-specific
system descriptions.

## Recommended Structure

A case study should be structured enough to support review:

1. Title and status.
2. Project context.
3. Problem or engineering question.
4. Constraints and assumptions.
5. Decisions made.
6. Alternatives considered.
7. Runtime state and diagnostics.
8. Safety, privacy and operational concerns.
9. Outcome.
10. Lessons learned.
11. Follow-up candidates for SAEF artifacts.
12. Related standards, knowledge, references or ADRs.

Keep the focus on engineering reasoning. Include code only as short excerpts
when it is necessary to explain a decision. Complete reusable implementations
belong in `references/`; reusable starting structures belong in `templates/`.

## Runtime Diagnostics in Case Studies

When a project involved runtime metadata, document how it was modeled:

- Registry for small structured metadata,
- Statistics for counters, timestamps and duration values,
- ErrorRingBuffer for bounded error or event history,
- ConfigurationHash for deterministic configuration fingerprints.

If the project used dedicated variables instead, explain whether they represented
real domain state or were intentionally visible for user interfaces,
visualisation or trigger logic.

Also document the initialization boundary when relevant: setup or Ensure errors
before diagnostics exist may only be visible through exceptions,
`IPS_LogMessage()` or the Symcon log.

## Privacy Rules

Case studies must not contain private installation data.

Do not include:

- credentials or tokens,
- private IP addresses or hostnames,
- personal IP-Symcon ObjectIDs,
- private MQTT topics,
- exact local object trees,
- unique household or site descriptions.

Use generalized names and sanitized examples. Keep private details in `private/`
or local overlays that are not committed.

## Promotion Path

A case study can lead to new SAEF artifacts, but only after review:

- repeated setup shape may become a template,
- focused demonstration may become an example,
- complete reviewed implementation may become a reference,
- recurring concept may become a knowledge article,
- recurring infrastructure need may justify a helper after Reuse Before Extend.

The case study should record the evidence for such promotion instead of
introducing the new reusable artifact by itself.
