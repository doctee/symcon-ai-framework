# Gate 90-H — adapter-state Windows qualification

**Status:** The exact OwnTracks adapter-state initializer passed its protected
Windows PowerShell 5.1 qualification, including absent-root preflight,
installation, idempotence, mutex contention and automatic rollback. Live
state-root preflight, provisioning and every module operation remain closed,
2026-09-06.

## Scope

This separately authorized gate qualifies
`Initialize-SaefOwnTracksPositionMapAdapterState.ps1` entirely in a randomly
named protected Windows scratch tree. The final bundle is bound to repository
commit `e92215abfe7e330abcce2223b9d43a109e3be33e` and initializer SHA-256
`e9ec3789cfdd1fdf6d9ae1265b9c7e38662061933c52b0065c7d97c617d18e3a`.
The final private qualification archive has SHA-256
`f4d59d25740719b4d78f3e30089e35f5309b631f34085d4d43db4d0e13376dab`.

The wrapper neither reads nor changes the installed deployment channel,
target allowlist, active or staged OwnTracks package, live OwnTracks state,
Symcon service, Module Control or a provider. Its only mutations are inside
the disposable scratch tree.

## Fail-closed harness correction

The first private harness revision passed its checksum and Windows parser
checks, then failed before any directory creation with the initializer's
coarse `contract` stage. A repository correction divided that stage into fixed,
path-free failure codes without changing any validation or mutation behavior.
The second run isolated the failure to `status_ancestor`; it again removed the
scratch tree and crossed no live boundary.

The positive test tree had been placed below the Windows temporary directory.
That environment was incompatible with the initializer's strict requirement
that the complete status-path ancestor chain contain no reparse point. The
final private harness therefore placed its random tree directly below the
extracted bundle after independently checking that complete ancestor chain.
The production initializer and its reparse-point rejection remained unchanged.
Earlier private bundles were retained rather than rewritten.

## Result

The approved elevated Windows PowerShell 5.1 run returned process exit code
`0`, `outcome: passed`, `currentPhase: completed` and no failure code. All nine
scenarios passed:

- bundle checksum validation;
- Windows PowerShell 5.1 parsing;
- non-mutating absent-root preflight;
- missing and incorrect confirmation rejection;
- protected state-root installation;
- idempotent verification of the existing root;
- bounded mutex-contention rejection and recovery;
- automatic removal of the still-empty root after an injected post-ACL fault;
  and
- the negative installed-channel, module and provider boundary.

The final nested initializer outcome was deliberately `rolled_back` with
failure stage `state_root_acl` and exit code `30`. This is the injected
rollback scenario, not the gate outcome. The wrapper verified the exact
instrumentation difference, observed rollback and removed the complete
scratch tree.

Every installed-channel, allowlist, live-state, Symcon, reload, module,
provider and publication attempt flag remained false. No private path, account,
host identity, ObjectID, credential, coordinate, tracker identifier or movement
history is recorded here.

## Decision boundary

Gate 90-H proves only the Windows behavior of the exact state-root initializer.
It neither validates the current private live policy nor creates the live root.

The remaining sequence stays separately gated:

1. run the exact initializer in read-only `preflight` mode against the private
   live adapter policy;
2. separately authorize creation of the one reviewed live state-root leaf;
3. rerun the channel-bound module preflight; and
4. keep activation, independent health and Safari acceptance, retention,
   publication and cleanup as later gates.

Gate 90-H authorizes none of those later actions.
