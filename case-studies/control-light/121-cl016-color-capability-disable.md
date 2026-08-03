# CL-016 Color Capability Disable

**Date:** 2026-08-03
**Gate:** Explicitly approved instance-specific safety change
**Result:** PASSED — NO DEVICE COMMAND

## Scope

The change disables only CL-016's unreliable Z2M color capability. STATE,
reported brightness and Kelvin color temperature remain enabled with
authoritative feedback. The native Z2M color target, retained facade value and
bounded diagnostic history remain available for a later Zigbee2MQTT V6 review.

The Lowboard Alexa expert row retains power, brightness, color temperature,
name and stable row identity. Only `ColorOnlyControllerID` changed from the
existing facade variable to `0`.

## Activation

A fresh preflight verified the active wrapper and Alexa configuration hashes,
the unique Lowboard Alexa row, inactive alarm interlock, available target and
equal facade and target values. Byte-exact wrapper and Alexa rollback sources
were written and hash-verified on the private Symcon host before mutation.

The first atomic attempt detected that a wrapper source replaced and executed
inside the same PHP evaluation still reconciled with the previously compiled
source. Its postcondition failed, so the transaction restored both sources
byte-exactly without a device command. The successful retry separated source
replacement, Alexa property update and the two reconciliation runs into
independently read-back Symcon calls.

## Postflight

The independent postflight verified:

- the exact no-color wrapper hash;
- exactly one Alexa expert-row delta and exactly one changed field;
- the existing color facade hidden with action ID `0` while retaining its ID,
  name, position, profile and value;
- the existing color feedback event inactive while retaining its identity,
  trigger and last-run value;
- active STATE, brightness and color-temperature actions and events;
- equal facade and target values with target availability true;
- unchanged native Z2M color target;
- an unchanged device-command counter of 23; and
- both earlier color feedback timeouts retained in bounded diagnostics.

CL-016 is therefore fully tested for every capability that remains enabled.
Color stays disabled until the target-module contract is separately reviewed
and proven stable.
