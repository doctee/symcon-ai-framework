# ADR-0007: Use a restricted SSH command channel for Windows deployment

Status: Accepted
Date: 2026-07-21

## Context

SAEF's first live fileset rollouts used reviewed PowerShell packages that were
copied to the Windows host and started manually. The transaction itself was
bounded and recoverable, but package transfer, preflight invocation, status
collection and activation required repeated operator mediation.

A general remote shell would remove that friction at the cost of granting a
much broader Windows administration capability. PowerShell remoting over SSH
is portable across macOS and Windows, but currently does not support custom
endpoint configuration or Just Enough Administration. A normal SSH PowerShell
subsystem would therefore expose more language and command surface than the
deployment workflow requires.

The operating client must also work from macOS and from an SSH terminal on
iPhone or iPad without requiring PowerShell on Apple devices.

## Decision

SAEF uses Windows OpenSSH only as an encrypted, key-authenticated transport to
a non-interactive forced-command dispatcher.

The contract is:

1. A dedicated SSH identity is limited by `Match User`, `ForceCommand` and
   `PermitTTY no` to `Invoke-SaefDeploymentGateway.ps1`. The Windows local
   account is matched in both its short and `.\`-qualified spelling because
   OpenSSH may authenticate either spelling without applying a bare-only user
   pattern.
2. TCP forwarding, PTY access and password authentication are disabled for
   that identity; Windows OpenSSH exposes no X11 path in this channel.
3. The dispatcher accepts exactly `probe`, `stage`, `preflight`, `activate`
   and `status`; it never evaluates the original SSH command as PowerShell.
4. Remote arguments contain only a strict package hash or deployment ID.
   Paths, service name, RPC endpoint, limits and dependency hashes come from a
   server-local policy excluded from Git.
5. The RPC endpoint must be loopback. Credentials are stored in a versioned
   DPAPI `LocalMachine` record protected by strict ACLs. This keeps public-key
   SSH independent of an unlocked interactive user profile.
6. Deployment ZIPs are bounded and verified by transmitted hash, exact entry
   membership, safe relative paths, file count, expanded size, file sizes and
   per-file SHA-256 values before an immutable inactive directory is created.
7. Activation is unavailable until a fresh successful preflight has rechecked
   staged files, bootstrap drift, service state and Symcon readiness.
8. Bootstrap selection replaces exactly one printable ASCII token with an
   equal-byte-length token. A byte-exact backup and the existing state-based
   restart coordinator provide rollback.
9. Status responses contain only bounded state facts and hashes, never local
   paths, credentials, hostnames or exception messages.
10. macOS and mobile clients use standard SSH commands or the POSIX
    `saef-deploy` client. PowerShell is required only on Windows.
11. The forced gateway and its hash-pinned restart child use process-local
    `ExecutionPolicy Bypass` so a restrictive host policy cannot silently
    disable the channel. No persistent user or machine policy is changed.
12. The dedicated alias-complete `Match User` block precedes broader active
    `Match` blocks. This preserves its key-file setting for an account that is
    also covered by Windows OpenSSH's default `Match Group administrators`
    block.
13. Package limits are enforced while reading every decompressed stream, not
    only from ZIP header metadata. Installation validates credential protection
    before mutation and restores replaced channel artifacts after later errors.
14. Forced-command package transfer uses ordered bounded Base64 chunks because
    Windows OpenSSH did not reliably forward stdin to this `ForceCommand`.
15. Each deployment declares required global runtime functions. A hash-pinned,
    side-effect-free Symcon script checks them before activation, after restart
    and after rollback. Ready runlevel alone is not functional acceptance.
16. The candidate bootstrap token is exactly the staged fileset's
    `.saef-filesets/<targetDirectoryName>/bootstrap.php` path relative to the
    Symcon scripts root. The builder and gateway reject merely name-matching
    or otherwise divergent paths.
17. Standalone module packages reuse the same five verbs and transfer protocol.
    Their activation is delegated only to a server-local target adapter whose
    path, private policy, profile and hashes are pinned outside the client
    package, as specified by ADR-0009.

Possession of the transport key does not replace SAEF's operational approval
gates. Agents still require explicit approval before `activate`.

## Rationale

The forced-command design preserves the cross-platform maturity of OpenSSH
while reducing the remote action surface to the workflow SAEF has already
tested manually. The Windows dispatcher can reuse the existing restart
coordinator instead of duplicating service, readiness and rollback logic.

Separating package construction, inactive staging, preflight, activation and
status makes each side effect explicit. Server-local policy prevents a stolen
client key from choosing arbitrary files, services or credential destinations.

## Consequences

### Positive

- The same protocol works from macOS, iPhone and iPad SSH clients.
- No general remote PowerShell or Windows shell is exposed.
- Filesets and bootstrap changes remain deterministic and hash-pinned.
- Activation cannot bypass drift checks or the rollback coordinator.
- Private installation details remain in local policy and package files.

### Negative

- Windows OpenSSH and a carefully ACL-protected dedicated account require a
  one-time administrative setup.
- Mobile package upload requires an SSH terminal that can run the bounded
  `saef-deploy` chunk client; Safari and Apple Shortcuts are not clients for
  this first version.
- The DPAPI credential must be recreated when the Windows host changes.
- The public repository can verify contracts statically, but the final
  PowerShell parser and OpenSSH configuration checks must run on Windows.

## Alternatives considered

### General SSH PowerShell remoting

Rejected because SSH-based PowerShell remoting does not currently support JEA
or custom endpoint constraints and would expose an unnecessary language and
command surface.

### WinRM with JEA

Rejected for the first channel because it complicates secure macOS support and
does not provide the same straightforward client path on iPhone and iPad.

### GitHub Actions with an administrative self-hosted runner

Rejected because repository workflow changes could become an indirect general
code-execution path on the Windows host.

### Browser or Apple Shortcuts HTTPS endpoint

Deferred because it requires a separately reviewed service lifecycle,
authentication, replay protection, certificate handling and exposure model.

## Related

- `deployments/symcon/windows/README.md`
- `deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1`
- `deployments/symcon/windows/Invoke-SaefSymconRestart.ps1`
- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `adr/ADR-0005-generate-symcon-helper-bundles.md`
- `adr/ADR-0009-use-target-bound-standalone-module-deployment.md`
