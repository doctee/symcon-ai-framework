# CL-003 Color Test and Temperature Stop

**Date:** 2026-08-03
**Gate:** Explicitly approved direct color and conditional color-temperature test
**Result:** PARTIAL PASS — COLOR RESTORE TIMED OUT, TEMPERATURE NOT RUN

## Fresh Preconditions

The live wrapper and shutdown consumer retained their expected source hashes.
The inverse alarm interlock was inactive, the target reported available, all
four facade values equalled their authoritative targets and runtime diagnostics
contained no preceding error or confirmation timeout. The physical power
switch remained on throughout the test.

## Bounded Color Sequence

The forward red request passed with exact authoritative color feedback and no
runtime failure. The Z2M target simultaneously changed reported brightness
from 100 to 28, reproducing the known coupling between its color projection and
brightness.

The single request to restore the original XY-derived RGB value did not produce
the requested authoritative projection. It settled at a slightly different
reported color and brightness 83, then produced one bounded `color` /
`feedback_timeout`. No color retry was issued. The already proven brightness
facade restored brightness to 100 successfully.

## Fail-Closed Result

The conditional Kelvin test was not started. Continuing after the failed color
restoration would have mixed two unresolved capability transitions and weakened
the diagnostic evidence.

The final state is safe and internally consistent: the lamp remains on at
brightness 100, facade and authoritative target agree, availability remains
true and the target remains in XY mode. The exact initial RGB projection was
not restored; the single color timeout remains visible in bounded diagnostics.

CL-003 therefore remains proven only for STATE and brightness. Color is now a
reproduced unreliable Z2M authoritative-feedback contract, while Kelvin and the
physical hard-off/hard-on observation remain separate gates. Disabling the
ControlLight color capability, as already done for comparable Z2M instances,
requires a separate command-free change approval.
