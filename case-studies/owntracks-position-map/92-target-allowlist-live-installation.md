# Gate 90-C — target-allowlist live installation

**Status:** The reviewed zero-to-one OwnTracks target installation completed
successfully with one bounded OpenSSH restart, preserved channel identity and
no Symcon restart or module operation, 2026-09-05.

## Scope

This separately authorized live gate invoked the installation branch already
reviewed in
[Gate 90-B](91-target-allowlist-live-installation-preflight.md). The invocation
was bound to the retained private installation plan, fixed confirmation phrase,
repository commit `f045f62cd8c47db6fbf9e725f04cdabd7eba7bb9`, initializer
SHA-256
`dbb1bd82de29d1bb490ae2978ef2536c4894ec37587ad2cdb9a804d5041a26d4`
and corrected OwnTracks adapter SHA-256
`d101baadfc120b5092a7621812b66fdfa87692554f20c6eca18fb2f06b3e0e62`.

The authorized mutation was limited to installation of the single
`saef-owntracks-position-map` target in the existing deployment-channel-v8
allowlist, the initializer's protected channel rewrite and one bounded OpenSSH
restart. Symcon restart or RPC contact, module preflight or activation,
provider contact, publication and cleanup were outside this gate.

## Result

The approved elevated Windows PowerShell 5.1 run returned exit code `0`. Both
the wrapper and initializer reported `installed`, with initializer exit code
`0`. The retained private plan hash matched the independently calculated file
hash and the exact plan reviewed in Gate 90-B.

The independent live postflight reported:

- exactly one installed target after an exact zero-to-one transition;
- preservation of all 30 non-target channel-policy properties;
- byte bindings to the reviewed initializer and corrected adapter;
- preserved public key and OpenSSH configuration;
- preserved credential semantics across the protected DPAPI rewrite;
- one attempted and successful OpenSSH restart;
- a preserved Symcon service process; and
- no rollback attempt because installation and postflight both succeeded.

The wrapper further reported no Symcon RPC contact, module preflight or
activation, provider contact or publication. No private path, deployment
account, host identity, ObjectID, credential, coordinate, tracker identifier,
movement history or installation-local plan content is recorded here.

## Decision boundary

Gate 90-C establishes only the target binding in the installed deployment
channel. It does not prove the client-to-channel path or invoke the newly
installed adapter.

The remaining sequence stays separately gated:

1. independently verify the installed allowlist and run the channel's
   read-only OwnTracks `probe`;
2. prepare and review an inactive package `stage` operation;
3. keep module `preflight`, `activate`, independent health and Safari
   acceptance, rollback retention, publication and cleanup as separate gates.

Gate 90-C authorizes none of those later actions.
