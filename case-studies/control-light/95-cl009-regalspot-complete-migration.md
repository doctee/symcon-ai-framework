# CL-009 Regalspot complete migration

## Outcome

CL-009 is active on ControlLight v2 with authoritative target feedback and
reported brightness semantics. Its STATE, brightness and color-temperature
facade variables, presentation links, alarm polarity and scene consumers were
preserved. The live inventory now contains 21 v2 wrappers and eight retained
legacy wrappers with no unclassified source.

The target exposes brightness on a raw 0–255 scale. CL-009 therefore selects
`dimmerTargetMax = 255`; a facade request of 70 percent was confirmed as raw
target value 179 and projected back as 70 percent.

## Capability and voice contract

The luminaire is a white-spectrum light. The legacy Alexa expert-light row
nevertheless exposed the hidden facade COLOR variable. Activation removed only
that unsupported color controller while retaining the existing device
identity, user name, STATE, brightness and color-temperature controllers.

Direct STATE, 70-percent brightness and 3500 K actions passed. The target
normalizes 3500 K deterministically to 3508 K, which is covered by the existing
per-instance ten-Kelvin tolerance. Echo Remote confirmed Alexa STATE and
brightness in both directions. Alexa answered the German `Neutralweiß` request
with “Ich weiß nicht, wie ich diese Einstellung für Regalspot vornehmen kann”
when submitted through Echo Remote's synthetic text-command channel and did
not dispatch a device action. A subsequent real spoken `Neutralweiß` command
was correctly applied according to the installation owner. Alexa
color-temperature control is therefore passed through its real user channel;
the synthetic text-command result remains a channel-specific limitation. The
independently tested direct Kelvin path remains authoritative and no failed
ControlLight device command was recorded.

The target module initially reported zero brightness and temperature while the
light was off. Its first powered feedback exposed the actual retained device
state of raw brightness 100/255 and 2202 K. That meaningful retained state was
restored before switching the lamp off.

## Auto-Off and other consumers

The shared room Auto-Off script formerly controlled and observed the raw target
brightness variable. It now switches the CL-009 STATE facade off and treats
STATE plus DIMMER as activity. Its existing event was repurposed under the
owned event contract and one brightness activity event was added. A second
reconciliation produced no source, object or event drift.

A forced timer-expiry test was deliberately omitted because the shared
Auto-Off script also controls unrelated room lights. The complete ControlLight
off path was exercised directly and through Alexa; structural Auto-Off
reconciliation and event action binding passed.

The existing room scene configuration already consumed facade brightness and
color temperature and remained byte-identical. A direct target-state observer
belongs to the installation's device-warning summary rather than light
control, so it remained unchanged.

## Diagnostics and closure

The completed migration recorded 35 successful executions and eleven commands
with zero errors, zero confirmation timeouts and an empty error history. The
managed Symcon runtime mirror already carried the current authoritative runtime
payload and required no rewrite.

Final postflight confirmed:

- alarm inactive and target instance active;
- the lamp off with its retained brightness and temperature restored;
- Alexa expert-light mapping unique and limited to real capabilities;
- presentation links and scene configuration unchanged; and
- 29 classified wrappers: 21 v2 and eight legacy.
