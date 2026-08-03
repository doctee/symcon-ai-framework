# Hue Wall Live Preflight and Offline Package

**Gate:** Fresh read-only live preflight and private offline package
**Result:** Passed; staging and activation remain closed
**Date:** 2026-07-26
**Live impact:** None

## Result

The dedicated CL-005, CL-012 and Hue Wall handler cohort remains ready for an
inactive staging gate. Direct source readback confirmed that both ControlLight
wrappers and the shared handler are byte-identical to the previous review.
Their intended active events, target contracts and independent subscribers also
remain unchanged.

The bounded probes completed with no transport or PHP execution error and no
truncation. No script, object or variable was written, no existing automation
script was executed and no device action was requested.

## Fresh Dependency Baseline

CL-005 remains a legacy Z2M STATE/brightness wrapper. CL-012 remains a legacy
Z2M STATE/brightness/color-temperature wrapper. Both retain active,
explicitly-bound target-feedback events and actionable local STATE facades.

The Hue handler still has:

- two active action events using change triggers;
- two active native-target feedback events using change triggers;
- two inactive unnamed redundant action events;
- four retained assumed-rocker variables and two retained debounce timestamps.

The independent alarm consumer and both ControlLight target-feedback
subscribers remain active. The two Hue module instances remain active and their
configuration fingerprints are unchanged. Their `device_mode` variables still
contain no reported value, so an awake-device verification remains part of the
physical test gate.

A fresh source identity baseline was captured for all 29 ControlLight wrappers.
The two cohort wrappers match their established legacy hashes exactly.

## Private Package

The private package contains:

- the CL-005 and CL-012 v2 wrapper candidates with `reported` brightness
  semantics and authoritative feedback;
- the shared Hue handler candidate routed only through both local ControlLight
  STATE facades;
- exact Base64 rollback sources for all three scripts, checked against their
  decoded live SHA-256 identities;
- a new immutable ControlLight fileset containing the existing ControlLight
  runtime and the Hue adapter classes;
- the full 29-wrapper source baseline;
- exact before/after contracts for the four reused handler events; and
- a local verifier that checks every candidate, rollback, preflight and fileset
  identity while all mutation gates remain closed.

The new fileset does not replace the immutable versions selected by existing
wrappers. A later staging operation can place it beside them without changing
runtime selection.

The proposed sequence remains strictly sequential:

1. stage the immutable fileset and private package without selection;
2. perform another drift check;
3. migrate and reconcile CL-005 twice;
4. verify the complete wrapper baseline;
5. migrate and reconcile CL-012 twice;
6. verify the complete wrapper baseline;
7. replace and reconcile the Hue handler twice while physical input is
   quiescent; and
8. run the separately approved physical integration matrix.

Any failure stops the sequence before the next member.

## Legacy Event Cleanup Decision

The two inactive unnamed events are genuine redundant artifacts. A separate
warning-free reference scan found:

- no script source containing either event ID;
- no link targeting either event;
- no child objects below either event;
- no stable Ident; and
- no active runtime role.

Deletion is therefore technically appropriate as eventual hygiene, but not
during migration. Keeping the events inactive costs no runtime work and
preserves maximum reversibility while the new event model is tested.

The recommended cleanup gate is after:

1. both wrappers and the Hue handler have passed the complete physical matrix;
2. an observation interval shows no missing input or unexpected subscriber;
3. a fresh check confirms the same IDs, owner, inactive state, empty Ident,
   trigger variables and zero references; and
4. a separate explicit deletion authorization is granted.

Only those two exact events may then be deleted. The active named events,
ControlLight feedback events and alarm consumer are not cleanup candidates.

## Offline Verification

The package verifier and all relevant candidate tests pass:

```console
php private/control-light/hue-wall-cohort-20260726/VerifyPackage.local.php
composer control-light:fileset-check
composer test:control-light-fileset
composer test:hue-wall-core
composer test:hue-wall-runtime
composer test:hue-wall-topology
```

No live staging, source selection, wrapper execution, handler execution, device
action or event deletion is authorized by this gate.
