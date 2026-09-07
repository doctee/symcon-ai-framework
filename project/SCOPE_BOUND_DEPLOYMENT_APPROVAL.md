# Scope-Bound Deployment Approval

Status: Stable Draft 1.0, repository implementation complete; Windows and live
integration remain separately gated

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
| `Invoke-SaefDeploymentGateway.ps1` | five-verb forced-command validation, staging, dispatch and channel mutex | unchanged; never receives arbitrary coordinator commands |
| `saef-deploy` | POSIX transport wrapper for the five verbs | retained as transport, not treated as an approval ledger |
| `Invoke-SaefSymconRestart.ps1` | bounded service restart and bootstrap rollback | remains a separately approved high-risk profile |
| runtime mirror and health probe | bounded post-restart compatibility checks | retained; not a substitute for target postflight |
| generic deployment retention | paired runtime-fileset cleanup | still rejects standalone-module deletion |
| OwnTracks module adapter | ownership, five writer locks, state snapshot, reload, health and rollback | invoked only through a pinned target profile |
| OwnTracks state initializer and migration | separate root provisioning and legacy move/reseal | outside deployment approval |
| OwnTracks miss-state adoption | hash-bound target state conversion | outside deployment approval |
| OwnTracks active-identity reseal | exact two-policy trust-anchor update and rollback | optional bound phase after postflight |
| OwnTracks adapter retention | adapter-transaction plan/apply | remains separate and cannot delete channel roots |
| module fileset/package builders | deterministic candidate bytes and identities | supply the immutable package inputs |

No generic confirmation ledger or crash-aware coordinator existed before this
workstream. Extending the transport wrapper alone would duplicate state and
could not resolve postflight rollback or reseal lock ownership.

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
completed or that rollback completed. An uncertain result stops for manual
recovery. The coordinator never repeats an uncertain mutation.

## Runner boundary

The runner callable receives only a fixed phase name and normalized context.
Its implementation must be installed and hash-pinned independently. On
Windows, each profile must pass PowerShell 5.1 parsing and synthetic tests for:

- exact policy, package and plan identities;
- ACL protection and reparse-point rejection;
- channel, adapter and writer lock order;
- baseline drift immediately before mutation;
- all supported failures before and after mutation;
- byte-exact rollback and unproven-rollback handling;
- interrupted activation, reseal and rollback inspection; and
- bounded private status output.

The runner must use the existing channel verbs. A server-side integration may
own the channel mutex across the sequence, but it must call only installed
profiles and must not reacquire the same mutex from the reseal helper. The
existing OwnTracks reseal script therefore needs a coordinator-aware fixed
entry point before it can be enabled in this sequence.

The exact offline gate and machine-readable result contract are defined in
`project/CHANNEL_V8_ONE_CLICK_WINDOWS_QUALIFICATION.md`. No Windows
qualification or installation is implied by the repository tests.

## Reference mappings

### OwnTracks

OwnTracks is the first reference pilot. Its plan binds target
`owntracks-position-map`, adapter profile
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
work. Targets opt in only after a runner profile and approval service have been
qualified and installed. Existing target allowlists gain no authority from the
repository implementation.

The one-click path requires new private approval state and secret ownership but
does not migrate module bytes, policies or adapter state. An interrupted manual
workflow cannot be imported implicitly; a fresh plan and baseline are required.

## Remaining gates

1. Review and repository integration of this contract.
2. Implement the exact fixed Windows runner profile and post-success rollback
   entry point without changing the gateway verb grammar.
3. Run protected Windows PowerShell 5.1 parser, ACL, reparse, lock, crash and
   rollback qualification for the exact runner bytes.
4. Install an approval secret/state owner and runner profile under a separate
   administrative gate.
5. Materialize a private OwnTracks plan and run read-only preflight.
6. Approve one OwnTracks **Jetzt anwenden** activation, then perform independent
   MCP and browser acceptance without provider-expanding behavior.
7. Decide retention separately under the cross-root contract.
8. Repeat adapter and Windows qualification before MediaCarousel opt-in.
