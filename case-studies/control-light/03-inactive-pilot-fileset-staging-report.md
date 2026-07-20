# ControlLight v2 Inactive Pilot Fileset Staging Report

**Gate:** Recoverable inactive filesystem staging
**Result:** PASS
**Date:** 2026-07-18
**Activation state:** ControlLight v2 remains inactive

## Scope

After explicit authorization, the reviewed ControlLight v2 dependency closure
was copied into a new versioned directory on the connected Symcon host. This
gate authorized only inactive filesystem placement.

It did not change the pilot wrapper, shared legacy core, global bootstrap,
Symcon object tree, events, variable actions or device state. It did not load a
ControlLight-v2 class or restart IP-Symcon.

## Transfer and Finalization

The complete fileset contains fourteen files: eleven canonical PHP sources and
three deployment metadata files. Each file was:

1. decoded only inside an explicitly named temporary directory;
2. checked against its local SHA-256 before atomic file finalization;
3. checked again through the pinned provenance manifest;
4. compared as part of the exact relative path map;
5. atomically finalized under the aggregate-hash versioned directory name.

The initial combined transport request exceeded the connector payload limit
and was rejected with HTTP 413 before PHP execution. Transfer then proceeded as
fourteen bounded, individually hash-checked writes. A later finalization probe
contained a parse error and likewise made no change; the corrected probe reused
the unchanged staged tree and completed successfully.

## Independent Readback

An independent read-only check after finalization confirmed:

| Check | Result |
| --- | --- |
| Final versioned directory present | PASS |
| Temporary staging directory absent | PASS |
| Exact 14-file path map | PASS |
| Eleven canonical source hashes | PASS |
| Manifest, bootstrap, runtime and aggregate marker | PASS |
| Selected by global bootstrap | No |
| Pilot wrapper identity | Unchanged |
| Pilot event contracts | Unchanged |
| Authoritative feedback equality | PASS |
| ControlLight-v2 classes loaded | No |
| Restart or device action | None |

Both the staging transaction and independent readback reported no transport or
PHP execution error in their successful calls, and neither result was
truncated.

## Gate Decision

Inactive fileset staging is **PASS**. The fileset is complete and canonical but
cannot execute because neither the global bootstrap nor the unchanged legacy
pilot wrapper references it.

The next gate is a fresh, read-only activation preflight immediately followed
by an explicit activation decision. The preflight must revalidate wrapper,
events, feedback, namespace and fileset drift. It does not inherit permission
to replace the wrapper or initialize v2 diagnostics.
