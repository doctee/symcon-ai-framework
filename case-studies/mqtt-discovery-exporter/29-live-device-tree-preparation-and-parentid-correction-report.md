# 29 Live Device-Tree Preparation and ParentID Correction Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** First live device-tree preparation and repeated-preparation verification
**Result:** First preparation PASS; repeatability blocked by a corrected deployment defect
**Date:** 2026-07-16
**Live-system impact:** Owned categories, command adapters and hidden events created; no MQTT publication or device command

## 1. Scope

The approved single-light pilot ran `prepareReconcile` against the activated
device-tree fileset. This operation was limited to owned object preparation.
It did not execute the retained publication plan and did not send a physical
device command.

## 2. First Preparation Result

The first preparation created the intended hierarchy:

```text
Exporter owner
|-- Diagnostics
|-- Devices
|   `-- Pilot device
|       |-- Commands
|       `-- Publishers
`-- hidden command and state events
```

Three command adapters were created for power, brightness and color
temperature. Registry read-back confirmed the exact category parents, stable
Idents, command and state indexes, and `resourceState = ready`.

The Publishers category remained empty because publisher adapters belong to
the later execution gate. Registry publication flags and hashes remained
unset, publish counters remained zero and the error history was initially
empty.

## 3. Repeatability Failure

The deliberately repeated preparation reused every object ID and created no
duplicates, but it raised a `RuntimeException` during ownership verification.
The IP-Symcon log recorded:

```text
Registry ownership verification failed: MQTT_DISCOVERY_EXPORTER_DEVICES
```

The existing Devices category itself was intact. The defect was in the PHP
contract check: the runtime read `ObjectParentID`, while `IPS_GetObject()`
returns the parent as `ParentID`.

The first run did not evaluate this branch because the category did not yet
exist in the Registry. The second run correctly exercised the existing-object
ownership path and exposed the mismatch.

## 4. Correction and Offline Verification

Both runtime ownership checks now use `ParentID`. The Symcon test doubles and
assertions were changed to model the same official API contract, preventing
the former non-platform key from hiding this defect.

Verification passed for:

- runtime diagnostics;
- repeated reconcile preparation;
- retained execution;
- command and state dispatch;
- leaf-first cleanup;
- discovery fixtures;
- complete repository lint, fileset, static-analysis and style checks.

The corrected fileset has a new deterministic identity. It is packaged with a
state-based activation transaction that expects the exact currently active
bootstrap, core and defective runtime identities before making any change.

## 5. Gate Decision

No publication or command gate may proceed on the currently active runtime.
The next step is a separately approved activation of the corrected fileset,
followed by exact runtime verification and a second `prepareReconcile` run.
Only a successful duplicate-free second run can close the preparation gate.
