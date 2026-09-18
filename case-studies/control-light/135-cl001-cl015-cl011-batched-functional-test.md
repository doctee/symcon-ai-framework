# CL-001, CL-015 And CL-011 Batched Functional Test

**Date:** 2026-09-18

**Scope:** Ordered direct capability, voice-consumer and shutdown-consumer tests

**Result:** PARTIAL PASS WITH TWO DEFERRED FOLLOW-UPS

## Test Boundary

The three presence-bound tests ran as one ordered block. Device actions were
completed before this report, fixture reconciliation or publication work. No
source, configuration, object, event, fileset or service state changed during
the block.

Every request used the existing ControlLight facade. Group results required
authoritative agreement from all configured members within the shared bounded
deadline. Exact live identities, timestamps and diagnostic values remain in
private evidence.

## CL-001 Result

The direct STATE, brightness and color-temperature sequence passed. The target
normalized requested brightness 40 to 39 and requested 3000 K to 3003 K; both
values were projected authoritatively to the facade. Power was restored to off
and retained brightness was restored to its initial value.

Voice power and brightness also passed. Two accepted color-temperature text
commands produced no downstream ControlLight command. Direct temperature
control is proven, so this remains a voice-consumer dispatch finding rather
than a device or ControlLight failure.

CL-001 is now fully device-tested for every enabled capability.

## CL-015 Result

The direct STATE, brightness and color-temperature sequence passed for all
three members. Voice power and brightness also passed with member-confirmed
feedback. The same accepted-without-dispatch color-temperature behavior seen
for CL-001 was reproduced.

The physical external inputs were not exercised because their hardware control
was confirmed defective during the supervised test. This is not classified as
a ControlLight failure. Both directions require a new presence-bound test after
the hardware control is replaced. CL-015 therefore remains outside the fully
device-tested count despite its successful direct capability matrix.

## CL-011 Result

The direct STATE, brightness and color-temperature sequence passed for all
three configured members. Color remains deliberately disabled and was not part
of the enabled-capability matrix.

The first shutdown consumer correctly did not dispatch in the current operating
mode because its configured condition was false. The separately authorized
global shutdown consumer then switched CL-011 off through its facade. All three
members confirmed off, and the facade, group and member state remained stable
during the bounded postflight.

One unrelated legacy shutdown branch remained active after the global consumer.
That observation is outside CL-011 and requires a separate diagnosis; it does
not alter the successful CL-011 facade hand-off.

CL-011 is now fully device-tested for every enabled capability and its global
shutdown dependency.

## Diagnostics And Open Follow-Ups

No tested ControlLight instance recorded a new error or confirmation timeout.
Existing historical group-staleness entries remained unchanged.

The remaining follow-ups are deliberately separate:

- repeat both CL-015 physical external-input directions after hardware
  replacement;
- diagnose why accepted voice color-temperature text commands for CL-001 and
  CL-015 did not dispatch a downstream smart-home command; and
- diagnose the unrelated legacy branch that remained active after the global
  shutdown consumer before proposing any correction.

The current installation baseline is 26 active v2 wrappers, 24 fully
device-tested wrappers and three retained legacy wrappers.
