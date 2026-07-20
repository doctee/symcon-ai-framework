# Wave 3 Functional Test and Capability Correction

**Gate:** CL-025 bounded functional sequence
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Final STATE off; retained brightness 100

## Functional Sequence

The first Wave 3 member passed the approved real-device sequence:

1. STATE on;
2. DIMMER 40;
3. DIMMER 100; and
4. STATE off.

Every phase produced exactly one new ControlLight command and completed with
authoritative local/target equality. The Z2M device normalized the requested
40 percent brightness to 39 percent; the runtime accepted the bounded device
normalization and synchronized the reported value. The 100 percent phase
reported exactly 100.

The final off phase retained brightness 100 locally and at the target, proving
the agreed `reported` semantics while STATE was false. Across the sequence,
execution and success counters remained equal. Errors, confirmation timeouts
and the bounded error history remained zero/empty. Compensation was not needed.

The final read-only regression verified all 29 wrapper identities and the safe
off state.

## STATE-Only Classification Correction

The second Wave 3 member was selected and migrated as STATE-only because its
legacy wrapper explicitly disabled DIMMER. A fresh target-tree inspection now
proves that the actual target device exposes an integer `brightness` variable
with the standard intensity profile, a valid action handler and retained value
50 while STATE is false.

The earlier STATE-only label is therefore a **wrapper configuration fact**, not
a physical device limitation. The lamp is dimmable, but its current v2 wrapper
does not expose or synchronize DIMMER.

Enabling that capability is not a test-only action. It changes the instance
contract, creates or adopts a local DIMMER variable and a target feedback event,
and immediately presents retained brightness 50 under `reported` semantics.
It therefore requires a small capability-correction package, presentation
allowlist, idempotency test and separate activation approval before a live
DIMMER sequence.
