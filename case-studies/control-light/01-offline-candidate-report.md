# ControlLight v2 Offline Candidate Report

**Status:** Candidate implemented and verified offline
**Live state:** Unchanged

## Inventory Baseline

The read-only baseline contains:

- 29 wrappers and one shared legacy core;
- 25 Z2M, three Matter and one Homematic configuration;
- 16 distinct wrapper variants;
- 28 configured targets and one template target;
- 80 existing local variables, 82 owned events, four external trigger events
  and 54 external links;
- one authoritative-feedback pilot;
- one Auto-Off-dependent instance and one exporter-dependent pilot group.

The detailed mapping remains in the ignored private manifest. The repository
fixture contains only sanitized keys and aggregate contracts.

## Implemented Decisions

1. The candidate is versioned and independent of the shared legacy core, so a
   future rollout can migrate and roll back one wrapper at a time.
2. Configuration and transformations are side-effect free in
   `ControlLightCore`.
3. Runtime object handling composes existing SAEF helpers. Missing or
   incompatible objects fail explicitly rather than being recreated.
4. Existing presentation metadata is preserved.
5. Device actions use `RequestAction()`; local and diagnostic state alone uses
   `SetValue()`.
6. Confirmation is authoritative, bounded and tolerant only where the target
   representation requires it.
7. A per-wrapper semaphore prevents concurrent commands for the same light
   while preserving parallelism between different wrappers.
8. Error history is bounded and diagnostics use Registry, Statistics,
   ConfigurationHash and ErrorRingBuffer.
9. Obsolete owned events are deactivated, not deleted, in the candidate phase.
10. Brightness semantics are explicit and cannot remain `pending` at runtime.

## Residual Work Before Live Preflight

- Add the canonical EnsureLink helper to the installed fileset and verify its
  source hash.
- Convert the private manifest into a hash-locked per-wave preflight input.
- Decide brightness semantics for every wave.
- Add downstream fixtures for the Auto-Off and both exporter consumers.
- Build backup, activation, verification and rollback scripts modeled after the
  successful hallway-light pilot transaction.

No live execution, source replacement, event change or variable action was
performed while producing this candidate.
