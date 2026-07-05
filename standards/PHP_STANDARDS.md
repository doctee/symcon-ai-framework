# PHP Standards

**Status:** Draft 1.0

## 1. Purpose

This standard defines engineering rules for writing PHP code within the Symcon AI Engineering Framework (SAEF).

It complements established PHP standards such as PSR-12 instead of replacing them. SAEF focuses on engineering decisions, maintainability and AI-assisted development.

---

## 2. Scope

This standard applies to:

- Symcon scripts
- Symcon modules
- Helper libraries
- Templates
- Reference implementations

Formatting and coding style should follow established community standards unless SAEF explicitly defines additional requirements.

---

## 3. General Principles

PHP code shall be:

- readable,
- maintainable,
- deterministic,
- defensive,
- reusable where appropriate.

Optimisation should never reduce readability without measurable benefit.

---

## 4. Configuration

Configuration values should be grouped in a dedicated configuration section at the beginning of a file.

Configuration shall be separated from implementation logic.

Installation-specific values shall not be hardcoded in reusable framework artifacts.

---

## 5. Functions

Create functions when logic:

- has a single responsibility,
- is reused,
- improves readability, or
- can be tested independently.

Functions should avoid hidden dependencies and unnecessary side effects.

---

## 6. Error Handling

Expected failures shall be handled explicitly.

Avoid suppressing errors.

Error messages should help identify the engineering problem rather than only reporting the technical symptom.

---

## 7. State Changes

Functions that modify external state should make this behaviour obvious.

Unexpected side effects should be avoided.

---

## 8. Defensive Programming

Validate external input before use.

Assume configuration, network communication and external systems may fail.

Prefer explicit validation over implicit assumptions.

---

## 9. Reuse

Reusable engineering knowledge should become:

- helper functions,
- templates,
- standards,
- or reference implementations.

Avoid copying implementation logic between projects.

---

## 10. AI Compatibility

Write code that remains understandable without additional explanation.

Prefer explicit names, small logical units and predictable control flow.

Generated code should require minimal manual interpretation during engineering review.
