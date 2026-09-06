# Gate 90-J — adapter-state live installation

**Status:** The separately authorized OwnTracks adapter-state initializer
created and verified exactly the previously reviewed empty live state-root
leaf. The installed deployment channel, target binding, module packages and
Symcon runtime remained unchanged, 2026-09-06.

## Scope

The private installation archive was bound to repository commit
`8e6c811ffbb1438c2f62ecbe846caaaf181c9612`, the Windows-qualified initializer
SHA-256
`6b9666d23e3c2c08494b4c06f4ac4a5a4c8c660e967a8b554dd4552205bb4c22`
and the passed Gate 90-I preflight archive SHA-256
`8c541350b3aa4d00b5082db6eaf8f0f2b6dfc6abcaa4ff1152d7925b6544f095`.

The wrapper repeated the installed single-target, adapter, private-policy,
active-module path and prospective state-root checks. It then ran the exact
initializer once with its explicit installation confirmation. The sole
permitted live mutation was creation of the absent configured leaf and
application of the non-inheriting `SYSTEM`, Administrators and deployment-
account ACL contract. A final initializer `preflight` had to accept that same
root without rewriting it.

## Result

The elevated 64-bit Windows PowerShell 5.1 run returned process exit code `0`,
wrapper `outcome: installed`, `confirmationVerified: true` and all nine
scenarios passed. The state root was absent before the run and present after
it. The installation initializer reported `installed`; the final read-only
postflight reported `already_present`. The root remained empty and no rollback
was required.

The final `failureStep` value names the last successfully evaluated postflight
assertion. It is not a failure when `failureCode` is `none` and the overall
outcome is `installed`. Likewise, `lastInitializerOutcome: already_present`
is the final read-only idempotence check, not a second installation.

The channel policy, pinned adapter and private policy retained their expected
hashes. Every installed-channel, target-allowlist, active-module, Symcon,
Module Control, OpenSSH, provider, publication and cleanup mutation flag
remained false. No private path, account, host identity, ObjectID, credential,
coordinate, tracker identifier or movement history is recorded here. The
private installation evidence remains retained and the one-time bundle must
not be reused.

## Decision boundary

Gate 90-J provisions only the adapter-owned prerequisite. It does not prove
the remaining live ownership, quiescence, configuration/state or health
checks, rerun channel-bound module preflight or activate the staged package.

The remaining sequence stays separately gated:

1. rerun the channel-bound module `preflight` against the unchanged inactive
   staged deployment;
2. inspect and document any later fail-closed adapter precondition;
3. separately review and authorize activation only after a complete successful
   preflight; and
4. keep independent health/Safari acceptance, rollback retention, publication
   and cleanup as later gates.

Gate 90-J authorizes none of those later actions.
