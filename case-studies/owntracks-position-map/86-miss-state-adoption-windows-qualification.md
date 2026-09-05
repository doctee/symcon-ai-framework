# Gate 86 — miss-state adoption Windows qualification

**Status:** Windows PowerShell 5.1 qualification passed against an exclusively
synthetic protected tree; all live-state, allowlist, installation and module
activation gates remain closed, 2026-09-05.

## Scope

This gate qualifies the format-1-to-format-2 adoption adapter from source
commit `fc1bf7f9007796897e292757f3abcef662110e0f`. It may create and remove only a
randomly named, adapter-owned tree below the Windows temporary directory.

The private wrapper is prohibited from reading or changing the installed SAEF
channel, its target allowlist, the active OwnTracks package, authoritative
runtime state or Symcon configuration. It contains no RPC, PHP, SSH, provider,
publication or module-reload path.

## Qualification design

The bundle pins the production adapter, adoption contract and reviewed runtime
store by SHA-256. After validating the bundle it executes byte-verified copies
inside a protected synthetic tree. Three independent scenarios prevent a
successful adoption from influencing the lock and rollback evidence:

1. a preflight followed by a successful lossless adoption;
2. an independently held Windows byte-range lock followed by bounded rejection
   and successful recovery after release; and
3. a fresh preflight followed by a deterministic post-replacement fault and
   automatic rollback.

The rollback fault exists only in a temporary instrumented adapter copy. The
wrapper verifies that removing the single injected throw line reproduces the
checksum-protected production adapter exactly. This exercises the real backup,
atomic replacement, catch and rollback path without adding a production fault
switch.

## Result

The approved elevated Windows PowerShell 5.1 run returned exit code `0` with no
parser errors and passed:

- bundle checksum validation;
- Windows PowerShell 5.1 parsing;
- non-mutating synthetic preflight;
- lossless synthetic format-1-to-format-2 adoption;
- lock-contention rejection and post-release recovery; and
- byte-exact automatic rollback after the forced post-replacement failure.

The final scenario reported adapter outcome `rolled_back`, failure boundary
`state_replace`, adapter exit code `30` and channel-observed process exit code
`30`. The instrumented-copy difference check and rollback observation both
passed. The synthetic scratch tree was removed successfully.

The wrapper reported no attempt to mutate the installed channel or target
allowlist, contact live OwnTracks or Symcon, reload a module, contact a provider
or publish an artifact. No private path, host identity, ObjectID, credential,
coordinate, tracker identifier or movement history is retained here.

## Remaining gates

1. Materialize a fresh private live adoption plan and run only the adoption
   adapter's `preflight` operation. This requires a separate authorization
   because it reads the authoritative miss state, active package and runtime
   locks, although it must not change them.
2. Review the reported source, candidate, semantic and active-package hashes;
   authorize the actual `adopt` operation separately.
3. Repeat the target-allowlist preflight and require
   `adapterPreflightReady: true` before considering target installation.
4. Keep target installation, channel `probe`, inactive `stage`, module
   `preflight`, `activate`, independent UI/Safari health, retention,
   publication and cleanup as separate gates.

Gate 86 adds no public helper or general migration API. It does not install a
Windows target, change a live state, reload a module, contact a provider,
publish or delete retained historical evidence.
