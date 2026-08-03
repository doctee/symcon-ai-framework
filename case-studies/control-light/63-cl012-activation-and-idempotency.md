# CL-012 Hue Cohort Activation and Idempotency

**Gate:** Second Hue cohort wrapper activation
**Result:** PASS — NO DEVICE COMMAND
**Date:** 2026-07-26
**Live impact:** One wrapper source replacement, diagnostics initialization and
one owned feedback-trigger correction

## Scope

After explicit approval, a fresh read-only delta preflight and the source-only
activation of retained wrapper `CL-012` were performed. The already activated
`CL-005` wrapper remained in service. The shared Hue Wall handler, physical
light commands and legacy-event cleanup remained outside the transaction.

Installation-specific IDs, exact rollback material and machine-readable
results remain in the private overlay.

## Delta Preflight

The mixed baseline containing the `CL-005` candidate and 28 expected retained
sources matched all 29 wrappers. The `CL-012` legacy source still matched its
rollback identity.

STATE, retained brightness and color temperature were identical on the local
facade and native target. The target reported available. Existing variable
actions, presentation, target and handler links, three feedback events and
three visualization links were complete. The expected reconciliation command
count was zero.

## Activation

The existing wrapper source was replaced and immediately read back
byte-for-byte. Two explicit wrapper executions then:

- reused the three existing facade variables and target link;
- preserved user-editable names, positions, visibility, profiles and
  presentation links;
- retained all local custom-action bindings and the handler link;
- initialized the standard Registry, Statistics and bounded ErrorRingBuffer
  diagnostics; and
- transitioned only the owned STATE feedback event from change-triggered to
  update-triggered while preserving the existing event object, target and
  explicit `Run Automation` action binding.

Brightness and color-temperature feedback remain change-triggered. The STATE
update trigger supports authoritative confirmation of repeated identical
Boolean reports.

## Idempotency and Regression

Both reconciliation runs succeeded. Diagnostics reported two executions, two
successes, zero commands, zero errors and zero confirmation timeouts. The
bounded error history remained empty.

All three local values remained exactly equal to their native targets. The
complete post-activation baseline—both Hue cohort wrapper candidates plus 27
retained sources—matched all 29 wrappers. `CL-005`, the shared legacy handler,
its active events and both inactive cleanup candidates remained unchanged.
Rollback was not required.

## Evidence Closure

The private record captures both preflight and postflight channels,
authorization scope, source identities, diagnostics, presentation and event
preservation, live values and the complete mixed-baseline regression.

The current fixture now classifies eleven wrappers as active v2 with explicit
`reported` semantics. Eight have completed their applicable real-device
matrices, and 18 wrappers retain their legacy contracts.

## Next Gate

Both Hue-cohort ControlLight facades are now active. The next separately
authorized mutation is the shared Hue Wall handler transition while physical
wall inputs are quiescent. That gate must first revalidate both facade
contracts, the six existing handler events and the exact handler source, then
replace and reconcile only the handler. Physical regression and deletion of
the two inactive cleanup candidates remain later gates.
