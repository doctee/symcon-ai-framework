# 16 Private Activation Inventory Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Read-only activation-transition inventory
**Result:** PASS
**Date:** 2026-07-15
**Live status:** No file, object, configuration, MQTT or device mutation performed

## 1. Scope

This gate checks whether the connected installation is in the expected state
before the minimal EnsureVariable deployment can be replaced by the complete
exporter fileset.

The inventory was evaluated inside the connected IP-Symcon runtime. The
connector reports execution success but does not return PHP values or output.
The check therefore used a fail-closed assertion set: any missing, additional
or mismatching condition raises a generic gate error; success means that every
listed condition passed. No source, path, ObjectID, Ident, name, hostname,
topic or credential was returned or stored in this repository.

## 2. Sanitized Result

| Check | Result |
| --- | --- |
| Runtime satisfies the fileset PHP 8.2 minimum | PASS |
| Existing minimal bundle exports available | 7 of 7 |
| Existing minimal bundle guard constants available | 2 of 2 |
| Active bundle is readable and has the canonical repository SHA-256 | PASS |
| Active bundle occurs exactly once in the runtime include set | PASS |
| Included bootstrap files referencing the active bundle | 1 |
| Migrated scripts calling `SAEF_EnsureVariable()` | 1 |
| Additional functions reserved by the new fileset already loaded | 0 |
| Exporter classes already loaded | 0 |
| Additional guard constants reserved by the new fileset already defined | 0 |

The active artifact matches
`dist/symcon/saef-ensure-variable.php` with SHA-256
`064a9b7e6d14d776dd7f83c459510a418aac2b8df71f5ec9efcfe2c591f0eb3f`.
This hash is public build provenance and contains no installation identity.

## 3. Transition Consequence

The inventory confirms the expected narrow overlap:

- the active deployment owns the seven previously reviewed functions and two
  corresponding guards;
- the complete fileset would introduce the remaining 29 functions, twelve
  additional guard constants and two exporter classes;
- none of those additional symbols is currently loaded;
- exactly one migrated caller depends on the existing
  `SAEF_EnsureVariable()` export.

The new bootstrap must still not be loaded into the current PHP process. Its
conflict preflight is expected to reject the seven functions and two constants
that are already active. Activation therefore requires replacement of the
bootstrap selection followed by a clean IP-Symcon restart. Coexistence or an
in-process include is not a valid transition.

## 4. Evidence Boundary

This public report records only counts, categorical results and the already
public canonical artifact hash. The private installation remains the source of
truth for:

- physical deployment and bootstrap paths;
- the complete bootstrap source and its recoverable backup;
- the migrated caller identity and recoverable source backup;
- installation-specific file ownership and restart procedure.

The read-only connector cannot create a recoverable filesystem snapshot, and
this gate did not authorize one. A fresh private snapshot must therefore be
taken immediately before any authorized bootstrap or filesystem mutation. It
must be kept outside public SAEF artifacts.

## 5. Gate Decision

The read-only activation inventory is **PASS**.

The next G6 sub-gate is a separately authorized pre-change backup and staged
filesystem placement. That step may copy the reviewed fileset and verify its
aggregate and per-file hashes, but it must not switch the bootstrap or restart
IP-Symcon. Bootstrap replacement, clean restart and the isolated no-entity
load remain a later explicit activation gate.
