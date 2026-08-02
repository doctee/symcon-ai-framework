# Root object name incident and guardrail

Date: 2026-08-02

## Impact

An early failed inactive-instance creation attempt unintentionally renamed the
IP-Symcon root category. The name matched the planned solar test instance. No
root children were moved or deleted, and no module configuration, timer,
weather request or device action was changed by the name repair.

## Cause

The failed creation path did not prove that the returned object identifier was
a positive, existing instance before passing it to an object presentation
mutator. In IP-Symcon, ObjectID `0` is the root category. False-like values or
missing array entries can become integer zero in weakly typed PHP caller code,
turning a presentation update into a root mutation.

The issue was not caused by duplicate Symcon ObjectIDs. It was a missing
mutation-target precondition.

## Repair

The private before snapshot captured the root identity, metadata and direct
child list. A guarded operation restored only the root name. Independent
readback confirmed the expected name, unchanged root identity and metadata,
and the exact same direct children. The weather and solar test instances
remained inactive and unchanged.

Private machine-readable before and after evidence is retained outside the
public repository.

## Prevention

SAEF now provides `SAEF_ValidateMutableObject()`. Object-creation helpers call
it immediately after every `IPS_Create*()` result and before the first parent,
Ident or presentation mutation. The guard requires:

- ObjectID greater than zero;
- current object existence; and
- the expected Symcon object type.

Regression tests explicitly reject ObjectID `0` and type mismatches. The same
rule is recorded in the repository agent instructions, Symcon standards and
live testing standard. ObjectID `0` remains valid only as an explicitly
intended parent or read target; changing the root itself requires a distinct,
explicit authorization.
