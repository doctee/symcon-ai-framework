# CL-024 readiness and atomic package

## Outcome

CL-024 is selected as the next ControlLight migration candidate. Its single
Zigbee2MQTT target is active, available and current. STATE and brightness are
actionable, both existing feedback events are active with explicit event
actions, and the complete 29-wrapper baseline contains fourteen active v2 and
15 retained legacy wrappers without an unclassified source.

The private hash-bound package passed offline verification. All live mutation,
wrapper execution and device-action gates remain closed.

## Consumer and presentation scope

No script or foreign event consumes the local CL-024 facade. Two presentation
links target its local STATE and brightness variables and must remain unchanged.
The voice-assistant audit found one brightness-only Alexa consumer. Because the
selected brightness contract is `reported`, that existing device must move
in-place to the expert-light contract with local STATE as power and local
brightness as brightness-only controller.

The Alexa device identity and user-facing name are presentation state. The
package therefore preserves both and treats wrapper activation plus Alexa
consumer alignment as one rollback unit.

## Reported-brightness initialization

CL-024 is currently off. Its legacy facade brightness is zero while its current
target reports retained brightness 100. The first v2 reconciliation is expected
to change only the local facade brightness from zero to 100:

- no device command;
- no target value change;
- no STATE change; and
- Alexa power remains false because it is derived from local STATE after the
  consumer migration.

This is the intended initialization for `reported` semantics, not optimistic
state or a switch-on action.

## Preserved contracts

The candidate preserves:

- the two-capability STATE/brightness surface;
- the inverse alarm interpretation;
- the target link and both feedback-event identities;
- user-controlled presentation; and
- the currently staged immutable ControlLight runtime.

Rollback restores the exact wrapper source and Alexa configuration, the two
feedback-event snapshots and the initial local brightness if reconciliation
has already occurred.

## Deferred alternative

CL-028 has a similarly small consumer surface but is not selected. Its target
currently reports unavailable and its feedback is stale, so activation and a
subsequent Alexa functional test would not have a trustworthy live feedback
baseline.

## Next gate

The next gate is a fresh read-only delta preflight followed by separately
authorized atomic CL-024/Alexa activation. A real-device and Echo Remote
functional test remains a later, separately authorized gate.
