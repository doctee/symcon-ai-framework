# Documentation Standards

**Status:** Draft 1.0

## 1. Purpose

This standard defines how documentation within the Symcon AI Engineering Framework (SAEF) shall be written and maintained.

Its goal is to ensure that documentation remains consistent, understandable, maintainable and suitable for both human readers and AI-assisted engineering.

---

## 2. Scope

This standard applies to all version-controlled documentation within the repository, including:

- README files
- Principles
- Architecture Decision Records (ADRs)
- Standards
- Engineering Knowledge
- Handbook content
- Templates
- Reference implementations

---

## 3. General Principles

Documentation shall:

- explain *why*, not only *what*;
- remain concise without omitting important rationale;
- be technically accurate;
- avoid unnecessary duplication;
- be kept consistent with related documents.

When information belongs to another document, reference that document instead of copying its content.

---

## 4. Language

The repository language is English.

Documentation should:

- use clear technical language;
- avoid ambiguous wording;
- avoid marketing language;
- avoid unnecessary abbreviations;
- define new terminology before using it.

---

## 5. Document Structure

Each normative document should contain, where applicable:

1. Purpose
2. Scope
3. Rules
4. Rationale
5. Exceptions
6. Related Documents

Not every document requires every section, but the overall structure should remain familiar throughout the repository.

---

## 6. Rules and Rationale

Normative statements should be accompanied by their rationale.

Preferred structure:

**Rule**

State the engineering rule.

**Rationale**

Explain why the rule exists and which engineering benefit it provides.

Rules without rationale should be avoided whenever practical.

---

## 7. Examples

Examples should:

- illustrate recommended practice;
- remain independent from private installations;
- avoid secrets, credentials and installation-specific object IDs;
- be complete enough to understand the concept.

---

## 8. References

Documentation should reference related:

- Principles
- ADRs
- Standards
- Knowledge articles

instead of duplicating information.

---

## 9. Maintenance

Documentation is part of the engineering process.

Whenever an engineering decision changes, affected documentation shall be reviewed as part of the same change.

---

## 10. AI Compatibility

Documentation should be written so that both engineers and AI systems can interpret it consistently.

Prefer explicit terminology, consistent structure and clearly justified engineering decisions over implicit assumptions.
