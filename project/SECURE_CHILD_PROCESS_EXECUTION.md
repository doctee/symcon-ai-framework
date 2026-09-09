# Secure Child Process Execution

Status: Stable Draft 1.0, repository implementation complete; exact Windows
PowerShell 5.1 qualification and installation remain separate gates

## Purpose

This contract defines how trusted SAEF Windows coordinators invoke another
reviewed PowerShell script. It closes process-lifetime and output gaps without
creating a general process API, a new SSH verb or target authority.

The reusable internal implementation is
deployments/symcon/windows/SaefChildProcess.ps1. It is deployment
infrastructure, not a PHP helper or public Symcon API.

## Call contract

A caller supplies:

- one absolute reviewed PowerShell script path;
- the exact lowercase SHA-256 of that script;
- a bounded array of script arguments;
- optional bounded environment values whose names use the SAEF prefix;
- a timeout from 1 to 900 seconds;
- a combined output limit from 1 byte to 1 MiB; and
- optionally one existing absolute non-reparse working directory.

The launcher supplies the Windows PowerShell executable and fixed NoLogo,
NoProfile, NonInteractive, ExecutionPolicy Bypass and File arguments. Callers
must not repeat interpreter options or File.

The result contains only exit code, termination reason, bounded output bytes,
observed output byte counts and duration. Non-zero child exit codes are
returned to the owning coordinator. Timeout and output overflow terminate the
Job Object and throw a classified exception.

## Security invariants

- No remote input selects an executable, script path or command string.
- Script identity is checked immediately before process creation.
- Shell execution, Invoke-Expression, cmd /c, PowerShell Command mode and
  alternate executables are outside the contract.
- Script and working-directory reparse points are rejected.
- Argument and environment values reject NUL and line breaks and have hard
  count and size limits.
- Standard input is redirected and closed; children cannot consume the
  gateway transport stream.
- Ambient SAEF-prefixed environment entries are removed before the explicitly
  bounded child environment is applied.
- Standard output and standard error share one capture budget.
- Timeout and output overflow terminate the complete assigned process tree.
- A pre-existing process-host type is rejected rather than reused.
- Process success never substitutes for the child's own status and postflight
  validation.

Production callers remain responsible for protected ACLs, target allowlists,
operation allowlists, lock ordering, state transitions and rollback.

## Approval capability handling

The scope-bound approval envelope is a short-lived capability. The gateway
passes it in the bounded SAEF_APPROVAL_ENVELOPE environment entry so it is not
visible in the normal process command line. The runner:

1. rejects simultaneous command-line and environment sources;
2. selects exactly one bounded Base64URL value;
3. clears the environment entry before any child launch; and
4. performs the existing HMAC, plan, identity, nonce, expiry and replay checks.

The optional command-line parameter remains temporarily accepted for direct
recovery compatibility, but channel and qualification paths do not use it.
Removal requires a separately reviewed compatibility decision.

## Consumers

| Owner | Child | Bound |
| --- | --- | ---: |
| deployment gateway | restart coordinator | 600 s / 64 KiB |
| deployment gateway | runtime mirror coordinator | 300 s / 64 KiB |
| deployment gateway | target adapter | 300 or 900 s / 64 KiB |
| deployment gateway | approval runner | 900 s / 64 KiB |
| approval runner | target adapter | 300 or 900 s / 64 KiB |
| approval runner | active-identity reseal | 300 s / 64 KiB |
| Windows approval qualification | profile initializer and runner | 120 or 180 s / 64 KiB |

The channel initializer's fixed sshd.exe syntax check is the only documented
bootstrap exception because the contract is not installed yet. It neither
uses remote input nor expands executable authority.

## Threat model

| Threat | Control |
| --- | --- |
| arbitrary command or executable | fixed PowerShell binary, File mode and hash-pinned server-selected script |
| argument injection | direct argument quoting, no shell, NUL/newline and aggregate bounds |
| approval disclosure in process list | bounded environment transfer and immediate clearing |
| ambient capability or transport-input inheritance | remove inherited SAEF entries and close redirected standard input |
| hung child | explicit timeout |
| orphaned descendant | kill-on-close Windows Job Object |
| output memory exhaustion | shared bounded byte capture and process termination |
| script replacement or link redirection | exact SHA-256, reparse rejection and caller-owned protected ACL |
| mixed channel generation | contract path/hash repeated by channel policy, approval policy and qualification evidence |
| false process success | independent bounded child status and owner postflight validation |

Residual risks are limited to trusted Windows implementation behavior, the
short process-start-to-job-assignment interval and administrator compromise.
They require exact Windows qualification and protected installation; repository
tests alone are not sufficient evidence.

## Qualification

Invoke-SaefChildProcessWindowsQualification.ps1 must run under Windows
PowerShell 5.1 against exact reviewed hashes. Its protected scratch cases prove:

- argument and environment roundtrip without capability text in the command
  line;
- removal of ambient SAEF-prefixed values and closed standard input;
- preservation of a non-zero exit code;
- no start after script-hash rejection;
- environment-name rejection;
- output-bound termination;
- timeout plus descendant termination; and
- complete scratch cleanup without production mutation or service restart.

The scope-bound approval Windows qualification also uses the exact same
launcher for profile and runner children and includes its hash in final
evidence.

The dedicated launcher result requires four positive and four negative case
groups, protected scratch cleanup, no production mutation and no service
restart.

## Migration and compatibility

The channel remains version 8 and keeps exactly probe, stage, preflight,
activate and status. Package, plan, target, adapter, reseal, restart,
postflight and rollback semantics are unchanged.

Installing this generation adds one protected runtime file and two local policy
fields. Existing installations require a separately authorized initializer
preflight, backup, install, OpenSSH syntax/restart gate and independent probe.
Existing approval profiles must be regenerated because their policy and
qualification evidence gain the exact child-process hash.

Earlier Windows approval evidence remains valid only for its historical source
generation. It cannot authorize the new runner or channel artifacts.

## Remaining gates

1. Repository review, complete SAEF checks and immutable commit.
2. Exact Windows PowerShell 5.1 parser and child-process scratch qualification.
3. Exact scope-bound approval Windows requalification.
4. Read-only installed-channel preflight and backup review.
5. Separately authorized channel installation and OpenSSH restart.
6. Independent probe, policy/hash readback and process cleanup postflight.
7. Separate target profile installation and live module activation gates.
