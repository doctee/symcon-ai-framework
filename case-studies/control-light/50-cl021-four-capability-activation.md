# CL-021 Four-Capability Activation

**Gate:** Post-release non-commanding wrapper migration
**Result:** PASS — DEVICE TEST PENDING
**Date:** 2026-07-24
**Live impact:** Wrapper source and owned diagnostics changed; no device command

## Scope

The migration selected one retained single-device Z2M ControlLight contract
with STATE, brightness, color-temperature and color capabilities. The existing
container, wrapper, target link, four local variables, four feedback events and
three presentation links remained in place.

One separate automation writes target brightness directly and another observes
target STATE. Both multi-controller dependencies were baselined and remained
outside the transaction. No Auto-Off consumer depends on this contract.
Installation-specific names, ObjectIDs, source backups and exact live evidence
remain in the private overlay.

## Contract Decision

The wrapper now selects the immutable current ControlLight v2 runtime with:

- explicit Z2M capability mapping;
- authoritative feedback;
- `reported` brightness semantics;
- bounded three-second feedback confirmation;
- per-wrapper serialization; and
- availability-aware timeout classification.

With `reported` semantics, the facade retains the device's reported brightness
while STATE is false. This matched the pre-activation local and target values
and the reviewed consumer behavior.

## Preparation and Preflight

The private package contained a hash-locked wrapper candidate, byte-exact
legacy rollback source, complete topology and presentation baselines, the four
existing event identities, consumer hashes and a fixed diagnostic allowlist.

The fresh read-only preflight immediately before activation verified:

- ready kernel and exact immutable runtime identities;
- the exact legacy wrapper source;
- all 29 wrapper source identities;
- operational and actionable target variables;
- equal local and authoritative STATE, brightness, color-temperature and color
  values;
- target availability;
- existing archive and presentation contracts; and
- unchanged direct-writer and state-observer consumers.

No live object or device changed during the preflight.

## Activation

After explicit approval, the bounded activation:

1. replaced only the selected wrapper source;
2. executed two non-commanding reconciliations;
3. reused all four local variables and all four feedback events;
4. changed only the STATE feedback event from change-triggered to
   update-triggered feedback;
5. created the ten allowlisted script-owned Registry, Statistics and
   ErrorRingBuffer variables; and
6. repeated the complete topology and all-wrapper regression.

Both reconciliations succeeded. Diagnostics recorded two executions and two
successes, with zero commands, errors and confirmation timeouts. All local and
target values remained equal and unchanged. Names, positions, visibility,
profiles, actions, archive logging, target and presentation links, event
identities and both known consumers were preserved. The second run created no
additional object and rollback was not required.

## Evidence Closure

The private machine-readable activation evidence records the authorization,
package and source hashes, exact initial and final values, diagnostic and event
identities, regression result and separate MCP transport, PHP execution and
truncation channels.

This sanitized report advances the current installation fixture to nine active
v2 contracts with explicit `reported` semantics and 20 retained legacy
contracts. Historical release and dated readiness reports remain unchanged.

## Gate Decision

The selected contract is safely activated on ControlLight v2, but it is not yet
classified as fully device-tested. A separately approved bounded test must
exercise STATE, brightness, color temperature and color through the local
ControlLight facade, verify authoritative feedback and restore the exact
initial state.
