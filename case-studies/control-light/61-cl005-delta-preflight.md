# CL-005 Hue Cohort Delta Preflight

**Gate:** Read-only delta preflight before first cohort member
**Result:** Passed; CL-005 activation remains closed
**Date:** 2026-07-26
**Live impact:** None

## Private Overlay and Activation Path

The public immutable fileset and the private activation package have different
ownership boundaries.

The fileset contains reusable runtime classes and helpers without
installation-specific IDs. The restricted deployment channel accepts only its
bounded immutable-fileset archive, validates every path and hash, and stages it
below `.saef-filesets/`. It intentionally provides no arbitrary remote file
upload.

The private overlay contains the three installation-specific wrappers and
their exact rollback sources. A later authorized activation does not need those
files to exist on the Symcon filesystem beforehand. The control process reads
the locally verified bytes, checks the live source hash, writes the candidate
through the bounded Symcon script API and reads it back. If any activation or
postcondition fails, the exact locally stored original bytes are restored
through the same API.

This keeps private IDs out of the public fileset and avoids leaving executable
rollback material in the live scripts directory.

## Delta Result

The staged immutable fileset remains byte-exact and the restricted channel
still reports its non-activating preflight as passed. `System.Locals` is
unchanged and contains no selection of the candidate fileset.

Direct source readback and an independent hash comparison found zero
differences across all 29 ControlLight wrappers. CL-005, CL-012 and the shared
Hue handler also retain their exact package rollback identities.

## CL-005 Contract

CL-005 remains the legacy Z2M STATE/brightness wrapper. The preflight captured
and verified:

- parent, local STATE and DIMMER presentation;
- both local custom-action bindings;
- the hidden target-root link;
- both active target-feedback events and explicit event actions;
- two visualization links to the existing local variables;
- native STATE, brightness and availability; and
- all matching local/native values.

The light is currently off, retained brightness is 100 percent on both sides,
and the target reports available. The proposed v2 initialization therefore has
no expected local-value correction and must issue no device command.

A complete script-source dependency scan found only the known legacy Hue
handler reference to the native STATE target. No additional script consumer
references the wrapper, its local variables, target link, target root or
brightness variable. The two visualization links remain presentation
consumers and are preserved because the migration reuses the existing local
variable IDs.

## Hue Boundary

The shared handler source and all six reviewed handler events remain unchanged.
It continues to control the native target during the first wrapper migration.
The handler is not migrated, executed or retargeted in the CL-005 gate.

The two inactive unnamed events also remain untouched.

## Next Gate

CL-005 is ready for a separately authorized source-only activation:

1. recheck the exact legacy source and candidate hashes;
2. replace only the CL-005 wrapper source;
3. read back the candidate hash;
4. reconcile twice without a device command;
5. verify presentation, links, feedback events, diagnostics and values;
6. compare all 29 wrapper sources against the mixed expected baseline; and
7. roll back immediately on any mismatch.

CL-012, the Hue handler, physical tests and event cleanup remain closed.
