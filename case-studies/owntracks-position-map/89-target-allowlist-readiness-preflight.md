# Gate 89 — target-allowlist readiness preflight

**Status:** The repeated read-only target-bound initializer preflight passed
after the format-2 state adoption; target installation and every channel or
module-operation gate remain closed, 2026-09-05.

## Scope

Gate 89 repeats the corrected [Gate 84](84-target-allowlist-preflight.md)
Windows preflight against the authoritative format-2 miss state established by
[Gate 88](88-miss-state-live-adoption.md). It reuses the already qualified
target-bound adapter policy, target definition and deployment-channel
initializer only in `PreflightOnly` mode.

The gate may update protected evidence inside its private extracted transfer
directory. It may not write the installed target allowlist, install or update
the deployment channel, restart OpenSSH or Symcon, invoke the module adapter,
stage or activate a package, reload the module, contact a provider, publish or
clean up retained evidence.

## Result

The approved elevated Windows PowerShell 5.1 run returned exit code `0`. Both
the wrapper and installed-channel initializer reported `passed`. The result
verified:

- the corrected ACL classifier and ordinal package-identity contract;
- unchanged active-package identity and the expected package file count;
- authoritative miss-state format 2;
- successful target-bound initializer preflight;
- adapter preflight readiness with no remaining blocker;
- an unchanged installed-channel policy; and
- an installed standalone-module target count of zero before and after the
  run.

The wrapper and initializer independently reported no system mutation or SSH
restart. The wrapper additionally reported no channel installation, module
preflight, module activation or provider contact. The installation-local
adapter, policy and target-definition hashes remain only in the retained
private Windows evidence.

No private path, account, host identity, ObjectID, credential, coordinate,
tracker identifier or movement history is recorded here.

## Decision boundary

`adapterPreflightReady: true` means the former format-1 prerequisite is closed
and a target installation proposal may now be prepared. It does not install a
target and does not authorize later channel or module operations.

The remaining sequence stays gated:

1. review and explicitly authorize installation of the single OwnTracks target
   in the existing deployment-channel allowlist;
2. verify the installed allowlist and run channel `probe` separately;
3. keep inactive `stage`, module `preflight`, `activate`, independent
   UI/Safari health, rollback retention, publication and cleanup as separate
   gates.

Gate 89 authorizes none of those later actions.
