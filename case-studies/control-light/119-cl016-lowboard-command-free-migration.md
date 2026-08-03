# CL-016 Lowboard Command-Free Migration

**Date:** 2026-08-03
**Gate:** Explicitly approved atomic wrapper, consumer and scene migration
**Result:** PASSED — REAL-DEVICE TEST NOT ATTEMPTED

## Instance Contract

CL-016 retains all four Z2M capabilities: STATE, reported brightness, Kelvin
color temperature and RGB color. The wrapper uses authoritative feedback and
the installation's inverse alarm binding. The already activated immutable
ControlLight runtime was reused byte-exactly; no fileset staging or restart was
required.

The existing facade variables, names, positions, profiles, action bindings,
feedback events, target link and voice-assistant references remain unchanged.
The device-warning automation remains a foreign native observer because it
intentionally watches device state and availability rather than issuing a
control action.

## Consumer Handoff

Two direct native device writers were migrated through exact one-line deltas:

- the wake-up automation now requests the facade brightness; and
- the global shutdown automation now requests the facade STATE.

Neither consumer was executed. Their broader effects make structural readback
and later natural execution safer than a manual migration-time invocation.

## Complete Scene Contract

The SceneControl instance contains eight targets and ten scenes. Because
`SZS_SaveSceneEx()` stores a complete scene, the migration captured and rewrote
the entire 10 by 8 matrix rather than only the three Lowboard fields.

Only the following approved transformations were applied:

- three Lowboard target VariableIDs moved to the existing facade while their
  stable target GUIDs and order remained unchanged;
- Lowboard color-temperature values were translated from 366/153 mired to
  2732/6535 Kelvin; and
- visible scene 10 (`aus`) retained STATE=false and ignores Lowboard brightness
  and color temperature, preventing post-off property actions from switching
  the light on again.

All other target values, ignore flags and scene captions remain unchanged. The
unused hidden scenes 4 through 9 were preserved rather than receiving an
unapproved semantic cleanup.

## Activation and Independent Postflight

Fresh wrapper, consumer, runtime, SceneControl and semantic-matrix hashes
matched the private rollback package immediately before mutation. A first
payload-loading attempt was rejected by PHP before activation code executed;
the corrected embedding retained the same verified payload and performed the
bounded transaction successfully.

Independent source readback and postflight confirmed:

- all three candidate source hashes;
- eight stable SceneControl target GUIDs and all ten complete scenes;
- unchanged facade and device-domain values;
- unchanged event identities, triggers and explicit Run Automation bindings;
- unchanged links, foreign observer and voice-assistant configuration;
- active target, SceneControl and voice-assistant instances; and
- zero executed scripts, scenes or device commands.

Normal later Z2M `last_seen` telemetry advanced independently; controlled
STATE, brightness, temperature and color values remained equal.

The sanitized structural baseline is now 25 active v2 wrappers, 19 fully
device-tested wrappers and four retained legacy wrappers across 29 tracked
ControlLight instances.

## Next Gate

CL-016 still requires a separately approved presence-bound capability test.
The direct STATE, brightness, color-temperature and color matrix can be tested
without invoking multi-device scenes or broad consumer automations. A scene or
consumer execution remains a separate side-effect-aware gate.
