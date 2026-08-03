# Off-State Color Transition Candidate

**Date:** 2026-07-27
**Gate:** Repository implementation and offline regression
**Result:** PASSED — LIVE ACTIVATION NOT ATTEMPTED
**Live impact:** None

## Finding Addressed

The CL-020 functional test proved two different native-HS normalization paths:

- while already on, the target settled within the existing 0.5-degree and
  0.5-percentage-point bounds;
- while off, the same class of color action implicitly powered the target on
  and settled 1.376 degrees from the facade projection.

A global tolerance increase would weaken the already precise on-state
contract. Sending an explicit STATE-on command before COLOR would add latency,
device traffic and another failure boundary even though this target already
performs the transition atomically.

## Candidate Contract

The normalized implementation adds an opt-in `colorOffStateTransition`:

```php
'colorOffStateTransition' => [
    'mode' => 'target-turns-on',
    'hueToleranceDegrees' => 2.0,
    'saturationTolerancePercentagePoints' => 0.5,
],
```

The default mode is `unchanged`, so no existing wrapper changes behavior.
`target-turns-on` is accepted only when STATE and COLOR are enabled and the
target format is native `HS_ARRAY_STRING`.

When COLOR is requested while authoritative target STATE is false, the runtime:

1. keeps the existing per-wrapper semaphore;
2. issues exactly one target COLOR `RequestAction()`;
3. confirms color with the separately bounded transition tolerances;
4. confirms authoritative target STATE=true; and
5. shares one overall confirmation deadline between both observations.

The runtime never writes target state optimistically and does not send a
second STATE request. DIMMER remains an independent contract and is only
synchronized from authoritative target brightness.

When target STATE is already true, the standard narrow color tolerances remain
in force. A missing power-on transition, missing color confirmation or expired
shared deadline fails closed through the existing classified feedback timeout.

## Performance Boundary

The successful path retains one device command. COLOR and STATE confirmation
share the existing configured timeout rather than consuming two sequential
timeout budgets. The only additional work is one initial STATE value read and,
when needed, one bounded STATE wait after color feedback.

This also covers a target whose physical power has just returned while its
reported STATE is still false: the command is not blocked by stale availability
or state evidence, and fresh COLOR plus STATE feedback remains mandatory.

## Offline Regression

The deterministic runtime tests prove:

- the observed 1.376-degree off-state normalization is accepted only in the
  explicit transition mode;
- exactly one COLOR action is issued;
- authoritative STATE=true is required;
- brightness remains unchanged;
- the same normalization is rejected while the target is already on;
- invalid target formats and unbounded transition tolerances are rejected; and
- all pre-existing runtime paths retain their behavior.

The implementation and generated fileset remain inactive. CL-020 stays on its
exact legacy wrapper until a new hash-locked package, command-free activation
and separately approved real-device regression are completed.
