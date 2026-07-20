# Wait Helper Selection and Regression

**Gate:** Scoped CL-023 fileset selection and non-commanding synchronization
**Result:** PASS
**Date:** 2026-07-19
**Device test:** Not performed

## Scope

After explicit approval, only sanitized candidate CL-023 was changed to load
the corrected hash-addressed ControlLight fileset. The wrapper retained the
three-second confirmation timeout, 100-millisecond poll interval,
authoritative feedback, `reported` brightness semantics and all existing
capability settings.

The preceding wrapper source was read back directly and matched the retained
rollback artifact before mutation. The replacement source was syntax-checked
offline and read back byte-exactly after installation.

## Activation

An immediate read-only gate reconfirmed the ready kernel, rollback wrapper,
candidate fileset and helper identities, event actions, target actions and safe
authoritative values. The wrapper was then replaced and executed twice with
sender `Execute`.

Both runs were non-commanding because local and target values already agreed:

| Metric | Before | After | Delta |
| --- | ---: | ---: | ---: |
| Executions | 28 | 30 | +2 |
| Successes | 25 | 27 | +2 |
| Commands | 4 | 4 | 0 |
| Errors | 3 | 3 | 0 |
| Confirmation timeouts | 3 | 3 | 0 |

The three retained timeout entries remain available as historical diagnostic
evidence. No rollback was required.

## Regression Readback

The final bounded read-only probe confirmed:

- exactly CL-023 references the corrected fileset;
- all 29 expected wrapper identities match, including the new CL-023 source;
- the shared legacy core is unchanged;
- owned diagnostic and event children are unchanged;
- STATE and DIMMER custom actions remain bound to CL-023;
- state feedback uses update and brightness feedback uses change, both with
  explicit script event action binding;
- Registry reports the new wrapper version and `reported` semantics;
- STATE remains `false` locally and at the target; and
- DIMMER remains `100` locally and at the target.

The connector reported no transport or PHP execution error and no truncation.

## Gate Decision

The corrected-helper selection and non-commanding idempotency regression are
**PASS**. This proves deployment integrity and zero-command reconciliation; it
does not exercise the corrected same-second feedback path.

The next gate is a separately approved real-device sequence on CL-023. It
should begin with STATE-on, verify authoritative confirmation and stop with
compensation on the first discrepancy before any DIMMER command continues.

## Subsequent Finding

The separately approved device test demonstrated that selecting the corrected
ControlLight directory did not select its guarded wait function. The shared PHP
context had already loaded the older copy from the active MQTT Discovery
Exporter fileset. Report 16 records the stopped test and rollback. This report
therefore remains valid only for filesystem selection and non-commanding
reconciliation, not for effective shared-helper selection.
