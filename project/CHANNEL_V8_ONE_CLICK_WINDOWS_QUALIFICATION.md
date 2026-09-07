# Channel v8 One-Click Windows Qualification

Status: Exact repository gate implemented; Windows PowerShell 5.1 execution
and installation remain separate

## Scope

`Invoke-SaefScopeBoundApprovalWindowsQualification.ps1` qualifies the exact
runner profile that connects the channel-v8 gateway to existing target
operations. It is an offline scratch qualification. It does not install the
runner, change the channel policy, restart OpenSSH or Symcon, stage or activate
a package, contact a provider, publish or delete retention artifacts.

The gate accepts exact expected hashes for the runner, target adapter and
reseal script. It also parses the profile initializer and synthetic fixtures.
A changed byte closes the result.

## Environment

- Windows PowerShell 5.1, not only PowerShell 7;
- protected administrator-owned scratch root on a non-production tree;
- a synthetic deployment account SID and least-authority ACL fixtures;
- no network, provider, Symcon RPC or production channel root;
- deterministic fixture clocks, nonces, plans and status paths; and
- cleanup verification for every scratch artifact.

## Required positive cases

1. Parse every exact PowerShell source with zero parser errors.
2. Install the exact approval profile in protected scratch, verify its
   least-authority directory and file ACLs, and prove a clean repeated
   read-only preflight.
3. Validate exact source, policy, plan, target, adapter and package hashes.
4. Execute qualify, stage, fresh preflight, activation, independent postflight
   and terminal status in the fixed order.
5. Execute the reseal sequence with channel-before-adapter lock ownership and
   a final independent postflight.
6. Prove byte-exact automatic rollback for failures after stage, activation,
   postflight and reseal.
7. Reconcile a persisted started phase through read-only inspection without
   repeating an uncertain mutation.
8. Return only bounded outcome, mutation, rollback and evidence-hash fields.

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

Pass requires exit code `0`, six positive and eight negative scenario groups,
successful
scratch cleanup, `productionMutationAttempted: false` and
`serviceRestartAttempted: false`.

Run the exact reviewed sources from an elevated Windows PowerShell 5.1
session:

```powershell
& .\Invoke-SaefScopeBoundApprovalWindowsQualification.ps1 `
    -ExpectedRunnerSha256 '<reviewed-lowercase-sha256>' `
    -ExpectedAdapterSha256 '<reviewed-lowercase-sha256>' `
    -ExpectedResealSha256 '<reviewed-lowercase-sha256>'

$LASTEXITCODE
Get-Content .\scope-bound-approval-windows-qualification.local.json -Raw
```

The positive groups cover profile installation and ACL verification in scratch,
the base sequence, reseal, safe pre-mutation resume and automatic rollback
after postflight and reseal failure. The negative
groups cover replay, expiry, wrong approver, wrong execution host, altered
HMAC, baseline drift, forbidden risk scope and lock contention. Existing
channel-v8 and target-adapter Windows
qualifications continue to prove their own SSH, writer-lock, ACL and live
adapter boundaries; this gate does not replace them.

## Later gates

Qualification does not authorize installation. Installation must separately
bind exact runner/profile hashes and protected state/secret ownership. A fresh
read-only installed preflight must follow. OwnTracks activation, postflight,
reseal, MediaCarousel opt-in and cross-root retention each remain distinct.
