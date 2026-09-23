# MediaCarousel repeatable deployment

Status: recovery, code-only reseal, baseline reconciliation and atomic
profile/bootstrap composition implemented. Exact-revision qualification,
reviewed private packaging and independent installed postflight remain gates.

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
both present and absent. The first recovery slice passed native Windows CI.
Later changes require their own qualification.

Native CI additionally executes the complete existing six-positive/eight-negative
approval matrix for both target labels, using synthetic adapters/resealers and an
explicit existing test account. It checks the actual MediaCarousel resealer in
ten separate filesystem/ACL cases, including consecutive package advances and
preserved unrelated bindings. These are isolated qualification results, not live
installation evidence. The additional bootstrap suite invokes the actual profile
initializer against an unpublished shadow channel, including rejected evidence
and post-publication rollback in all three cultures.

The resealer filesystem test now consumes a generation created by the actual
binding publisher rather than a manually ACL-corrected policy directory. The
publisher gives the verified deployment SID explicit inheritable read/execute
on that directory, with full control reserved for SYSTEM and Administrators;
protected policy and backup files remain administrator/SYSTEM-only. A negative
control removes that SID rule and requires the complete resealer to reject it
before any policy mutation. This closes the installer/resealer ACL test gap;
it does not substitute for the complete installed-profile/runner/adapter/RPC
integration gate before another live activation.

The shared runner now binds reseal arguments to exact target/profile pairs.
The existing OwnTracks reseal entrypoint keeps its filename and default contract;
only a separately pinned MediaCarousel profile passes the new explicit target.
MediaCarousel reseal verifies transaction/snapshot hashes and unchanged instance
snapshots against the protected configuration baseline. It rejects a remaining
schema transition and changes only the package identity, not configuration hashes.

The binding updater has a separate `reviewed_baseline_reconciliation` plan kind.
It accepts a hash-bound `reviewed-baseline.local.json` containing the exact active
package identity, unchanged instance membership with reviewed configuration hashes,
and identities of prior independent evidence. The candidate policy may only adopt
these exact hashes and remove the consumed schema transition. All other fields,
including unknown future fields, remain unchanged. Existing schema-transition
plans retain their original behavior. Live ownership, full instance snapshots
and package bytes are still checked before and after atomic pointer publication.
Evidence preparation is a review responsibility: the updater must never generate
an approved baseline by merely observing current drift. This new mode is not an
implicit repair option of an ordinary deployment.

## Installed baseline and addressed gap

Channel v8 already transports and activates MediaCarousel packages. This is
not proof that the MediaCarousel target implements the scope-bound approval
profile described in ADR-0010. The pre-bootstrap installed adapter accepts only `preflight`
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

The installed runner passes a literal OwnTracks confirmation. The installed
MediaCarousel adapter lacks the new recovery fields. Merely installing an
approval-policy JSON or renaming the reseal script would therefore be incorrect;
the new sources must first pass the complete profile qualification together.

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

### Approval state path correction

The bootstrap plan must explicitly provide `approvalBootstrap.approvalRoot`:
a new absolute root outside the channel installation, adapter policy, module,
managed-fileset and runtime-state roots. Its existing parent must already have
trusted write ownership. The installer retains the resealer's existing path
guards unchanged; putting approval evidence inside the generation is invalid.
The initializer still owns secret/state ACL provisioning.

A fully bound MediaCarousel profile may be relocated only with an exact reviewed
`approvalStateSha256`. The updater inventories bounded terminal ledger records
and phase evidence in ordinal order, copies every byte into the new profile and
rechecks both inventories under the channel-before-adapter locks. Identity,
secret, runner and resealer bindings remain unchanged. Nonterminal records,
unexpected entries, state drift and unrelated policy changes fail closed. It
publishes only one atomic channel pointer after copy/readback, retains the old
profile and ledger on success or failure, and never resets consumed approvals.

The Windows bootstrap regression feeds the real initializer's output paths
through both actual resealer managed-root guards, reproduces the former invalid
path, and exercises a second profile generation with preserved consumed state.
This is path-boundary and migration evidence; full adapter/resealer transaction
tests and later live activation remain separate gates.

The restricted channel cannot install its own new protected binding or approval
profile. Administrative bootstrap is a capability boundary, not another user
approval request. If no separately authorized administrative execution channel
exists, one operator-assisted Windows start is necessary. The fixed `repeatable`
package profile self-extracts and invokes the existing binding coordinator.
Its optional approval bootstrap stages a complete profile in the new generation,
qualifies the shadow configuration and publishes one atomic channel pointer.
See `case-studies/media-carousel/06-protected-binding-generation.md` for the
preservation and rollback contract. It does not reinstall the channel, restart
services or activate module code. Source qualification, a reviewed private plan
and fresh installed evidence are required; this document is not proof of live
installation.
