# CL-020 Home Assistant Entity action-contract blocker

## Outcome

CL-020 remains on its exact legacy source. Its prepared ControlLight v2
candidate passed two non-commanding reconciliation runs, but the first bounded
STATE action exposed a defect at the target-module boundary. The device,
facade, wrapper topology and all consumers were restored to their initial
values and sources. The current inventory therefore remains 21 v2 wrappers,
eight retained legacy wrappers and seventeen fully device-tested wrappers.

## Failed target action contract

The target is provided by the installed `Home Assistant Entity` module. Its
shared device trait reads the Boolean `EmulateStatus` property after sending an
action, but this module type does not register that property. The resulting
warning makes the module return action failure. ControlLight correctly treats
that return value as a rejected `RequestAction()` and fails closed.

The target nevertheless dispatched the state command asynchronously: shortly
after the rejected return, authoritative target and facade feedback both
reported on. A bounded compensating off request produced the same false return
and warning, but again changed the device asynchronously. This combination is
unsafe for an authoritative caller because the synchronous result contradicts
the externally visible effect. SAEF must not reinterpret a rejected target
action as success.

Read-only scope analysis found thirteen installed instances of the affected
module type. All thirteen were operational, and none had the property expected
by the shared trait. The defect is therefore module-wide rather than specific
to CL-020.

## Rollback and preserved dependencies

After the failed first capability test, no brightness, color-temperature or
color request was attempted. The lamp was returned to its exact initial state,
including retained brightness, Kelvin and color values. The wrapper was
restored byte-exactly to its legacy source, all temporary v2 diagnostics were
removed, and the original four event identities remained.

The existing Alexa expert-light configuration and scene configuration stayed
byte-identical throughout. Presentation links, alarm polarity and the local
facade values also remained unchanged. Exact installation evidence is retained
only in the private overlay.

## Separate repair gate

The narrow technical repair is to register `EmulateStatus` with its existing
default in the `Home Assistant Entity` module's `Create()` method, matching the
contract already assumed by its shared trait. This is not a ControlLight
runtime change and is deliberately outside the completed CL-020 approval.

A later live repair requires a separate gate because the Store-managed module
is shared by thirteen instances and must be reloaded. That gate must include:

1. a fresh complete source read and byte-exact module backup;
2. a one-property source patch with exact hash verification;
3. a controlled module reload and all-instance structural/value postflight;
4. a bounded direct target action test that evaluates return value and later
   feedback independently; and
5. immediate module rollback on any invariant failure.

Only after the target module returns a truthful accepted action result should
the unchanged CL-020 candidate be reactivated and its complete four-capability,
Alexa and scene regression matrix executed.
