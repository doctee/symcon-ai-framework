# Current Fileset and Mirror Activation

**Gate:** Four active wrappers and their managed runtime mirror
**Result:** PASS — CURRENT FILESET SELECTED
**Date:** 2026-07-20
**Live impact:** Four wrapper source-path updates, one mirror update and one generated-info cleanup

## Purpose

The repository runtime advanced by one functionally neutral static-analysis
cleanup after the first generated-mirror activation. This gate removed the
resulting repository/live drift by deploying the current hash-addressed
ControlLight fileset, selecting it in all four active wrappers and reconciling
the visible mirror to the same authoritative runtime.

No wrapper was manually executed, no event was modified and no device action
was requested.

## Fileset staging

A fresh preflight verified the ready kernel, the complete prior fileset, the
absence of the new target and staging directories, all 29 wrapper source
identities and the current mirror rollback source.

The complete generated fileset was then transferred into an inactive temporary
directory. Every file was checked for byte count and SHA-256 before and after
write. The directory contained exactly the expected files and was atomically
renamed to its aggregate-hash target. The temporary directory was absent after
finalization.

The first all-files transport request was rejected before reaching Symcon. A
separate read-only check proved that it created neither a staging nor target
directory. The successful retry used individually verified file transfers and
the same final atomic rename boundary.

The previous hash-addressed fileset remains untouched as the wrapper rollback
target.

## Wrapper selection

For each active wrapper, the candidate changed exactly one string: the old
hash-addressed fileset directory became the new hash-addressed directory.
Configuration, ownership, alarm semantics and capabilities remained byte-for-
byte unchanged.

The four updates were applied sequentially with complete source backups and
direct readback. A failure would have restored every already changed wrapper in
reverse order. All four writes passed, so rollback was not required.

The complete 29-wrapper source regression subsequently matched: the four active
wrappers had their exact new candidates and the other 25 remained unchanged.
All active wrapper scripts remained syntactically valid and non-broken.

One wrapper ran later through its existing regular event path. Its latest
execution and success timestamps matched, its error history predated the
deployment and no new authoritative-feedback command timestamp appeared. The
other wrappers were deliberately not executed merely to prove source selection.

## Coupled mirror reconciliation

The first mirror-content attempt encountered a post-write invariant mismatch.
Its exact rollback completed immediately and was independently confirmed. The
wrappers remained selected on the new, already verified fileset.

The retry generated the mirror directly from the new live runtime file while
still requiring equality with the offline candidate hash. Direct readback then
matched the expected mirror and runtime payload exactly. The complete private
reference index remained stable, and four representative console searches each
returned one mirror match without transport error, PHP error or truncation.

The original pilot had also placed the old runtime hash in `ObjectInfo`. Because
that field still matched the exact machine-generated pilot text, it was migrated
with compare-and-swap semantics to a stable, hash-free authority statement. A
different user-entered description would have stopped that metadata change.
Name, position, icon and visibility remained unchanged.

## Result and remaining observation

The four active wrappers and the visible mirror now select and describe the same
current file-backed runtime. The previous fileset and all source backups remain
available for deterministic rollback.

Source, topology, diagnostics and reference-search gates are complete. Three
wrappers have not yet traversed the new runtime through a regular event since
selection. Their existing event activity can provide bounded non-invasive
operational observation; no manual device test is required for this functionally
neutral source revision.
