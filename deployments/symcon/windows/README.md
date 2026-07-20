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

## Transaction boundary

The coordinator controls service restart, readiness verification and optional
bootstrap restoration. It does not stage a fileset, select private paths,
change MQTT configuration or authorize device actions. Candidate and rollback
hashes must come from the separately reviewed private activation record.
