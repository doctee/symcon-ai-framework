# 32 Shared Wait Helper Inactive Staging Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**Gate:** Private package and inactive corrected-fileset staging
**Result:** PASS
**Date:** 2026-07-19
**Activation state:** Candidate present but unselected

## 1. Package

The private Gate-A package contains:

- an exact copy of the deterministic candidate fileset;
- the existing reviewed external restart coordinator and policy;
- a mutation-closed private contract;
- the live inactive-staging status;
- an offline package verifier; and
- a complete SHA-256 inventory.

The verifier proves the exact fifteen-file map, all canonical source hashes,
aggregate and bootstrap identities, equal-length old/new include tokens and the
absence of any authorized activation operation. The package and every recorded
hash passed independently after construction.

## 2. Bounded Transfer

The target directory is derived from aggregate identity
`591acf8ff4418aec0fdbb711efa291254f6718935795c6b56be91fce0fdb755e`.
The initial collision probe confirmed that neither final nor temporary target
existed and that the kernel was ready.

Files were decoded only inside an explicitly named temporary directory. Small
files used one bounded transfer; the large exporter Runtime used four ordered
chunks with exact offset and size checks. Each completed file was checked by
SHA-256 before its temporary filename was finalized.

A first local orchestration attempt stopped before any remote file call because
the isolated JavaScript runtime lacked a Base64 helper. Chunk creation was
moved to the local PHP preparation step; no partial live file resulted from the
failed local attempt.

## 3. Atomic Finalization and Readback

Before directory finalization, one probe verified:

- the exact fifteen-file relative path map;
- every source and metadata hash;
- the pinned manifest and bootstrap identities;
- the aggregate marker; and
- the expected corrected wait-helper identity.

The complete temporary directory was then atomically renamed. A separate
read-only probe confirmed the same map and hashes from the final directory and
that the temporary directory is absent.

## 4. Non-selection and Regression

Independent live evidence confirmed:

| Contract | Result |
| --- | --- |
| Candidate referenced by a Symcon script | No |
| `System.Locals` hash | Unchanged |
| Active bootstrap token | Old fileset, exactly once |
| Effective wait helper | Old MQTT fileset |
| Exporter caller identity | Unchanged |
| Exporter diagnostic counters | Unchanged |
| ControlLight wrapper identities | Unchanged |
| ControlLight STATE | `false` locally and at target |
| ControlLight DIMMER | `100` locally and at target |
| MQTT publication or device command | None |
| Service restart | None |

The successful connector result contained no transport or PHP execution error
and was not truncated.

## 5. SAEF Documentation Correction

The incident exposed a framework-level documentation gap. SAEF already
required namespace conflict checks and clean-process replacement, but did not
state the fileset-crossing ownership rule prominently enough.

The helper standard, helper-library README and bundle build design now state:

- guards are collision protection, not version selection;
- the earliest globally loaded artifact owns a guarded helper;
- all exporting and consuming filesets must be inventoried;
- shared-helper updates must pass through that load owner; and
- effective source identity must be verified after a clean-process transition.

## 6. Gate Decision

Gate A is **PASS**. The corrected MQTT fileset is complete, immutable by hash
and safely inactive. The active installation still uses the old helper exactly
as intended for this gate.

The next gate is a non-activating maintenance preflight and fresh byte-exact
rollback capture for `System.Locals`, followed by a separate decision on the
atomic bootstrap switch and supervised restart. Gate A grants neither action.
