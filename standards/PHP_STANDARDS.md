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

### 4.1 Five-digit numeric literals in Symcon scripts

Bare five-digit decimal literals should not be used for non-ObjectID values in
PHP stored in IP-Symcon script objects. Symcon integrity tools may use this
numeric range as a heuristic for detecting ObjectID references and cannot infer
whether a value is instead a timeout, limit or domain constant.

Prefer a named value and make the unit explicit. A readable calculation may be
used when it communicates the intended unit:

```php
$timeoutMilliseconds = 10 * 1000;
IPS_Sleep($timeoutMilliseconds);
```

Do not replace a clear domain literal with arbitrary arithmetic solely to evade
a checker. When the literal is the clearest representation, document and use
the integrity tool's number or line exclusion after verifying that the value is
not an ObjectID.

See Rule RS-001.4 in `standards/SYMCON_STANDARDS.md`.

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

### 6.1 Narrow Symcon lookup exception

IP-Symcon lookup functions may represent an expected "not found" result as both
a PHP warning and `false`. A narrowly scoped `@` suppression is permitted only
when all of the following conditions hold:

- the lookup is read-only;
- all lookup inputs have already been validated;
- the suppressed expression contains exactly one known Symcon lookup call;
- `false` is checked immediately and handled as an expected branch;
- exceptions are not caught, hidden or converted into a normal result;
- tests cover both the existing-object and missing-object branches;
- the reason for the suppression is documented at the helper or engineering
  decision level.

The approved current case is `IPS_GetObjectIDByIdent()` after validation of the
parent object and Ident. In the connected Symcon runtime, a missing Ident returns
`false`, emits one `E_WARNING` and does not throw an exception. Suppression avoids
logging an expected absence during idempotent creation.

Do not generalize this exception to mutations, multiple operations, arbitrary
warnings or calls whose failure modes have not been verified. A temporary error
handler that suppresses every warning from the same call is not inherently safer
unless it can distinguish the expected absence from unexpected failures without
depending on localized message text.

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
