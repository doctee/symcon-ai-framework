# Home Assistant Entity fix and CL-020 color finding

## Outcome

The shared `Home Assistant Entity` action contract is repaired. A targeted
Module Control reload applied the missing Boolean property to all thirteen
installed instances without restarting the Symcon service or issuing a device
command. Direct target STATE requests now return truthful success and retain
authoritative feedback behavior.

CL-020 nevertheless remains on its exact legacy source. Its second v2 attempt
passed STATE, brightness and color temperature, but its color target normalizes
the requested RGB value too strongly for authoritative equality. The attempt
was stopped at that capability, all initial values were restored, and the
Alexa and scene consumers were left unchanged. The inventory remains 21 v2
wrappers, eight retained legacy wrappers and seventeen fully device-tested
wrappers.

## Target-module repair

The Store-managed module source was protected by a byte-exact local backup and
an expected source hash. The patch added only the missing
`EmulateStatus=false` property registration already assumed by the shared
device trait.

`MC_ReloadModule()` was available on the live Symcon version and accepted the
Store module's relative directory identifier. The targeted reload completed
without a kernel restart. Every affected instance retained its identity,
parent, status, configuration apart from the new default property, child
topology, profiles and actions. All thirteen instances reported status 102,
the property was present and false on all thirteen, and none retained pending
configuration changes.

Recreating the instances temporarily reset attributes that Home Assistant does
not publish while the light is off. A read-only state synchronization correctly
left those values absent. The bounded off-on-off module contract test then
reacquired the real device attributes and restored the exact retained
brightness, Kelvin and color state. Both STATE actions returned true, produced
authoritative feedback and emitted no missing-property warning.

## Second CL-020 capability test

The unchanged CL-020 candidate passed two command-free reconciliation runs.
The direct matrix then established:

- STATE on was accepted and confirmed;
- 70 percent brightness mapped to target value 179 on the target's 0–255
  scale;
- requested 3500 K normalized to 3508 K inside the configured ten-Kelvin
  tolerance; and
- the color command was accepted but normalized to a materially different
  authoritative RGB projection.

ControlLight correctly recorded the color result as one feedback timeout
instead of overwriting the facade optimistically. The same class of lossy
color-boundary problem was already isolated for CL-021; no global tolerance
relaxation is justified.

## Rollback and consumer decision

The lamp was restored through the reliable Kelvin path, followed by brightness
and STATE. Facade and target values then matched the complete initial snapshot.
The wrapper was restored byte-exactly to legacy and all temporary v2 diagnostic
objects were removed, leaving the original four event identities.

Unlike a light without color consumers, CL-020 cannot simply disable its color
capability without an explicit consumer decision. Its existing Alexa expert
light and scene both consume the color facade. Keeping the legacy wrapper
therefore avoids silently removing established behavior. A future migration
must either repair the target's RGB/HS conversion or deliberately redesign
both color consumers before activating a no-color v2 contract.

The module patch is Store-managed and may be replaced by a later module update.
Its upstream equivalent should therefore be tracked separately from SAEF; the
SAEF runtime itself required no weakening or module-specific workaround.
