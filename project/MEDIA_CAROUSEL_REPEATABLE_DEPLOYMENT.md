# MediaCarousel repeatable deployment

Status: recovery implementation candidate; native qualification and the
remaining profile/reseal integration are pending. Not live-ready.

## Current implementation slice

The adapter candidate adds read-only `postflight`/`inspect` and explicit
post-success `rollback`. Fresh transactions retain manifest/snapshot hashes,
the predecessor package identity and byte-exact prior active-state evidence.
Intent is persisted before package mutation. Inspection deliberately accepts
only a fully completed activation and never retries an uncertain transaction.
Legacy records without the new evidence require manual review.

Recovery-state tests exercise invalid evidence and UTF-8 across three cultures.
The existing real Windows loopback-RPC transaction suite now exercises these
operations, rejects tampered recovery evidence, and covers predecessor state
both present and absent. No successful native result is claimed until CI runs.

## Scope and current gap

Channel v8 already transports and activates MediaCarousel packages. This is
not proof that the MediaCarousel target implements the scope-bound approval
profile described in ADR-0010. Its adapter currently accepts only `preflight`
and `activate`; its installed policy pins a package and instance configuration
baseline. A successful activation records the new active package but does not
reseal that protected baseline. Later intentional settings changes must not be
silently interpreted as approved deployment drift.

The repair must preserve the five remote verbs, package ownership, all instance
configuration and presentation metadata, retained rollback artifacts and the
unrelated target bindings. It must not restart a service or relax ACL checks.

## Existing owners and reuse boundary

| Responsibility | Existing implementation | Required treatment |
| --- | --- | --- |
| Approval proof, drift, replay, claim | `tools/deployment/ScopeBoundApproval.php` | Reuse unchanged |
| Restricted remote transport | `deployments/symcon/windows/saef-deploy` and gateway | No new verb or administrative shell |
| Windows phase orchestration | `Invoke-SaefScopeBoundApprovalRunner.ps1` | Reuse phase/rollback journal; remove the OwnTracks-specific reseal confirmation through an explicitly bound target contract |
| Bounded child execution | `SaefChildProcess.ps1` | Reuse unchanged |
| MediaCarousel package/configuration ownership | `adapters/Invoke-SaefMediaCarouselModuleAdapter.ps1` | Add independently verifiable postflight, inspection and post-success rollback |
| Protected binding replacement | `adapters/Update-SaefMediaCarouselBinding.ps1` | Reuse immutable generation and atomic pointer publication; do not widen preservation checks implicitly |
| Profile installation | `Initialize-SaefScopeBoundApprovalProfile.ps1` | Reuse protected installation; requires an administrative bootstrap outside the five-verb channel |
| Active identity reseal reference | `adapters/Invoke-SaefOwnTracksPositionMapActiveIdentityReseal.ps1` | Reuse its reviewed safeguards; do not execute it for MediaCarousel as-is |

The existing runner passes a literal OwnTracks confirmation. The existing
reseal additionally expects snapshot fields that MediaCarousel does not yet
persist. Merely installing a MediaCarousel approval-policy JSON or renaming
the existing reseal script would therefore be incorrect.

Existing OwnTracks live sources and bindings remain untouched. Any shared
source extension must retain its old default contract and pass the existing
OwnTracks regression and Windows qualification suites before publication.
Source reuse does not authorize replacing the live OwnTracks generation.

## Required transaction invariants

1. Bootstrap reconciles an exact fresh package/configuration baseline with
   independently retained evidence of the previous authorized transitions.
   Any unexplained difference stops the operation. It never restores valid
   user settings to obsolete policy values.
2. Capture prior active-state bytes and package identity durably before the
   first directory switch. Bind manifest, transaction and snapshot identities.
3. Postflight re-reads actual module files, complete instance membership,
   configuration, metadata, health and pending changes. A success marker alone
   is not sufficient evidence.
4. Inspection never repeats an uncertain switch or reload. Ambiguous partial
   transactions fail closed for recovery review.
5. Post-success rollback is a separate adapter operation using the exact
   captured predecessor. It must reject foreign drift, retain the failed
   candidate, reload only the target and prove restored configuration/health.
6. Reseal follows independent successful postflight, never precedes it. Bind
   the next package baseline to the exact completed transaction. Configuration
   fingerprints may advance only to that transaction's verified expected
   result, not arbitrary current configuration. Consume a one-use schema
   transition explicitly rather than carrying it into unrelated updates.
7. Journal policy and package transitions so a reseal failure can restore
   protected policy bytes before package rollback. Unproven recovery returns
   `manual_recovery_required`, never success.
8. Retention remains disabled. No transaction, backup, old instance or
   package is removed by the update workflow.

## Qualification and rollout

Required offline/native Windows cases include two consecutive successful
updates without another binding installer; all declared cultures; unchanged
configuration bytes; rejected foreign configuration/package drift; missing,
malformed and tampered evidence; duplicate/replayed approval; lock contention;
interruption before and after each mutation; failed postflight/reseal; proven
reverse rollback; and unchanged OwnTracks bindings. Windows PowerShell 5.1
tests remain mandatory, not replaceable by static source assertions.

Only after these gates: publish the reviewed sources, perform the separately
authorized protected profile bootstrap, independently verify it, then activate
the bounded image-loading diagnostic candidate through the existing channel.
The diagnostic candidate must not change instance schema or camera settings.

## Operator boundary

The restricted channel cannot install its own new protected binding or approval
profile. Administrative bootstrap is a capability boundary, not another user
approval request. If no separately authorized administrative execution channel
exists, one operator-assisted Windows start is necessary. Its eventual package
must self-extract, verify exact hashes and baseline, preserve unrelated fields,
avoid service restart, and print bounded console JSON. No package is ready or
installed merely because this architecture document exists.
