# Wave 4 Functional Test Report

**Gate:** Complete enabled capabilities for CL-004 and CL-017
**Result:** PASS — BOTH INITIAL STATES RESTORED
**Date:** 2026-07-20
**Live impact:** Twelve bounded device actions; no compensation required

## Common preflight

Both wrappers retained their exact v2 source, actionable local and target
variables, authoritative feedback equality and healthy diagnostics. Their alarm
contracts permitted user control. The complete 29-wrapper source regression
passed before the first device action.

CL-004 started at STATE true, DIMMER 100 and color temperature 2702. CL-017
started at STATE false with retained DIMMER 100 and color temperature 2702.
Both error histories were empty and remained immutable baselines.

## CL-004 sequence

The first member completed:

1. STATE off;
2. STATE on;
3. DIMMER 40;
4. DIMMER 100;
5. color temperature 3000 K; and
6. restore color temperature 2702 K.

Every phase added exactly one ControlLight command and completed with local and
target feedback equality. The device normalized DIMMER 40 to 39 and 3000 K to
3003 K, both within the configured bounded tolerances. The final state exactly
matched the initial STATE true, DIMMER 100 and 2702 K.

CL-004 finished with six command additions, no error or timeout delta and no
change to its empty ErrorRingBuffer.

## CL-017 sequence

Only after the complete CL-004 result passed, the second member completed:

1. STATE on;
2. DIMMER 40;
3. DIMMER 100;
4. color temperature 3000 K;
5. restore color temperature 2702 K; and
6. restore STATE off.

Again, every phase added exactly one command with authoritative local/target
equality. The same device grid normalized DIMMER 40 to 39 and 3000 K to 3003 K
within tolerance. Reported semantics retained DIMMER 100 and 2702 K after the
final STATE-off action.

The final CL-017 state exactly matched its initial STATE false, DIMMER 100 and
2702 K. Its six commands added no error or timeout, and its ErrorRingBuffer
remained empty.

## Final regression

The combined postflight proved:

- twelve intended device actions and no compensation action;
- execution and success counters equal for both wrappers;
- zero errors and confirmation timeouts;
- unchanged error-history hashes;
- exact restoration of all six local/target values;
- all three CL-017 presentation links unchanged in object, target and metadata;
- both candidate source identities exact; and
- all 29 wrapper sources matching the six-v2/23-legacy regression baseline.

## Gate decision

Both Wave 4 members are fully functionally tested for every enabled capability.
Together with the four earlier active instances, all six active v2 ControlLight
wrappers now have bounded real-device evidence.

Wave 4 requires no further ControlLight gate. The broader Auto-Off modernization
could therefore begin as a separate task without leaving an unfinished
ControlLight functional test in flight. That task and the complete functional
verification of its `CL-026` dependency are recorded in
`42-autooff-modernization-and-cl026-contract-verification.md`.
