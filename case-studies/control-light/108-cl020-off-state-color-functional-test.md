# CL-020 Off-State Color Functional Test

**Date:** 2026-07-27
**Gate:** Explicitly approved real-device and Alexa regression
**Result:** PASSED

## Direct Facade Transition

The test started with authoritative STATE=false. One COLOR action on the local
facade:

- issued exactly one target command;
- changed the target to STATE=true;
- confirmed the target's normalized native hue and saturation within the
  transition-only tolerance;
- preserved facade and target brightness exactly; and
- completed without an error or confirmation timeout.

The transition confirmed in 1.698 seconds. Restoring the retained color while
on and then restoring STATE=false also passed.

## Alexa Transition

The installed Echo Remote text-command path then requested blue while the
target was off. Independent readback proved:

- exactly one additional ControlLight command;
- authoritative STATE=true feedback;
- the expected normalized native blue hue and saturation;
- unchanged facade and target brightness; and
- no runtime error or confirmation timeout.

This closes the consumer path that originally exposed the off-state transition.
Alexa command acceptance alone was not treated as confirmation; the result is
based on ControlLight diagnostics and authoritative target feedback.

## Restoration and Postflight

Both test phases restored the refreshed initial state exactly: STATE=false,
retained brightness, retained color temperature and retained native color.
Across the complete direct, Alexa and compensation sequence, all six intended
commands succeeded. Runtime errors and confirmation timeouts remained zero,
and the bounded error history remained empty.

The candidate source hash, all four active feedback events and their explicit
event-action bindings remained unchanged. Target, Alexa and scene
configurations also retained their preflight hashes. The scene was verified
structurally but not executed because it controls additional targets outside
this test's authorization.

## Current Classification

CL-020 is now fully device- and consumer-tested for its four enabled
capabilities, including the opt-in `target-turns-on` off-state color contract.
The current installation classification is 22 active v2 wrappers, 18 fully
device-tested wrappers and seven retained legacy wrappers.
