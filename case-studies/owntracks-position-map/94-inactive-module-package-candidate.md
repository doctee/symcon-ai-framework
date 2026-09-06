# Gate 90-E — inactive module package candidate

**Status:** The exact OwnTracks Position Map standalone-module package was
built reproducibly and passed all offline integrity and adapter-contract
checks; transfer, staging and every live module operation remain closed,
2026-09-05.

## Scope

This separately authorized repository-only gate packages the tracked
OwnTracks Position Map distribution for deployment ID
`saef-owntracks-position-map-20260905-01` and installed target ID
`saef-owntracks-position-map`. The source is repository commit `0a4bedb` and
the existing `saef-owntracks-position-map-v1` transaction contract.

The gate may create and verify one ignored private ZIP and its private build
evidence. It may not contact the installed channel, transfer or stage the
package, invoke the target adapter, change Symcon, contact a tile provider,
publish or remove retained live evidence.

## Source reconciliation

The generated distribution and the previously inventoried active package both
contain 37 regular files totalling 762567 bytes. The apparent earlier count
difference was a manual counting error, not missing private tile content. The
distribution tree is fully tracked, unchanged in the worktree and current
against its module-fileset manifest.

The resulting package identity SHA-256 is
`65b81cf76741f31f08688c32596450ca7bfe4435613a4e4e353eff16553179fa`.
It equals the independently retained active-package identity, so this pilot
candidate introduces no module-content delta.

## Offline result

The deterministic builder produced a 781094-byte ZIP with package SHA-256
`995b3d31c9f2382df737cc71f9d60ac029cfc333f69da884172e2c46d311cca7`.
It contains 39 entries: `deployment.json`, the canonical transaction contract
and 37 module files.

Verification completed successfully for:

- the current OwnTracks module fileset;
- the complete OwnTracks core, runtime, renderer, distribution, security and
  deployment-adapter test group;
- the generic deployment-channel and retention tests;
- ZIP structural and compressed-data integrity;
- exact deployment, target and library identities;
- the complete entry set and deterministic entry order;
- every packaged file size and SHA-256;
- the transaction-contract SHA-256;
- the aggregate package identity; and
- a second byte-identical build with the same package SHA-256.

The temporary replay archive was removed after comparison. The reviewed ZIP,
plan, verifier and local notes remain in ignored private evidence. No host,
account, key, credential, path, ObjectID, coordinate, tracker identifier or
movement history is recorded here.

## Decision boundary

Gate 90-E establishes an exact inactive package candidate only. Its identity
matching the active package minimizes content risk but does not waive any
deployment transaction or health gate.

The remaining sequence stays separately gated:

1. authorize transfer of only the reviewed package hash through channel
   `stage`, which writes an inactive managed deployment and status but does not
   invoke Symcon or replace the active module;
2. verify the staged package and run target-bound module `preflight` in a later
   gate;
3. keep `activate`, independent health and Safari acceptance, rollback
   retention, publication and cleanup as later gates.

Gate 90-E authorizes none of those later actions.
