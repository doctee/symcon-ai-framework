# Channel v8 One-Click Windows Qualification

Status: Required separate gate; no runner installed or qualified yet

## Scope

This gate qualifies the exact future Windows runner profile that connects
`SaefDeploymentApprovalCoordinator` to the existing channel-v8 and target
operations. It is an offline scratch qualification. It does not install the
runner, change the channel policy, restart OpenSSH or Symcon, stage or activate
a package, contact a provider, publish or delete retention artifacts.

The gate may start only after the runner, all invoked fixed scripts and every
example/private policy have recorded SHA-256 identities. A changed byte closes
the result.

## Environment

- Windows PowerShell 5.1, not only PowerShell 7;
- protected administrator-owned scratch root on a non-production tree;
- a synthetic deployment account SID and least-authority ACL fixtures;
- no network, provider, Symcon RPC or production channel root;
- deterministic fixture clocks, nonces, plans and status paths; and
- cleanup verification for every scratch artifact.

## Required positive cases

1. Parse every exact PowerShell source with zero parser errors.
2. Validate exact source, policy, plan, target, adapter and package hashes.
3. Execute qualify, stage, fresh preflight, activation, independent postflight
   and terminal status in the fixed order.
4. Execute the reseal sequence with channel-before-adapter lock ownership and
   a final independent postflight.
5. Prove byte-exact automatic rollback for failures after stage, activation,
   postflight and reseal.
6. Reconcile a persisted started phase through read-only inspection without
   repeating an uncertain mutation.
7. Return only bounded outcome, mutation, rollback and evidence-hash fields.

## Required negative cases

- malformed, expired, replayed or second-click approval;
- wrong plan, target, adapter, operation, package, baseline, user or host;
- allowlist, restart, provider, publication or retention-deletion scope;
- missing, broad or inherited mutation ACL and unexpected explicit SID;
- path escape, junction, symlink or other reparse point;
- lock contention at channel, adapter and each writer boundary;
- baseline drift between plan review, preflight and activation;
- wrong qualification or postflight profile;
- partial stage, activation, reseal and rollback;
- crash before mutation, during mutation and after successful mutation but
  before response delivery;
- corrupt, oversized, missing or stale state/evidence; and
- rollback that cannot prove original bytes and identities.

Every negative case must fail closed without an unauthorized mutation. An
uncertain or failed rollback must preserve scratch recovery evidence and report
manual recovery.

## Machine-readable result

The qualification writes one private bounded status record containing format
version, timestamp, phase, outcome, exit code, exact source and profile hashes,
case counts, scratch-mutation and cleanup facts, failed check, error type and
whether any production mutation or service restart was attempted. Paths,
accounts, SIDs, commands, credentials and exception messages are excluded.

Pass requires exit code `0`, all positive and negative cases, successful
scratch cleanup, `productionMutationAttempted: false` and
`serviceRestartAttempted: false`.

## Later gates

Qualification does not authorize installation. Installation must separately
bind exact runner/profile hashes and protected state/secret ownership. A fresh
read-only installed preflight must follow. OwnTracks activation, postflight,
reseal, MediaCarousel opt-in and cross-root retention each remain distinct.
