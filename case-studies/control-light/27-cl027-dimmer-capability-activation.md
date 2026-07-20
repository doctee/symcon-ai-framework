# CL-027 DIMMER Capability Activation

**Gate:** Correct previously disabled DIMMER capability
**Result:** PASS
**Date:** 2026-07-19
**Live state:** STATE off; reported brightness 50

## Scope

After read-only target inspection proved an actionable Z2M `brightness`
variable, the current STATE-only v2 wrapper was corrected under explicit user
authorization. The candidate changed only its version and removed the explicit
`identDim=''` override. Temperature, color and external triggers remained
disabled.

The package retained the exact current STATE-only v2 source as rollback. The
rollback contract allowed deletion only of the local DIMMER variable and
DIMMER feedback event after preflight proved both Idents absent.

## Preflight

The immediate read-only gate passed:

- all 29 wrapper identities matched;
- the current wrapper matched its exact rollback source;
- no local DIMMER or DIMMER event existed;
- target brightness was integer, actionable and retained value 50;
- local and target STATE were false;
- existing ownership and presentation remained exact; and
- execution/success diagnostics matched with zero commands, errors and
  confirmation timeouts.

## Activation and Idempotency

Only the selected wrapper source was changed. Direct readback matched the
candidate hash, and two configuration runs completed successfully without a
device command.

The runtime created exactly:

- one local integer `DIMMER` variable with the standard intensity profile,
  default presentation position and the owning wrapper as custom action; and
- one owned `EV_TARGET_DIM` OnChange event targeting the existing brightness
  variable with explicit Run Automation action binding.

The second run reused both objects. Existing STATE presentation and action
contracts were preserved. Under `reported` semantics, local DIMMER immediately
synchronized to retained target brightness 50 while STATE remained false.

Diagnostics ended with equal execution/success counters and zero commands,
errors and timeouts. Registry metadata records the new deterministic
configuration fingerprint. Rollback was not required.

## Regression and Next Gate

The final read-only regression passed all 29 wrapper identities. The lamp ended
off with local/target brightness 50/50.

DIMMER is now live and controllable through ControlLight. A real-device
sequence such as STATE on, DIMMER 40, DIMMER 100 and STATE off is a separate
functional gate; it was not implicitly executed during capability activation.
