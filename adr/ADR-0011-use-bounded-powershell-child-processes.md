# ADR-0011: Use bounded PowerShell child processes

Status: Accepted for repository integration; Windows and live use remain gated
Date: 2026-09-09

## Context

The restricted deployment gateway, scope-bound approval runner and Windows
qualification used several direct Windows PowerShell child launches. Their
script paths and arguments were structured and hash-pinned by surrounding
contracts, but process lifetime, output, descendant cleanup and sensitive
argument handling were not owned by one reusable boundary.

Extending every coordinator independently would duplicate security behavior.
Adding a gateway verb or accepting a command, executable or script path from a
remote request would widen the channel's authority.

## Decision

SAEF uses the internal SaefChildProcess.ps1 contract for runtime and
qualification launches of reviewed PowerShell scripts.

The contract:

- selects the fixed Windows PowerShell 5.1 executable itself;
- accepts only an absolute, non-reparse script with an exact expected SHA-256;
- invokes no shell and accepts no command text or alternate executable;
- bounds argument count, per-value size and aggregate command-line size;
- permits only bounded SAEF-prefixed environment additions;
- removes ambient SAEF-prefixed environment entries and closes redirected
  standard input before the child can consume transport input;
- bounds combined standard output and standard error;
- applies an explicit timeout; and
- assigns the child to a Windows Job Object configured to terminate the
  process tree when the job closes.

The gateway policy pins both the installed contract path and its exact hash.
The scope-bound approval policy and Windows qualification evidence repeat the
contract hash. A mixed generation therefore fails before adapter mutation.

The approval envelope is delivered to the runner as the
SAEF_APPROVAL_ENVELOPE environment value, not as a command-line argument. The
runner rejects two simultaneous sources, validates the bounded encoding and
clears the environment value before invoking an adapter or reseal child.

The deployment initializer continues to execute the fixed sshd.exe syntax
bootstrap check directly. That executable and argument set are locally fixed,
and the child-process contract is not yet installed at that point. This narrow
bootstrap exception does not permit a remote or policy-selected command.

## Rationale

There are multiple runtime and qualification consumers with the same process
safety needs, so one internal component is justified by demonstrated reuse.
It composes existing coordinators and adapters without changing their
ownership, status schemas or rollback responsibilities.

Hash-pinned scripts and protected ACLs remain necessary. The process contract
does not turn an untrusted script into a trusted one and does not authorize a
target, deployment or operation.

## Consequences

### Positive

- Hung children and descendants have one deterministic termination boundary.
- Unbounded child output cannot exhaust the gateway or qualification process.
- Approval material is no longer exposed in ordinary process command lines.
- Gateway, approval and Windows qualification use the same launch semantics.
- The five remote channel verbs and target allowlists remain unchanged.

### Negative

- The C# Job Object host must be parsed and executed under Windows PowerShell
  5.1 before installation.
- Assigning a newly started, already hash-pinned PowerShell process to a Job
  Object has a small startup interval; protected source ACLs and fixed script
  identity remain part of the defense.
- Every changed launcher generation invalidates earlier exact Windows
  qualification evidence and must be requalified.

## Alternatives considered

### Keep direct launches in every coordinator

Rejected because timeout, output and process-tree behavior would continue to
drift across security-sensitive call sites.

### Use Start-Process, jobs or a shell command

Rejected because output capture and process-tree termination are incomplete or
would introduce another command parsing boundary.

### Add a general remote execution operation

Rejected because it would defeat the restricted forced-command design.

## Related

- adr/ADR-0007-use-restricted-windows-deployment-channel.md
- adr/ADR-0010-use-scope-bound-one-click-deployment-approval.md
- project/SECURE_CHILD_PROCESS_EXECUTION.md
- project/CHANNEL_V8_ONE_CLICK_THREAT_MODEL.md
- deployments/symcon/windows/SaefChildProcess.ps1
