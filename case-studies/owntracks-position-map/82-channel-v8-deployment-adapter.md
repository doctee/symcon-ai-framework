# Gate 82 — channel-v8 OwnTracks deployment adapter

**Status:** Repository implementation and offline contract verification;
Windows qualification is recorded separately in Gate 83 and all installation
and live module gates remain closed, 2026-09-05.

## Reuse before extend

The pilot reuses the version-8 package builder, five-command transport,
inactive staging, server-local hash-pinned target dispatch and bounded adapter
status. It does not add a command, endpoint, generic module API, map
abstraction or shared helper. OwnTracks-specific transaction knowledge remains
inside one adapter profile.

The public implementation consists of:

- `Invoke-SaefOwnTracksPositionMapModuleAdapter.ps1` for `preflight` and
  `activate`;
- a private-policy example with placeholders only;
- a canonical, package-bound transaction contract;
- `Invoke-SaefOwnTracksPositionMapModuleRetention.ps1` and a read-only
  retention-plan example; and
- an offline contract/package regression.

The repository target allowlist remains empty.

## Ownership and package identity

The private policy must pin the library and module GUIDs, the positive Module
Control instance, exactly one positive OwnTracks module instance, the
byte-exact instance-configuration SHA-256 and the active module-tree identity.
The adapter rejects Git-managed module trees, reparse points, broad writable
ACLs, ambiguous library membership, pending instance changes and any candidate
that differs from its channel manifest.

No public file contains a live ObjectID or path. The zero IDs and placeholder
hashes in the example are deliberately non-runnable.

## Quiescence and state preservation

The runtime has five known writer roots: day cache, provider cache, tile
request budget, provider request budget and miss state. The adapter acquires
their existing lock files in a fixed order under its own named transaction
mutex. It then requires zero unexpired leases in both request budgets and zero
unexpired pending reservations in miss-state format 2.

While all locks remain held it captures the three authoritative JSON files
byte-for-byte. The two caches are rebuildable, but activation does not delete,
reset or replace them. Instance configuration, object presentation metadata,
package identity and authoritative state are rechecked after the targeted
reload.

Adapter profile version 1 supports only state format 2 to format 2. It rejects
format 1 and any candidate/rollback format transition. The older format-1
rollback retained by step 80 therefore cannot be selected silently; using it
still requires the separately reviewed legacy converter and a future adapter
profile.

## Activation, health and rollback

Activation copies the verified candidate into a same-volume transaction
directory before changing the active path. It preserves the old package and
fresh private snapshot, switches directories by rename and calls exactly one
targeted `MC_ReloadModule` for the configured module directory. It does not
call `MC_UpdateModule`, restart a service or apply unrelated instances.

Health requires ready runlevel, exact library/module ownership, the same single
instance, byte-identical configuration and presentation metadata, candidate
package identity, unchanged state bytes and zero active leases.

On any post-mutation failure the adapter moves the failed candidate aside,
restores the previous package and the fresh state snapshot, performs one
targeted rollback reload, restores configuration only if drift occurred and
repeats health against the previous package identity. Failure to prove that
sequence returns `manual_recovery_required`; it is never reported as a clean
rollback.

## Retention contract

The OwnTracks retention command is separate from activation. `plan` produces a
fresh bounded inventory and digest without deletion. `apply` requires a local
administrator, the same adapter mutex, a matching approved inventory digest
and explicit artifact names. It always protects the active transaction,
manual-recovery evidence and the configured number of recent successful and
rolled-back transactions. Package, configuration and state evidence live in
the same transaction directory and are removed only as one adapter-owned unit.

The generic channel retention tool remains prohibited for standalone modules.

## Remaining gates

| Gate | State |
| --- | --- |
| repository adapter and deterministic package contract | complete |
| Windows PowerShell 5.1 parse and synthetic transaction test | complete in Gate 83 |
| PHP `flock()` / Windows range-lock interoperability test | complete in Gate 83 |
| private policy creation and target-allowlist initializer preflight | closed |
| channel target installation | closed |
| live `probe` | closed |
| inactive `stage` | closed |
| read-only module `preflight` | closed |
| explicit `activate` | closed |
| independent Symcon/UI/Safari postflight | closed |
| retention `plan` and later `apply` | closed and separate |

No Windows installation, allowlist change, Symcon call, provider request,
module activation, publication or retained-artifact deletion occurred in this
gate.
