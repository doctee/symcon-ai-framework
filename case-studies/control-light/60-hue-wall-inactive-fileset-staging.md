# Hue Wall Inactive Fileset Staging

**Gate:** Inactive immutable fileset staging
**Result:** Passed; source activation remains closed
**Date:** 2026-07-26
**Live impact:** One unselected immutable fileset directory created

## Result

The adapter-capable ControlLight fileset was transferred through the restricted
deployment channel and staged successfully under its hash-derived immutable
directory. The channel probe, 41-chunk upload, commit and non-activating
preflight all passed.

The staged directory contains exactly 19 files. Independent live readback
confirmed the expected hashes for:

- the fileset identity marker and source manifest;
- the generated bootstrap;
- the existing ControlLight runtime; and
- the new Hue Wall core and runtime.

## Inactive Selection Proof

`System.Locals` remains byte-identical to the pre-staging snapshot. It contains
exactly one reference to the previously active MQTT fileset and no reference to
the staged Hue-capable ControlLight fileset.

The deployment channel's global activation operation was not invoked. No
service restart occurred.

Direct post-staging readback also confirmed:

- the CL-005 and CL-012 legacy wrapper sources are unchanged;
- the shared Hue handler source is unchanged;
- both active action events still use their former change triggers;
- both active native-feedback events remain unchanged;
- both unnamed legacy events remain inactive;
- all four local/native STATE values remain unchanged; and
- no object, variable or device action was performed.

## Private Package Placement

The restricted deployment channel deliberately accepts only its bounded
immutable-fileset package format. It does not provide arbitrary remote file
placement for private wrapper candidates or rollback sources.

Consequently, the private three-script candidate and exact rollback package
remains in the local private overlay. Its hashes and verifier are closed and
passed. A later authorized activation can read those exact local artifacts and
apply them through the existing bounded Symcon control path. Copying private
configuration into the public fileset or the live scripts directory would
weaken both the channel policy and SAEF's private-data boundary.

## Path Correction

The private candidates were finalized against the deployment channel's actual
managed location below `.saef-filesets/`. The package verifier now asserts that
all three candidates select that exact staged directory. This correction
changed only local private candidate hashes; no live source had been staged or
selected.

## Next Gate

The next safe step is a fresh read-only delta preflight immediately before a
separately approved CL-005 source activation. That preflight must revalidate:

- the staged fileset hashes;
- all three rollback source hashes;
- the complete 29-wrapper source baseline;
- CL-005 presentation, event and consumer contracts;
- unchanged Hue handler and event topology; and
- zero runtime selection of the new fileset outside the intended wrapper.

It must not execute CL-005, replace a script, change an event or issue a device
command. CL-012 and the Hue handler remain later sequential gates.
