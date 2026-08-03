# Echo Remote Alexa functional test

## Outcome

The two active reported-brightness ControlLight consumers corrected in the
preceding configuration gate passed a supervised Alexa integration test. Text
commands entered through the installed Echo remote integration exercised Alexa
device resolution, the Symcon Alexa endpoint, the local ControlLight facade,
the native target and authoritative feedback without relying on microphone
input.

For each wrapper, power on, brightness 40 percent and power off were
authoritatively confirmed. Both devices normalized the requested brightness to
39 percent and retained it while off, exactly as required by the `reported`
brightness contract. The group-backed wrapper additionally confirmed the
aggregate endpoint and both configured members.

All ControlLight errors and confirmation timeouts remained zero. Both initial
states and brightness values were restored, including the Auto-Off timer state
activated as a normal side effect of the group-backed light being switched on.

## Confirmation boundary

The remote module returned `true` for every submitted text command. Two later
brightness submissions produced no ControlLight command and no state change.
The successful return therefore proves only acceptance by the remote module;
it is not authoritative evidence that Alexa resolved and dispatched a
smart-home directive.

Every automated voice-assistant test must independently require:

- the expected ControlLight command-counter delta;
- bounded authoritative facade and target feedback;
- unchanged error and timeout counters; and
- exact initial-state restoration or an explicitly documented compensation
  exception.

This boundary prevents remote API acceptance, Alexa rate limiting or intent
non-dispatch from being reported as a successful device test.

## Restoration finding

The non-group wrapper initially had reported brightness zero while off. The
ControlLight facade intentionally maps a brightness request at or below zero
to `STATE=false`; it therefore cannot overwrite retained target brightness
with zero while already off. The test first proved this no-op contract, then
used one bounded native target compensation to restore the exact initial zero
value. Authoritative target feedback propagated that value back to the facade.

The native step is a test-compensation exception, not an operational consumer
path. Normal Alexa operation continues to use only the local ControlLight
facade.

## Test classification

This test proves Alexa text-intent and smart-home integration behavior. It does
not test wake-word recognition, microphone capture or speech-to-text accuracy.
Those acoustic concerns are outside the ControlLight consumer contract.
