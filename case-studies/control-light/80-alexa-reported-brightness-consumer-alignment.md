# Alexa reported-brightness consumer alignment

## Outcome

Two active ControlLight v2 instances with `reported` brightness semantics were
moved from a brightness-only Alexa device contract to the existing expert
light contract. Their Alexa device identities and user-facing names were
preserved. Each device now uses the local ControlLight `STATE` facade as its
power controller and the local `DIMMER` facade as its brightness-only
controller.

The Alexa instance remained operational, both ControlLight action owners and
all four facade values remained unchanged, and no light command was issued.
An independent readback found each migrated device exactly once in the expert
collection and no longer in the dimmer-only collection.

## Engineering decision

A brightness-only voice-assistant contract is incompatible with
`brightnessSemantics=reported`. Reported brightness deliberately retains the
last device brightness while `STATE` is false. A consumer that derives power
from brightness would therefore report an off light with retained non-zero
brightness incorrectly.

Voice-assistant consumers of a reported ControlLight facade must use:

- `STATE` as the power authority;
- `DIMMER` only as brightness; and
- the facade variables rather than native target variables.

A brightness-only mapping is permitted only for an explicitly reviewed
one-dimensional or `effective` compatibility contract.

## Scope and rollback

The approved live scope contained only the two already-active reported
instances identified by the targeted audit. Two brightness-only mappings
belonging to retained legacy ControlLight instances were left unchanged for
their later migrations. An existing correct expert-light mapping served as the
unchanged control.

The private evidence contains the exact pre-change Alexa configuration,
configuration hashes, installation IDs and the independent postflight. The
activation retained the existing Alexa device IDs so names and routine
references were not intentionally recreated.

## Remaining verification

This gate proves configuration, ownership and absence of unintended device
actions. A real Alexa command test is a separate device-action gate. The
installed voice-assistant remote module exposes a text-command operation that
can exercise Alexa intent resolution and the smart-home directive path without
microphone input. It is suitable for a supervised repeatable integration test,
but does not prove wake-word, microphone or speech-recognition behavior.

Future ControlLight migrations must inventory voice-assistant consumers during
preflight and reject brightness-only consumers when the selected brightness
semantics are `reported`.
