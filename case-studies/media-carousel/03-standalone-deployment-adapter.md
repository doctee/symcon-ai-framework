# MediaCarousel Standalone Deployment Adapter

**Status:** Repository extraction and hardening in progress; all Windows and
live gates closed
**Recovery source:** Historical dirty worktree, inspected without mutation
**Channel:** SAEF deployment channel version 8

## Purpose

This workstream extracts the MediaCarousel target adapter from historical
recovery input into a current clean worktree. It composes the existing Channel
v8 standalone-module contract and does not add a gateway verb, public helper or
generic deployment abstraction.

OwnTracks remains the first qualified and live-validated Channel-v8 target.
MediaCarousel is a later reuse candidate. This repository change does not
allowlist it, install policy, stage a package, contact Symcon or change the live
module.

## Reuse Decision

The generic gateway already owns package integrity, immutable staging, target
dispatch, secure child execution and the generic status envelope. The
scope-bound approval runner already owns short-lived one-plan authorization.
Neither should learn MediaCarousel configuration or state semantics.

The adapter remains target-specific because only it can prove:

- the one-library/one-module identity;
- the exact configured MediaCarousel instance set;
- byte-exact instance configuration preservation;
- the package-managed module-directory ownership transition;
- targeted `MC_ReloadModule()` behavior; and
- rollback of the package and any configuration drift.

No separate MediaCarousel retention engine is introduced. Standalone artifacts
span adapter state, channel deployment state and managed filesets, so deletion
remains disabled until the existing
`project/STANDALONE_MODULE_CROSS_ROOT_RETENTION.md` contract is implemented and
qualified generically.

## Ownership and Baseline

The private adapter policy must bind:

- exact library and module metadata;
- one positive Module Control instance and its module identity;
- the dedicated package-managed active directory;
- a protected target-owned adapter-state root;
- the complete expected MediaCarousel instance list;
- the byte-exact configuration hash of every instance; and
- the exact active-package tree identity.

The public policy file contains only non-runnable placeholders. It carries no
private ObjectID, path or configuration hash.

The currently installed Git-managed module tree is not silently adoptable.
Moving to the package-managed path is an ownership migration with its own live
inventory, protected backup, Windows qualification and rollback gate.

## Quiescence and State

MediaCarousel has no server timer, creates no child objects and writes no media.
Its message registrations and the attributes `RegisteredMediaIDs` and
`RegisteredCategoryID` are derived by `ApplyChanges()`. The image index and
compressed image cache are client-local browser state and are not part of a
server package rollback.

The adapter therefore serializes package operations with a named mutex and
admits only the exact policy-bound instance set at allowed healthy statuses,
without pending configuration changes. Immediately before mutation it captures
each instance's byte-exact UTF-8 configuration, hash and relevant object state.

This proves server-side configuration quiescence. A later live gate must still
measure any transient effect on open visualization clients.

## Activation and Postflight

Preflight validates the package manifest, transaction contract, private policy,
candidate and active package identities, paths, ACLs, Module Control and exact
instance baseline without mutation.

Activation copies the already verified candidate into a same-volume transaction
directory and verifies the copied bytes again. Immediately before mutation it
rechecks Symcon ownership, the complete snapshot and the active package
identity. It switches complete directories through bounded renames, invokes
exactly one targeted `MC_ReloadModule()` and then proves:

- ready Symcon runlevel;
- exact library and module identity;
- exact instance inventory;
- unchanged configuration and object state;
- no pending instance changes; and
- exact active candidate package identity.

It never invokes `MC_UpdateModule`, downloads content or restarts a service.

## Rollback

The rollback package and fresh configuration snapshot exist before the active
path changes. On a later failure the adapter retains the failed candidate,
restores the previous complete package, verifies the policy-bound old package
identity, performs one targeted reload, restores only byte-drifted instance
configurations and repeats health checks.

The adapter reports `rolled_back` only after successful post-rollback proof.
Otherwise it reports `manual_recovery_required` and retains transaction state.
The brief interval between the two directory renames is bounded but cannot be
made a single filesystem operation for non-empty directories.

## Verification Scope

Repository tests verify:

- non-runnable public policy placeholders;
- exact package and instance baseline fields;
- ordinal package-tree identity ordering;
- manifest-bound candidate packaging;
- one targeted reload call site;
- package preparation before active-path mutation;
- fresh baseline and active-package recheck immediately before mutation;
- rollback and manual-recovery status paths;
- absence of update, restart and expression-evaluation commands; and
- retention ownership remaining with the disabled cross-root contract.

Exact Windows PowerShell 5.1 parsing and synthetic execution are deliberately
not claimed by these platform-neutral tests.

## Remaining Gates

1. Repository review, commit, pull request and exact CI.
2. Windows PowerShell 5.1 parser and synthetic transaction qualification for
   the exact adapter bytes.
3. Fresh read-only Symcon MCP inventory of Module Control, installed ownership,
   all MediaCarousel instances and current package identity.
4. A reversible migration design from the Git-managed module source to the
   package-managed active directory.
5. Private adapter-state provisioning, policy materialization and target
   allowlist installation through protected backups.
6. Optional scope-bound approval profile and active-identity reseal design,
   followed by exact Windows requalification.
7. Candidate build, inactive stage and read-only target preflight.
8. Separately authorized live activation and independent Symcon and browser
   postflight.
9. Cross-root retention implementation and its own deletion gate.

No gate in this document authorizes the next one.
