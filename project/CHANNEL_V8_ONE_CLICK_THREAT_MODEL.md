# Channel v8 One-Click Threat Model

Status: Stable Draft 1.0, repository contract only

## Protected assets

The contract protects the active module bytes, adapter and channel policies,
Symcon configuration and persistent state, rollback material, deployment
credentials, approval secret and retained evidence. It also protects the
forced-command boundary from becoming a general administration channel.

The approval plan is public-structure data but its materialized identities and
evidence are private installation data. The HMAC secret is never written to a
plan or status record.

## Trust boundaries

1. An authenticated UI or local controller establishes the approver identity.
2. The approval service owns the HMAC secret and a protected private state root.
3. The coordinator accepts only a canonical plan and fixed phase identifiers.
4. A Windows runner maps phases to installed, hash-pinned profiles.
5. The channel gateway retains its five fixed SSH verbs and its global mutex.
6. The target adapter owns module-specific locks, state, health and rollback.
7. Independent postflight does not trust the adapter's activation result alone.
8. A shared internal child-process boundary owns PowerShell selection, script
   identity, timeout, output capture and descendant termination.

## Threats and controls

| Threat | Required control |
| --- | --- |
| approval replay or double-click | one nonce claim per plan; terminal states reject re-entry |
| altered target, package or operation | HMAC over canonical plan hash and duplicated scope bindings |
| culture-dependent plan serialization | ASCII keys sorted bytewise with PHP `SORT_STRING` and Windows `StringComparer.Ordinal`; fixed PHP-derived JSON and SHA-256 reproduced under `en-US`, `de-DE` and `tr-TR` |
| stolen approval on another host or account | opaque approver, execution-host and channel-host bindings |
| stale approval | issue time, bounded expiry and maximum lifetime of 900 seconds |
| plan drift after review | exact plan hash plus fresh baseline comparison immediately before activation |
| TOCTOU after client preflight | adapter revalidates package, policy, ownership, configuration and quiescence while holding server locks |
| concurrent deployment | global controller lock, channel mutex, then target adapter mutex and writer locks |
| approval-state alteration | HMAC-protected bounded state records in a protected private root; deployment identity has write access only below the state leaf |
| secret replacement through parent ACL | exact non-inheriting ACLs give the deployment identity read/traverse on approval roots and read-only access to the secret |
| process crash or lost response | persist phase as `started`; resume invokes fixed read-only inspection before continuation; one identical-envelope retry is allowed only after `not_applied` proof |
| uncertain mutation | no blind retry; prove completed step or rollback, otherwise manual recovery |
| process loss during rollback | signed `rollback/started` state becomes manual recovery; rollback is never blindly repeated |
| partial activation or failed postflight | automatic target-owned byte-exact rollback and independent rollback evidence |
| failed rollback | terminal `manual_recovery_required`; preserve all recovery artifacts |
| evidence disclosure | bounded hashes and phase facts only; no paths, credentials, raw identities or private payloads |
| approval disclosure through process inventory | environment-only delivery to the runner and immediate clearing before child execution |
| hung or output-flooding child | explicit timeout, combined output budget and fail-closed termination |
| orphaned child process tree | kill-on-close Windows Job Object |
| mixed launcher and runner generation | exact child-process hash in channel policy, approval policy and Windows qualification evidence |
| remote-code expansion | no executable, path, RPC endpoint or command in plan; no new gateway verb |
| privilege expansion through one click | allowlist, restart, provider, publication and retention flags must be false |
| cross-root retention race | separate contract, channel-before-adapter lock order, fresh plan and all-root backup |

## Residual risks

The PHP coordinator proves the platform-neutral approval and state-machine
semantics. It does not by itself prove Windows ACLs, PowerShell parsing,
cross-language lock interoperability or a target's rollback implementation.
Those properties require an exact-profile Windows qualification before a
profile can be installed or allowed.

Repository tests also cannot prove Windows Job Object behavior. The exact
launcher must pass PowerShell 5.1 parser, timeout, output-limit and descendant
cleanup tests. A short interval remains between starting the already
hash-pinned child and assigning it to the Job Object; protected source ACLs and
server-selected scripts remain mandatory controls.

A producer and verifier running under the same ambient culture can agree on
the same wrong bytes. Cross-runtime canonicalization therefore requires a
fixed externally expected byte vector, not only equality between two values
created on one machine.

An approval service compromise can issue valid proofs. Its secret and state
root therefore require least-authority ACLs, rotation and separate operational
monitoring. Secret rotation invalidates unclaimed approvals and must not erase
in-progress recovery state.

## Security invariants

- A user action authorizes only one exact plan, not a class of deployments.
- Read-only inspection precedes every continuation after an uncertain phase.
- No terminal result can be converted back to running; changed state fails its
  HMAC before it can influence execution.
- A successful transport or runner call is not sufficient; expected outcome,
  baseline and evidence hashes must all validate.
- A child process result is not sufficient; the owning coordinator must still
  validate the child's bounded status and independent postflight.
- A cleanup, restart, publication or provider action cannot be smuggled into
  the one-click operation list.
- A fresh preflight remains reusable only after the runner proves that no
  activation mutation began; the gateway does not replace it with an aborted
  activation record in that case.
- Failure remains recoverable or explicitly manual; it is never reported as a
  successful deployment without complete postflight.
