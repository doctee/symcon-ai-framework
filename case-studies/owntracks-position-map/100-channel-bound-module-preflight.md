# Gate 90-K — repeated channel-bound module preflight

**Status:** The repeated target-bound OwnTracks module preflight passed through
the installed SAEF deployment channel version 8 after the adapter-owned state
root had been provisioned. The staged candidate remains inactive, 2026-09-06.

## Scope

The existing deployment client addressed only the previously staged inactive
deployment `saef-owntracks-position-map-20260905-01`. A sanitized channel
status was read first, followed by exactly one `preflight` operation and one
independent status readback. No package was uploaded again and the channel's
`activate` operation was not invoked.

The preflight used the installed target binding and its pinned OwnTracks
adapter and private policy. The adapter validated the transaction contract,
protected active, candidate and state paths, both module identities, exactly
one configured module instance, fresh configuration, the shared adapter mutex,
five runtime file locks, zero active leases and the format-2 authoritative
runtime state. It contacted the configured local Symcon RPC only for the
bounded ownership, configuration and runlevel reads required by that contract.

## Result

The first sanitized status read showed the retained failed result from the
earlier missing-state-root attempt. The new `preflight` then returned channel
and process exit code `0`, `success: true` and `outcome: passed`. A separate
status request confirmed `phase: preflight`, `outcome: passed` and
`deploymentExitCode: 0` for the same standalone-module deployment.

Because the installed gateway accepts a standalone-module preflight only after
the adapter returns its exact successful status and then revalidates the
unchanged staged deployment, this closes the previous `path_ownership`
blocker and proves the remaining adapter preconditions for the current live
state. The preflight acquired and released the bounded runtime locks and wrote
only its adapter/deployment status evidence. It did not create an activation
transaction, switch a package, call `MC_ReloadModule`, change configuration or
runtime state, restart a service, contact a tile provider, publish or clean up
retained artifacts.

No private path, account, host identity, ObjectID, credential, coordinate,
tracker identifier or movement history is recorded here.

## Decision boundary

Gate 90-K proves current activation readiness for this exact inactive
candidate. It is not activation authority and does not prove post-activation
runtime or physical browser behavior.

The remaining sequence stays separately gated:

1. review the exact activation and automatic rollback boundary against this
   successful fresh preflight;
2. separately authorize one channel-bound `activate` operation;
3. perform an independent health and Safari/iPad acceptance gate while
   retaining the previous package and transaction evidence; and
4. keep publication and retention cleanup closed until their own review.

Gate 90-K authorizes none of those later actions.
