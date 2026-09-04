# Standalone Symcon module deployment channel

**Status:** Channel version 8 is installed and live-verified. The standalone
module target allowlist remains intentionally empty, so module activation is
not enabled.

## Shared-impact inventory

The changed API is the existing forced-command deployment protocol. Its source
owner is `Invoke-SaefDeploymentGateway.ps1`; the initializer installs that file
and writes its protected local policy. `saef-deploy` is the only repository
client. The deterministic package builders, retention tool, restart
coordinator, runtime health probe and optional source mirror are direct
consumers of the current manifest/state layout.

The first effective live owner is the installed, hash-pinned Windows gateway,
not a PHP helper, bootstrap guard or later module. Known consumers are the SAEF
runtime-fileset deployments and their generated helper closure. Standalone
module publication is separate repository tooling and does not contact a live
installation.

No helper API, PHP autoload boundary or application runtime is changed. The
separately authorized channel installation restarted only OpenSSH; it did not
restart Symcon, activate a module or alter an active observation or pilot.
Before any later live channel update, active pilots and restart constraints
must be inventoried again.

Command-free regression coverage includes the original runtime-fileset package,
chunk-client reconstruction, strict command grammar, source checksums,
forbidden shell patterns and a deterministic synthetic standalone-module
package. Windows syntax, ACL, real SSH negotiation and adapter transaction
tests passed before the version-8 installation and remain mandatory for later
channel changes.

## Package contract

Create a private plan from
`deployments/symcon/windows/standalone-module-deployment-plan.example.json` and
an adapter-owned transaction contract from
`standalone-module-transaction.example.json`. Then build locally:

```console
php tools/build-symcon-module-deployment-package.php \
  private/standalone-module-deployment-plan.local.json
```

The builder requires a matching `library.json` GUID, rejects links and unsafe
paths, canonicalizes the transaction JSON and produces a deterministic ZIP.
Its manifest binds deployment and target identities, every module file, a
canonical package identity and the exact transaction-contract hash.

The existing client transfers the result unchanged:

```console
deployments/symcon/windows/saef-deploy <private-alias> stage \
  private/standalone-module-candidate.local.zip
```

The gateway identifies the deployment kind inside the verified manifest. The
remote grammar and 4096-byte ordered Base64 transport are unchanged.

## Server-local target binding

An administrator creates a private target file from
`standalone-module-targets.example.json`. Each target provides only:

- a stable public target ID;
- an adapter profile;
- the expected module-library GUID;
- the reviewed adapter path; and
- the private adapter-policy path.

The initializer parses the adapter, hashes both dependencies and writes the
hashes into the protected channel policy. The supplied file paths never enter a
package or status response. Omitting `-StandaloneModuleTargetsPath` creates an
empty allowlist and disables standalone-module activation.

Adding or changing a target is a deployment-channel mutation. Run the
initializer in `-PreflightOnly` mode first and authorize the later installation
separately. Installing repository channel version 8 restarts only OpenSSH; it
does not activate a module or restart Symcon.

## Adapter invocation and result

The gateway starts the pinned adapter with fixed argument names and
server-derived values:

- operation `preflight` or `activate`;
- staged manifest and candidate paths;
- staged transaction-contract path;
- pinned private adapter-policy path;
- loopback RPC URI and DPAPI credential path; and
- private bounded status path.

The adapter must atomically write a version-1 status record containing
`timestampUtc`, `operation`, `deploymentId`, `manifestSha256`,
`packageIdentitySha256`, `outcome`, `activationAttempted`,
`rollbackAttempted` and `rollbackSucceeded`. Preflight accepts only `passed`
without a mutation attempt. Activation accepts only `activated`, `rolled_back`
or `manual_recovery_required` with consistent rollback facts.

An adapter must additionally enforce its private contract for unique Symcon
ownership, positive object types, configuration hash, module/library identity,
protected ACL, reparse-point rejection, writer locks and leases, state schema,
targeted reload, health checks and rollback retention. These responsibilities
cannot be inferred by the generic gateway.

## Approval and rollback boundaries

The gates remain distinct:

1. build and local package verification;
2. live `probe`;
3. inactive `stage`;
4. read-only module `preflight`;
5. explicit `activate`;
6. independent postflight; and
7. separately authorized retention cleanup.

One approval must name the exact package and phase. Channel installation does
not authorize module activation. Module activation does not authorize
publication, cache deletion, state reset, rollback-artifact removal or channel
upgrade.

The existing generic paired-retention tool recognizes standalone-module state
but rejects it as a deletion candidate. Cleanup must be implemented and proven
by the same target adapter that understands active and rollback package/state
references; it cannot be inferred from directory pairing alone.

When candidate and rollback state schemas differ, rollback means quiescing all
writers, snapshotting the fresh state, applying the reviewed adapter-owned
conversion and switching state plus package consistently. Restoring a stale
backup is not rollback.

## Current boundary

This work implements the provider-neutral package, transport, staging,
allowlist and adapter-dispatch contracts. It deliberately introduces no map
abstraction and contains no module-specific paths, ObjectIDs, configuration or
state converter.

The installed channel reports both `runtime-fileset` and `standalone-module`
as supported deployment kinds. Independent macOS and Windows postflight checks
confirmed channel version 8, the unchanged five-command boundary, alias-complete
public-key-only SSH handling and bounded rejection of invalid target contracts.
The installed standalone-module target count is zero. No standalone-module
package was staged, preflighted or activated during the channel installation.

A concrete module adapter and its private target binding are the next module
workstream, not a generic helper. Their installation, package preflight,
activation, postflight and retention cleanup remain separately authorized
gates.
