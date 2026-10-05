# Standalone-Module Cross-Root Retention

Status: Repository implementation complete; Windows qualification and every
operational gate remain outstanding

## Current implementation

Target adapters own transaction and rollback state below the adapter-state
root. The channel separately owns deployment records and managed filesets.
The generic `Invoke-SaefStandaloneModuleCrossRootRetention.ps1` consumer now
correlates the adapter transaction, channel deployment-state and managed
fileset roots without adding a gateway verb. Its default `plan` operation is
read-only. `apply` requires the exact generated review plan, the target's
private policy, the installed channel policy and a separate target-specific
confirmation.

OwnTracks is the first enabled contract. Its historical adapter-only retention
apply path is retired so that no caller can remove only one part of a logical
deployment. MediaCarousel remains disabled until its own target contract and
Windows qualification are admitted. The generic channel cleaner continues to
reject standalone-module deployments.

The repository implementation does not establish operational eligibility. The
exact source still needs the separate Windows PowerShell 5.1 scratch
qualification described below, followed by an independently reviewed channel
installation. No live plan or apply has been run.

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

`Invoke-SaefStandaloneModuleCrossRootRetentionWindowsQualification.ps1`
provides that synthetic contract. It uses temporary protected roots only and
covers culture-invariant ordering/timestamps, three-root correlation, ACL and
reparse rejection, protected candidates, plan drift, lock contention,
post-claim failure, partial-move rollback, successful quarantine, inspection
and replay rejection. Passing repository tests does not substitute for running
these exact bytes in elevated Windows PowerShell 5.1.

The same synthetic qualification runs on Windows CI before an operator package
is handed off. Negative scenarios bind the expected rejection reason as well
as exit code and outcome, so an unrelated earlier failure cannot satisfy a
protection test. The final qualification status retains the last child status,
its hash, process termination and bounded stderr before scratch cleanup.

Adapter transaction directory names preserve the existing UTC suffix
`yyyyMMddTHHmmssZ`; deployment identifiers retain their separate lowercase
contract. The partial-move fixture denies both the child's Delete right and
the parent's DeleteChildren right to exercise actual rollback on NTFS.

## Implemented assignment

The repository workstream implements an adapter-owned generic cross-root
retention engine for channel-v8
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

Permanent removal from backup or quarantine is intentionally absent. It is a
later retention decision with its own bounded contract and must never be
inferred from a successful cross-root apply.
