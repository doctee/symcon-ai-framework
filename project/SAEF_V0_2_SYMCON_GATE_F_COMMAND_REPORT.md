# SAEF v0.2 Symcon Gate F Command Report

**Gate:** Supervised MQTT power command and compensation
**Result:** PASS
**Date:** 2026-07-20
**Live impact:** Two supervised power test cycles with immediate restoration

## Scope

This Gate F sub-gate tested the existing MQTT power-command adapter for the
single supervised pilot entity. The test was restricted to power. Brightness
and color-temperature commands, cleanup and ControlLight selection were not
authorized by this sub-gate.

Object IDs, private topics, device names, locations and payload routing remain
outside this repository.

## Preflight

The fresh read-only preflight confirmed:

- the kernel and MQTT instances were ready;
- prepared and published configuration fingerprints matched;
- the Registry contained exactly one power-command binding;
- the command variable was a string variable with an action;
- its active update event was owned by the exporter and used the explicit Run
  Automation action binding;
- the target was a boolean variable with an action; and
- diagnostics and bounded error history matched the preceding Gate F state.

The planned transaction selected the opposite power state and retained the
original state as its immediate compensation target.

## Rejected Injection Attempt

An initial attempt to inject the command with `SetValue()` was rejected by
IP-Symcon because the MQTT device value is read-only through that write path.
No event, MQTT publication or device action occurred. Target values,
diagnostics and all counters remained unchanged.

This was a useful fail-closed result: an inbound MQTT adapter must not be
treated as generic script-owned state.

## Command And Compensation

The corrected test used `RequestAction()` on the MQTT Device value, which is
the documented IP-Symcon mechanism for publishing through a device-defined
topic. The intended power change was observed and confirmed. The original
power state was then restored through the same path without requiring the
direct-action fallback.

The final read-only stability check confirmed:

- the original power state was restored;
- brightness and color-temperature values were preserved;
- the ErrorRingBuffer remained unchanged;
- the failure counter did not advance;
- the kernel remained ready; and
- no counters continued changing after the transaction.

## Duplicate Dispatch Finding

The transaction submitted two MQTT commands: one test command and one
compensation command. Runtime Diagnostics recorded four confirmed command
dispatches. Execution, success and publication counters advanced consistently
with those duplicate dispatches and the associated state events.

The observed shape is consistent with two updates per locally published
message: the MQTT Device action updates the local adapter path and the broker
then returns the same message to the subscribed adapter. This is an inference
from the bounded counter evidence, not proof that an external MQTT producer
would duplicate delivery.

Changing the command event from update to change is not an acceptable immediate
fix. The update trigger is deliberate because repeated identical external MQTT
commands must still be processed.

## Independent Producer Retest

The corrected retest used a temporary isolated MQTT client process as an
independent producer. It reused the existing authenticated broker connection
contract without creating Symcon objects or storing credentials, endpoints or
topics in the repository.

The producer sent exactly one non-retained power command. Independent Symcon
observation confirmed:

- exactly one confirmed command dispatch;
- the expected power-state transition;
- one associated state-event execution;
- unchanged brightness and color-temperature values;
- no failure-counter or ErrorRingBuffer change; and
- a ready kernel throughout the bounded observation.

The producer then sent exactly one non-retained compensation command. It again
produced exactly one confirmed command dispatch and restored the original power
state. A delayed read-only snapshot found the state, diagnostics and counters
stable with no continued activity.

This separates the two behaviors conclusively: the duplicate dispatches belong
to publishing through the same subscribed MQTT Device used as the local test
producer. They were not reproduced by an independent MQTT client.

## Gate Decision

The power-command sub-gate is **PASS**. The external producer proved the
one-message-to-one-dispatch contract, bounded authoritative confirmation and
successful compensation without unrelated state changes.

The local loopback method must not be reused for inbound MQTT command tests.
Future capability checks must use an independent producer and retain the same
fresh-baseline, exact-dispatch and immediate-compensation requirements.

Brightness and color-temperature commands remain separate physical-device
gates. This result also does not select the staged final ControlLight fileset.

## Related Artifacts

- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `project/SAEF_V0_2_SYMCON_GATE_F_MQTT_REPORT.md`
- `case-studies/mqtt-discovery-exporter/11-command-state-dispatch-report.md`
- `case-studies/mqtt-discovery-exporter/14-supervised-integration-and-rollback-plan.md`
