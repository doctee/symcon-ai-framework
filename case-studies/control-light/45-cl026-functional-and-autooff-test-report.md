# CL-026 Functional and Auto-Off Test Report

**Gate:** Complete enabled capabilities plus real Auto-Off timer integration
**Result:** PASS — INITIAL STATE RESTORED
**Date:** 2026-07-20
**Live impact:** Six intended device actions; no compensation action required

## Preflight

The exact activated v2 source and modernized Auto-Off source remained
unchanged. STATE was false locally and at the target, retained DIMMER was 28,
color temperature was 2604 K, motion and alarm were inactive, and the regular
Auto-Off timer was inactive with its original ten-minute interval.

The ControlLight diagnostic baseline contained five successful executions,
zero commands, zero errors, zero confirmation timeouts and an empty
ErrorRingBuffer.

## Device Sequence

The authorized sequence completed:

1. STATE on, with authoritative local/target feedback and the regular Auto-Off
   timer armed;
2. DIMMER 40, normalized by the device to 39 and confirmed on both sides;
3. color temperature 3000 K, normalized to 3003 K and confirmed;
4. retained DIMMER restored exactly to 28 through the known adjacent command
   grid value;
5. color temperature restored exactly to 2604 K; and
6. a real three-second Auto-Off TimerEvent requested only STATE false and
   confirmed local and target shutdown.

Every phase added exactly one ControlLight command. DIMMER activity while STATE
was true retained the Auto-Off timer. Temperature changes did not alter STATE.
Timer expiry preserved retained DIMMER and color temperature.

## Baseline Restoration

The real TimerEvent correctly left the timer inactive, but its cyclic interval
metadata still reflected the temporary three-second test interval. The test
restored the regular 600-second interval and immediately returned the timer to
inactive without executing another action.

Auto-Off's bounded suppression map also retained the completed STATE command
entry after its deadline. Read-only inspection proved that the deadline had
expired and no follow-up cycle was active. The test then restored the original
empty script-owned runtime state. This cleanup changed no device or
ControlLight value.

## Verification Notes

One provisional postflight matrix contained a manually copied hash typo for an
unrelated wrapper. Direct read-back matched the known baseline; the final
regression therefore used a deterministic aggregate over the complete sorted
29-wrapper matrix. A separate strict assertion initially treated the expired
suppression entry as active state; it was reclassified after comparing its
deadline with current time.

Neither stop represented a ControlLight or Auto-Off runtime failure.

## Final Regression

The final postflight proved:

- six intended commands and no compensation command;
- 27 executions and 27 successes;
- zero errors and zero confirmation timeouts;
- unchanged empty ErrorRingBuffer;
- exact STATE false, DIMMER 28 and color temperature 2604 K locally and at the
  target;
- inactive Auto-Off timer with the original 600-second interval;
- empty suppression map and zero follow-up cycles;
- unchanged Auto-Off source and exactly one script consumer;
- all three presentation links and archive settings preserved; and
- all 29 wrapper sources matching the seven-v2/22-legacy baseline.

## Gate Decision

`CL-026` is fully activated and device-tested for every enabled capability.
The live Auto-Off integration is confirmed against v2 `reported` semantics.
No further `CL-026` migration or functional-test gate remains open.
