# Channel v8 One-Click Windows Qualification

Status: Exact repository and Windows PowerShell 5.1 gates passed; installation
remains separate

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
- deterministic fixture clocks, nonces, plans and status paths;
- an independent PHP-derived canonical JSON/SHA-256 vector executed under
  `en-US`, `de-DE` and `tr-TR`; and
- cleanup verification for every scratch artifact.

Before scratch scenarios begin, the exact Windows implementation must reproduce
the fixed reference vector with `StringComparer.Ordinal` under all three
cultures. This prerequisite does not increment the six positive or eight
negative scenario-group counts. It closes the same-host blind spot in which a
plan producer and runner could share the same culture-dependent ordering bug.

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
Failed profile-installer qualification additionally records only its bounded
phase, process and status exit codes, outcome, failed step, error type, rollback
flags and standard-error byte count. It does not retain the standard-error
content or the scratch tree.

Pass requires exit code `0`, six positive and eight negative scenario groups,
successful
scratch cleanup, `productionMutationAttempted: false` and
`serviceRestartAttempted: false`.

## Qualified artifact

The exact culture-invariant sources from commit
`10e482a7959c84524415066d90b514d2774974ba` passed the protected Windows gate
on 2026-09-08 with Windows PowerShell `5.1.26100.9168`:

| Artifact | SHA-256 |
| --- | --- |
| qualification | `bbcbc7519a387ef4e910c6b3ceba52aef51b9c85ff47a3bd3c6a16c6889ff7ff` |
| profile initializer | `339b52035f835dd2c5f3ac66b0b934cb305b9bcfd599cb6a066349198f23744e` |
| approval runner | `a372c997bdffe7600e424d9519512312dad447844d05cb2882c891ffb6949ce6` |
| OwnTracks adapter | `9be435b538e70f40b3096209ea15ecf6995ce12681a2de125e89622da4111f49` |
| OwnTracks reseal | `8213ef11255991a670d9ca33b3d92173399344ffd85543ce548d1cf33742f405` |
| canonical culture vector | `8b5c8f1ad3815fcd35b593c95d78af0776d33d0b4e29242ca92f4f07d0a6a0a7` |

All six positive and eight negative scenario groups passed. The culture vector
was identical under `en-US`, `de-DE` and `tr-TR`. Scratch mutation was confined
to the qualification tree and cleanup succeeded. Production mutation, installed
channel reads or writes, target-allowlist changes, Symcon contact and service
restart were all false.

The private transfer bundle and raw machine status remain excluded. This
bounded record does not authorize profile installation or live activation.

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

The culture prerequisite must fail with
`culture_invariant_canonicalization` before scratch setup if either the exact
canonical JSON or its fixed SHA-256 differs. `Sort-Object` is prohibited for
canonical strings; display or numeric cleanup ordering is outside this rule
only when it cannot feed an identity, signature, manifest, backup or plan.

## Later gates

Qualification does not authorize installation. Installation must separately
bind exact runner/profile hashes and protected state/secret ownership. A fresh
read-only installed preflight must follow. OwnTracks activation, postflight,
reseal, MediaCarousel opt-in and cross-root retention each remain distinct.
