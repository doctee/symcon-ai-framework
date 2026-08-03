# CL-008 live delta preflight

## Outcome

The bounded, read-only live inspection found no drift that invalidates the
private CL-008/Auto-Off candidate:

- the CL-008 legacy wrapper source still matches its exact rollback hash;
- the Auto-Off source still matches its exact rollback hash;
- group endpoint, facade and both member variable mappings remain unchanged;
- every command boundary still has the expected action handler;
- both members are available and supplied current STATE, brightness and
  `last_seen` evidence during the inspection;
- the two existing Auto-Off member events remain active and correctly bound;
- the two foreign device-warning events and their availability links remain
  unchanged; and
- the facade presentation links still target STATE and DIMMER.

No script, object, event or variable was changed. Neither a device/group action
nor an MQTT publication occurred.

## Source and topology baselines

The live source hashes are identical to the rollback sources contained in the
private package. CL-008 still has only its two legacy aggregate-target feedback
events. Auto-Off still owns its motion event, timer, two CL-008 member events
and the two unrelated retained light events.

The intended activation therefore has a deterministic topology delta:

- CL-008 creates four owned member feedback events and retains its two owned
  aggregate-target events for group-projection observation;
- Auto-Off removes the two owned member events and creates one STATE control
  event plus one DIMMER activity event for the facade; and
- all unrelated and foreign objects remain outside the mutation set.

## Group configuration

The Symcon group instance exposes group ID and MQTT topic, but no
`optimistic` or `off_state` option. Those settings therefore cannot be treated
as a Symcon-side contract. This is non-blocking because command success is
defined exclusively by fresh individual member feedback, not by the aggregate
group projection.

The two device instances still expose the same IEEE identities and actionable
STATE/brightness variables recorded by the authoritative group-list response.
An activation-adjacent group-list re-query remains required after inactive
fileset staging so an external Zigbee2MQTT membership edit cannot pass
unnoticed.

## Fileset gate

The candidate fileset
`saef-clhw-84827ffca42391f` is not present on the Symcon host. This is expected:
the previous step built and verified it locally but did not authorize live
staging.

The next mutation gate is limited to inactive, hash-verified staging. Staging
must not change the runtime selector, either script source, any Symcon object
or any device value. Atomic wrapper/Auto-Off activation remains a later,
separately approved gate.
