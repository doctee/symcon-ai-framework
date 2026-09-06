# Gate 90-B — target-allowlist live-installation preflight

**Status:** The read-only live-installation preflight passed for the exact
zero-to-one OwnTracks target plan; target installation, OpenSSH restart and
every channel or module-operation gate remain closed, 2026-09-05.

## Scope

This separately authorized gate inspected the installed SAEF deployment
channel version 8 and prepared the exact installation plan for one
`saef-owntracks-position-map` target. It reused the initializer and corrected
adapter qualified in
[Gate 90-A](90-target-allowlist-installation-windows-qualification.md), but
invoked the initializer only in its non-mutating preflight mode.

The private bundle was bound to repository commit
`f045f62cd8c47db6fbf9e725f04cdabd7eba7bb9`, initializer SHA-256
`dbb1bd82de29d1bb490ae2978ef2536c4894ec37587ad2cdb9a804d5041a26d4`
and corrected OwnTracks adapter SHA-256
`d101baadfc120b5092a7621812b66fdfa87692554f20c6eca18fb2f06b3e0e62`.

The gate was permitted to create protected private evidence only inside the
extracted transfer directory. It was not permitted to change the installed
channel, target allowlist, public key, DPAPI credential, OpenSSH configuration,
Symcon service or active OwnTracks package.

## Result

The approved elevated Windows PowerShell 5.1 run returned exit code `0`. The
wrapper and installed-channel initializer both reported `passed`. All eight
scenarios passed:

- complete private-bundle checksum validation;
- Windows PowerShell 5.1 parsing;
- byte identity of the seven installed channel-v8 runtime artifacts;
- read-only validation of the installed public key and decryptable LocalMachine
  DPAPI credential;
- active OwnTracks policy and authoritative format-2 miss-state validation;
- initializer preflight for exactly one target;
- exact target-count planning from zero to one while preserving all 30
  non-target policy properties; and
- a negative live-mutation postflight.

The independently calculated SHA-256 of the retained private plan matched the
hash reported by the wrapper. The wrapper also confirmed that channel policy,
channel tree, public key, DPAPI credential and OpenSSH configuration remained
unchanged. The initializer reported no mutation, restart or repair. The
wrapper reported no channel or target installation, module preflight or
activation, Symcon RPC contact, provider contact or publication.

No private path, deployment-account name, host identity, ObjectID, credential,
coordinate, tracker identifier, movement history or installation-local plan
content is recorded here.

## Decision boundary

Gate 90-B proves that the currently installed channel and retained private
evidence still produce the reviewed exact zero-to-one target plan. It neither
installs the target nor authorizes the installation branch already present in
the reviewed wrapper.

The remaining sequence stays separately gated:

1. Gate 90-C may, only after explicit authorization, invoke the same wrapper's
   hash- and confirmation-bound installation branch. That operation rewrites
   the protected channel policy and credential, installs exactly one target
   and performs one bounded OpenSSH restart; the established initializer
   rollback may perform another restart only after failure.
2. A later gate independently verifies the installed allowlist and invokes
   channel `probe`.
3. Inactive package `stage`, module `preflight`, `activate`, independent
   health and Safari acceptance, retention, publication and cleanup all remain
   separate gates.

Gate 90-B authorizes none of those later actions.
