# Wave 4 Offline Package Report

**Gate:** Private package construction for CL-004 followed by CL-017
**Result:** PASS — PACKAGE HASH-LOCKED
**Date:** 2026-07-20
**Live impact:** Readback of two legacy wrapper sources only; no mutation

## Corrected cohort

The installation owner reported that the previously proposed second member is
currently hard-powered off. Retained Symcon values cannot prove current device
reachability, so that contract was removed from Wave 4 without attempting a
device action.

The reviewed sequential cohort is now:

1. CL-004, dependency-free Z2M STATE/DIMMER/color temperature;
2. CL-017, the same capability shape with one existing presentation-link
   dependency that must remain exact.

Both contracts use authoritative feedback and explicit `reported` brightness
semantics. Color and external triggers remain disabled. Existing alarm polarity
is preserved per instance.

## Private package

The excluded package contains:

- one complete v2 candidate wrapper per cohort member;
- one directly read, byte-exact Base64 rollback source per member;
- candidate and rollback SHA-256 pins;
- the current 29-wrapper source-regression matrix;
- the active aggregate fileset, runtime and corrected wait-helper identities;
- all ordered fileset source identities;
- private ownership, target, local-variable and legacy-event inputs; and
- closed switches for staging, source selection, wrapper execution and device
  actions.

The wrappers contain only configuration and shared-runtime delegation. They do
not duplicate object, event, diagnostics or wait logic and contain no direct
`RequestAction()`, `SetValue()`, script mutation or script execution call.

## Transaction and rollback contract

The first member must complete its fresh preflight, source transition, two-run
idempotency and full postflight before the second member may be considered. Any
failed assertion stops the sequence and restores the exact rollback bytes of
the current member.

CL-017 adds a fixed acceptance condition: the fresh preflight must capture every
presentation link that consumes its local variables, and postflight must prove
the exact same link objects, targets and presentation metadata. Unknown or
changed consumers stop activation rather than being reconciled implicitly.

Functional device sequences remain separate from configuration activation and
require their own approval and compensation baseline.

## Offline verification

The following checks pass:

- PHP syntax for both candidates and the package verifier;
- byte-exact candidate and decoded rollback hashes;
- rollback equality with each member's entry in the 29-wrapper baseline;
- exact sequential cohort order and stop boundary;
- explicit authoritative feedback and `reported` semantics;
- enabled temperature and disabled color capability;
- absence of wrapper-side action and mutation APIs;
- active fileset and wait-helper pins;
- ControlLight core, runtime, topology and managed-mirror tests;
- generated fileset currency and fileset regression; and
- repository diff-format validation.

The first generated candidate pins reflected the pre-patch byte stream. The
package verifier detected the additional final newline introduced by file
creation before any live step. The two candidate pins were updated to their
actual stored bytes, after which the complete verification passed.

## Gate decision

Wave 4 package construction is complete. It grants no live activation authority.
The next gate is a newly generated, bounded, read-only live preflight. It must
revalidate current source identities, kernel and helper readiness, complete
fileset integrity, target actions and feedback, ownership/topology, presentation
links, absence of conflicting consumers and the nonexistence of v2 diagnostics
for both candidates.
