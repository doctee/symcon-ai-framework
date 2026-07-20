# Managed Runtime Mirror Live Activation

**Gate:** One existing non-action-path mirror script
**Result:** PASS — GENERATED MIRROR ACTIVATED
**Date:** 2026-07-20
**Live impact:** Content-only update of the existing visible mirror

## Scope

This gate replaced the manually assembled ControlLight mirror-pilot source with
the deterministic output of `ControlLightRuntimeMirror::render()`. It did not
change the authoritative runtime file, a wrapper, event, variable, autoload
mapping or device.

The existing object remained the owned target. Its private ID, parent and live
reference index are recorded only in the excluded activation evidence.

## Preflight

Immediately before the write, the gate verified:

- stable script type, parent, Ident and presentation metadata;
- byte-exact equality of the current source with the private rollback backup;
- presence and readability of the authoritative live runtime file;
- equality of the live runtime with its pinned private hash;
- deterministic extraction, sorting and deduplication of the complete private
  reference index; and
- byte-exact equality between the generated payload and the live runtime.

The generated candidate was produced locally by the reviewed PHP implementation,
not reconstructed by the live mutation channel.

## Activation and rollback boundary

Exactly one script-content write was attempted. Direct readback then matched the
generated candidate byte-for-byte. Script ID, type, parent, Ident, visible name,
position, icon and visibility remained unchanged.

The previous complete source stayed available as the rollback target throughout
the transaction. No invariant failed, so rollback was not required.

The generated executable preamble contains only the normalized reference index,
its provenance hashes and `__halt_compiler()`. The complete live runtime follows
the halt marker and therefore remains visible but inert.

## Reference-search acceptance

The installation's optional console-reference function was feature-detected in
a bounded, read-only postflight probe. Four representative private dependencies
covering wrapper, local variable, target and legacy-core roles each returned
exactly one match in the managed mirror. Transport and PHP execution completed
without error or truncation.

This search remains an acceptance aid only. Direct script readback and hashes
are the deployment verification; the undocumented function is not a runtime
dependency.

## Repository/live source gate

The mirror correctly embeds the currently deployed live runtime. During the
same repository work, a static-analysis precision cleanup removed redundant
null-coalescing fallbacks from the canonical ControlLight source without
changing its intended behavior. Consequently, the newly generated repository
fileset is one source revision ahead of the live runtime.

That difference is intentional for this narrowly scoped mirror activation. A
future live fileset update must deploy the current authoritative runtime and
reconcile the mirror in the same gated transaction so that the mirror cannot
remain stale after the runtime changes.

## Result

The pilot has progressed from a manually assembled feasibility object to a
deterministically generated managed runtime mirror. It remains visible and
outside every ControlLight action path.

This is still the first independent use case. The public-helper promotion gate
defined by ADR-0006 and EK-007 remains closed until a second use case validates
the same ownership and reconciliation contract.
