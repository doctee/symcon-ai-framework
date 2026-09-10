# Gate 90-Q — Windows restart reference reconciliation

**Status:** Repository correction, complete local OwnTracks verification and
reproducible inactive package complete; Windows, stage and live gates remain
closed, 2026-09-07.

## Observed boundary

After a regular Windows restart, the previously healthy OwnTracks instance
reported invalid configuration. A subsequent channel-bound preflight failed
closed at Symcon ownership without attempting activation or rollback.

The bounded read-only diagnosis found unchanged instance configuration and no
pending property changes, but the kernel reported no outgoing references for
the instance. Applying the unchanged configuration did not restore the active
state. The visualization therefore returned only its generic configuration
error and did not expose location data or contact a tile provider.

The repository implementation explained this restart-specific state: the
module persisted its previous reference identifiers in an attribute and used
that attribute as the source for `UnregisterReference()`. Persistent module
attributes may survive a host restart while the kernel reference list is
empty. Attempting to unregister such a missing kernel reference aborts
`ApplyChanges()` before the configured references are registered again.

## Correction

The module still decodes and validates the persisted reference attribute so
corrupt module state fails closed. Cleanup now obtains the actual outgoing
reference list from `IPS_GetReferenceList()` and unregisters only those
references that currently exist in the kernel. It then clears the persisted
list and registers the newly validated configuration references exactly as
before.

This makes the kernel list authoritative for a kernel operation while retaining
the attribute as bounded persistent bookkeeping. It also clears any real
module-owned kernel reference left by an interrupted prior registration even
if that reference was not yet written to the attribute.

The change does not alter source selection, Archive Control reads, tile
authorization, cache or provider budgets, hooks, visualization behavior,
module identity, adapter policy or deployment-channel verbs.

## Regression and local verification

The runtime harness now rejects an attempt to unregister a reference that is
absent from its synthetic kernel. Its repeated-`ApplyChanges()` scenario then
models the observed restart split explicitly:

1. valid references and their persistent bookkeeping are created;
2. the synthetic kernel reference list is cleared without changing the
   persistent attribute;
3. `ApplyChanges()` must recover active status; and
4. the seven configured references must be restored without duplicates and
   persisted in deterministic order.

The complete OwnTracks suite, packaged-runtime checks, deterministic 37-file
module fileset, distribution validation, focused PHPCS and PHPStan checks, PHP
syntax and repository whitespace checks pass. Static analysis used an existing
lock-identical dependency tree; no dependency was downloaded or substituted.

## Inactive package

The corrected 37-file standalone module was built twice with byte-identical
output under deployment identifier
`saef-owntracks-position-map-20260907-04`:

- package identity:
  `d400c9a2d81799583bf9d9afe3e4b4c830757a11a7bdd5e86e265cf61a06cd39`;
- ZIP SHA-256:
  `ac291e9277a95e6f2b820931f2822c9ac1dc2d48b976a1dc534073f2cea942c9`;
- file count: 37; and
- archive integrity: passed.

The package remains local and inactive. It has not been transferred to or
staged through the Windows channel.

## Remaining gates

Windows PowerShell qualification of the exact package, channel stage,
target-bound preflight, activation with one controlled module reload,
independent health verification, browser acceptance and retention decisions
remain separate gates. The failed inactive deployment and the active rollback
boundary must remain untouched until those gates explicitly decide otherwise.
