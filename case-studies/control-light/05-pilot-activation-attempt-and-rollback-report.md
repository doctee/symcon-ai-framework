# ControlLight v2 Pilot Activation Attempt and Rollback Report

**Gate:** Pilot wrapper transition and runtime verification
**Result:** FALSE-NEGATIVE VERIFICATION — ROLLBACK PASS
**Date:** 2026-07-19
**Current live state:** Legacy wrapper restored; v2 fileset remains inactive

## Outcome

The explicitly authorized pilot wrapper transition reached the v2 runtime and
completed one successful initial synchronization. Runtime diagnostics recorded:

- one execution;
- one success;
- zero device commands;
- zero errors.

Authoritative state, reported brightness and temperature remained equal, and
the existing presentation and event contracts were not intentionally changed.

The activation verifier nevertheless rejected the run because it attempted to
observe the v2 class from a separate script context. IP-Symcon does not expose
that class across these isolated execution contexts. The failure was therefore
a verifier false negative, not a ControlLight runtime failure.

## Rollback

The automatic rollback immediately restored and executed the exact approved
legacy wrapper. Its first diagnostics cleanup stopped safely without deleting
anything because it read a parent field that is not provided by the live
`IPS_GetObject()` response.

A bounded follow-up inventory established that the only additional children
were the ten expected v2 diagnostics variables. Cleanup then required all of
the following before deletion:

- direct membership below the pilot wrapper through `IPS_GetParent()`;
- variable object type;
- exact diagnostics Ident allowlist;
- complete allowlist equality with no unknown child.

All ten diagnostics were removed. An independent readback then confirmed:

| Check | Result |
| --- | --- |
| Approved legacy wrapper source | Restored |
| Original three wrapper event children | Restored exactly |
| v2 diagnostics | Absent |
| Event trigger and action contracts | PASS |
| Local variable action ownership | PASS |
| Authoritative feedback equality | PASS |
| Versioned fileset | Present but inactive |
| Global bootstrap selection | Unchanged |

No device action and no service restart occurred.

## Corrective Changes

The private transaction package now:

1. no longer treats cross-context class visibility as an activation gate;
2. resolves ownership with `IPS_GetParent()` rather than a nonexistent object
   response field;
3. retains the exact source, fileset, event, diagnostics, zero-command and
   authoritative-feedback checks.

## Gate Decision

Rollback recovery is **PASS**. The installation is back at the pre-activation
ControlLight state, with the canonical v2 fileset still staged and inactive.

The successful v2 runtime evidence supports a corrected retry, but the previous
activation authorization is consumed. Any retry requires a new complete
read-only preflight and a new explicit user approval.
