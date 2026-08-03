# CL-001 away-safe activation

## Outcome

CL-001 is active on ControlLight v2 with authoritative target feedback and
`reported` brightness semantics. Its STATE, brightness and color-temperature
facade remained value-identical to the available native target throughout two
idempotent reconciliation runs.

The activation issued zero device commands and changed no facade or target
value. Both runs succeeded with zero errors and confirmation timeouts. The
installation baseline is now sixteen active v2 wrappers and 13 retained legacy
wrappers, with all 29 sources classified and unchanged outside CL-001.

## Away and alarm boundary

The installation owner was away during activation. The configured inverse
alarm variable was false, which means that the ControlLight alarm contract was
active. That value and interpretation were preserved exactly.

No real device, Alexa or other consumer command was submitted. This is
important because Symcon's explicit `Action` sender deliberately bypasses the
alarm gate; using it for a functional test would not be made safe merely by the
active alarm variable. The inverse alarm behavior is instead closed by a
deterministic runtime regression covering blocked `VoiceControl` and
`WebFront`, retained `Action` access and re-enabled voice control after the
alarm becomes inactive.

The real STATE, brightness, color-temperature and Alexa matrix remains an
explicit presence-bound gate.

## Ownership and consumers

All three existing facade variables retained their IDs, names, positions,
profiles and custom action owner. The three existing feedback-event IDs were
reused with explicit event actions. STATE moved in place from OnChange to
OnUpdate; brightness and color temperature retained OnChange.

No AutoOff, script, event or presentation-link consumer references the CL-001
facade. Its existing Alexa expert-light entry already used separate facade
power, brightness and color-temperature controllers, so the Alexa
configuration and device identity required no mutation.
