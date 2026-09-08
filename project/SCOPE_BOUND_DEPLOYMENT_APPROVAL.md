# Scope-Bound Deployment Approval

Status: Stable Draft 1.0, repository implementation complete; exact Windows
qualification, installation and live use remain separately gated

## Purpose

The scope-bound approval contract allows a reviewed channel-v8 deployment to
be presented as one conscious action, **Jetzt anwenden**. It reduces approval
friction without collapsing qualification, preflight, activation, postflight
or rollback into one unobservable command.

The reusable core is `tools/deployment/ScopeBoundApproval.php`. It is an
execution-neutral library, not a live deployment command. A production caller
must provide authenticated identity context, a private HMAC secret, a protected
state directory and a fixed runner profile.

## Existing channel-v8 inventory

The design reuses these existing responsibilities:

| Component | Existing responsibility | One-click treatment |
| --- | --- | --- |
| `Invoke-SaefDeploymentGateway.ps1` | five-verb forced-command validation, staging, dispatch and channel mutex | retains five verbs; `activate` accepts one bounded approval envelope and invokes only the installed pinned runner |
| `saef-deploy` | POSIX transport wrapper for the five verbs | transports the bounded approval envelope without becoming an approval ledger |
| `Invoke-SaefSymconRestart.ps1` | bounded service restart and bootstrap rollback | remains a separately approved high-risk profile |
| runtime mirror and health probe | bounded post-restart compatibility checks | retained; not a substitute for target postflight |
| generic deployment retention | paired runtime-fileset cleanup | still rejects standalone-module deletion |
| OwnTracks module adapter | ownership, five writer locks, state snapshot, reload, health and rollback | invoked only through a pinned target profile |
| OwnTracks state initializer and migration | separate root provisioning and legacy move/reseal | outside deployment approval |
| OwnTracks miss-state adoption | hash-bound target state conversion | outside deployment approval |
| OwnTracks active-identity reseal | exact two-policy trust-anchor update and rollback | optional bound phase after postflight |
| OwnTracks adapter retention | adapter-transaction plan/apply | remains separate and cannot delete channel roots |
| module fileset/package builders | deterministic candidate bytes and identities | supply the immutable package inputs |

The new `Invoke-SaefScopeBoundApprovalRunner.ps1` owns the Windows-side
crash-aware state machine. `Initialize-SaefScopeBoundApprovalProfile.ps1`
installs its exact qualified bytes, private policy, secret and bounded state
root. `tools/apply-approved-symcon-deployment.php` prepares and applies the
reviewed plan from POSIX systems. These components compose the existing
gateway and target adapters; none creates a general command channel.

## Plan contract

`deployments/symcon/windows/deployment-approval-plan.example.json` shows the
public structure. A materialized private plan binds:

- channel version 8, deployment ID and package identity;
- one target and adapter profile;
- exact Windows qualification and independent postflight profiles;
- an ordered operation sequence;
- expected active-package, adapter-policy, channel-policy and any additional
  target-owned starting identities;
- an opaque channel-host identity; and
- explicit false values for risks outside the plan.

The supported base sequence is:

1. `qualify`
2. `stage`
3. `preflight`
4. `activate`
5. `postflight`
6. failure-only `rollback`

A reseal plan inserts `reseal` and `final_postflight` before rollback. No other
operation names or ordering are accepted. The plan never contains executable
paths, credentials, RPC endpoints or arbitrary arguments.

## Approval proof

`createSaefDeploymentApproval()` canonicalizes the plan and creates an
HMAC-SHA-256 proof. The maximum lifetime is 900 seconds. The proof repeats the
security-critical target, adapter, operations and baseline identities so any
incorrect projection fails before state is claimed.

The caller supplies raw approver and execution-host identities; only their
SHA-256 bindings enter the proof. The channel-host binding is already an opaque
plan value. A 256-bit nonce makes each approval unique. The HMAC secret must be
at least 32 bytes and must remain outside plans, logs and evidence.

## Coordinator behavior

`SaefDeploymentApprovalCoordinator` requires an existing writable non-link
state root. It obtains one non-blocking coordinator lock, validates proof,
expiry and all bindings, then atomically records the claim before calling the
runner. Every state record has its own HMAC, symbolic-link state is rejected
and the default root capacity is 256 records. Capacity exhaustion fails closed
and requires separately reviewed retention; the coordinator never evicts
recovery evidence automatically.

Each phase is written as `started` before invocation and `completed` only after
the exact expected result and evidence hash are validated. Preflight must return
the complete expected baseline map. Since preflight directly precedes
activation, drift aborts before active-package mutation. The target adapter
must repeat its authoritative checks under channel-before-adapter lock order.

After any potentially mutating failure, the coordinator invokes the fixed
rollback phase. A positive rollback result is terminal `rolled_back`; every
unproven result is `manual_recovery_required`. Status readback is separate from
apply and remains available after a lost client response.

If a process ends after a phase was persisted as started, the same still-valid
approval may resume only through `inspect`. Inspection may prove that the step
completed, that rollback completed or that activation did not begin. The last
case preserves the fresh gateway preflight and permits one delivery retry with
the identical envelope. An uncertain result stops for manual recovery. The
coordinator never repeats an uncertain mutation. A persisted `rollback`
`started` state is always manual recovery; rollback is not retried after a
process boundary.

## Runner boundary

The installed runner receives only the server-controlled deployment, target
and policy paths plus the bounded approval envelope. It maps the fixed plan
phases to the hash-pinned target adapter, optional reseal script and read-only
qualification evidence. On Windows, each exact profile must pass PowerShell
5.1 parsing and synthetic tests for:

- exact policy, package and plan identities;
- ACL protection and reparse-point rejection;
- channel, adapter and writer lock order;
- baseline drift immediately before mutation;
- all supported failures before and after mutation;
- byte-exact rollback and unproven-rollback handling;
- interrupted activation, reseal and rollback inspection; and
- bounded private status output.

The runner uses the existing channel verbs. The gateway owns the channel mutex
across the sequence and calls only installed profiles. The OwnTracks reseal
script has a fixed coordinator-aware mode that validates inherited lock
ownership instead of reacquiring that mutex.

The exact offline gate and machine-readable result contract are defined in
`project/CHANNEL_V8_ONE_CLICK_WINDOWS_QUALIFICATION.md`. The repository gate
checks the contract, but does not substitute for executing the exact sources
under Windows PowerShell 5.1.

## Operator flow

Preparation stages the inactive package, runs the existing read-only preflight
and saves the server-generated canonical plan with owner-only permissions:

```console
php tools/apply-approved-symcon-deployment.php \
  --ssh-alias=<private-alias> \
  --package=<private-package.zip> \
  --prepare-plan=<review-plan.local.json>
```

After review, one exact confirmation performs a fresh preflight, compares its
plan byte-semantically, creates the short-lived proof, requests approved
activation and performs independent status readback:

```console
php tools/apply-approved-symcon-deployment.php \
  --ssh-alias=<private-alias> \
  --package=<private-package.zip> \
  --plan=<review-plan.local.json> \
  --secret-record=<approval-secret.local.json> \
  --approver-identity=<private-identity> \
  --execution-host-identity=<private-host-identity> \
  --confirm="Jetzt anwenden"
```

The client never creates the reviewed plan itself and never restages during
apply. It may redeliver the same envelope once only when status proves that no
activation mutation began. All other uncertain outcomes stop closed.

## Reference mappings

### OwnTracks

OwnTracks is the first reference pilot. Its plan binds target
`saef-owntracks-position-map`, adapter profile
`saef-owntracks-position-map-v1`, exact package and policies, the restart-
recovery source identity when applicable, and the active-identity reseal. The
existing adapter remains responsible for five runtime locks, quiescence,
configuration/state snapshots, one targeted reload, health and rollback.

The active-identity reseal is composed after independent postflight. Its
channel-policy and adapter-policy hashes become plan baselines and its backup
must participate in coordinated reverse rollback. No OwnTracks object,
provider or live identity appears in the generic implementation.

### Media Carousel

Media Carousel reuses the same plan and proof format after its adapter is
merged and independently Windows-qualified. Its target/profile, package,
configuration and policy hashes differ, while phase names, confirmation,
claim, drift, status and recovery semantics remain identical. It must retain
its own package ownership, reload, health and target-state rules. There is no
MediaCarousel allowlist or live activation in this workstream.

## Lifecycle-commit reconciliation

The read-only OwnTracks worktree
`private/worktrees/owntracks-channel-v8-post-merge` remains recovery input. Its
local commits should be handled as follows after their own review:

| Commits | Decision | Reason |
| --- | --- | --- |
| `79818de` | integrate separately | Independent module-runtime restart correction; the coordinator does not replace it. |
| `1d8742e`, `2502786`, `5f97a59` | retain as OwnTracks evidence | Windows boundary, stage and failed recovery-preflight records describe the target history, not generic code. |
| `b71485e` | adapt, then integrate separately | Keep the source/candidate-bound degraded recovery inside the target adapter; expose only fixed runner results. |
| `161ec25`, `1a81672`, `0a0685d`, `254208a`, `30e34b7`, `1eebeaf` | retain and reference | Qualification, private policy and live recovery gates remain target-specific evidence and cannot be generalized into authority. |
| `9d85bfd`, `5b9fb0d`, `bd1a09d` | reuse with a narrow adapter | Preserve reseal validation; adapt mutex ownership and result shape for the fixed coordinator runner. |
| `9fd0fa1` | replace only the future implementation, retain the evidence | It proves the cross-root gap but contains no deletion authorization; the separate cross-root contract supersedes ad hoc cleanup design. |

Documentation-only lifecycle evidence should not be copied wholesale into a
generic public contract.

No commit from that branch was cherry-picked, rebased, modified or published by
this workstream.

## Compatibility and migration

Channel version 8 and its five remote verbs are unchanged. Existing manual
stage, preflight, activate, status, reseal and retention workflows continue to
work. The additional `activate <deployment-id> approved <envelope>` grammar is
available only for a target whose server policy contains a complete,
hash-pinned approval profile. Targets opt in only after exact qualification and
installation. Existing target allowlists gain no authority from repository
code alone.

The one-click path requires new private approval state and secret ownership but
does not migrate module bytes, policies or adapter state. An interrupted manual
workflow cannot be imported implicitly; a fresh plan and baseline are required.

## Remaining gates

1. Execute the protected Windows PowerShell 5.1 parser, ACL, reparse, lock,
   crash and rollback qualification for the final exact source hashes.
2. Install the qualified runner, secret, private state root and target policy
   through the administrative profile installer.
3. Materialize a private OwnTracks plan and complete read-only review and
   preflight.
4. Approve one OwnTracks **Jetzt anwenden** activation, then perform independent
   Symcon MCP and browser acceptance without provider-expanding behavior.
5. Implement and qualify cross-root retention in its separate workstream; no
   deletion is authorized here.
6. Repeat target-adapter and Windows qualification before MediaCarousel opts
   in.
