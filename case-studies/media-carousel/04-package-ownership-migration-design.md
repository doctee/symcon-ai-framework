# MediaCarousel package ownership migration design

## Status

Repository design and target-specific implementation complete. The exact
PowerShell generation has not yet passed Windows PowerShell 5.1 qualification.
No Windows execution, live mutation, target allowlist change, staging, reload
or activation is authorized by this document.

## Context

The MediaCarousel Channel-v8 adapter deliberately accepts only an
adapter-owned package directory without repository metadata. The current live
installation is healthy but remains a clean Module Control checkout on the
standalone repository's `main` branch. It therefore cannot be adopted by
removing `.git` or by pointing the adapter policy at the existing directory.

A separately authorized bounded Symcon MCP inventory confirmed one
loader-visible MediaCarousel directory, one healthy Module Control owner, the
expected library and module identities, a finite healthy instance set, no
unapplied configuration and complete resolving references. Installation IDs,
paths and byte identities remain private evidence.

The live working-tree payload and the canonical repository payload did not
produce the same byte identity even though Module Control reported a clean
checkout at the expected commit. Checkout normalization is a plausible cause,
but the migration contract must not depend on that inference. It must treat
the complete Git-managed source tree and the canonical package candidate as
two independently verified byte sets.

## Reuse Before Extend

The migration shall compose existing SAEF boundaries:

- ADR-0007 for the restricted deployment environment;
- ADR-0009 for target-bound package ownership and adapter responsibility;
- ADR-0011 for any Windows PowerShell child process;
- the MediaCarousel adapter's module, instance, configuration and reload
  validation;
- the existing channel mutex before the MediaCarousel adapter mutex;
- deterministic module filesets and manifest-driven publication; and
- protected byte-exact backup, postflight and rollback patterns already used
  by target-specific migration tooling.

The migration shall not add a gateway verb, extend the standalone deployment
manifest, change the MediaCarousel runtime module, introduce a public PHP API
or create a generic ownership helper. Only MediaCarousel currently needs this
transition, and its source-owner checks and rollback semantics are specific to
that module. Reuse by a second target would justify extracting only the proven
common mechanics after both implementations are compared.

## Ownership decision

The current Module Control checkout remains the authoritative rollback source.
The canonical standalone repository at the exact reviewed commit is the only
candidate source for the package-managed directory. The migration must not
copy the current working tree and then merely remove repository metadata.

The active loader directory keeps the same public directory name. This avoids
a second loader-visible copy of the same library and module GUIDs. The
directory's ownership changes from Module Control Git checkout to
adapter-managed immutable package bytes; instance objects and configurations
retain their identities. Reference observations may change normally when a
configured category rotates its current image children.

## Repository artifacts

The repository implementation adds only target-specific operational artifacts:

- `Invoke-SaefMediaCarouselModuleOwnershipMigration.ps1`;
- a non-runnable policy example containing no paths or ObjectIDs;
- a deterministic migration transaction contract;
- and a focused platform-neutral contract test.

The coordinator parses Git metadata directly from the exact reviewed checkout,
uses no external Git process and accepts SHA-1 or SHA-256 full object IDs. The
private policy binds the reviewed clean source-tree identity; cleanliness is
established by the preceding inventory gate rather than inferred from a branch
name. Review plan, status, nonce claim and transaction evidence must remain
below the protected private migration-state root.

The private policy binds the exact public module and library GUIDs, Module
Control identity, loader directory name, expected repository URL, branch and
full commit, expected instance and configuration identities, candidate package
identity, protected migration-state root, loopback RPC and fixed mutex names.
Reference identities are intentionally not policy inputs: category mode owns a
stable configured category whose selected media children are rotating runtime
input. No client or remote request may supply an executable, module path,
credential path or RPC destination.

## Read-only plan

Operation `preflight` is read-only with respect to the loader tree and Symcon.
It may write only a bounded private status and review plan selected by the
local administrator. It must prove:

1. Windows PowerShell 5.1 parsed the exact migration generation.
2. Symcon is ready and the loopback credential is usable.
3. Exactly one Module Control instance owns exactly one loader-visible
   MediaCarousel directory.
4. The source checkout is clean, valid, on the expected branch and exact full
   commit, contains `.git`, has no reparse point and stays within the loader
   root.
5. The complete bounded source tree and its ACL have fresh identities.
6. The candidate is outside the loader root, contains no repository metadata,
   has no reparse point and matches the reviewed manifest, library, module,
   file inventory and package identity.
7. Every expected MediaCarousel instance exists, is ready, has no pending
   change and matches its byte-exact configuration. Current references are
   captured as bounded evidence and checked semantically: list references must
   equal the currently existing configured image media; category references
   must contain the configured category and only current image children below
   that category, within the configured item limit.
8. No second loader-visible directory exposes the same library or module GUID.
9. Migration, channel and adapter roots are pairwise disjoint and on the same
   volume required for atomic directory moves.
10. No channel or adapter operation holds a required lock.

The canonical review plan binds all fresh identities, target and adapter
profile, expected source and candidate ownership, fixed operation sequence,
expiry and nonce. `apply` accepts the plan only by exact SHA-256 and a fixed
confirmation phrase. A new preflight invalidates no earlier evidence, but an
apply with stale source, candidate, stable instance, configuration, ACL or
policy identity fails before mutation. A normal category rollover does not
invalidate a plan, but its newly observed references must pass the same
semantic checks immediately before mutation.

## Protected backup

After acquiring the channel mutex and then the MediaCarousel adapter mutex,
`apply` rechecks the full plan before preparing mutation. It creates one
private migration transaction below a preconfigured protected root and writes
a manifest for:

- the complete source-tree identity;
- root ACL and ownership evidence;
- the canonical candidate identity;
- the fresh instance configuration snapshot;
- current reference observations and their semantic source mode;
- Module Control and module identities; and
- plan, policy and implementation hashes.

The candidate is copied into a protected same-volume staging directory and
verified again. The source checkout itself becomes the rollback artifact by a
same-volume directory move. This preserves every source byte, including Git
metadata, instead of attempting to reconstruct the checkout from a package.
The backup remains retained after success; deletion belongs to a later
retention gate.

## Apply sequence

The only permitted mutation sequence is:

1. persist the `prepared` transaction state;
2. repeat the source, candidate, instance and configuration identity checks and
   revalidate the current reference semantics;
3. move the Git-managed loader directory to the protected rollback location;
4. move the verified package staging directory to the unchanged loader path;
5. invoke exactly one `MC_ReloadModule()` for that directory;
6. verify library and module identity, package identity, every instance,
   configuration, current reference semantics and ready status; and
7. persist the terminal `migrated` state.

The candidate directory must carry an explicit protected ACL suitable for the
installed channel and Symcon service before it is moved into the loader root.
The migration may not call `MC_UpdateModule`, restart a service, change an
instance configuration during the success path, contact a provider, publish a
repository or delete retained evidence.

## Rollback and interruption

Any failure after the source move triggers automatic rollback:

1. move an installed candidate to a retained failed-candidate location;
2. move the untouched Git checkout back to the loader path;
3. invoke exactly one targeted reload of the restored directory;
4. restore a byte-exact configuration snapshot only when drift occurred;
5. prove the original package, module, stable instance and configuration
   identities, current reference semantics and status; and
6. report `rolled_back` only after complete proof.

An unproven restore reports `manual_recovery_required` and preserves every
transaction artifact. A separate `inspect` operation classifies interrupted
states without mutation. A separately confirmed `rollback` operation provides
post-success reversal while the retained checkout and its manifest still
match. Neither lost output nor a repeated command may repeat a completed
mutation.

## Windows qualification

The exact implementation still must pass Windows PowerShell 5.1 parsing and a
separately packaged synthetic qualification for:

- clean Module Control checkout to package ownership;
- multiple unsorted instance bindings;
- candidate and source byte-identity independence;
- source commit, branch, cleanliness and repository-URL drift;
- candidate manifest, package and module-identity drift;
- configuration drift before mutation and semantically valid category-reference
  rollover between plan and apply;
- duplicate loader-visible GUID ownership;
- path overlap, escape, reparse points and unsafe ACLs;
- lock contention and immediate pre-mutation drift;
- reload and postflight failure with byte-exact rollback;
- partial directory moves and interrupted-state inspection;
- explicit post-success rollback;
- corrupted or missing rollback evidence; and
- absence of production, service, provider, publication and retention side
  effects during qualification.

Qualification uses only an ACL-protected random scratch tree and loopback mock
RPC. It must not touch an installed channel or real module directory.

## Migration and compatibility

The transition does not change MediaCarousel configuration, visualization
behavior, module GUIDs, library GUIDs or the loader directory name. Existing
instances remain in place. Module Control can still perform the targeted
reload used by the adapter, but repository update and branch operations stop
being the package owner after migration.

Reversal restores the exact Git checkout and therefore restores Module Control
ownership. A later Channel-v8 target installation may proceed only after the
package-managed postflight is complete and a private adapter policy is
materialized from a fresh inventory.

## Separate gates

1. Review the completed repository-only migration artifacts.
2. Commit, pull request and CI for the exact implementation.
3. Windows PowerShell 5.1 synthetic qualification of the merged bytes.
4. Fresh live read-only preflight and protected backup plan.
5. Separately authorized live ownership migration.
6. Independent live and browser postflight.
7. Private adapter-state and policy provisioning.
8. Target allowlist installation.
9. Candidate build, inactive stage and read-only Channel-v8 preflight.
10. Separately authorized activation and independent postflight.
11. Cross-root retention implementation and deletion gate.

No gate authorizes the next one.
