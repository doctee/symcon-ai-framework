# CL-007 Spiegel atomic migration and functional test

## Outcome

CL-007 is active on ControlLight v2 with authoritative target feedback and
`reported` brightness semantics. Its existing target link, user presentation
links, local variables and three feedback events were preserved. Auto-Off now
uses the local CL-007 STATE facade for shutdown and local brightness only as an
additional activity signal.

STATE, brightness, color temperature and a real shortened Auto-Off timer expiry
all passed. Runtime diagnostics ended with zero errors and zero confirmation
timeouts.

## Atomic activation

The fresh read-only preflight confirmed an operational and available
Zigbee2MQTT target, matching local and target values, the immutable staged
runtime, three explicitly bound feedback events, three user presentation links
and a drift-free 29-wrapper baseline.

The private package bound the wrapper candidate, byte-exact rollback source,
deterministic Auto-Off delta and both expected source hashes. Wrapper and
Auto-Off source changes were installed as one rollback unit before either
configuration was reconciled.

The first activation attempt reached a correct source, topology and value
postflight but used obsolete diagnostic Idents in its verifier. It therefore
failed closed and restored both sources and their previous event contracts.
The v2-owned diagnostics were retained non-destructively for reuse. No device
command occurred.

The corrected retry reused those diagnostics, read both candidate sources back
exactly and reconciled both configurations twice. The existing three
ControlLight feedback event objects remained in place; only the STATE trigger
was upgraded to update semantics. Auto-Off replaced the two direct-target
events with owned facade STATE and brightness-activity events. All presentation
links, values and unrelated wrapper sources remained unchanged.

## Capability test

All commands used RequestAction on the local facade:

| Step | Requested | Authoritative result |
| --- | ---: | ---: |
| STATE on | `true` | local and target on |
| Brightness | 70% | local and target 69% |
| Color temperature | 2700 K | local and target 2702 K |
| STATE off | `false` | off, retained 69% and 2702 K |
| STATE on | `true` | on, retained 69% and 2702 K |
| Restore brightness | 100% | local and target 100% |

The device normalized both bounded numeric requests. Before the test, color
temperature had never produced target feedback and both local and target
variables contained the sentinel value zero. Zero Kelvin is not a physical
device setting and cannot be restored through the action contract. The first
authoritative 2702-K report is therefore recorded as initialization rather than
an unexplained restoration deviation.

## Auto-Off and closure

The actual production TimerEvent was shortened to five seconds only after
motion was inactive, CL-007 was on and the other managed lights were off. It
switched the local facade and target off while retaining brightness 100% and
color temperature 2702 K. No follow-up cycle was needed.

The inactive timer interval was restored to 1800 seconds, internal suppression
state was cleared, and the other managed lights remained unchanged. The seven
expected device commands produced no runtime error or confirmation timeout.
The complete 29-wrapper source regression remained consistent.
