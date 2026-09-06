# Gate 90-H — adapter-state Windows qualification

**Status:** The corrected OwnTracks adapter-state initializer passed its repeat
protected Windows PowerShell 5.1 qualification, including a real hidden
ancestor. Live state-root provisioning and every module operation remain
closed, 2026-09-06.

## Scope

This separately authorized gate qualifies
`Initialize-SaefOwnTracksPositionMapAdapterState.ps1` entirely in a randomly
named protected Windows scratch tree. The final bundle is bound to repository
commit `39c8b4b055187d82cd96bd04cccd0bcfc6148420` and initializer SHA-256
`6b9666d23e3c2c08494b4c06f4ac4a5a4c8c660e967a8b554dd4552205bb4c22`.
The final private qualification archive has SHA-256
`e677604709720dcb2c263a52d56afee0f181b46be29a5dde35a5310c6352243d`.

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

## Hidden-ancestor correction and repeat qualification

The later read-only live preflight stopped before reading the installed target
binding. A local Windows probe established that the complete channel ancestor
chain existed, contained no reparse point and differed from the passing bundle
chain only by the hidden standard `ProgramData` directory. The initializer's
directory validator used `Get-Item` without `-Force`, which cannot reliably
inspect that hidden ancestor in Windows PowerShell 5.1.

The correction adds `-Force` only to that read-only attribute inspection. It
does not create an object, follow or permit a reparse point, change an ACL,
contact Symcon or weaken any path boundary. The repeat qualification placed all
synthetic scenarios below a scratch directory carrying the real Windows
`Hidden` attribute. The corrected initializer traversed that ancestor while
retaining the existing reparse-point rejection. The failed earlier live wrapper
created only its private evidence and reported every channel, state-root,
module, service, provider and publication mutation flag as false.

## Result

The approved elevated Windows PowerShell 5.1 run returned process exit code
`0`, `outcome: passed`, `currentPhase: completed` and no failure code. All ten
scenarios passed:

- bundle checksum validation;
- Windows PowerShell 5.1 parsing;
- non-mutating absent-root preflight;
- inspection through the real hidden scratch ancestor;
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
