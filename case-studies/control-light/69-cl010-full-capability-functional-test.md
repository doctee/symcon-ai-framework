# CL-010 full-capability functional test

## Outcome

CL-010 passed its complete enabled-capability device test through the local
ControlLight facade. STATE, brightness, authoritative feedback, reported
brightness semantics and exact restoration were verified without an error or
confirmation timeout.

The current installation therefore has 12 active v2 wrappers, of which 11 have
complete enabled-capability device evidence. The other 17 wrappers remain
legacy.

## Preflight

The bounded read-only preflight confirmed:

- the exact activated wrapper source;
- an operational and available single-device target;
- matching local and native `STATE=true` and `DIMMER=100`;
- an empty error history and zero prior command, error and timeout counters.

One passive authoritative feedback execution had occurred since activation.
It increased the execution and success counters equally without a command or
error and was accepted as healthy regular runtime activity.

## Device sequence

The sequence used only `RequestAction()` on the local ControlLight variables:

| Step | Requested | Authoritative result |
| --- | ---: | ---: |
| STATE off | `false` | local and native `false` |
| STATE on | `true` | local and native `true` |
| DIMMER | 70% | local and native 69% |
| Restore DIMMER | 100% | local and native 100% |

The 70% request was normalized by the device to 69%. Local and native feedback
agreed exactly, and the one-percent difference is inside the established
bounded brightness tolerance.

While STATE was false, both local and native DIMMER remained at the retained
100% value. This directly verifies the configured `reported` brightness
contract rather than treating DIMMER as effective emitted light.

## Restoration and regression

The final state exactly matches the captured baseline:

- STATE is on locally and natively;
- DIMMER is 100% locally and natively;
- the target remains available;
- all 29 wrapper sources match the current expected baseline.

The final diagnostics contain four commands, no errors, no confirmation
timeouts and an empty bounded error history. All runtime executions, including
the action and authoritative feedback paths, completed successfully.

CL-010 is therefore fully device-tested. CL-008 remains excluded from this
single-device result because its Zigbee2MQTT group requires separate aggregate
and partial-member semantics.
