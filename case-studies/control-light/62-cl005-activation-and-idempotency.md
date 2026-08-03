# CL-005 Hue Cohort Activation and Idempotency

**Gate:** First Hue cohort wrapper activation
**Result:** PASS — NO DEVICE COMMAND
**Date:** 2026-07-26
**Live impact:** One wrapper source replacement, diagnostics initialization and
one owned feedback-trigger correction

## Scope

After explicit approval, only the retained `CL-005` wrapper was migrated to the
staged immutable ControlLight v2 fileset. `CL-012`, the shared Hue Wall handler,
physical light commands and legacy-event cleanup remained outside the
transaction.

Installation-specific IDs, exact source backups and machine-readable results
remain in the private overlay.

## Activation

The transaction rechecked the exact legacy and candidate identities, replaced
the existing wrapper source and immediately read it back. The activated source
matched the reviewed candidate byte-for-byte, so rollback was not required.

Two explicit wrapper executions then reconciled the existing facade. They:

- reused the existing STATE and DIMMER variables and target link;
- preserved user-editable names, positions, visibility, profiles and
  presentation links;
- retained both local custom-action bindings;
- initialized the standard Registry, Statistics and bounded ErrorRingBuffer
  diagnostics below the wrapper; and
- changed the owned STATE feedback event from change-triggered to
  update-triggered while retaining the existing event object, target and
  explicit `Run Automation` action binding.

The STATE update trigger is intentional. It allows the confirmation path to
observe repeated authoritative feedback even when the Boolean value is
identical. The DIMMER event remains change-triggered.

## Idempotency and Regression

Both reconciliations succeeded. Diagnostics reported two executions, two
successes, zero commands, zero errors and zero confirmation timeouts. The error
history remained empty.

Local and native STATE remained off, retained brightness remained 100 percent
on both sides, and the target reported available. No target action was issued.

The mixed post-activation baseline—this one candidate plus the other 28
pre-existing sources—matched all 29 wrappers. `CL-012`, the shared Hue Wall
handler, its four active events and both inactive cleanup candidates remained
unchanged.

## Evidence Closure

The private record captures authorization scope, source and rollback hashes,
separate transport and PHP-execution results, diagnostic counters, topology
preservation and the complete wrapper regression. This sanitized report
records the engineering outcome without installation-specific identifiers.

The current fixture now classifies ten wrappers as active v2 with explicit
`reported` brightness semantics, eight of which have completed their applicable
real-device matrices. Nineteen wrappers remain on their retained legacy
contracts.

## Next Gate

`CL-005` is activated but not yet physically tested within the Hue Wall cohort.
The next separately approved mutation is the fresh delta check and source
activation of `CL-012`. The shared handler transition and the complete physical
matrix remain later, independent gates.
