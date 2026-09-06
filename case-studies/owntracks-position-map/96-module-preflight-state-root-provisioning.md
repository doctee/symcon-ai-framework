# Gate 90-G — module preflight and state-root provisioning correction

**Status:** The first target-bound OwnTracks module preflight failed closed on
the missing adapter-owned transaction/rollback state root. A repository-only,
non-activating provisioning command and its local contract verification are
complete. Its separate Windows qualification passed in
[Gate 90-H](97-adapter-state-windows-qualification.md); every live mutation
remains closed, 2026-09-06.

## First preflight result

The separately authorized deployment-channel `preflight` invoked the pinned
OwnTracks adapter for the already staged inactive package. The channel returned
its adapter-failure outcome without activating the package. The adapter status
reported fixed failure code `path_ownership`, `activationAttempted: false` and
no rollback attempt.

A bounded read-only Symcon-MCP diagnostic then separated the first failing
precondition without exposing installation data. The configured adapter state
directory did not exist. The active and staged module trees were plain, had the
expected 37 files and matched their independently pinned package and metadata
identities. They are therefore not the cause of this first failure. Candidate
ACL, Symcon ownership, quiescence, runtime state and health checks occur later
in the adapter and were not reached; this result makes no claim about them.

No active module, configuration, runtime state, installed channel, target
allowlist, service or provider was changed. The inactive staged deployment
remains available for a later preflight.

## Repository correction

`Initialize-SaefOwnTracksPositionMapAdapterState.ps1` now owns the missing
provisioning boundary. It deliberately is not called by the generic gateway or
the module adapter:

- `preflight` hash-validates the private adapter policy and checks its fixed
  target/profile, path separation, same-volume boundary, complete plain parent
  chain, deployment account, protected parent ACL and shared adapter mutex;
- it reports whether installation is required without creating a directory;
- `install` additionally requires an exact confirmation phrase and elevation;
- it creates only the absent configured leaf and applies the channel's existing
  non-inheriting `SYSTEM`, Administrators and deployment-account ACL pattern;
- it verifies the created path and removes only its still-empty leaf if that
  verification fails; and
- an existing root is verified but never silently rewritten.

The command has no Symcon RPC, Module Control, service, channel-policy,
target-allowlist, provider, publication or active-module mutation call site.
Its atomic status contains only bounded state facts and fixed failure stages,
never paths or account identities.

The general standalone-module contract now states that adapter-owned writable
roots are separately provisioned prerequisites. Candidate preflight remains
strictly non-mutating and must fail closed instead of inferring a private path.

## Verification

The new static contract test passed, including the single creation site,
single non-recursive rollback-removal site, preflight guard, shared ACL
primitives, fixed confirmation and absence of RPC, reload, restart or provider
operations. The complete OwnTracks test suite also passed, covering runtime,
gateway, WebHook, security, distribution, package, adapter and miss-state
contracts.

The subsequent protected Windows scratch-tree qualification in
[Gate 90-H](97-adapter-state-windows-qualification.md) proved Windows
PowerShell 5.1 parsing, localized ACL behavior, mutex contention and rollback
cleanup for the exact initializer without touching the live installation.

## Decision boundary

This gate changes repository sources only. It does not create the live state
root, rerun the module preflight, activate the staged package, contact a tile
provider, publish or remove retained evidence.

The remaining sequence stays separately gated:

1. run the exact initializer in read-only mode against the private live policy;
2. separately authorize creation of the one reviewed live state-root leaf;
3. rerun the channel-bound module preflight; and
4. keep activation, independent health/Safari acceptance and retention as later
   gates.

Gate 90-G authorizes none of those later actions.
