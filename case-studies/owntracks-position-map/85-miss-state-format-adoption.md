# Gate 85 — lossless miss-state format adoption

**Status:** Repository implementation and synthetic contract verification
complete; the separate Windows qualification passed in
[Gate 86](86-miss-state-adoption-windows-qualification.md), while every
installation or live-state gate remains closed, 2026-09-05.

## Decision

The format-1 miss state is adopted before the channel-v8 module adapter is
allowed to run. The adoption is an OwnTracks-specific prerequisite, not a new
deployment-channel command and not a general SAEF state-migration API.

The repository adds:

- `Invoke-SaefOwnTracksPositionMapMissStateAdoption.ps1` with the two explicit
  operations `preflight` and `adopt`;
- a canonical format-1-to-format-2 contract;
- a deliberately non-runnable private-plan example; and
- a repository regression for locking, preservation, rollback and runtime
  compatibility.

No target is added to the public allowlist. The existing module adapter keeps
its strict format-2-to-format-2 transaction boundary.

## Reuse before extend

The adoption reuses the module adapter's named mutex, fixed five-lock order,
Windows byte-range lock convention, zero-active-lease check, protected-ACL
classifier, ordinal module-tree identity and same-directory atomic file
replacement. It pins the reviewed `OwnTracksTileMissStateStore` payload by
relative path and SHA-256 before accepting format 2.

No helper or public PHP API is added. The state transformation remains inside
this case-study adapter because only the OwnTracks resolver owns the
`pendingReservations` ledger.

## Two-phase authorization

`preflight` requires a private, byte-exact source hash and the exact active
module-tree identity. Under the shared adapter mutex and all five runtime
locks it requires zero unexpired tile/provider leases, validates the complete
format-1 schema, prepares format 2 in memory and reports the candidate and
semantic hashes. It neither creates a transaction nor writes the active state.

`adopt` requires a new private plan containing:

- the same source and active-package hashes;
- the exact candidate hash reported by the approved preflight;
- a protected, pre-existing and same-volume transaction root; and
- the explicit confirmation `adopt-format-1-to-2`.

The mutating operation additionally requires an elevated local Windows
administrator. It is not exposed as a deployment-channel verb or allowlisted
target. Its private status file must reside directly in the dedicated adoption
root. That root may not overlap the active module, the five runtime roots or
the module adapter's own transaction root.

State drift, package drift, an unsupported store payload, a reparse point,
broad write ACL, lock contention, an active request lease or any schema
difference stops before active-state mutation.

## Lossless transformation

The adapter preserves every selection key and its:

- `updatedAt` timestamp;
- selection fingerprint;
- six consumed/request counters; and
- complete negative-cache map.

It changes only the top-level version from 1 to 2 and adds an empty
`pendingReservations` map to each resolver state. The source and candidate
must have the same independently derived semantic hash. No selection may be
removed and no counter may be reset.

This is intentionally stricter than triggering the normal runtime migration
through a synthetic resolver access: such an access would also advance the
selection's `updatedAt`. The adoption therefore prepares the schema directly
and uses the existing store as the pinned format-2 compatibility boundary.

## Backup, switch and rollback

Before replacing `state.json`, `adopt` creates a private transaction containing
the exact format-1 source bytes, exact candidate bytes and a hash-only record.
Both payload hashes are read back before the active file can change. The
candidate is then replaced atomically by a temporary file in the active state
directory while all runtime locks remain held.

The postcondition independently verifies the candidate bytes, full format-2
schema, semantic hash and zero active leases. Any failure after replacement
restores the retained format-1 bytes atomically and verifies their exact hash,
schema and semantics before reporting `rolled_back`. An unprovable restore is
reported as `manual_recovery_required`, never as success.

The retained source is only an immediate transaction rollback. After any
successful format-2 runtime write it is stale and must not be restored. A
later old-package rollback still requires a fresh quiescent format-2 snapshot
and `tools/prepare-miss-state-rollback.php --prepare-legacy` as specified in
Gate 80.

Adoption evidence has its own owner and is outside the module transaction
retention inventory. Automatic cleanup is prohibited; deletion needs a
separate authorization and state-aware review.

## Synthetic evidence

The repository test proves that:

- a held miss-state writer lock prevents preparation and leaves the source
  bytes unchanged;
- the prepared format-2 candidate preserves timestamps, counters, fingerprints
  and negative-cache entries while initializing an empty reservation ledger;
- the pinned runtime accepts the candidate without resetting consumed bytes;
- a forced post-replacement failure restores the original pretty-printed
  format-1 bytes and SHA-256 exactly; and
- the existing fresh format-2-to-format-1 rollback preparation preserves the
  same conservative accounting semantics.

Static checks additionally bind backup-before-replace ordering, automatic
rollback-before-rollback-evidence ordering, the fixed lock set, active-package
and source/candidate hash gates, absence of reload/provider/channel calls and
the four explicit exit outcomes.

## Remaining gates

The Windows PowerShell 5.1 synthetic qualification, including lock contention
and forced rollback, is complete in Gate 86. The remaining gates are:

1. Materialize a fresh private live preflight plan and run only `preflight`.
2. Review its source, candidate and active-package hashes, then authorize
   `adopt` separately.
3. Repeat the target-allowlist preflight and require
   `adapterPreflightReady: true` before considering target installation.
4. Keep target installation, channel `probe`, inactive `stage`, module
   `preflight`, `activate`, independent UI/Safari health, retention,
   publication and cleanup as separate gates.

This gate did not contact Symcon or a provider, create or modify a Windows
target, install a channel file, reload a module, change a live state, publish
an artifact or delete retained evidence.
