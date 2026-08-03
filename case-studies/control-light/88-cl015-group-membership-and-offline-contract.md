# CL-015 group membership and offline contract

## Outcome

CL-015 is an authoritative Zigbee2MQTT group with exactly three permanently
powered members. A separately approved call through the installed
Zigbee2MQTT configurator returned group ID 2 and the same three member
identities found during the preceding read-only object inventory.

The transaction published one extension metadata request. It sent no device or
group command and changed no IP-Symcon script, object or variable. Transport and
PHP execution completed without error.

This closes the membership-discovery gate. It does not activate CL-015.

## Selected feedback contract

The group receives one command through its existing Zigbee2MQTT group endpoint.
Command success requires bounded, fresh confirmation from every configured
member:

- STATE requires all three members to match the commanded state;
- brightness requires all three members to report within the bounded
  brightness tolerance;
- color temperature requires all three members to report within the existing
  bounded Kelvin tolerance; and
- the aggregate group endpoint must agree with the confirmed member result.

Passive STATE retains the existing `any-member-on` group rule. A stale or
partial member set cannot silently collapse the facade to off. Partial,
unavailable and aggregate-projection failures remain distinct diagnostics and
never cause an optimistic facade write.

## Dependencies preserved

The existing two independent external update triggers remain explicit,
alarm-aware on/off mappings. The inverse alarm contract remains unchanged:
the configured Boolean value means control is permitted only while the alarm is
inactive.

The Alexa expert device currently consumes facade STATE, brightness and color
temperature. Therefore color temperature remains an enabled capability and is
included in the member-confirmed contract rather than being removed for
convenience.

Member devices, their feedback variables and their availability/freshness
signals remain foreign dependencies. ControlLight owns only its facade and the
deterministically identified observation events below its wrapper.

## Offline verification

The shared runtime now supports member-confirmed color-temperature feedback in
addition to STATE and brightness. Regression coverage proves:

- confirmation through every configured member;
- rejection of partial color-temperature feedback;
- capability-complete member validation;
- idempotent temperature observation-event reconciliation;
- preservation of single-device contracts; and
- unchanged alarm and external-trigger behavior.

The regenerated immutable fileset has SHA-256
`406c68c52bbac335babe4a36c70c69bbb448c8e160ed48c6f96ddabd74cc94b1`.
The complete repository check passes.

## Remaining gates

The private candidate and exact rollback source are prepared but inactive.
Before activation, the new fileset must be staged under its immutable directory
and verified independently. A later command-free activation transaction must
then revalidate source, fileset and member identities, reconcile twice for
idempotency, and run the sanitized 29-instance structural regression.

Real-device STATE, brightness, color-temperature, external-trigger and Alexa
tests remain a subsequent presence-dependent gate.
