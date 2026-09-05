# Gate 90-A — target-allowlist installation Windows qualification

**Status:** The repository-bound Windows PowerShell 5.1 qualification of the
existing channel-v8 initializer passed for an empty-to-one OwnTracks target
installation and its automatic rollback; live target installation and every
channel or module operation remain closed, 2026-09-05.

## Scope

This separately authorized gate qualifies the existing deployment-channel
initializer with the corrected OwnTracks adapter entirely in protected,
randomly named Windows temporary trees. It neither invokes the installed
channel nor changes its empty standalone-module target allowlist.

The final private bundle is bound to repository commit
`bb12ddff5820f8e2743cc8b20ec1e15d407c3c52`, initializer SHA-256
`dbb1bd82de29d1bb490ae2978ef2536c4894ec37587ad2cdb9a804d5041a26d4`
and corrected OwnTracks adapter SHA-256
`d101baadfc120b5092a7621812b66fdfa87692554f20c6eca18fb2f06b3e0e62`.
The production initializer and adapter remained unchanged during this gate.

The harness redirects the initializer's SSH configuration and authorized-key
destinations into its scratch tree. Both OpenSSH restart sites become verified
no-op markers and only the real `sshd -t` process invocation is bypassed.
Removing these exact test substitutions must recreate the checksum-protected
production initializer byte-for-byte.

## Harness correction

Two earlier private revisions failed closed during synthetic adapter preflight
at `path_ownership`, before the initializer scenarios or any live contact. The
production adapter already contained the Gate-84 correction that orders module
paths with `StringComparer.Ordinal`. The reused Gate-83 harness still duplicated
the older culture-dependent `Sort-Object` calculation, so German Windows and
the current adapter derived different identities for the same mixed-case module
tree.

The final harness uses the same explicit ordinal relative-path array as the
production adapter. It also creates a new non-inheriting scratch DACL containing
only the current operator, `SYSTEM` and the local Administrators group. This is
a private test-harness correction, not a production adapter change.

## Result

The approved elevated Windows PowerShell 5.1 run returned exit code `0` and
reported `outcome: passed`. It passed all six scenarios:

- complete bundle checksum validation;
- Windows PowerShell 5.1 parsing;
- corrected-adapter synthetic preflight, activation and forced rollback;
- synthetic empty-to-one target installation;
- byte-exact channel-file and target-file rollback after a deterministic
  post-policy failure; and
- the negative live-channel and OpenSSH boundary.

The final initializer outcome inside the gate was deliberately `failed` with
exit code `20`: this is the injected rollback scenario, not the gate outcome.
`lastRollbackAttempted`, `lastRollbackSucceeded`, `rollbackObserved`, the exact
initializer instrumentation-diff check and scratch cleanup all reported true.
The corrected adapter independently reported `passed`, exit code `0` and no
failure code.

The wrapper reported no installed-channel or target-allowlist mutation, live
OwnTracks-state or Symcon contact, actual OpenSSH restart, module preflight or
activation, provider contact or publication. The synthetic scratch tree was
removed successfully. No private path, account, host identity, ObjectID,
credential, coordinate, tracker identifier or movement history is recorded
here.

## Decision boundary

Gate 90-A proves that the current production initializer can install exactly
one hash-bound OwnTracks target and restore the prior synthetic channel after a
failure. It does not authorize or establish a live installation.

The remaining sequence stays separately gated:

1. prepare and review a fresh live-installation wrapper that preserves the
   existing channel values and proves its exact one-target delta;
2. explicitly authorize the live initializer run, including the bounded
   OpenSSH restart;
3. independently verify the installed allowlist and run channel `probe`;
4. keep inactive package `stage`, module `preflight`, `activate`, independent
   health and Safari acceptance, retention, publication and cleanup as separate
   gates.

Gate 90-A authorizes none of those later actions.
