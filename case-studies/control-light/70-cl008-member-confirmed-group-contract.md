# CL-008 member-confirmed group contract

## Outcome

CL-008 is confirmed as a real Zigbee2MQTT group and is not eligible for the
single-device ControlLight contract. Its group contract is now defined.
Subsequent authoritative discovery confirmed two members and usable individual
STATE, brightness and freshness feedback for both. Activation is nevertheless
not ready because the runtime extension, Auto-Off hand-off and rollback package
still have to be built and tested offline.

Two passive subscriptions against the existing Symcon MQTT broker were also
tested without publishing. The broker accepted the subscriptions, but did not
replay `bridge/groups` as retained metadata. This route therefore cannot supply
the membership proof in this installation.

The selected contract is **member-confirmed**. ControlLight sends one command
to the Zigbee group, but success requires bounded confirmation from every
configured member. The group endpoint remains the command target and aggregate
projection; it is not sufficient physical evidence by itself.

No live source, object, variable or device was changed during this assessment.

## Why aggregate feedback is insufficient

Zigbee group commands are broadcasts. Each device decides locally whether it
belongs to the addressed group; the coordinator does not acknowledge
successful execution by every member. Zigbee2MQTT also enables optimistic group
state publication by default. A matching group STATE or brightness value can
therefore confirm the broker projection without proving that each lamp acted.

Zigbee2MQTT documents two group STATE aggregation modes. With the default
`all_members_off`, the group remains on while at least one member is on.
`last_member_state` instead follows the member that changed most recently.
These are aggregate presentation rules, not all-member command confirmation.

References:

- [Zigbee2MQTT groups](https://www.zigbee2mqtt.io/guide/usage/groups.html)
- [Zigbee2MQTT group settings](https://www.zigbee2mqtt.io/guide/configuration/all-settings.html#groups)

The live Symcon target exposes group STATE, brightness and one
`device_status`. It exposes no membership list. The group availability value
must therefore be treated as endpoint/bridge metadata, never as evidence that
all members are reachable.

The module's `UpdateInfo` action is not an acceptable membership-discovery
shortcut. Its documented implementation requests group exposes, stores them
as module metadata and maps them to variables. That can change the instance
layout, yet the response does not contain the group member inventory needed by
this contract.

The suitable next discovery operation is the Zigbee2MQTT Symcon extension's
group-list request. Its response contains the group ID and member IEEE
addresses. Although it does not command a lamp, it publishes a metadata request
and is therefore a separate live-query approval gate.

That separately approved, transaction-bound request completed successfully.
It returned exactly two configured members for CL-008. Both resolve to existing
active Zigbee2MQTT device instances with actionable STATE and brightness
variables plus individual availability and `last_seen` feedback. No group or
device command was sent.

## Selected authority model

The CL-008 facade uses two distinct evidence levels:

| Evidence | Role | Sufficient for command success |
| --- | --- | --- |
| Group STATE/brightness | Aggregate projection and command-target feedback | No |
| Group `device_status` | Post-timeout endpoint classification | No |
| Every configured member's individual feedback | Physical member confirmation | Yes |

`authoritativeFeedback=true` may be enabled only when the member-confirmation
path is configured and validated. It must not be enabled merely because the
group variables change after a command.

Member objects are dependencies, not ControlLight-owned resources.
Installation IDs and member names belong only in the private wrapper
configuration.

## STATE contract

Passive facade STATE uses **any-member-on** semantics:

- false means every configured member reports off;
- true means at least one configured member reports on;
- unknown or stale members do not silently collapse to false.

This matches the safety intent of Zigbee2MQTT's `all_members_off` mode and
prevents Auto-Off or warning consumers from treating a partially lit group as
off.

Command postconditions are deliberately stronger:

- an off command succeeds only when every configured member reports off;
- an on command succeeds only when every configured member reports on.

The group endpoint must agree with the derived facade state after the member
postcondition. A disagreement is recorded as an aggregate-projection mismatch,
not hidden by updating the local facade optimistically.

## Brightness contract

CL-008 uses planned `reported` brightness semantics. DIMMER represents the
reported group command level; it is not:

- an arithmetic average of member brightness;
- a minimum or maximum member value;
- proof that every member currently emits the same light level.

When STATE is false, DIMMER mirrors the group endpoint's reported brightness,
which may be retained or zero according to the target's actual reporting. The
runtime must not derive effective brightness from STATE.

A brightness command succeeds only when:

1. the group endpoint publishes a compatible normalized level; and
2. every configured member reports a level inside the established bounded
   tolerance.

The local facade adopts the normalized group level only after both conditions
pass. Diverging member levels remain diagnostic evidence and are never averaged
into an apparently successful value.

## Freshness and idempotency

Member confirmation uses one common deadline and one polling loop for the
whole group. Runtime cost is proportional to the number of members per poll,
not the member count multiplied by the timeout.

For a member that differed before dispatch, a matching post-command update is
required. A member already at the requested value may count only when its
feedback and availability evidence satisfy a bounded freshness policy.
Pre-existing stale equality is not command confirmation.

Only one semaphore is acquired for the logical group target. Parallel commands
to unrelated targets remain possible, while concurrent CL-008 commands are
serialized.

Repeated reconciliation must reuse:

- the existing local STATE and DIMMER variables;
- the existing group-target feedback events;
- user-edited names, positions, profiles and presentation links;
- member-observation events owned below the wrapper once they exist.

Foreign member instances, variables and events are never renamed, moved,
hidden or reconfigured.

## Failure classification

The bounded diagnostic contract distinguishes:

- `group_endpoint_timeout`: the aggregate endpoint did not confirm;
- `group_member_offline`: at least one member is unavailable after timeout;
- `group_member_stale`: a member has matching but insufficiently fresh
  evidence;
- `group_partial_feedback`: reachable members disagree with the requested
  postcondition;
- `group_projection_mismatch`: members agree but the group aggregate differs;
- `group_contract_invalid`: membership or required member capabilities are
  missing.

The error history stores bounded member keys and counts, not private MQTT
topics or an unbounded device snapshot. No failure writes optimistic local
STATE or DIMMER values.

## Readiness and migration gates

Before an offline candidate can be built:

1. ~~obtain explicit approval for the bounded extension group-list query;~~
2. ~~obtain the exact member list from its authoritative Zigbee2MQTT response;~~
3. ~~bind every member to its existing Symcon device instance and individual
   STATE, brightness, availability and freshness variables;~~
4. record the group's `optimistic` and `off_state` options before activation;
5. ~~prove that every member exposes actionable STATE and brightness plus
   usable current `last_seen` feedback;~~
6. implement shared-deadline member confirmation inside the ControlLight case
   study and add pure, runtime and topology regressions;
7. replace Auto-Off's two member-level controls with one facade STATE control
   and facade DIMMER activity observation in the same activation transaction;
8. verify that the current two presentation links, both legacy group feedback
   events and foreign member-warning events remain reusable;
9. build hash-bound ControlLight, Auto-Off and rollback artifacts.

Live activation and device tests remain separate explicit approval gates.
The current legacy wrapper stays active until all readiness conditions pass.
