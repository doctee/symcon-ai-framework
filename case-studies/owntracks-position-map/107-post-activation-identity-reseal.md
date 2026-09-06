# Gate 107 — post-activation active-package identity reseal

**Status:** Repository implementation, offline contract verification and
Windows PowerShell 5.1 qualification passed; both live reseal gates remain
separate, 2026-09-06.

## Observation

The first OwnTracks activation whose package identity differed from the
previous active package completed successfully. Independent health verified
the new active tree, the previous rollback tree, the activation transaction,
the single configured instance and format-2 runtime state.

The private adapter policy correctly remained pinned to the package identity
that had been active when the transaction began. That pin is a fail-closed
administrative trust anchor: it proves the expected rollback baseline, but it
also means a later module preflight must not proceed until an administrator
reviews and reseals the newly active identity.

The module adapter must not rewrite its own hash-pinned trust anchor. Doing so
would collapse the separation between a remotely invocable target operation
and the local administrator who controls the target allowlist.

## Reseal contract

Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1 adds a target-specific
administrative operation without adding a sixth deployment-channel verb.

Its read-only preflight:

- binds the exact installed channel-policy hash;
- requires the unique hash-pinned OwnTracks target and private adapter policy;
- keeps its status evidence outside all managed channel, state and module roots;
- acquires the channel mutex before the OwnTracks adapter mutex;
- verifies the completed activation status, adapter status and manifest;
- verifies the active adapter record and retained transaction;
- recomputes the active, staged and rollback package identities with ordinal
  path sorting; and
- produces the exact proposed adapter-policy and channel-policy hashes.

Apply additionally requires elevation and the exact confirmation phrase. It
changes only expectedActivePackageIdentitySha256 in the private adapter policy
and the resulting expectedAdapterPolicySha256 in the channel target binding.
Both files are written atomically. Any recoverable failure restores their
previous bytes and reports rolled_back; an unprovable rollback reports
manual_recovery_required. Every failed status includes the exception message,
type and HRESULT needed to distinguish a contract, ACL and filesystem failure
without weakening the fail-closed result.

The command has no module reload, Symcon RPC, service restart, state movement,
provider request, publication or cleanup call site. The active module,
adapter-owned transaction history and rollback package remain unchanged.

## Windows qualification

The private Windows gate passed checksum and Windows PowerShell 5.1 parsing,
read-only preflight, missing and wrong confirmation, channel-mutex contention,
the exact two-field reseal and byte-exact automatic rollback scenarios. Its
negative boundary confirmed that it did not touch the installed channel,
target allowlist, live adapter policy, OwnTracks state, Symcon, a service or a
provider.

## Remaining gates

1. run a read-only live preflight and review its exact proposed hashes;
2. perform the separately authorized live reseal;
3. repeat independent channel and adapter readiness checks; and
4. retain the active and rollback transactions. Publication and cleanup remain
   separate.
