# CL-008 membership and dependency preflight

## Outcome

The approved, bounded Zigbee2MQTT Symcon-extension query confirmed that CL-008
contains exactly two members. Both members map one-to-one to existing active
Zigbee2MQTT device instances and expose:

- actionable Boolean STATE feedback;
- actionable percentage brightness feedback;
- Boolean device availability; and
- current `last_seen` timestamps.

The query published one metadata request to the extension group-list topic. It
did not publish a device or group command and did not change any Symcon object,
variable or script.

This closes the membership and reporting-discovery gates. It does not authorize
or complete activation.

## Existing dependency correction

The earlier facade-only inventory found no Auto-Off consumer on the CL-008
wrapper or group endpoint. Member-level inspection now reveals the actual
dependency: Auto-Off currently models the two group members as two independent
brightness controls.

That behavior is incompatible with the selected member-confirmed group
contract after migration. A timeout would bypass the CL-008 facade, issue two
independent device actions and perform confirmation outside the group runtime.
It would also split one logical light into two shutdown participants.

CL-008 activation must therefore be atomic with the Auto-Off hand-off:

1. replace both member brightness controls with one CL-008 facade STATE
   control;
2. observe facade STATE and DIMMER as the logical light's activity;
3. let ControlLight issue the single group command and confirm both members;
4. reconcile Auto-Off events idempotently only after the new facade runtime is
   ready; and
5. restore both exact script sources and event topology on rollback.

The facade STATE control is preferred over DIMMER as the Auto-Off command
boundary because shutdown means an explicit off command. DIMMER remains
activity evidence while the facade is on.

## Existing member consumers

Each member STATE already has a foreign event owned by the device-warning
summary. Each event contains a link to that member's availability variable.
These events and links are not ControlLight-owned and must remain unchanged.

The new ControlLight member-observation events will be separately owned below
the CL-008 wrapper and use deterministic Idents. They observe member STATE and
brightness only; they never take ownership of, rename, move or reconfigure the
device instances or their variables.

## Offline candidate requirements

The implementation must preserve the existing single-device behavior for all
other ControlLight instances and add group behavior only when an explicit
member-confirmed configuration is present.

Required regressions include:

- normalization rejects empty, duplicate or capability-incomplete members;
- passive STATE is true when any fresh member is on;
- passive STATE does not collapse to false while member evidence is stale;
- one group command is issued regardless of member count;
- the group endpoint and every member share one confirmation deadline;
- already-matching members require fresh evidence;
- partial, stale, offline and projection-mismatch failures remain distinct;
- no optimistic facade value is written after failure;
- member observation events reconcile idempotently and preserve foreign events;
- all 29 installed contracts retain their existing normalized behavior; and
- Auto-Off replaces exactly the two member controls with one facade control.

## Remaining gates

The next step is an offline runtime and regression candidate. After it passes,
a fresh read-only delta preflight must bind the exact live source hashes and
verify the still-current two-member inventory. Live source changes, Auto-Off
reconciliation and real-device tests remain separately approved gates.
