# Gate 106 — channel and adapter-state separation correction

**Status:** A fail-closed live staging attempt exposed a namespace and capacity
integration defect. The corrective repository candidate and deterministic
OwnTracks module package are verified offline; Windows qualification, channel
update, state migration and another staging attempt remain later gates,
2026-09-06.

## Observation

The installed version-8 channel accepted `probe` and advertised exactly one
standalone-module target. The new immutable OwnTracks package transferred all
192 declared chunks, but the server rejected `stage commit` with
`stage_usage`. No inactive candidate was committed and the active module was
not touched.

A bounded read-only Symcon MCP inspection separated the two causes:

- the generic inventory already contained 16 valid deployment/fileset pairs,
  which equals the installed default deployment-count limit; and
- the adapter-owned OwnTracks transaction root had been provisioned as an
  additional child inside the generic deployment-state root, where the gateway
  correctly rejects it as a non-deployment directory.

The state root was installed after the first OwnTracks stage operation. That
ordering explains why the initial pilot deployment succeeded while the next
package could not be staged. The managed filesets occupied only a small
fraction of the byte budget; package contents, transfer hash and provider
behavior were not the cause.

## Corrective contract

The repository candidate makes three coordinated changes:

1. Channel policy gains a protected adapter-state root below the Symcon scripts
   root, pairwise disjoint from generic deployment state and managed filesets.
2. The initializer exposes `MaxDeploymentCount` with default 16 and unchanged
   hard maximum 64. The reviewed live target value is 24 so no unrelated
   retained deployment must be deleted merely to continue the OwnTracks pilot.
3. The OwnTracks migration command moves the bounded legacy transaction tree on
   the same volume while holding the channel mutex before the adapter mutex. It
   verifies the tree identity, atomically updates the private adapter policy and
   its hash-pinned channel binding, and restores both policies plus the original
   directory after a recoverable failure.

The migration performs no Symcon RPC call, Module Control reload, module
activation, provider request, publication or retention cleanup. Its preflight
is read-only and its apply operation requires the exact explicit confirmation.

## Package and offline evidence

The corrected OwnTracks distribution contains 37 files. Two independent
deterministic builds produced the same 783065-byte ZIP, package identity
`ccfc2c326e16eb8b31421a7bcbb574c7324626d45b48580263250bc7e76b2b7e`
and package SHA-256
`543c858f0a15e4fed063528846451dfc106cbeb347b0b7bc8e56366f2aaca611`.
ZIP integrity, manifest identity, file order, sizes, hashes and transaction
contract all passed. The complete OwnTracks regression, generic channel tests
and adapter-state migration contract passed without Symcon or provider
contact.

## Remaining gates

1. qualify the exact channel and migration sources with Windows PowerShell 5.1
   synthetic success, contention and rollback scenarios;
2. run a read-only live update/migration preflight;
3. install the separated channel root and bounded capacity policy;
4. migrate and reseal the byte-identical OwnTracks adapter state;
5. repeat channel `probe`, inactive `stage`, target-bound `preflight`, explicit
   activation and independent health/browser/Safari checks; and
6. retain all existing rollback artifacts; publication and cleanup remain out
   of scope.
