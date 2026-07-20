# Runtime Mirror and Reference Search Pilot

**Gate:** One authorized non-action-path Symcon script object
**Result:** PASS — PILOT KEPT VISIBLE
**Date:** 2026-07-20
**Live impact:** One informational script object; no wrapper or device change

## Purpose

The versioned ControlLight runtime is intentionally executed from the Symcon
scripts filesystem. This preserves one file-level source of truth and avoids
duplicating runtime code across wrapper objects, but the complete runtime is
not directly visible in the Symcon object tree. Dynamic Ident/link discovery
also limits the usefulness of the console's numeric reference search.

The pilot tested a hybrid visibility layer without changing the execution
architecture.

## Pilot Object

One visible managed script object was created beside the existing central
lighting scripts. Its source contains:

- a read-only verifier for the active runtime file path and pinned SHA-256;
- an explicit private reference index for the four active wrappers and their
  parents, local variables, targets, events, alarms and legacy-core dependency;
- a clear generated/do-not-edit ownership header; and
- the complete `ControlLightRuntime.php` source after `__halt_compiler()`, where
  it remains visible but cannot enter execution.

Manual execution only verified file existence and hash. The object is not
referenced by any wrapper, event or variable action and is not part of the
ControlLight action path.

## Reference Search Evidence

The live console's own reference implementation was first identified through
the installed function metadata. A known legacy reference confirmed that it
returns source object, line content, line number and character position.

Four representative IDs were then queried: an active wrapper, a local
variable, a target variable and the legacy central script. Every query returned
exactly one match in the new mirror/reference object with the expected line and
position. This proves that normal PHP arrays in the generated reference block
restore useful console reference discovery.

The full runtime text was independently read back from the script object and
matched the local runtime source suffix byte-for-byte. Executing the mirror
completed without PHP or transport error and did not issue a device action.

## Engineering Decision

The hybrid approach is viable:

- filesystem runtime remains authoritative and executable;
- Symcon object remains generated, visible and non-authoritative;
- numeric live relationships are generated private metadata;
- mirror drift can be detected by source and embedded-runtime hashes; and
- runtime performance, per-wrapper semaphore behavior and rollback boundaries
  remain unchanged.

The pilot should not yet become a generic public helper. The next gate is an
idempotent installation-specific generator that updates this one owned object,
preserves its user-controlled name and position, verifies direct readback and
fails on unexpected ownership or content drift. Only after repeated use should
the pattern be promoted into a reusable SAEF abstraction.
