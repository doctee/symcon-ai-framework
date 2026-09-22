# Uniform explicit color command contract

## Behavior

For genuinely RGB-capable lights, an authorized explicit color request means
ON plus the requested color. A powered light remains powered; a repeated
confirmed request produces no redundant device commands. Passive color feedback
only synchronizes the facade and never switches the light. Existing sender and
alarm authorization remains in force; manual-on-only protection is not bypassed.

Brightness is not inferred from color or rewritten to a fixed default. The
runtime issues no brightness request as part of a color command. Actual retained
brightness and physical color reproduction require per-device acceptance.

## Transport-specific implementation

- `target-turns-on`: the previously tested HA/Matter HS target powers on through
  its native color request. One color command confirms both COLOR and STATE.
- `power-on-first`: targets whose native color request does not power on use the
  existing confirmed STATE path first, then the existing color path. Both share
  one semaphore and one overall confirmation deadline. Missing power feedback
  stops before color; missing color feedback never reports success.
- `unchanged`: remains the compatibility default. The policy is explicitly
  selected only for individually qualified consumers, not enabled globally.

```php
'colorOffStateTransition' => [
    'mode' => 'power-on-first',
    'hueToleranceDegrees' => 0.0,
    'saturationTolerancePercentagePoints' => 0.0,
],
```

STATE and COLOR must both be enabled. The explicit mode does not widen the
normal color matcher. It composes existing action, lock, wait, diagnostics and
manual-on protection; no new public helper is introduced.

## Acceptance boundaries

Offline tests cover RGB requests from OFF and ON, same-color OFF, repeated
requests, brightness preservation, one lock, one deadline, missing power,
power lost during color confirmation, passive feedback and manual-on protection.
Existing dimming, alarms and Matter color tests remain regression requirements.

Live activation is separate evidence. Test the Z2M pilot first, then eligible
legacy consumers. A color variable alone is not proof of RGB capability.
For groups, aggregate color feedback is not proof that every member changed;
member-aware color confirmation is a prerequisite for group closure. Optical
acceptance and Alexa end-to-end acceptance must not be inferred from local tests.

Member-confirmed groups with enabled color must configure a unique
`colorVariableID` for every member. The initial supported format is `INT_HEX`.
The existing bounded group confirmation loop checks all member colors and, for
explicit power-on-first, all member power states. Per-member color events reuse
the owned event helper. Aggregate-only or partial confirmation fails closed.

## First live cohort

CL-018, CL-021 and CL-029 passed technical RGB power-on-first acceptance with
unchanged brightness and command-free identical repeats. CL-018 and CL-029 also
passed positive dimming and Kelvin regression after migration from the legacy
dispatcher. Original native power, brightness, color and color-mode values were
restored, without new command errors or timeouts. Alexa bindings point to the
same facade variables; this is structural evidence, not a spoken-color test.
Optical acceptance remains a separate gate.

## Additional single-device and group acceptance

CL-003, CL-016 and all three members of CL-011 passed optical blue, red and
green acceptance. Direct facade tests passed identical repeats without extra
commands, same-color requests from OFF, different-color requests from OFF at
reduced retained brightness, dimming and Kelvin regression. No new command
errors or confirmation timeouts occurred. The group checks confirmed every
member, not merely the aggregate endpoint.

Original power, brightness and governing color mode were restored. Device-derived
temperature while in RGB mode and an initially uninitialized color cache are
not force-written to manufacture byte-identical feedback restoration.

Alexa structural bindings are now verified: the two existing single-device
expert endpoints retain their identities and include the facade color channel;
one new group endpoint uses the tested power, brightness, color and Kelvin
facades. Other instance properties and device states remained unchanged. A
guarded one-shot migration with backup, readback and rollback was used; its
temporary live script was removed and absence verified afterward.

CL-011 subsequently passed user-observed spoken Alexa green from OFF and spoken
OFF. Independent postflight found all three members OFF with retained green and
unchanged brightness; command count increased by three with no new errors or
timeouts. The user-selected final OFF state was preserved without further
device actions. Subsequent acceptance results are recorded below.

CL-003 and CL-016 also passed user-confirmed Alexa color requests from OFF and
subsequent spoken OFF. Readback found matching native and facade states, retained
requested red/blue, unchanged brightness and no new errors or timeouts. Thus the
three consumers have direct, optical and spoken acceptance.

CL-018 and CL-029 subsequently passed user-observed blue, red and green on
2026-09-22. Blue from OFF also confirmed power-on at retained test brightness;
the following readback showed both OFF with retained green, matching facades and
no new errors or timeouts. They also passed user-confirmed spoken Alexa blue/red
from OFF followed by spoken OFF. Final native and facade states matched OFF at
retained test brightness with no new errors or timeouts. The user-selected OFF
state was preserved.

CL-021 also passed user-observed blue, red and green on 2026-09-22, followed by
spoken Alexa OFF, blue from OFF and final OFF. Independent readback confirmed
matching native and facade OFF, retained blue and unchanged brightness. No new
errors or confirmation timeouts occurred. The user-selected final OFF state was
preserved without additional device actions.

All six qualified consumers now have technical, optical RGB and spoken Alexa
color-from-OFF/OFF acceptance. This closes the cohort test gate, not unrelated
lamp migrations, merge approval or immutable-artifact retention.
