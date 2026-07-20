# ControlLight Wave 2 Functional Test Report

**Gate:** Controlled RequestAction and authoritative feedback test
**Result:** BLOCKED — STATE feedback exceeded confirmation window
**Date:** 2026-07-19
**Live state:** v2 wrapper retained; device restored to its initial state

## Approved Sequence

The approved test intended to switch STATE on, set DIMMER to 40, restore DIMMER
to 100 and switch STATE off. Every step required equal local and target
feedback before the next command.

The baseline was:

- STATE false locally and at the target;
- retained DIMMER 100 locally and at the target;
- zero commands, errors and confirmation timeouts;
- empty ErrorRingBuffer;
- exact wrapper, shared-core and all-instance source identities.

The configured alarm was active. The test deliberately used the local
`RequestAction()` path with sender `Action`, which the runtime permits for
programmatic actions. It did not claim to exercise the alarm-blocked WebFront
path.

## Finding

The first STATE-on request issued one target command. Authoritative STATE
feedback did not arrive within the configured three-second confirmation window.
ControlLight correctly:

- incremented Commands;
- incremented Errors and Confirmation Timeouts;
- appended a bounded RuntimeException entry to the ErrorRingBuffer;
- avoided reporting the command as a success.

The test stopped immediately; no DIMMER command was issued.

## Compensation

The compensation read showed that STATE had subsequently become true. This
proves the device feedback arrived after the timeout rather than the command
being rejected. A second authorized STATE command restored false and was
confirmed successfully. DIMMER was already 100 and required no command.

Final live state:

| Item | Value |
| --- | ---: |
| STATE local / target | false / false |
| DIMMER local / target | 100 / 100 |
| Commands added | 2 |
| Errors | 1 |
| Confirmation timeouts | 1 |
| Error history entries | 1 |

The second command is the compensating switch-off. Wrapper, core, all 29 source
identities, local actions and event contracts remain exact. No diagnostic
history was cleared, preserving the failure evidence.

Archive Control logging is disabled for the four involved local and target
variables. The exact sub-second feedback arrival cannot therefore be
reconstructed after the fact.

## Gate Decision

The Wave 2 functional acceptance gate is **BLOCKED**. No further instance should
be migrated until this instance passes the complete sequence.

The evidence supports a narrow candidate change from a three-second to a
five-second confirmation timeout while retaining the 100 ms polling interval.
Five seconds remains bounded and covers the observed just-over-three-second
STATE response with margin. That configuration change and a repeated real
device test require a new explicit approval and their own rollback boundary.

## Follow-up

The separately approved five-second experiment reproduced the timeout and
therefore disproved the latency hypothesis. Report 12 identifies a same-second
metadata race in the wait helper; this recommendation is retained here only as
the historical decision made from the evidence then available.
