# Wave 4 Live Preflight Report

**Gate:** Fresh read-only preflight for CL-004 followed by CL-017
**Result:** PASS — FIRST MEMBER READY FOR SEPARATE ACTIVATION
**Date:** 2026-07-20
**Live impact:** None

## Execution boundary

The hash-locked private package was verified immediately before the live probe.
The probe used only read APIs. It did not stage a file, update source, execute a
wrapper, request an action, change an event or create a temporary object.

Transport and PHP execution succeeded independently, and the bounded result was
not truncated.

## Shared dependencies and regression

The preflight proved:

- ready kernel runlevel;
- all 14 files in the selected hash-addressed ControlLight fileset with exact
  names and SHA-256 identities;
- the process-effective corrected wait-helper identity through Reflection;
- all 29 installed wrapper sources against the current mixed v2/legacy
  regression matrix; and
- exact legacy source equality with both packaged rollback images.

The dependency scan covered 13,899 live objects. The visible managed runtime
mirror was excluded only from semantic consumer classification because its
reference index intentionally names every ControlLight dependency. It remained
part of the installation and was not changed.

## CL-004 result

CL-004 retained its wrapper parent, target link, local variables and legacy
target events. All local and target variables remained actionable. STATE,
DIMMER and color temperature matched their authoritative targets exactly.

No installed script, exact event trigger or presentation link consumes a local
CL-004 variable. No v2 diagnostic Ident exists below the wrapper.

The current feedback is STATE true with DIMMER and color temperature both zero.
This is internally aligned and therefore safe for a non-commanding configuration
transition, but it does not by itself prove useful brightness or temperature
device behavior. The later functional gate must establish that separately.

## CL-017 result

CL-017 retained its wrapper parent, target link, local variables and legacy
target events. STATE false, retained DIMMER 100 and color temperature 2702 all
matched their authoritative targets, and every target remained actionable.

No installed script or event consumes a local CL-017 variable. Exactly three
presentation links were found, and their IDs and target-reference sets were
retained. The full private object, target and presentation snapshot required for
activation was subsequently captured by the fresh delta preflight in report 39.
No v2 diagnostic Ident exists below the wrapper.

## Gate decision

Wave 4 read-only preflight is **PASS**. This authorizes no mutation. The next
separate gate is activation of CL-004 only. Its transaction must:

1. re-read and compare the wrapper to the exact rollback bytes immediately
   before the write;
2. replace only that wrapper source;
3. execute two non-commanding configuration passes;
4. prove idempotent object reuse, presentation preservation, explicit event
   action binding and diagnostic invariants;
5. require zero command, error and confirmation-timeout deltas; and
6. pass the complete 29-wrapper source regression.

CL-017 must remain untouched until CL-004 postflight passes and a fresh delta
preflight reconfirms its three-link snapshot. A bounded real-device sequence for
CL-004 remains a later, separately approved gate.
