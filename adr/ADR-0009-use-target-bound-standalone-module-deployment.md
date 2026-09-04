# ADR-0009: Use target-bound adapters for standalone module deployment

Status: Accepted
Date: 2026-09-03

## Context

The restricted Windows deployment channel already provides a hardened,
cross-platform transport for deterministic runtime filesets. Its five verbs,
ordered chunk upload, strict archive validation, inactive staging, fresh
preflight, serialized activation and bounded status response are also the
right boundary for standalone IP-Symcon module packages.

Standalone modules cannot, however, share one generic activation algorithm.
Their package location, effective module owner, quiescence locks, persistent
state schema, configuration snapshot, Module Control reload and rollback
conversion are module-specific. Treating these details as generic paths sent by
the client would expand the forced command into an administrative shell.

## Decision

SAEF extends the existing restricted channel with the deployment kind
`standalone-module`. It retains exactly `probe`, `stage`, `preflight`,
`activate` and `status`; no sixth command or alternate transfer endpoint is
introduced.

The client uploads one deterministic package containing:

- a bounded deployment manifest;
- an exact module fileset below `module/`; and
- a hash-bound `module-transaction.json` contract.

The generic gateway validates and stages those bytes. Activation is delegated
only to a server-local target entry. Each entry binds one public target ID,
module-library GUID and adapter profile to an absolute adapter path, an
absolute private policy path and their exact SHA-256 hashes. The client can
select the public target ID but cannot supply a path, executable, RPC endpoint
or credential.

The initializer accepts target bindings only from an explicitly selected local
file, syntax-checks every PowerShell adapter and pins both adapter and policy
hashes into the ACL-protected channel policy. An empty target list keeps
standalone-module activation disabled.

The target adapter owns the module-specific transaction. It must fail closed
unless it can prove unique ownership, byte-exact configuration and package
identity, quiesce every declared writer, snapshot current package and state,
apply the candidate, execute one targeted Module Control reload and validate
postconditions. If state formats differ, the adapter must use an explicitly
reviewed converter; the framework never infers a conversion. Any failure after
mutation must either prove rollback or report `manual_recovery_required`.

Adapter status is a fresh, bounded record bound to deployment-manifest and
package hashes. The gateway returns only allowlisted state facts. Adapter paths,
private policy, installation identifiers and error messages are never returned.

## Rationale

This reuses the established channel instead of creating another webhook, SSH
identity or transfer client. The generic layer is responsible for properties it
can prove uniformly; the target adapter remains responsible for application
state it alone understands. Hash-pinned local allowlisting prevents a stolen
transport key from turning that separation into arbitrary script execution.

## Consequences

### Positive

- Standalone modules use the same reviewed transfer and approval boundary.
- Module-specific state handling remains explicit and testable.
- Adding a target does not broaden the remote command grammar.
- Targets are disabled until an administrator installs a reviewed binding.

### Negative

- Each module family needs a reviewed transaction adapter and local policy.
- Repository tests can validate package and dispatcher contracts, but final
  parser, ACL and transaction tests still require Windows.
- Updating the gateway or target allowlist remains a separate live
  administrative gate.

## Alternatives considered

### General module-directory replacement in the gateway

Rejected because it cannot safely infer locks, state compatibility,
configuration ownership or rollback preparation.

### Continue using temporary Symcon scripts

Rejected as a recurring mechanism. A temporary script can be justified for a
single explicitly approved transition, but it is not a reusable deployment
channel.

### Add a module-upload webhook

Rejected because it would duplicate authentication, replay protection,
transfer limits and status handling already provided by the restricted channel.

## Related

- `adr/ADR-0007-use-restricted-windows-deployment-channel.md`
- `adr/ADR-0008-use-manifest-driven-module-publication.md`
- `project/STANDALONE_MODULE_DEPLOYMENT_CHANNEL.md`
- `deployments/symcon/windows/README.md`
