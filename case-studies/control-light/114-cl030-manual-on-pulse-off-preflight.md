# CL-030 Manual-On/Pulse-Off Preflight

## Outcome

The prospective CL-030 contract is implemented and verified offline. The lamp
is not a symmetric switch: measured electrical power is authoritative lamp
state, remote off uses one bounded supply interruption, and remote on requires
manual activation. Relay feedback is used only to confirm that supply was
restored after the pulse.

No live object, script, variable, consumer or device was changed during this
step. Activation remains pending because the restricted deployment endpoint
rejected its configured public key before package staging.

## Contract

- `stateCommandMode=off-only` makes the asymmetry explicit.
- A requested on state that is not already confirmed is classified as
  `manual_activation_required` without a target action.
- An off request is always delegated to the adapter, even while facade feedback
  is still false. The adapter then observes authoritative power for a short,
  bounded interval before deciding that the lamp is already off.
- A transition to active power during that interval produces exactly one off
  pulse. Missing off feedback or missing relay restoration is a classified,
  bounded failure; there is no blind retry.
- Manual state changes flow from measured power through the adapter state into
  the ControlLight facade.

This delegation rule covers the important race in which the lamp is switched on
manually and an off command follows before the normal power-feedback event has
updated the facade.

## Consumer Plan

Activation will preserve Home Assistant and Apple Home identity by retaining
the existing location, device, topic, entity and UUID namespace contract. The
new SAEF exporter will use separate facade state and action ownership with
non-optimistic feedback.

The presentation link, Alexa device row, scene target, alarm-warning event and
Auto-Off entry are all part of the atomic facade handoff. Auto-Off is not part
of the pulse adapter itself; it remains an ordinary downstream off consumer.

## Verification

The deterministic offline suite proves:

1. manual on is rejected without a device command;
2. stable off is idempotent and causes no relay command;
3. delayed manual-on feedback inside the observation window is detected;
4. that race produces exactly one off pulse;
5. power-off feedback and restored supply are both required;
6. existing bidirectional ControlLight instances retain their prior behavior.

The generated ControlLight fileset is deterministic and includes the adapter
core and runtime. Its private deployment package is hash-bound; staging,
activation and all real-device commands remain separate gates.
