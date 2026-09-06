# Gate 83 — channel-v8 Windows adapter qualification

**Status:** Windows PowerShell 5.1 synthetic transaction and Symcon-PHP lock
interoperability complete; installation and live module gates remain closed,
2026-09-05.

## Scope

This separately authorized gate qualifies the Gate-82 OwnTracks adapter on the
target operating-system family without installing the deployment channel,
changing its target allowlist or activating a module package. The public
adapter and transaction contract stayed unchanged during the qualification.

The private Windows harness contains installation-specific execution evidence.
Only its sanitized result is recorded here. No user path, host identity,
ObjectID, credential, coordinate, tracker identifier, movement history,
temporary challenge or private endpoint is committed.

## Evidence

The final bounded run passed all of these scenarios:

- deterministic bundle checksums;
- Windows PowerShell 5.1 parsing;
- synthetic adapter `preflight`;
- synthetic adapter `activate`;
- forced synthetic rollback with matching process and status exit codes; and
- integrated Symcon-PHP `flock()` interoperability with Windows range locks.

The lock scenario used a fresh challenge-bound scratch directory with a
protected DACL. Symcon PHP acquired the adapter's real lock file, the adapter
failed closed at quiescence while the lock was held, the harness released the
lock, and the following preflight succeeded. The Windows harness then verified
the evidence contract and removed the complete scratch directory.

An earlier private harness revision incorrectly depended on
`set_time_limit()`, which is not exposed by the integrated Symcon PHP runtime.
Removing that unnecessary harness-only call left its own 20-second deadline in
place. A focused probe had already established that file creation, non-blocking
exclusive `flock()`, atomic evidence publication, unlock and cleanup work in
the same runtime. No PHP CLI installation is required by the final scenario.

The final gate status reported `outcome=passed`, exit code `0`, no failure code,
successful scratch cleanup and matching adapter process/status exit codes.
Symcon contact was limited to the explicitly approved temporary PHP handshake.

## Security and mutation boundary

The qualification did not:

- install the channel or its OwnTracks target;
- add or change a target allowlist entry;
- stage, replace, reload or activate the live OwnTracks module;
- change an OwnTracks instance, configuration, runtime state, archive or
  visualization;
- contact a tile or routing provider; or
- publish, push, merge or delete retained artifacts.

Passing this gate establishes Windows execution and lock semantics for the
adapter. It does not establish target ownership, live package health or UI
acceptance.

## Remaining gates

Each remaining operation needs its own explicit authorization:

1. create the private target policy and run the target-allowlist initializer
   preflight;
2. install the target-bound channel files;
3. run the channel `probe`;
4. transfer and verify an inactive candidate with `stage`;
5. run the module adapter's read-only live `preflight`;
6. explicitly `activate` the candidate and verify rollback readiness;
7. perform independent Symcon health and Safari/iPhone/iPad acceptance; and
8. run retention `plan`, with any later `apply` remaining separate.
