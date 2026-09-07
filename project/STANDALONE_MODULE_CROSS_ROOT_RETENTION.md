# Standalone-Module Cross-Root Retention

Status: Required follow-up; deletion remains disabled

## Current gap

Target adapters own transaction and rollback state below the adapter-state
root. The channel separately owns deployment records and managed filesets.
OwnTracks retention can therefore plan and delete only adapter-state units,
while generic channel retention intentionally refuses standalone-module
deployment/fileset pairs.

The current OwnTracks inventory found no eligible adapter transaction and no
safe channel-owned deletion route. All artifacts remain protected. The result
is evidence of a lifecycle gap, not permission to widen either existing
cleaner.

## Required atomic unit

A future target-owned cross-root plan must correlate exactly one logical
deployment across:

- the adapter transaction directory and its package/state evidence;
- the channel deployment-state directory; and
- the channel managed-fileset directory.

The plan must list every relative artifact name, root role, byte count and
recursive SHA-256. It must also bind target, adapter profile, active package,
active transaction, channel policy, adapter policy and complete inventory hash.
Unknown, duplicate, missing, unpaired or cross-target artifacts make the plan
non-executable.

## Protection rules

The planner and apply path must always protect:

- active and staged deployments;
- the active adapter transaction;
- every direct rollback dependency;
- manual-recovery and interrupted transactions;
- the configured recent successful and rolled-back histories;
- artifacts younger than the target retention age;
- any package or state referenced by an approval or recovery record; and
- every artifact belonging to another target.

Retention selection is allowlist-only. Age alone never authorizes deletion.

## Apply contract

Apply requires a fresh exact review plan, a separate retention confirmation and
the lock order channel mutex, target adapter mutex, then target writer locks.
After locks are held it must recompute all identities and eligibility.

Before removing an artifact from any root, apply creates a bounded byte-exact
backup manifest covering the complete atomic unit. It then moves all selected
artifacts to same-volume quarantine, verifies absence from operational roots,
verifies the retained inventory and records bounded private evidence. Any
failure restores every moved byte to its original path and revalidates the
original inventory. Failed or unproven restoration ends in manual recovery and
preserves backup plus quarantine.

Permanent backup/quarantine deletion is a later retention decision. The
one-click deployment approval explicitly rejects retention deletion and cannot
serve as this confirmation.

## Windows qualification

The exact implementation must pass Windows PowerShell 5.1 parsing and synthetic
tests for three-root correlation, path containment, reparse rejection, ACLs,
same-volume rename assumptions, lock contention, active/rollback protection,
cross-target isolation, inventory drift, partial moves, backup corruption,
automatic restoration and manual-recovery preservation.

No production root may be used for qualification. Installation, plan, apply
and final backup cleanup remain separate approvals.

## Complete follow-up assignment

Implement an adapter-owned generic cross-root retention engine for channel-v8
standalone modules. Start from current clean `origin/main` in a dedicated
worktree. Reuse the current channel and adapter retention validators; do not
add a gateway verb or accept client-supplied paths. First inventory every owner,
consumer, rollback reference and lock. Define a canonical read-only plan binding
the three roots, target/profile, complete inventory, policies, active and
rollback identities, exact candidates and expiry. Apply must reacquire channel-
before-adapter locks, revalidate the plan, create a byte-exact all-root backup,
quarantine atomically where possible, verify postflight and restore all roots
on any failure. Reject active, staged, rollback-relevant, manual-recovery,
young, unpaired, unknown and cross-target artifacts. Qualify exact bytes under
Windows PowerShell 5.1 with positive and negative ACL, reparse, drift,
parallelism, partial-move and rollback tests. Keep plan, installation, apply,
backup cleanup, publication and live access as separate gates. Use OwnTracks as
the first reference and MediaCarousel only after its adapter exists. Do not
perform live deletion in the repository workstream.
