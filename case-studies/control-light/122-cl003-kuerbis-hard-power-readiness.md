# CL-003 Kürbis Hard-Power Readiness

**Date:** 2026-08-03
**Gate:** Read-only inventory and offline package
**Result:** READY FOR A SEPARATE COMMAND-FREE ACTIVATION GATE

## Hard-Power Contract

The luminaire is normally disconnected by a physical mains switch. Loss of
Zigbee availability is therefore not authoritative logical STATE=false
feedback. While mains power is absent, the last reported STATE may remain
stale. A permitted interactive command after physical power-on must still be
dispatched immediately; availability is evaluated only after missing bounded
feedback and must not become a pre-dispatch wait.

The currently powered target reports available with fresh telemetry. STATE,
brightness and color match the existing facade. The Kelvin target and facade
both remain at zero and have no prior update evidence, so color temperature is
not yet functionally proven. Color is likewise deferred to the later direct
test because the current Z2M module has an already documented device-specific
color/brightness feedback risk.

## Dependencies

The read-only reference scan found:

- four presentation links to the existing facade variables;
- no Alexa row and no SceneControl target;
- one global shutdown writer to native STATE, already guarded by target
  availability; and
- one foreign native STATE observer with an availability link.

The package preserves all presentation links and the native observer. The
shutdown consumer delta changes only its action target from native STATE to the
existing facade STATE while retaining its availability condition.

## Proposed Gates

1. Freshly verify wrapper, consumer, runtime, target and ownership hashes.
2. Activate the wrapper and consumer delta atomically without executing either
   consumer or a device action.
3. Reconcile twice and verify idempotency, facade/target equality, diagnostics,
   links and the complete wrapper inventory.
4. In a separately approved presence-bound test, exercise STATE and brightness
   first, then Kelvin and color individually with exact restoration.
5. Separately observe one physical hard-off/hard-on cycle. The expected offline
   result is unavailable plus retained last STATE, not a synthetic off value.

No live source, object, Alexa, scene or device change was made by this gate.
