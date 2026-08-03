# CL-020 Off-State Color Inactive Fileset Staging

**Date:** 2026-07-27
**Gate:** Explicitly approved inactive fileset staging
**Result:** PASSED — ACTIVATION NOT ATTEMPTED
**Device impact:** None

## Pre-Upload Fail-Closed Correction

The deployment package builder rejected two local directory-token candidates
before any upload. They used fourteen and sixteen hash-prefix characters,
whereas the active bootstrap requires the established fifteen-character prefix
to preserve exact token length. The final target token is byte-length equal to
the active token.

No remote upload, staging or other live mutation occurred before this local
correction. The candidate wrapper and private package hashes were rebound to
the corrected target directory before transfer.

## Staging Result

The restricted deployment channel v7 passed its readiness probe. It transferred
the bounded package through the ordered chunk protocol, verified the package
hash and staged the new immutable directory without selecting it.

Server-side preflight passed. A separate Symcon readback then enumerated the
directory recursively:

- expected files: 19;
- actual files: 19;
- path-set equality: passed; and
- all nineteen SHA-256 values: passed.

## Unchanged Runtime

Independent post-staging evidence confirms:

- `System.Locals` is byte-exactly unchanged;
- CL-020 remains on its exact legacy wrapper;
- the staged fileset is not referenced by that wrapper;
- the four existing feedback events remain active with explicit actions;
- facade and target values remain unchanged;
- target, Alexa and scene configurations remain unchanged;
- the managed runtime mirror remains unchanged; and
- no script, object or variable was written outside the new inactive directory.

No activation command, service restart, script execution or device action was
attempted.

## Next Gate

Activation remains closed. It requires a fresh drift-sensitive delta preflight,
exact wrapper-source comparison, separately approved source replacement and two
non-commanding reconciliation runs. The real off-state color/Alexa sequence
remains a further, separately approved device-test gate.
