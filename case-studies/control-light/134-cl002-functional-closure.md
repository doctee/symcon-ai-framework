# CL-002 Functional Closure

**Date:** 2026-09-18

**Scope:** External on/off inputs and direct STATE/brightness facade

**Result:** PASS

## Test Boundary

CL-002 was already active on ControlLight v2 with two preserved external
short-press inputs, authoritative STATE and brightness feedback, `reported`
brightness semantics and an inverse alarm contract. Its remaining functional
gate required the installation owner to be present.

The authorized test used the existing physical inputs and local ControlLight
facade only. It did not change source, configuration, object identities, event
topology, presentation, runtime fileset or service state. The inverse alarm
input was in its permitted state; active-alarm rejection was not part of this
functional gate.

## Physical Input Result

The physical on input produced exactly one device-command increment and
authoritative STATE=true feedback. The physical off input then produced exactly
one further command increment and authoritative STATE=false feedback. Both
existing trigger identities and their OnUpdate contracts remained active.

## Direct Facade Matrix

The complete enabled-capability sequence passed:

| Request | Authoritative result | Classification |
| --- | --- | --- |
| STATE=true | STATE=true | exact |
| DIMMER=40 | DIMMER=39 | accepted device normalization |
| STATE=false | STATE=false | exact |
| DIMMER=0 while already off | STATE=false, DIMMER=39 | idempotent off |

The final DIMMER request is deliberately not a request to erase retained
device brightness. ControlLight maps zero brightness to STATE=false. Because
the lamp was already off, the request was idempotent and did not issue another
target command. Under `reported` semantics the facade continues to expose the
authoritative retained brightness of 39 while STATE is false.

## Diagnostics And Final State

Across both physical inputs and the direct facade matrix:

- the device-command counter increased by five: two physical-input commands
  and three effective direct commands;
- executions and successes each increased by 17;
- error and confirmation-timeout counters remained at zero; and
- the bounded error history remained empty.

The physical state was restored to off. Brightness settled from the initial
zero to the authoritative device-normalized value 39 because brightness was
explicitly exercised. Forcing it back to zero would contradict the selected
`reported` contract and is therefore neither required nor desirable.

## Result

CL-002 is fully device-tested for every enabled capability and for both
external on/off inputs. The current ControlLight baseline advances to 26 active
v2 wrappers, 22 fully device-tested wrappers and three retained legacy
wrappers.

Exact live identities, counter transitions and timestamps remain in private
evidence. This report contains no installation ObjectIDs, paths or MQTT topics.
