# State-based IP-Symcon restart coordinator for Windows

`Invoke-SaefSymconRestart.ps1` performs the restart portion of a supervised
SAEF activation transaction. It must run from an elevated PowerShell process
outside the `IPSServer` service. Do not start it through an IP-Symcon script.

The coordinator does not use fixed restart delays. It waits, in order, for:

1. the Windows service state `Stopped`;
2. the Windows service state `Running`;
3. kernel runlevel `10103`; and
4. a kernel start time newer than the preflight value.

The supplied policy permits up to 180 seconds for each Windows service state
transition and up to 900 seconds for Symcon readiness. These are upper bounds:
the next step begins immediately when the required state is observed.

## Invocation

Use placeholders or private local variables for installation-specific paths.
Never add credentials or private paths to the repository.

First run the exact reviewed file in non-activating mode. This loads the script,
validates its policy and optional hashes, checks the running Windows service and
reads the current Symcon runlevel and kernel start time. It cannot reach a
service stop or bootstrap restoration:

```powershell
$credential = Get-Credential

& .\Invoke-SaefSymconRestart.ps1 `
    -PreflightOnly `
    -RpcUri 'http://127.0.0.1:3777/api/' `
    -Credential $credential `
    -StatusPath '<private-status-directory>\restart-status.json' `
    -ActiveBootstrapPath '<active-bootstrap-path>' `
    -ExpectedActiveBootstrapSha256 '<current-active-sha256>'

$LASTEXITCODE
Get-Content -Raw '<private-status-directory>\restart-status.json'
```

Exit code `0` with status phase `preflight`, outcome `passed` and
`restartAttempted: false` is required before an activation transaction may be
authorized.

For authenticated JSON-RPC, the credential must contain the Symcon license
email address and Remote Access password. The coordinator constructs an
explicit UTF-8 HTTP Basic header because Windows PowerShell does not reliably
select Basic authentication when only `Invoke-RestMethod -Credential` is used.
Special characters are encoded as part of the Basic header and must not be
URL-escaped by the operator.

For a later, separately authorized activation, omit `-PreflightOnly` and supply
the complete rollback set:

```powershell
$credential = Get-Credential

& .\Invoke-SaefSymconRestart.ps1 `
    -RpcUri 'http://127.0.0.1:3777/api/' `
    -Credential $credential `
    -StatusPath '<private-status-directory>\restart-status.json' `
    -ActiveBootstrapPath '<active-bootstrap-path>' `
    -ExpectedActiveBootstrapSha256 '<candidate-sha256>' `
    -RollbackBootstrapPath '<rollback-bootstrap-path>' `
    -ExpectedRollbackBootstrapSha256 '<rollback-sha256>'

$LASTEXITCODE
```

Omit all three rollback parameters only when rollback is deliberately not part
of the transaction. Supplying an incomplete rollback set fails preflight.

## Outcomes

| Exit code | Meaning |
| ---: | --- |
| `0` | Requested operation passed: either preflight-only or activation restart. |
| `10` | Preflight failed; no restart was requested. |
| `20` | Activation restart failed and no rollback was configured. |
| `30` | Activation failed, but bootstrap rollback and recovery succeeded. |
| `40` | Activation and rollback recovery both failed; manual recovery is required. |

The JSON status file is replaced atomically after every phase change. It holds
only state facts, timestamps and exception types. It deliberately excludes RPC
credentials, file paths and exception messages.

Runtime-health failures additionally expose one fixed diagnostic substage such
as `source_hash`, `execution` or `result_contract`. These bounded values locate
the rejected contract without returning source, function names, paths or error
messages.

The coordinator explicitly normalizes JSON arrays returned by Windows
PowerShell 5.1 before counting required functions. This avoids treating a
deserialized function list as one nested pipeline object while preserving the
byte-exact contract hash.

## Transaction boundary

The coordinator controls service restart, readiness verification and optional
bootstrap restoration. It does not stage a fileset, select private paths,
change MQTT configuration or authorize device actions. Candidate and rollback
hashes must come from the separately reviewed private activation record.

## Restricted deployment channel

The optional deployment channel adds an operating-system-neutral SSH boundary
around the coordinator. PowerShell remains a Windows-side implementation
detail. A client can use the same protocol from macOS or an SSH terminal on
iPhone and iPad.

The channel consists of:

- `Invoke-SaefDeploymentGateway.ps1`, the Windows forced-command dispatcher;
- `Invoke-SaefRuntimeMirror.ps1`, the bounded post-activation mirror
  coordinator;
- `SaefRuntimeSourceMirror.php`, the hash-pinned deployment-internal mirror
  reconciler;
- `SaefRuntimeHealthProbe.php`, the inert, hash-pinned Symcon compatibility
  sentinel executed around each activation restart;
- `Initialize-SaefDeploymentChannel.ps1`, the one-time guarded Windows
  bootstrap;
- `deployment-channel-policy.example.json`, the public policy shape;
- `tools/build-symcon-deployment-package.php`, the deterministic package
  builder;
- `deployment-package-plan.example.json`, the private plan shape; and
- `saef-deploy`, the POSIX client for macOS and compatible mobile terminals.

The dispatcher recognizes exactly five commands:

| Command | Side effect |
| --- | --- |
| `probe` | Validates pinned policy, DPAPI credential, active bootstrap, service state and read-only Symcon RPC readiness. |
| `stage begin|chunk|commit ...` | Transfers one bounded ZIP as ordered fixed-size chunks and stages an inactive fileset only after full hash verification. |
| `preflight <deployment-id>` | Revalidates every file, bootstrap drift and Symcon readiness without activation. |
| `activate <deployment-id>` | Requires a fresh preflight, replaces one equal-length bootstrap token and invokes the restart coordinator with rollback. |
| `status <deployment-id>` | Returns the sanitized bounded status record. |

There is no command for arbitrary PowerShell, arbitrary paths, service names,
RPC endpoints or script execution. All paths, limits, the loopback RPC URI and
the hashes of the restart and mirror artifacts come from
`deployment-channel.local.json` on the Windows host. That local file is
excluded from Git.

### Optional SAEF helper source mirror

The channel can maintain one visible, non-authoritative Symcon script containing
the `helpers/` source closure of the successfully activated fileset. The
filesystem fileset remains authoritative. Domain runtime sources,
installation-specific reference indexes, private configuration and ObjectIDs
are not copied into this mirror.

The generated script uses `__halt_compiler()` before the byte-exact helper
payload, has no event or action binding and is never loaded by the runtime.
Creation uses the active fileset's `SAEF_EnsureScript()`. Both currently
deployable filesets therefore include that existing helper; the mirror does not
introduce another Ensure implementation or public API.

### Recommended Symcon placement

Framework-owned runtime objects should live below a dedicated `System/SAEF`
category. Place the runtime health probe and source mirror below its `Runtime`
child; reserve sibling `Diagnostics` and `Maintenance` categories for objects
with those explicit responsibilities. The mirror parent configured in the
deployment-channel policy and its pinned local state must always match this
live parent.

System-wide MQTT owners should live below `System/Messaging/MQTT`, separated
into `Discovery Exporter`, `Runtime` and `Diagnostics` where those
responsibilities exist. Move a self-owned exporter tree only as a complete,
ID-preserving unit. Broker transports, device adapters and device-specific MQTT
objects remain with their owning system or device unless a separate ownership
analysis justifies moving them.

Enable the mirror only through the private initializer input by supplying an
existing parent object ID:

```powershell
& .\Initialize-SaefDeploymentChannel.ps1 `
    -DeploymentUser '<private-deployment-user>' `
    -PublicKeyPath '<private-ed25519-public-key-path>' `
    -RpcCredential $credential `
    -RuntimeMirrorParentID <private-existing-parent-id>
```

The initializer only records and protects this local policy; it does not create
the script. Each deployment preflight validates the parent and any previously
pinned mirror ID without mutation. After a successful activation and restart,
the channel reconciles the mirror automatically and records its script ID and
hashes only in excluded local state. Identical content is a no-op, existing
presentation is preserved, and a failed content update restores the previous
mirror.

Mirror failure, including failure to start the pinned mirror coordinator, does
not invalidate a successfully restarted runtime. Such an activation returns
exit code `0` with outcome `activated_mirror_degraded`; when the coordinator
started, its separate mirror status record provides the bounded failure stage.
Operators can repair discoverability without rolling back healthy production
code. Initial creation and later updates are therefore Symcon mutations and
remain subject to the normal reviewed deployment activation gate.

`probe` invokes the existing restart coordinator strictly in preflight-only
mode. It writes a bounded local status record but cannot stop or restart a
service, change the bootstrap or activate a fileset. A `ready` response therefore
also proves that the DPAPI credential can be decrypted by the SSH execution
identity and that authenticated loopback RPC is operational.

### Runtime compatibility gate

Every deployment plan declares a sorted, unique and bounded list of global PHP
functions that must survive the activation. A dedicated Symcon script containing
the byte-exact `SaefRuntimeHealthProbe.php` source checks that contract in a
normal script context. The restart coordinator verifies the script object type
and source hash, runs the probe before activation, runs it again after the new
kernel reaches the ready runlevel, and repeats it after any rollback restart.

Runlevel readiness alone is not functional acceptance. If the post-restart
probe finds a missing function or rejects its contract, the restart coordinator
uses the existing byte-exact bootstrap rollback and does not report the
candidate runtime as activated. The probe has no event, action or device access;
it only validates function availability and returns counts plus a contract hash.

The probe script is created once through the existing `SAEF_EnsureScript()`
helper under an explicitly selected private parent. Its resulting private object
ID is supplied as `-RuntimeHealthProbeScriptID` when installing the channel.
The initializer never invents Symcon object creation logic.

### Package contract

Create a private plan from `deployment-package-plan.example.json`. The plan
references:

- one reviewed generated fileset;
- a byte-exact snapshot of the currently active bootstrap;
- equal-byte-length current and candidate bootstrap tokens;
- the exact candidate token
  `.saef-filesets/<targetDirectoryName>/bootstrap.php` relative to the Symcon
  scripts root;
- a non-empty `requiredRuntimeFunctions` compatibility contract; and
- a private ZIP output path.

Build the package from the repository root:

```console
php tools/build-symcon-deployment-package.php private/deployment-plan.local.json
```

The builder rejects symlinks, unsafe paths, ambiguous token replacement and an
existing output. It records every file size and SHA-256 value, stores archive
entries in sorted order with fixed metadata and prints only the deployment ID,
package hash, file count and package size.

The gateway independently checks:

- the transmitted ZIP hash and compressed-size limit;
- the declared positive package byte count and one active upload at a time;
- ordered 4096-byte raw chunks transported as bounded Base64 command
  arguments, without general stdin, SFTP or filesystem access;
- automatic cleanup of expired upload state and full package hash verification
  before ZIP processing;
- exact archive membership, path safety and duplicate names;
- expanded size and file-count limits enforced again while each decompressed
  stream is read;
- a bounded persistent deployment count and total managed byte budget;
- every declared file size and SHA-256 value;
- immutable target and deployment identities;
- exact agreement between the candidate bootstrap token and the managed
  fileset destination;
- the complete staged directory before preflight and activation; and
- active and candidate bootstrap hashes around the exact token replacement.

Staging never selects the fileset, restarts Symcon or executes PHP.
`stage`, `preflight` and `activate` share a host-wide non-blocking mutex, so two
sessions cannot race package accounting, bootstrap validation or activation.
The default local policy permits at most 16 staged deployments and 512 MiB of
managed files. Reaching either limit fails closed and requires deliberate local
retention cleanup; no remote delete operation exists.

### Standalone module packages

Channel version 8 also accepts deterministic packages built with
`tools/build-symcon-module-deployment-package.php`. This reuses the same
`stage`, `preflight`, `activate` and `status` protocol; the SSH client and
forced-command grammar do not gain a module-specific command.

The generic gateway verifies the module `library.json` identity, canonical
package hash, complete `module/` inventory and the exact
`module-transaction.json` hash. It stages the candidate below the existing
managed root without selecting or loading it.

Module activation is available only when the protected server policy contains
one matching entry in `standaloneModuleTargets`. The entry binds a target ID
and adapter profile to a local PowerShell adapter and private adapter policy,
including both hashes. A client cannot provide any of those paths. An empty
target list disables standalone-module activation.

Create the private target input from `standalone-module-targets.example.json`
and pass it only to a separately authorized initializer preflight/install:

```powershell
& .\Initialize-SaefDeploymentChannel.ps1 `
    -PreflightOnly `
    -DeploymentUser '<private-deployment-user>' `
    -StandaloneModuleTargetsPath '<private-absolute-targets-path>'
```

The adapter contract and required transaction behavior are defined in
`project/STANDALONE_MODULE_DEPLOYMENT_CHANNEL.md`. In particular, the adapter,
not the generic gateway, must prove module ownership, quiescence,
configuration/state preservation, targeted Module Control reload and either
successful postflight or rollback. The framework never guesses a state
conversion.

Retention cleanup must preserve the one-deployment-to-one-fileset invariant.
Create a private plan from `deployment-retention-plan.example.json` and run the
cleanup script without `-Apply` first. The preflight reads every retained
`deployment.json`, rejects missing, duplicate or cross-paired mappings, scans
runtime files for candidate-fileset references, simulates removal of the exact
pairs and writes a machine-readable status. A later, separately authorized
`-Apply` run requires a local administrator, creates and SHA-256-verifies a
private backup, removes only the declared pairs and revalidates the complete
mapping. Deployment-only or fileset-only cleanup is intentionally unsupported.
Standalone-module candidates are also rejected here: their target adapter must
provide a separate state-aware retention workflow before any deletion gate.

### OwnTracks Position Map pilot adapter

The first repository-only target profile is documented in
`case-studies/owntracks-position-map/82-channel-v8-deployment-adapter.md` and
implemented under `deployments/symcon/windows/adapters/`. Its public policy is
an intentionally non-runnable placeholder: private positive instance IDs,
configuration and active-package hashes, protected paths and the Module Control
binding must be supplied only in excluded local configuration.

The adapter composes the existing standalone package and status contract. It
adds no remote verb. Its OwnTracks-specific boundary is exactly one pinned
module instance, five runtime lock files, zero active request leases, a fresh
format-2 authoritative-state snapshot, one targeted `MC_ReloadModule` per
activation/rollback direction and adapter-owned package/state retention.
Format changes fail closed. The companion retention command defaults to the
read-only `plan` operation; `apply` is a later local-administrator gate.

The adapter never creates its own transaction/rollback state root during
module `preflight` or `activate`. Provision that target-owned root separately
with `Initialize-SaefOwnTracksPositionMapAdapterState.ps1`. Its `preflight`
operation validates the hash-pinned private adapter policy, deployment account,
path separation, parent ACL and shared adapter mutex without creating a
directory. A separately authorized `install` operation requires the exact
confirmation phrase, creates only the configured missing leaf, applies the
same non-inheriting `SYSTEM`/Administrators/deployment-account ACL pattern as
the channel initializer and removes the still-empty leaf automatically if
post-creation verification fails. Existing roots are verified but never
rewritten by this command.

Adding this source does not populate `standaloneModuleTargets`, install the
adapter or its state root on Windows, or authorize a live preflight or
activation.

```powershell
& .\Invoke-SaefDeploymentRetentionCleanup.ps1 `
    -PlanPath '.\deployment-retention-plan.local.json'

$LASTEXITCODE
Get-Content .\deployment-retention-cleanup-status.local.json -Raw
```

### One-time Windows boundary

Use a dedicated local Windows account and a dedicated Ed25519 SSH key. Deny
interactive and Remote Desktop logon for that account. OpenSSH Server must
already be installed and running; the initializer deliberately does not add a
Windows capability or change firewall rules.

Copy this directory to the Windows host and verify `SHA256SUMS`. Run the
initializer from an elevated PowerShell process under an independent local
administrator account. The configured deployment account must exist, be
enabled and currently remain a local administrator because the bounded gateway
coordinates the IP-Symcon service restart. It should be denied local and Remote
Desktop logon after bootstrap.
The non-mutating preflight verifies the source checksums, parses every supplied
PowerShell runtime source, and checks Symcon, OpenSSH, loopback RPC configuration
and all paths:

```powershell
Unblock-File .\*.ps1

& .\Initialize-SaefDeploymentChannel.ps1 `
    -PreflightOnly `
    -DeploymentUser '<private-deployment-user>'

$LASTEXITCODE
Get-Content .\deployment-channel-bootstrap-status.local.json -Raw
```

Exit code `0`, outcome `passed`, `mutationAttempted: false` and
`sshdRestartAttempted: false` are required before installation. Then perform a
separate authorized installation with the public key and Symcon RPC credential:

```powershell
$credential = Get-Credential

& .\Initialize-SaefDeploymentChannel.ps1 `
    -DeploymentUser '<private-deployment-user>' `
    -PublicKeyPath '<private-ed25519-public-key-path>' `
    -RpcCredential $credential `
    -RuntimeHealthProbeScriptID <private-existing-probe-script-id>

$LASTEXITCODE
Get-Content .\deployment-channel-bootstrap-status.local.json -Raw
```

The initializer creates the private local policy, protects the gateway,
credential and state locations with Windows ACLs, installs the dedicated public
key, validates `sshd_config`, and restarts only `sshd`. It does not restart
Symcon or activate a fileset. The RPC credential uses Windows DPAPI
`LocalMachine` protection plus a non-secret format entropy value and strict file
ACLs. This allows non-interactive public-key SSH sessions to decrypt it without
an unlocked RDP profile. It is not transferable to another host and must never
enter a deployment package. Any legacy user-bound CLIXML credential is removed
only after a successful reinstall.

Credential protection is validated before the first filesystem mutation. Once
installation begins, the initializer keeps in-memory snapshots of every
replaced channel artifact and its ACL. A later failure restores those files
together with `sshd_config`, so the previously active forced-command channel
does not remain on a mixed artifact generation.

Validation failures before snapshot creation still pass an explicit empty
snapshot collection through the same cleanup path. The cleanup treats that
collection as a valid no-op while continuing to require the parameter. Early
preflight failures therefore retain the documented exit code `10`, write a
bounded failure status and never enter rollback or mutation handling.

The SAEF `Match User` block is reconciled before the first other active `Match`
block. OpenSSH keeps the first value obtained for a setting, so this ordering is
required when the deployment identity is also covered by Windows' default
`Match Group administrators` block. The same block contains the short local
account name and its `.\`-qualified spelling. Windows OpenSSH may authenticate
either spelling, while a bare-only user pattern does not reliably constrain the
qualified form. A repeated initializer run replaces and repositions exactly one
well-formed SAEF block; duplicate or partial markers fail closed.

For review, the initializer appends this bounded `Match User` shape to
`%ProgramData%\ssh\sshd_config`:

```text
Match User <private-deployment-user>,.\<private-deployment-user>
    AuthenticationMethods publickey
    PasswordAuthentication no
    PubkeyAuthentication yes
    AuthorizedKeysFile __PROGRAMDATA__/ssh/saef_deploy_authorized_keys
    PermitTTY no
    AllowTcpForwarding no
    PermitOpen none
    ForceCommand powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "<private-gateway-path>"
```

`ForceCommand` is enforced by Windows OpenSSH only for non-PTY sessions;
`PermitTTY no` is therefore part of the security boundary. The initializer
validates the complete SSH configuration before restarting `sshd` and restores
the prior configuration if validation or restart fails. Windows acceptance
must additionally exercise real client negotiation for the short,
machine-qualified and `.\`-qualified account spellings; parser success and one
`sshd -T` spelling do not prove that every accepted login spelling reaches the
same block. Keep an independent administrative recovery session available
during the first test.

`ExecutionPolicy Bypass` applies only to the forced PowerShell processes. It is
needed on hosts whose machine policy otherwise blocks all scripts. It does not
change a user or machine execution policy. The executable script paths and
dependency hashes remain server-controlled, and their files are protected by
the initializer's ACLs. The gateway never accepts PowerShell source or a script
path from the SSH client.

Windows OpenSSH documents `ForceCommand`, `Match` and key-based authentication
in its server and key-management guidance. PowerShell SSH remoting is not used
because SSH-based remoting does not currently support custom endpoint
configuration or JEA:

- <https://github.com/PowerShell/Win32-OpenSSH/wiki/sshd_config>
- <https://learn.microsoft.com/windows-server/administration/openssh/openssh_keymanagement>
- <https://learn.microsoft.com/powershell/scripting/security/remoting/ssh-remoting-in-powershell>

### macOS client

Pin the Windows host key and define a private alias in `~/.ssh/config`. Keep
the hostname, username and key path out of the repository:

```text
Host saef-symcon
    HostName <private-host>
    User <private-deployment-user>
    IdentityFile <private-key-path>
    IdentitiesOnly yes
```

Then use the POSIX client:

```console
deployments/symcon/windows/saef-deploy saef-symcon probe
deployments/symcon/windows/saef-deploy saef-symcon stage private/candidate.local.zip
deployments/symcon/windows/saef-deploy saef-symcon preflight saef-example-release
deployments/symcon/windows/saef-deploy saef-symcon activate saef-example-release
deployments/symcon/windows/saef-deploy saef-symcon status saef-example-release
```

The client requires strict host-key checking, batch public-key authentication,
no TTY and no forwarding. It computes the package SHA-256 locally and transfers
ordered 4096-byte chunks through the bounded forced-command protocol.

### iPhone and iPad client

Use an SSH terminal that supports key authentication, host-key verification and
local files, for example Termius, Blink Shell or an equivalent managed client.
Configure the same private SSH alias and pinned host key. Copy
only the reviewed ZIP into the terminal's private file area and run the same
`saef-deploy` script or the equivalent commands:

```console
ssh -T saef-symcon probe
ssh -T saef-symcon preflight saef-example-release
ssh -T saef-symcon activate saef-example-release
ssh -T saef-symcon status saef-example-release
```

For package transfer, use the same `saef-deploy` client so the package is sent
through the ordered chunk protocol. Raw stdin upload and SFTP are intentionally
not available.

```console
./saef-deploy saef-symcon stage candidate.local.zip
```

Do not place the SSH private key, package, bootstrap snapshot, host identity or
deployment status in iCloud Drive or a shared clipboard. A browser-only or
Apple Shortcuts interface is intentionally not part of this first channel; it
would require a separately reviewed HTTPS authentication and replay-protection
design.

### Authorization boundary

Possession of the SSH key grants access only to the five dispatcher commands;
it does not itself authorize a production activation. SAEF agents must still
obtain explicit approval before `activate`. Package creation, staging,
preflight, activation and postflight remain distinct recorded gates.
