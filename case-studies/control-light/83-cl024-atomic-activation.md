# CL-024 atomic activation

## Outcome

CL-024 is active on ControlLight v2 with authoritative target feedback and
`reported` brightness semantics. Its existing Alexa device was moved in place
from brightness-only to separate facade power and brightness controllers.
Alexa identity, user-facing name, two presentation links and both feedback
event identities were preserved.

Two completed reconciliation runs produced two successes, zero commands, zero
errors and zero confirmation timeouts. STATE remained false locally and at the
target. Local brightness initialized from the legacy placeholder zero to the
current retained target brightness 100 without changing the target or issuing
a device command.

The current installation baseline is therefore fifteen active v2 wrappers and
14 retained legacy wrappers. A real-device and Echo Remote test remains a
separate gate.

## Protected activation history

The first activation attempt used `IPS_RunScript()` inside the transaction
coordinator. As already observed in an earlier migration, that call did not
wait for the target wrapper in this installation context. The verifier checked
for diagnostics before the asynchronous target execution completed and
restored wrapper, Alexa configuration, event contract and facade value exactly.
No device command occurred.

The successful attempt staged the same hash-locked wrapper and Alexa delta,
then used the direct waiting script-execution channel for both reconciliation
runs.

## Timestamp verifier correction

The first postflight of the successful attempt rejected target
`VariableUpdated` timestamps because it compared them with an older
package-time snapshot. Target values were unchanged, local and target values
matched, the command counter and last-feedback command timestamp remained zero,
and both runtime executions succeeded without diagnostics.

This is the same over-strict assertion class already documented for an earlier
ControlLight activation. Passive target feedback may advance timestamps without
a command or value change. The corrected acceptance contract therefore uses:

- authoritative local/target equality;
- zero ControlLight command evidence;
- zero errors and confirmation timeouts;
- stable target domain values; and
- complete topology, consumer and wrapper regression.

The healthy activation was not rolled back merely because passive feedback
timestamps advanced.

## Consumer and topology closure

The final independent readback confirmed:

- the existing Alexa device exactly once in the expert-light collection;
- local STATE as power and local brightness as brightness-only controller;
- the remaining deferred legacy Alexa consumer unchanged;
- STATE feedback upgraded in place to OnUpdate;
- brightness feedback retained as OnChange;
- explicit event actions on both existing event IDs;
- both presentation links byte-for-byte semantically preserved; and
- all 29 wrappers classified, with fifteen v2 and 14 legacy sources.
