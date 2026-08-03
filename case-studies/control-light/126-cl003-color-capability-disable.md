# CL-003 Color Capability Disable

**Date:** 2026-08-03
**Gate:** Explicitly approved instance-specific command-free safety change
**Result:** PASSED — NO DEVICE COMMAND

## Scope

Only CL-003's unreliable Z2M color capability was disabled. STATE, reported
brightness and Kelvin color temperature remain enabled with authoritative
feedback. The native Z2M color target, its current value, the native state
observer and the retained bounded color-timeout diagnosis remain untouched.

CL-003 has no Alexa or SceneControl binding. Its shutdown consumer references
only the STATE facade and therefore required no change.

## Activation

A fresh preflight verified the exact active-wrapper and consumer hashes, the
activated shared runtime, inactive inverse alarm interlock, active target
instance, target availability and equal facade/target values. The exact active
wrapper source was already present as the private, hash-verified rollback.

The candidate changed only the version marker and selected an empty color Ident.
Source readback confirmed the expected candidate hash and an unbroken script
before execution. Two separate reconciliation runs then completed idempotently.
The device-command counter remained exactly 7.

## Postflight

The independent postflight verified:

- active and unchanged STATE, brightness and color-temperature facades/events;
- the existing color facade hidden and disarmed while retaining identity,
  presentation, profile and value;
- the existing color feedback event inactive while retaining identity, trigger
  and last-run metadata;
- unchanged native color target and native STATE observer;
- unchanged shutdown consumer source;
- equal facade and target values for every enabled capability;
- STATE=true, brightness=100 and target availability=true; and
- the preceding bounded color timeout retained without a new error or timeout.

CL-003 remains not fully device-tested because Kelvin and the physical
hard-off/hard-on contract are still untested. Color is no longer part of its
active ControlLight contract and stays available natively for a later Z2M
module review.
