# CL-008 group functional test

## Outcome

CL-008 passed its complete enabled-capability test against both physical group
members:

- STATE on confirmed the group endpoint and both member STATE variables;
- 70 percent normalized consistently to 69 percent at group, facade and both
  members;
- STATE off retained the reported 69-percent device brightness;
- STATE on restored both members at the retained brightness;
- a separate reproduction held STATE on and 50 percent consistently for five
  seconds; and
- final compensation restored group, facade and both members exactly to
  STATE false and brightness zero.

Both member availability variables remained true. No confirmation timeout or
runtime error occurred.

## Isolated parallel action

After the first 70-percent transition, all participants reported STATE false.
Runtime diagnostics counted one additional ControlLight command beyond the
seven facade actions initiated by the test.

The Auto-Off timer did not run during that transition, and the same sequence
could not be reproduced: the controlled 50-percent transition remained on and
stable at the group and both members. The user subsequently confirmed that
another person issued a voice command at that time. The isolated transition is
therefore confirmed external facade control rather than a ControlLight or
group-feedback failure.

## Restoration

Reported brightness was restored from the tested values to the exact initial
zero through the native group brightness action after STATE was off. This
compensation preserved the production contract: normal users continue to use
the CL-008 facade, while the native action was used only to recover the
otherwise non-representable initial retained-brightness value.

The test and its reproduction observed eight ControlLight group commands,
including the single external/concurrent facade command, plus two native group
brightness-zero compensation commands. Final diagnostics showed zero errors
and zero confirmation timeouts.

The Auto-Off timer was armed normally by facade activity and then restored to
its initial inactive state. Both script sources and all six CL-008 event
contracts remained unchanged.

## Auto-Off safety gate

A real timer expiry was not attempted because:

- the configured motion sensor was active; and
- another light controlled by the same Auto-Off script was on at 100 percent.

Forcing expiry would either skip shutdown because of motion or risk switching
off an unrelated active light. The production source was not narrowed or
temporarily modified merely to manufacture a pass.

The remaining Auto-Off test should run only when motion is inactive and every
other managed control is already off. It must then turn on CL-008, use an
actual shortened timer expiry, confirm both members off through the facade and
restore the original timer state and all light values.
