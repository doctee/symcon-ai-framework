# Gate 90-I — adapter-state live preflight

**Status:** The corrected OwnTracks adapter-state initializer and private live
wrapper passed their separately authorized read-only Windows PowerShell 5.1
preflight. Live state-root provisioning and every module operation remain
closed, 2026-09-06.

## Scope

The private preflight archive was bound to repository commit
`db4c7f0e00a45618afd6da73f26480eba37c97cd`, the Gate 90-H-qualified
initializer SHA-256
`6b9666d23e3c2c08494b4c06f4ac4a5a4c8c660e967a8b554dd4552205bb4c22`
and the already installed single OwnTracks target's pinned adapter and private
policy hashes. The archive SHA-256 was
`8c541350b3aa4d00b5082db6eaf8f0f2b6dfc6abcaa4ff1152d7925b6544f095`.

The wrapper read the installed channel and target binding, private adapter
policy, active module path, prospective state-root parent, ACLs and hashes. It
invoked the exact production initializer only with `Operation=preflight`.
Its only writes were protected private evidence and sanitized status files
inside the freshly extracted gate directory.

## Result

The elevated 64-bit Windows PowerShell 5.1 run returned process exit code `0`,
wrapper `outcome: passed`, `currentPhase: completed` and initializer
`outcome: ready`. All six scenarios passed:

- bundle checksum validation;
- Windows PowerShell 5.1 parsing;
- read-only validation of the installed single-target binding;
- confirmation that the live adapter state root was absent;
- the exact initializer's read-only live preflight; and
- a negative-mutation postflight over the channel, adapter and policy hashes.

The target count was exactly one and its binding was verified. The state root
was absent both before and after the run, while `installRequired` was true.
The initializer neither attempted creation nor changed an ACL or attempted a
rollback. No bounded I/O retry was required.

The wrapper's final `failureStep` value names the last evaluated negative
postflight assertion; it is not a failure when `failureCode` is `none` and the
overall outcome is `passed`.

Every state-root, installed-channel, allowlist, active-module, Symcon, reload,
module, OpenSSH, provider and publication mutation flag remained false. No
private path, account, host identity, ObjectID, credential, coordinate,
tracker identifier or movement history is recorded here.

## Decision boundary

Gate 90-I proves the exact current live prerequisites without changing them.
It does not create the one reviewed state-root leaf, rerun the channel-bound
module preflight or activate the staged package.

The remaining sequence stays separately gated:

1. create and verify only the reviewed empty adapter state-root leaf with its
   restricted ACL and automatic empty-leaf rollback on failure;
2. rerun the channel-bound module preflight;
3. separately review and authorize activation; and
4. keep independent health/Safari acceptance, rollback retention, publication
   and cleanup as later gates.

Gate 90-I authorizes none of those later actions.
