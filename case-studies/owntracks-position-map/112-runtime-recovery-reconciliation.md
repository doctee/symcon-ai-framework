# Gate 112: Runtime recovery reconciliation

**Status:** Repository reconciliation implemented; Windows PowerShell 5.1,
private policy, installed-adapter and live recovery gates remain closed,
2026-09-11.

## Finding

The exact OwnTracks restart-recovery implementation and its successful live
evidence existed on a retained recovery branch, while the later scope-bound
approval integration was developed from a parallel repository line. The
recovery commit was therefore never an ancestor of the current `main`; it was
not deliberately removed from a shared history.

A bounded one-time debug capture subsequently observed a runtime guard message
that is absent from both the expected active package and current repository
source. Configuration remained byte-identical, the sole instance remained
status `200`, and no pending changes or references were present. This supports
a loaded-runtime/source ownership mismatch, but does not by itself authorize a
reload or package activation.

## Reconciliation decision

The earlier recovery behavior is composed into the current OwnTracks adapter
instead of restoring the historical adapter wholesale. The current fixed
`preflight`, `activate`, `postflight`, `inspect` and `rollback` operations,
scope-bound approval runner, secure child-process contract and active-identity
reseal remain authoritative.

Normal operation still accepts exactly status `102`. A private recovery block
can be armed only when all of these values match:

- the active-package trust anchor and exact recovery source identity;
- the staged deployment ID and exact recovery deployment ID;
- the candidate package and exact recovery package identity; and
- the sole source status `200` with target status `102`.

Recovery changes no configuration, object metadata, runtime-state, lock,
lease, package-ownership or rollback requirement. The adapter records the mode
in all new activation evidence. Independent postflight and post-success
rollback require the three records to agree; legacy records without the field
remain normal-only for compatibility. Resealing the active trust anchor to the
repaired package makes the source-bound recovery block inert without deleting
the retained transaction facts needed for rollback.

## Reuse before extend

No helper, public API, gateway verb or general module-recovery abstraction is
added. The target-specific adapter already owns package identity, instance
health, targeted Module Control reload, quiescence and byte-exact rollback.
Extending that existing responsibility is narrower than teaching the generic
gateway about OwnTracks status semantics.

## Required gates

1. Run the exact adapter and integrated approval scenarios under Windows
   PowerShell 5.1 without production mutation.
2. Materialize a private recovery policy from fresh read-only identities and
   bind only the reviewed inactive candidate.
3. Reinstall the exact hash-pinned adapter/profile only after backup review.
4. Repeat channel and adapter preflight read-only.
5. Review the server-generated scope-bound plan.
6. Authorize one exact activation separately.
7. Verify independent Symcon health before active-identity reseal.
8. Keep provider contact, service restart, publication and retention deletion
   outside this recovery sequence.

No live Symcon call, package activation, provider request, service restart,
policy installation, publication or cleanup occurs in this repository gate.
