# CL-003 Kürbis Command-Free Activation

**Date:** 2026-08-03
**Gate:** Explicitly approved wrapper and shutdown-consumer activation
**Result:** PASSED — FUNCTIONAL AND HARD-POWER TESTS PENDING

## Activation

A fresh preflight found the physically switched luminaire powered, available
and recently seen. Wrapper, global shutdown consumer, immutable runtime,
facade ownership, four presentation links and the foreign native warning
observer matched their private rollback contract. The alarm interlock was
inactive and every current facade value equalled its target value.

Byte-exact wrapper and consumer backups were written and hash-verified on the
private Symcon host. The wrapper was replaced by the authoritative v2 contract.
The only consumer delta changed the guarded shutdown action from native STATE
to facade STATE; its existing target-availability condition was retained.
Neither consumer nor any device action was executed.

Two separate wrapper reconciliations passed after source readback. They
produced two executions, two successes, zero commands, zero errors and zero
confirmation timeouts.

## Independent Postflight

The postflight confirmed unchanged STATE=true, brightness=100, color and
availability, as well as unchanged zero Kelvin values. All four facade and
feedback-event identities, names, positions, profiles and actions were
preserved. The four presentation links and native STATE/availability observer
were unchanged. No Alexa or SceneControl contract exists for this luminaire.

The complete current 29-wrapper set was checked as present and non-empty. The
first regression covered the original 28-wrapper cohort after Dummy retirement;
the later CL-030 adapter wrapper was then verified separately and included in
the total.

## Remaining Gates

CL-003 is active but not yet fully device-tested. STATE and brightness should
be tested first with exact restoration. Kelvin remains unproven because its
target has never reported an update, and color retains the known Z2M
color/brightness feedback risk.

A separate physical hard-off/hard-on observation must expect unavailable with
retained stale STATE while power is absent. A permitted action immediately
after hard-on must be dispatched without waiting for availability to recover.
