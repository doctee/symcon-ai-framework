# CL-024 direct test and Alexa alarm-block verification

## Outcome

CL-024 passed its complete direct STATE and brightness test through the local
ControlLight facade. Every command was confirmed by the authoritative native
target. The target's bounded normalization of a 70 percent request to 69
percent was reflected by the facade, and the exact initial off/100-percent
state was restored.

The separately exercised Echo Remote path verified the intended alarm block.
Both submitted German text commands reached the ControlLight runtime while the
configured alarm contract was active. They produced two runtime executions but
no success entry, device command or state change, exactly matching the
`blocked_by_alarm` branch. The lamp remained off and no runtime error or
confirmation timeout occurred.

The result is therefore intentionally split:

- direct ControlLight and device behavior: passed;
- reported off-state brightness behavior: passed;
- Echo Remote and Alexa delivery to ControlLight: passed; and
- active-alarm voice-control block: passed.

Direct requests use Symcon's `Action` sender and are deliberately permitted by
the runtime even while the alarm is active. Voice-assistant requests use
`VoiceControl` and are subject to the alarm gate. The different results of the
two test paths are therefore part of the intended contract.

## Alexa configuration readback

The Symcon Alexa instance remained operational and contained exactly one
expert-light entry named `Treppenlicht`. That entry retained its prior device
identity and referenced the CL-024 facade STATE and brightness variables as
separate controllers. No matching-name duplicate existed in the Symcon Alexa
configuration.

Together with the two new runtime executions, this readback proves that the
Alexa request reached the correct local consumer and was intentionally
stopped before device dispatch. Remote-module acceptance alone would not have
been sufficient evidence.

## Restoration and diagnostics

The direct sequence issued four real device commands: on, brightness 70,
brightness 100 and off. Local and native STATE finished off, while both
brightness values finished at the initial retained 100 percent. Availability
remained true. Errors, confirmation timeouts and the bounded error history
remained empty.

No Alexa discovery, cloud synchronization or other compensating mutation was
performed. A later allowed-state Alexa command matrix is optional and requires
a separately authorized test situation in which the alarm contract permits
voice control.
