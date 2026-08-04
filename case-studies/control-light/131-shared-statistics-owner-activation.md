# Shared Statistics Owner Activation

**Date:** 2026-08-04
**Scope:** Effective shared-helper owner, Hue Wall, ControlLight and MQTT exporter
**Result:** PASSED AFTER BOUNDED ROLLBACK AND MINIMAL REBUILD

## Purpose

The Hue Wall concurrency correction depends on a Statistics helper that
serializes each statistic variable independently. Updating a later ControlLight
consumer fileset would not select that guarded global helper because the MQTT
fileset is loaded earlier and therefore owns the effective implementation.

This gate updated the earliest owner and then verified every affected consumer
without issuing a device command or publishing MQTT state.

## Preflight Finding

Initial staging exposed a retention inconsistency left by an earlier cleanup:
a live-referenced immutable fileset remained, but its failed-preflight
deployment state had been removed. The runtime sources were intact. The missing
state was restored byte-exactly from the verified retention backup before any
new package was staged.

The first owner candidate then failed preflight because its declared survival
contract required a newer validation function that was not active. No restart
or runtime selection occurred.

## Bounded Correction Sequence

A revised complete candidate passed preflight and was activated, but its
command-free regression showed that it also changed the MQTT exporter runtime.
That was broader than the approved helper-only maintenance boundary.

The gate therefore:

1. restored the byte-identical preceding MQTT runtime through its immutable
   rollback package;
2. reconstructed that exact live fileset on a clean private boundary;
3. replaced only the Statistics helper with the reviewed per-variable
   serialization implementation;
4. verified that the MQTT runtime and bootstrap source identities remained
   byte-identical to the known-good version; and
5. activated the minimal owner through a clean-process restart.

The complete recovery required three controlled restarts: the rejected broad
runtime result, its exact rollback and the final minimal helper owner. This is
recorded explicitly rather than being summarized as a single successful
restart.

## Command-Free Regression

Post-activation verification passed for:

- ready Symcon runlevel and the selected owner identity;
- Reflection identity of the effective Statistics helper;
- unchanged MQTT exporter runtime identity;
- both MQTT exporter configurations and all active event bindings;
- all ControlLight wrapper sources and STATE action bindings;
- Hue Wall event topology, bounded error history and counter invariants; and
- absence of new ControlLight, Hue Wall or exporter failures.

No device action or MQTT publication was attempted. The final owner contains
the corrected Statistics helper while preserving the previously proven MQTT
runtime.

## Follow-Up Boundary

The failed and superseded intermediate immutable packages are retained only
until this evidence is closed. Their later deletion is a separate retention
gate with a fresh reference scan, verified backup and a mandatory one-to-one
deployment-state/fileset postflight.

A future full MQTT runtime update remains separate from shared-helper
maintenance. New shared helper APIs must first be present in the earliest live
owner before a later consumer may depend on them.
