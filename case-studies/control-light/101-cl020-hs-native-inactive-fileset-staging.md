# CL-020 HS-native Inactive Fileset Staging

**Gate:** Inactive fileset transfer and independent readback
**Result:** PASSED — RUNTIME NOT SELECTED
**Date:** 2026-07-27
**Device actions:** None

## Outcome

The exact HS-native ControlLight fileset was transferred through the restricted
deployment channel and staged successfully in a new immutable directory. The
channel probe, bounded 48-chunk upload, server-side commit and non-activating
preflight all passed. The directory contains exactly the expected nineteen
files.

No wrapper or active runtime was changed. The activation command was not
invoked and the Symcon service was not restarted.

## Pre-upload Correction

Local package validation found that the initially selected directory token was
one byte longer than the active bootstrap token. The restricted channel
requires equal-length tokens to keep later replacement and rollback
deterministic.

The target directory and inactive wrapper candidate were corrected to the
established fifteen-character hash-prefix convention before any upload. All
dependent hashes were then regenerated and verified. No incorrect directory or
candidate reached the live system.

## Independent Verification

Read-only live inspection verified all nineteen staged file hashes and sizes.
The fileset identity, ordered source manifest, bootstrap, runtime, command
exception and HS-native core match the local package exactly.

`System.Locals` remains byte-identical to the pre-staging baseline. It still
contains one reference to the active MQTT owner and no reference to the staged
ControlLight bootstrap.

The post-staging invariants also prove:

- the CL-020 legacy wrapper source and four child event identities are
  unchanged;
- all four events remain active with explicit event action binding;
- target, Alexa, scene and repaired Home Assistant Entity hashes are
  unchanged;
- facade STATE, DIMMER, color temperature and color are unchanged;
- target STATE, brightness, color temperature and HS color are unchanged; and
- no script, object or variable write and no device action occurred.

## Remaining Gates

The next step is a fresh delta preflight followed by a separately approved
CL-020-only wrapper activation and two non-commanding reconciliations.

The real-device capability sequence, color/brightness independence check,
Alexa regression and scene regression remain a later, separately approved
gate. CL-021 remains color-disabled and outside this migration.
