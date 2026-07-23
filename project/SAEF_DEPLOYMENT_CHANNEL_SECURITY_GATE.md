# SAEF Deployment Channel Security Gate

**Result:** Transport security PASS; corrected runtime activation PASS after
successful rollback of the first candidate

**Date:** 2026-07-22

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
restart coordinator must control the IP-Symcon service. This privilege is not
exposed as a shell: the account has no password SSH, TTY, forwarding, local
logon or Remote Desktop path through the reviewed channel.

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

These risks are accepted for channel version 5. A future version should reduce
the Windows service-control privilege or add a separate local activation
authorization mechanism before expanding network exposure.

## Gate Decision

The restricted transport channel is **PASS** for supervised SAEF use on the
reviewed private network. The first runtime activation remains recorded as
**FAIL with successful rollback**; the corrected immutable candidate is
**PASS** after preflight, explicit authorization, post-restart runtime-health
verification and source-mirror reconciliation.

## Related Artifacts

- `adr/ADR-0007-use-restricted-windows-deployment-channel.md`
- `deployments/symcon/windows/README.md`
- `deployments/symcon/windows/Initialize-SaefDeploymentChannel.ps1`
- `deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1`
- `deployments/symcon/windows/Invoke-SaefSymconRestart.ps1`
- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
