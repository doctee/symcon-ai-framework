# CL-013 and CL-028 Munich complete migration

## Outcome

CL-013 and CL-028 are active ControlLight v2 instances with authoritative
feedback and reported brightness semantics. Both retained their existing
facade variables, target links, user presentation and alarm polarity. The
installation now contains 20 v2 wrappers and nine retained legacy wrappers,
with no unclassified source.

CL-013 passed direct STATE, brightness and color-temperature control. CL-028
passed direct STATE and brightness control. Every direct sequence restored the
initial on/100-percent state; CL-013 additionally restored its initial color
temperature.

## Bounded temperature normalization

The supervised CL-013 test exposed deterministic target normalization: a
3500 K request is reported as 3508 K. The prior global five-unit comparison
correctly rejected that difference, but waiting longer could never make the
two values equal.

ControlLight therefore gained a bounded `colorTemperatureTolerance`
configuration value:

- the global default remains five target units;
- normalization rejects values outside the inclusive range 0–100; and
- CL-013 alone selects ten units, the narrowest value covering the observed
  eight-Kelvin projection.

Core, runtime and member-confirmed group regressions passed before a new
immutable fileset was staged through the restricted channel. The fileset was
not selected as the global helper owner; only the two private wrappers load it
directly.

CL-013 also needs a ten-second confirmation interval for its slower downward
color-temperature feedback. Its 200 ms polling interval caps a full failed
wait at 50 checks. Commands remain single-shot: the runtime never retries the
device action.

## Alexa and consumer contracts

CL-013 already used an expert-light Alexa row. Its identity and STATE,
brightness and color-temperature facade targets were preserved. Echo Remote
tests confirmed Alexa STATE and brightness delivery. The normal Alexa
`Kaltweiß` intent reached the device and produced its maximum 4000 K value, but
Alexa's requested value was outside the luminaire range and therefore could
not satisfy strict authoritative equality. The direct in-range temperature
contract remains fully tested; the out-of-range voice-intent finding is
retained rather than weakening confirmation globally.

CL-028's existing Alexa device was migrated in place from brightness-only to
expert-light STATE plus brightness. Its device identity and user-facing name
were preserved. Direct and Echo Remote STATE/brightness matrices passed with
zero errors or timeouts and exact restoration.

The existing CL-028 scene controller continues to consume facade brightness.
Its configuration hash remained unchanged. Neither instance has an Auto-Off
dependency or a foreign facade-trigger event.

## Protected attempts and diagnostics

The first CL-013 activation verifier used incorrect diagnostic Idents and two
unsuitable generic API assumptions. Each defect was detected within the
private migration boundary. The exact legacy source, original events and
values were restored before retry, and no device action occurred.

A later malformed private source-transfer payload briefly left the wrapper
empty. Hash readback detected this before execution; the locally verified
complete source was restored directly and values remained unchanged.

The CL-013 error ring deliberately retains the supervised test history:
pre-tolerance normalization timeouts, out-of-range Alexa color-temperature
attempts and one semaphore rejection caused by an intentionally premature
compensation attempt. The final independent restoration added one expected
command with no error or timeout delta. Deleting this history would conceal
relevant engineering evidence.

CL-028 finishes with eight commands, eight authoritative direct/Alexa actions,
zero errors, zero confirmation timeouts and equal facade/target values.

## Runtime mirror and closure

The visible, non-authoritative Symcon runtime mirror was stale relative to the
new runtime. It was regenerated with its existing 42-entry private reference
index. Script identity, name, position, visibility, icon and information text
were preserved; its inert payload now matches the active runtime source.

The final postflight confirms:

- both devices available and restored;
- both Alexa mappings unique and operational;
- CL-028 scene ownership unchanged;
- 29 classified wrappers: 20 v2 and nine legacy; and
- no Auto-Off mutation or unrelated device action.
