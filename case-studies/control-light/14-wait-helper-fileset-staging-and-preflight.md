# Wait Helper Fileset Staging and Preflight

**Gate:** Inactive live fileset staging and read-only CL-023 drift preflight
**Result:** PASS
**Date:** 2026-07-19
**Activation state:** Corrected fileset present but unselected

## Scope

After explicit authorization, the corrected ControlLight dependency closure
was placed beside the active version rather than overlaying it. This gate did
not change a wrapper, bootstrap selection, object, event, variable value or
device state. It did not load the corrected runtime.

## Staging Transaction

The destination name is derived from the aggregate fileset identity
`9c85e83d1664afb22d0390d77cd200329dc19d12d2d8c84c6a0a221b595d767d`.
All fourteen files were transferred independently into an isolated temporary
directory. Every transfer checked the decoded bytes before writing and the
written file before atomic file finalization.

The complete temporary tree was then checked for:

- the exact fourteen-file relative path map;
- every individual SHA-256 identity;
- manifest aggregate, bootstrap and ordered-source identities; and
- the exact aggregate marker.

Only after those checks passed was the complete directory atomically renamed
to its final hash-addressed name. The temporary directory is absent.

Two initial finalization probes stopped safely before the rename. One contained
an incorrectly transcribed manifest hash in its verifier table; the other used
an order-sensitive PHP array comparison. Neither probe changed or retransferred
the staged files. The corrected verifier normalized both maps and completed.

## Independent Preflight

A separate read-only probe then confirmed:

| Contract | Result |
| --- | --- |
| Kernel ready | PASS |
| Exact fileset and all source hashes | PASS |
| Corrected wait-helper identity | PASS |
| Temporary directory absent | PASS |
| New fileset referenced by a script | No |
| Shared legacy core unchanged | PASS |
| All 29 wrapper identities unchanged | PASS |
| CL-023 ownership and child allowlist | PASS |
| Local and target action contracts | PASS |
| Explicit state and brightness event bindings | PASS |
| Existing diagnostic ownership and counter relations | PASS |
| STATE local / target | `false` / `false` |
| DIMMER local / target | `100` / `100` |

An earlier preflight assertion incorrectly expected the same trigger type for
both feedback events. The runtime contract intentionally uses update for STATE
and change for DIMMER. A second stale assertion expected historical absolute
diagnostic counters; passive successful executions had advanced while command,
error and timeout counters remained unchanged. The final probe checked the
runtime event contract and diagnostic invariants instead.

The successful connector result reported no transport or execution error and
was not truncated.

## Gate Decision

Inactive staging and the current drift preflight are **PASS**. The new fileset
has zero live script references, so the existing two v2 wrappers continue to
use the prior helper. No device command was issued.

The next gate is a separately approved CL-023 wrapper selection followed by a
non-commanding synchronization and regression readback. A real STATE/DIMMER
device test remains a later, separately approved gate.
