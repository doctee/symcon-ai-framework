# ControlLight v2 Pilot Pre-activation Drift Report

**Gate:** Immediate read-only activation drift preflight
**Result:** PASS
**Date:** 2026-07-18
**Live state:** Inactive fileset present; wrapper unchanged

## Scope

After successful inactive staging, a bounded read-only preflight revalidated
the complete activation boundary. No file, script, object, event, variable or
device state was changed.

## Evidence

The following contracts all passed in one observation:

- ready kernel and exact versioned fileset directory;
- exact fourteen-file map and all eleven canonical source hashes;
- pinned manifest, bootstrap, runtime and aggregate fileset identities;
- no global bootstrap selection of the ControlLight fileset;
- exact approved legacy pilot wrapper and expected ownership parent;
- target link, alarm variable and three explicitly bound event contracts;
- actionable state, brightness and temperature targets;
- no existing v2 diagnostics;
- compatible active helper signatures;
- authoritative state, reported brightness and temperature equality.

The connector reported no transport or PHP execution error, and the result was
not truncated.

## Gate Decision

The activation drift preflight is **PASS**, but it grants no activation
authority. Its evidence expires after fifteen minutes. After explicit approval,
the entire check must run once more immediately before replacing the pilot
wrapper.

The proposed activation changes only the pilot wrapper, runs one non-commanding
initial synchronization and creates script-owned diagnostics. On any failure,
the exact legacy wrapper is restored and only newly created, allowlisted
diagnostic children are removed. The shared legacy core, global bootstrap and
other 28 ControlLight wrappers remain outside the transaction.
