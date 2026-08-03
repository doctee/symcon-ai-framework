# CL-030 Functional Test and Race Hardening

## Outcome

CL-030 passed its complete presence-bound functional test. A direct facade-off
request and the critical manual-on/immediate-Alexa-off sequence each produced
exactly one physical supply pulse, confirmed authoritative off feedback and
restored permanent supply. Adapter and ControlLight diagnostics remained free
of errors and confirmation timeouts.

The current installation baseline is 24 v2 wrappers, 19 fully device-tested
wrappers and five retained legacy contracts across 29 tracked instances.

## Direct Pulse Test

The lamp was switched on manually. Measured power, the adapter-owned internal
state and the ControlLight facade all changed to on while the supply relay
remained enabled.

One direct facade-off action then:

1. issued one adapter pulse;
2. confirmed measured power below the configured on threshold;
3. confirmed restored supply;
4. synchronized both state levels to off;
5. completed without retry, error or timeout.

## Findings During the Immediate-Off Test

The first Alexa race attempt exposed two independent configuration findings.

First, CL-030 referenced the alarm contract of the other site. The active
wrapper was corrected to use the established local alarm variable with direct
semantics: false means inactive and true means active. The corrected wrapper
was read back by hash and reconciled without a device command.

Second, device-side change telemetry was disabled. With periodic telemetry
alone, Alexa's off request arrived before authoritative manual-on feedback and
the original 1.5-second observation window expired before the next power
sample. The adapter therefore made the safe but operationally incomplete
idempotent-off decision and issued no pulse.

The device was configured to publish an additional power message after a
five-watt absolute change while retaining its ten-second periodic telemetry.
This follows the Tasmota `PowerDelta` contract; periodic telemetry remains
necessary because power-delta reporting does not cover every transition to
zero. The adapter observation window was independently increased to three
seconds.

## Performance Contract

The longer window applies only when an off request arrives while authoritative
power still reports off:

- an already observed on state pulses immediately;
- a delayed on sample ends observation immediately;
- polling sleeps for 100 milliseconds and does not busy-wait;
- a genuinely redundant off request is bounded to three seconds;
- the adapter semaphore prevents concurrent physical pulses.

The offline regression reproduces an on sample arriving after 2.1 seconds and
proves one pulse, confirmed power-off feedback and restored relay state inside
the bounded window.

## Successful Alexa Race

After both corrections, the lamp was switched on manually and Alexa was asked
to switch it off immediately. The postflight proved:

- one additional physical pulse;
- restored supply;
- final periodic measured power at the original off baseline;
- internal and facade state both off;
- zero adapter and ControlLight errors;
- zero confirmation timeouts.

Alexa produced two logical off invocations during the successful sequence.
The first issued the required physical pulse. The second reached the
already-off state, was contained by the adapter's idempotency path and issued
no second pulse. This is the required safety behavior for assistant retries or
duplicate delivery.

Private timestamps, ObjectIDs, hashes, counter deltas and rollback sources are
retained in the local machine-readable test evidence.

## Source Presentation

After functional closure, the visible ControlLight wrapper was expanded from
its compact deployment representation into the maintained multiline source
form. Runtime path construction, input mapping and configuration are now
readable directly in the Symcon console. The deterministic configuration hash,
light state, relay state and all command counters remained unchanged during
this formatting-only reconciliation.

A subsequent installation-wide source audit verified the same explicit
`$runtimePath`, `$configuration` and `$sourceIPS` structure in all 24 active v2
wrappers. CL-030 alone still used an inline runtime call and was aligned with
that established layout. The other 23 wrappers and all five retained legacy
scripts required no write. The second formatting-only reconciliation again
preserved the configuration hash and every device and diagnostic counter.
