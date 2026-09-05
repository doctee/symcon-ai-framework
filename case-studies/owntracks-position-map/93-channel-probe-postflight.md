# Gate 90-D — installed-target channel probe

**Status:** The external deployment-channel probe passed with channel version
8 and exactly one structurally and cryptographically valid standalone-module
target; package staging and every OwnTracks adapter mutation remain closed,
2026-09-05.

## Scope

This separately authorized gate invoked exactly one `probe` through the pinned
SAEF SSH client and private host alias after the successful
[Gate 90-C](92-target-allowlist-live-installation.md) target installation. It
did not stage a package or invoke a standalone-module adapter operation.

Within this channel contract, `probe` is deployment-read-only rather than
strictly execution-free. The gateway takes its channel mutex, validates the
installed channel policy and all allowlisted target dependencies, and invokes
the hash-bound restart coordinator in `PreflightOnly` mode. That coordinator
performs loopback Symcon RPC readiness checks and the configured hash-bound
runtime-health script check, then writes the bounded channel-probe diagnostic
status. It does not restart OpenSSH or Symcon, reload a module or alter an
OwnTracks package.

## Result

The approved macOS client call completed in approximately five seconds and
returned process exit code `0`. The forced-command gateway returned
`success: true`, `operation: probe`, `outcome: ready` and response exit code
`0`. It reported:

- channel version 8;
- the fixed five-operation command surface: `probe`, `stage`, `preflight`,
  `activate` and `status`;
- the two supported deployment kinds: runtime fileset and standalone module;
  and
- exactly one installed standalone-module target.

Before returning that count, the installed gateway validates every target for
unique safe identity, bounded regular non-reparse dependency files, pinned
adapter and policy hashes, adapter-profile identity and policy structure. The
single target therefore passed the independent installed-channel validation
path after the exact zero-to-one installer postflight.

The public response disclosed no target path, host, account, key, credential,
RPC endpoint, ObjectID, coordinate, tracker identifier or movement history.
No provider was contacted.

## Decision boundary

Gate 90-D proves external reachability and readiness of the installed channel
plus structural and cryptographic validity of its single allowlisted target.
It does not execute the OwnTracks deployment adapter itself and does not
qualify a package candidate.

The remaining sequence stays separately gated:

1. build and review the exact inactive OwnTracks package candidate;
2. authorize only its bounded channel `stage` transfer;
3. run target-bound module `preflight` separately;
4. keep `activate`, independent health and Safari acceptance, rollback
   retention, publication and cleanup as later gates.

Gate 90-D authorizes none of those later actions.
