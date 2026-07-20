# ControlLight v2 Pilot Migration Readiness Report

**Gate:** Hash-locked pilot preparation and read-only live runtime preflight
**Result:** RUNTIME PREFLIGHT PASS
**Date:** 2026-07-18
**Live state:** Unchanged; activation not authorized

## Scope

The private migration package targets only the successfully tested hallway
pilot. The shared legacy core and the other 28 ControlLight wrappers remain
outside the transaction.

The pilot retains the explicit `reported` brightness contract: `DIMMER` mirrors
the device-reported retained brightness even while `STATE` is false. This
decision applies only to the pilot and does not decide later instances.

## Deployment Boundary

ControlLight v2 is deployed as a versioned filesystem dependency closure. The
pilot wrapper loads its runtime entry directly from that closure. This avoids:

- replacement of the shared legacy core;
- modification of the active global SAEF bootstrap;
- function redeclaration through the generated standalone bootstrap;
- a service restart merely to migrate the first wrapper.

Canonical helper files remain guarded and composable with the already active
SAEF runtime. `SAEF_EnsureLink()` is included in the ControlLight fileset and is
loaded only if not already available.

## Read-only Preflight

The default package invocation is non-activating and checks:

1. the exact candidate manifest, bootstrap, runtime and per-source hashes;
2. ready kernel state and the exact approved legacy pilot-wrapper hash;
3. pilot parent, target-link and alarm contracts;
4. all three target events, including explicit event-action binding;
5. actionable state, brightness and temperature target variables;
6. equality of authoritative local and target feedback;
7. absence of pre-existing v2 diagnostics;
8. compatible active helper signatures through bounded read-only
   introspection.

The status artifact records `mutationAttempted=false`. Activation additionally
requires that status to be no older than 15 minutes and repeats the complete
preflight to close the drift window.

## Activation and Verification Contract

Activation is guarded by an explicit switch and remains unauthorized. If later
approved, the transaction will:

1. save and hash-check the current wrapper source;
2. stage the exact fileset under its aggregate-hash versioned directory;
3. replace only the pilot wrapper;
4. run one `Execute` synchronization, which contains no `RequestAction()` path;
5. verify source, event, authoritative feedback and diagnostics contracts;
6. require the command statistic to remain zero during initial sync.

Existing names, positions and icons remain presentation-owned by the user. The
v2 runtime reconciles functional type, profile, action, Ident and event
contracts without rewriting existing presentation metadata.

## Rollback Contract

On an activation failure, the package restores and executes the exact legacy
wrapper source, verifies the old event and feedback contracts, and removes only
new direct child variables whose Idents belong to the fixed v2 diagnostics
allowlist. Unknown children or ownership mismatches stop rollback with a
distinct review-required exit code.

An already copied but unused versioned fileset may remain after rollback. It is
inert because neither the global bootstrap nor the restored wrapper references
it. The package never deletes or overlays an active fileset.

IP-Symcon executes the relevant script calls in isolated PHP contexts. Class
presence observed from a separate probe is therefore not an activation
invariant; source, runtime behavior and diagnostics provide the valid evidence.

## Gate Decision

The bounded read-only live runtime preflight passed. It verified the approved
legacy wrapper identity, ready kernel, topology, event action binding,
actionable targets, authoritative feedback equality, absent v2 diagnostics,
compatible active helper signatures. The MCP result reported neither transport nor PHP execution errors
and was not truncated. No live state was changed.

This runtime evidence is deliberately not an activation token. The Windows-side
preflight must still verify the transferred candidate tree and create a fresh,
at most 15-minute-old `preflight-status.json` immediately before any approved
activation. Live activation still requires a new, explicit user approval after
review of that complete evidence.
