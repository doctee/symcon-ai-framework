# CL-015 Install-Test Input Closure

**Date:** 2026-09-18

**Scope:** Existing physical on/off inputs of the member-confirmed Z2M group

**Result:** PASS — FULL DEVICE TEST MATRIX CLOSED

## Finding And Correction

The physical control remained usable despite its defective normal short-press
signal. Its diagnostic install-test signal changed reliably. The two existing
ControlLight input events were therefore retargeted from the unavailable
short-press source to the corresponding install-test source.

The event identities, update-trigger semantics, on/off direction, owning
wrapper and member-confirmed group contract were preserved. No new event or
parallel owner was introduced.

## Controlled Activation

The owner source was backed up before activation. The configuration was applied
through the existing idempotent wrapper and reconciled without a device command.
Readback confirmed exactly two active external events with their original
identities and update triggers.

## Functional Result

The supervised physical on and off actions each produced exactly one
ControlLight command. All three configured members confirmed the requested
state within the shared bounded deadline. The facade and group feedback agreed,
and the final state was restored to off.

No error or confirmation-timeout counter increased. CL-015 is consequently
fully device-tested for all enabled capabilities and both physical inputs.

Exact live identities and timestamps remain in private evidence.
