# 17 Private Backup and Staging Report

**Case study:** IP-Symcon MQTT Discovery Exporter
**G6 sub-gate:** Recoverable pre-change backup and inactive filesystem staging
**Result:** PASS
**Date:** 2026-07-15
**Live status:** Files written only to private backup and inactive staging locations

## 1. Scope

This gate creates the recoverable private evidence required for a later
activation and places the reviewed exporter fileset on the connected target
filesystem without selecting or loading it.

The gate did not modify the active bundle, bootstrap source, migrated caller or
IP-Symcon object tree. It did not restart IP-Symcon, load either exporter class,
publish MQTT data or invoke a device action.

## 2. Recoverable Private Backup

The target installation now contains a protected private snapshot with:

- the byte-exact active minimal bundle;
- the byte-exact included bootstrap source;
- the byte-exact single migrated caller source;
- private restore metadata containing the original installation mapping,
  modes and SHA-256 values.

Directories were restricted to the runtime owner and backup files were written
with owner-only permissions. Each backup was written through a temporary file,
atomically renamed and compared with both the captured metadata and the still
active source.

The snapshot deliberately remains outside this repository. Its paths, caller
identity and bootstrap contents are private installation data.

## 3. Inactive Fileset Placement

The complete generated deployment tree contains fifteen files:

- twelve byte-exact canonical PHP sources;
- `bootstrap.php`;
- `fileset.sources.json`;
- `fileset.sha256`.

Each file was compressed only for transport, reconstructed on the target,
verified against its individual repository SHA-256 and written atomically into
a temporary tree. Finalization compared the complete relative path/hash map,
rejecting missing, additional or mismatching entries, before atomically naming
the tree as the versioned staging candidate.

The staged provenance matches:

- fileset SHA-256:
  `bbc44c98500895319cf862f0dacc6492cadac2aedb0c6e3e302ec2c9027cfb2c`;
- bootstrap SHA-256:
  `3567e73a1ac93743f6daa5a21dcd208c3a7845e4f391ebca31c9bf86839725c9`.

Both values are deterministic public build provenance and contain no private
installation identity.

## 4. Post-Staging Invariants

| Check | Result |
| --- | --- |
| Backup artifact set complete and internally consistent | PASS |
| Active minimal bundle still has its captured SHA-256 | PASS |
| Included bootstrap still has its captured SHA-256 | PASS |
| Migrated caller still has its captured SHA-256 | PASS |
| Staged relative file set | 15 of 15, no extras |
| Staged per-file hashes | 15 of 15 |
| Staged aggregate and bootstrap provenance | PASS |
| Exporter classes loaded into the active process | 0 |
| Bootstrap selection changed | No |
| IP-Symcon restart performed | No |

## 5. Rollback Readiness

No rollback is necessary because the active selection was not changed. The
private backup nevertheless provides the exact bundle, bootstrap and caller
sources required by the later activation rollback procedure.

Before activation, the same three active hashes and the complete staged tree
must be checked again. Any drift invalidates this gate and requires a fresh
snapshot rather than reuse of stale evidence.

## 6. Gate Decision

The private pre-change backup and inactive filesystem staging gate is **PASS**.

The next G6 sub-gate is the explicitly authorized activation transaction:

1. repeat the drift and staged-tree preflight;
2. replace the old minimal-bundle include with the staged fileset bootstrap;
3. restart IP-Symcon into a clean namespace;
4. verify the previous seven exports, all new exports and both exporter classes;
5. verify the existing migrated caller without changing its target;
6. perform the isolated no-entity load twice;
7. restore the backed-up bootstrap and restart if any gate fails.

That activation, restart and rollback authority is not granted by this report.
