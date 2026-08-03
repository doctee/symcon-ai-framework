# Hue Cohort Full-Capability Functional Test

**Gate:** CL-005 and CL-012 enabled-capability regression
**Result:** PASS — INITIAL STATE RESTORED
**Date:** 2026-07-26
**Live impact:** Eight bounded device commands

## Scope and Offline Boundary

After explicit approval, the remaining enabled capabilities of CL-005
Glaskugel and CL-012 Küchendecke were tested through their local ControlLight
facades. Both targets are permanently powered and have no physical isolation
path. A live power-off/recovery test is therefore not applicable.

The installation will not manufacture an outage by disabling shared Z2M, MQTT
or Symcon infrastructure. Timeout, rejected-action and non-optimistic failure
behavior remain covered by executable runtime regressions. A genuine future
communication outage may be closed opportunistically through passive evidence.

## Preflight

Both targets reported available. Every local facade value matched native
feedback, both error histories were empty and no wrapper had an outstanding
timeout. Initial values were:

- Glaskugel: off, retained brightness 100%;
- Küchendecke: off, retained brightness 100%, color temperature 2702 K.

## CL-005 Glaskugel

Requesting 70% brightness while off switched the device on. The target
normalized the requested value to 69%; local and native feedback matched and
the configured brightness tolerance accepted the result.

Brightness was restored to 100% while on, then STATE was restored to off. The
sequence produced exactly three ControlLight commands, with no error or
confirmation timeout.

Together with the previously completed STATE and Hue Wall tests, CL-005 has
now passed every enabled capability.

## CL-012 Küchendecke

Requesting 70% brightness while off also switched this target on and produced
authoritative 69% feedback. A subsequent 3000 K request was normalized by the
device to 3003 K and accepted within the configured color-temperature
tolerance.

Color temperature was restored to 2702 K, brightness to 100% and STATE to off.
The sequence produced exactly five ControlLight commands, with no error or
confirmation timeout.

Together with the previously completed STATE and Hue Wall tests, CL-012 has
now passed every enabled capability.

## Final Regression

Both lamps ended in their exact initial state and reported available. Local and
native STATE, brightness and applicable color-temperature values were equal.
Both ControlLight error histories remained empty.

The Hue Wall handler remained unchanged at 17 confirmed commands, zero
failures, zero timeouts and zero dropped valid actions. All 29 ControlLight
wrapper sources matched the expected current baseline.

CL-005 and CL-012 are now classified as fully device-tested for all enabled
capabilities. The current installation status advances to eleven active v2
wrappers, ten fully device-tested wrappers and 18 retained legacy wrappers.
CL-021 remains the separate target-color-contract exception.

## Next Gate

No further Hue-cohort command test is required. After a regular observation
period, the next separately approved mutation may be the ownership-checked
cleanup of the two inactive unnamed Hue events and the retained obsolete
handler state. Cleanup is optional and must preserve the active event topology,
presentation and independent consumers.
