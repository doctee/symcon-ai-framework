# SAEF Deployment Channel Security Gate

**Result:** Original transport PASS superseded by Windows account-alias
finding; active boundary hotfixed, permanent channel fix pending revalidation

**Date:** 2026-07-22; version 7 reverified 2026-07-23; alias boundary
reassessed 2026-09-04

**Scope:** Restricted Windows OpenSSH deployment channel

**Behavior state:** Corrected managed fileset active; runtime function contract
and source mirror verified

## Scope

This gate reviewed the first SAEF remote deployment channel from its macOS
client boundary through Windows OpenSSH, the forced-command gateway and the
read-only authenticated Symcon RPC probe. Private addresses, host identities,
account names, key fingerprints, credentials and installation paths remain
outside this repository.

The gate covered source contracts, bounded package handling, local policy,
account and filesystem restrictions, positive readiness probes and negative
transport tests. A later extension staged and activated the first real package
under explicit authorization and exposed the runtime compatibility gap below.

## First Activation Incident

The bounded chunk transport, staging hashes, deployment preflight, controlled
restart and ready runlevel all passed. The selected bootstrap nevertheless
caused normal event scripts to lose installation-required global functions.
Runlevel `10103` therefore proved process readiness but not functional runtime
compatibility. The optional source mirror degraded independently because its
isolated execution context did not inherit `System.Locals` functions.

The root cause was an invalid candidate path contract: the package token
selected `<targetDirectoryName>/bootstrap.php`, while the gateway had staged
the immutable fileset below `.saef-filesets/<targetDirectoryName>/bootstrap.php`.
The resulting failed `require` prevented `System.Locals` initialization and
therefore removed both legacy System Functions and SAEF helper globals from
normal script contexts.

The operator stopped further rollout, atomically restored the byte-exact
rollback bootstrap and used the pinned restart coordinator. The restored
runtime reached the ready runlevel with a new kernel start time. Bounded
read-only probes confirmed that the affected legacy functions and expected
SAEF helper functions were available again. No device or MQTT action was part
of the incident response.

The repository response adds a mandatory bounded
`requiredRuntimeFunctions` package contract, a hash-pinned side-effect-free
Symcon health probe, checks before and after activation and after rollback, and
automatic rollback when process readiness passes but the function contract
fails. Builder and gateway now also require the exact managed-fileset bootstrap
token, so this path mismatch is rejected before staging or activation.

## Windows Boundary

The operator-provided local audit confirmed:

- a dedicated enabled deployment account with public-key-only SSH access;
- denied interactive and Remote Desktop logon for that account;
- `PermitTTY no`, `AllowTcpForwarding no` and a fixed PowerShell
  `ForceCommand` in the effective `Match User` configuration;
- a running OpenSSH service and a syntactically valid server configuration;
- an inbound TCP rule limited to the intended private network ranges;
- administrator-owned protected gateway, policy, credential and authorized-key
  files;
- bounded writable fileset and state roots for the deployment identity; and
- valid hashes for the installed gateway and restart coordinator.

The deployment account remains a local administrator because the current
restart coordinator must control the IP-Symcon service. The original review
concluded that this privilege was not exposed as a shell. The reassessment
below supersedes that conclusion for the previous bare-only configuration.

### Windows Account-Alias Reassessment

The later channel-version-8 Windows gate tested real SSH negotiation with all
accepted local-account spellings. The short and machine-qualified spellings
advertised only public-key authentication and reached the restricted boundary.
The `.\`-qualified spelling was also accepted by Windows OpenSSH but did not
match the original bare-only `Match User` pattern. It therefore inherited the
global password, keyboard-interactive, TTY, forwarding, key-file and command
defaults.

This was a high-severity boundary defect because the dedicated account remains
a local administrator. The vulnerable configuration shape had existed since
the restricted channel was introduced; the available evidence does not by
itself demonstrate exploitation. A separately authorized live hotfix replaced
only the SAEF user pattern with one block covering the short and `.\`-qualified
spellings, retained a byte-exact protected backup and restarted only `sshd`.
Unauthenticated postflight negotiation then confirmed public-key-only handling
for every tested spelling. No Symcon restart, deployment activation or object
mutation occurred.

The permanent repository correction generates the alias-complete block and
adds a source regression. Windows release gates must retain real negotiation
tests because parser success and `sshd -T` account resolution alone did not
expose the bypass.

### Early Preflight Status Reassessment

The subsequent Windows gate confirmed the exact alias-complete block and
public-key-only negotiation for all three tested account spellings without an
active mutation. Its synthetic duplicate-target check then exposed a separate
failure-reporting defect: target validation failed before rollback snapshots
existed, and the cleanup function rejected the empty snapshot collection
during PowerShell parameter binding. The process therefore exited with code
`1` before writing the intended bounded preflight status.

The repository correction keeps the snapshot parameter mandatory but
explicitly permits an empty collection as a no-op. This preserves rejection of
missing cleanup input while restoring deterministic exit code `10`, failure
status and no-rollback evidence for every validation error before snapshot
creation. Channel commands, target policy and version remain unchanged. Final
acceptance still requires the complete Windows gate to pass.

## Credential Migration

The first deep probe exposed that a `CurrentUser` DPAPI CLIXML credential
depended on an interactive user profile. After interactive logon was denied and
the profile was closed, the probe failed at `credential_source` without
revealing credential material.

The channel now uses a versioned DPAPI `LocalMachine` record with strict
administrator and SYSTEM ACLs. Windows PowerShell explicitly loads the DPAPI
assembly in both creation and consumption paths. A successful deep probe with
no interactive deployment-account session confirmed that the replacement is
usable by the forced command while remaining host-bound.

The failed migration also showed that the initializer could replace runtime
artifacts before credential protection completed. The gateway then failed
closed because its pinned local contract no longer matched. The initializer was
hardened so credential protection completes before the first mutation and all
subsequently replaced channel artifacts and ACLs are snapshotted and restored
together with `sshd_config` after an installation failure.

## Live Verification

The freshly installed channel passed these external tests:

| Test | Expected result | Result |
| --- | --- | --- |
| Deep `probe` | Authenticated Symcon readiness, no mutation | PASS |
| Arbitrary command | Sanitized rejection, exit code `10` | PASS |
| Command separator injection | Sanitized rejection, exit code `10` | PASS |
| Forced TTY allocation | SSH rejection, exit code `255` | PASS |
| Wrong client key | Public-key authentication rejection | PASS |
| Short, machine and `.\`-qualified account spelling | Same public-key-only boundary | PASS after hotfix |
| Invalid package hash | Sanitized rejection without staging | PASS |
| Missing deployment status | Sanitized rejection | PASS |
| Ordered bounded package chunks | Exact reconstruction and package hash | PASS |
| First real package preflight | Restart and mirror preconditions | PASS |
| First real package activation | Preserve required runtime functions | **FAIL; rolled back** |
| Corrected managed-path package preflight | Exact target path, 74-function sentinel and mirror preconditions | PASS |
| Corrected managed-path package activation | Restart, 74-function sentinel, rollback readiness and source mirror | PASS |

The successful probe validated the server-pinned policy, machine credential,
active bootstrap identity, IP-Symcon service state, authenticated loopback RPC
and ready runlevel. Responses contained no private paths, credential content or
exception messages.

The corrected package was staged under a new immutable identity. Its preflight
passed after the exact managed-fileset path contract and Windows PowerShell 5.1
array normalization were enforced. Restart readiness, all 74 preserved global
functions and mirror ownership were verified without activation or restart.
The separately authorized activation then reached the ready runlevel, passed
the same runtime contract after restart and created the non-executable source
mirror. An independent probe found all 75 expected functions, including the
new `SAEF_EnsureScript()`, and the exact candidate bootstrap hash.

### Channel Version 7 Reverification

The repository successor was installed through the same guarded Windows
bootstrap without activating a fileset or restarting IP-Symcon. Its external
deep probe returned channel version 7 and authenticated ready state. Repeated
negative checks rejected an unknown operation, an extra probe argument and an
incomplete stage command with sanitized command failures. A forced TTY request
was rejected by SSH with exit code `255`. A final deep probe still returned
ready state, confirming that the rejection checks did not alter channel state.

## Repository Verification

The repository checks cover:

- the exact five-command dispatcher allowlist;
- strict argument, path, archive, size, count and hash validation;
- byte-exact limits on manifest, hash and extraction streams independent of ZIP
  header claims;
- immutable staging and fresh-preflight requirements;
- serialized mutating operations and persistent storage budgets;
- machine-scoped credential handling and mutually exclusive credential sources;
- initializer account, ACL, SSH ordering and rollback contracts;
- alias-complete local-account matching for the forced-command boundary;
- deterministic package construction; and
- restart coordinator policy and state traces.

`make check`, the dedicated deployment-channel test, runtime health probe test
and restart-coordinator test pass after the repository hardening. The corrected
live activation passed its non-mutating preflight, post-restart compatibility
gate and independent verification.

## Residual Risks

- The deployment identity is still a local administrator. A defect in Windows
  OpenSSH, PowerShell or the forced gateway could therefore have high impact.
- Possession of the private SSH key enables all five bounded transport
  operations. SAEF's explicit activation approval is an operational control,
  not a second cryptographic factor.
- DPAPI `LocalMachine` protects the credential at rest and binds it to the host,
  but SYSTEM and trusted local administrators remain inside its security
  boundary.
- The reviewed Windows OpenSSH negotiation did not advertise a hybrid
  post-quantum key exchange. Host upgrades should be monitored while the
  channel remains restricted to trusted private networks.
- Mobile use inherits the key storage, host-key verification and local-file
  security of the selected SSH terminal.

The active installation has the alias bypass hotfixed, but the original
version-7 transport PASS is superseded until the permanent repository fix has
completed its Windows parser, ACL and real-negotiation gate. A future version
should reduce the Windows service-control privilege or add a separate local
activation authorization mechanism before expanding network exposure.

## Gate Decision

The original restricted transport decision for channel version 7 is
**SUPERSEDED** by the account-alias finding. The active Windows boundary is
**TEMPORARILY REMEDIATED** by the separately verified hotfix. Permanent
acceptance requires repository integration followed by the distinct Windows
parser, ACL and real-negotiation gate. The historical first runtime activation
remains recorded as **FAIL with successful rollback**; the corrected immutable
runtime candidate remains **PASS**.

## Related Artifacts

- `adr/ADR-0007-use-restricted-windows-deployment-channel.md`
- `deployments/symcon/windows/README.md`
- `deployments/symcon/windows/Initialize-SaefDeploymentChannel.ps1`
- `deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1`
- `deployments/symcon/windows/Invoke-SaefSymconRestart.ps1`
- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
