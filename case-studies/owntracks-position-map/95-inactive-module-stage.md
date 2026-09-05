# Gate 90-F — inactive module stage

**Status:** The exact reviewed OwnTracks Position Map package was transferred
through deployment channel version 8 and committed as an inactive standalone-
module deployment; the target adapter and active module were not invoked,
2026-09-05.

## Scope

This separately authorized gate stages only the package established in
[Gate 90-E](94-inactive-module-package-candidate.md), with deployment ID
`saef-owntracks-position-map-20260905-01` and package SHA-256
`995b3d31c9f2382df737cc71f9d60ac029cfc333f69da884172e2c46d311cca7`.

The allowed live mutation is limited to the channel's bounded upload state,
inactive managed deployment directory and deployment status. The operation may
not invoke the module adapter, contact Symcon or a tile provider, change the
active module, restart a service, publish or remove retained evidence.

## Result

The package passed a fresh local hash and manifest verification immediately
before transfer. The channel accepted the exact 781094-byte hash and announced
191 ordered 4096-byte chunks. The existing client then sent those chunks
through separate authenticated forced-command SSH requests and performed one
final `stage commit`.

The complete transfer took approximately six minutes and 39 seconds. The
client process and final server response both returned exit code `0`. The
server reported `success: true`, `operation: stage`, `outcome: staged`, the
reviewed package SHA-256, the expected deployment ID, 37 files and deployment
kind `standalone-module`.

Before returning `staged`, the installed gateway requires complete ordered
upload state, exact byte count and package hash, safe extraction, bounded and
sorted manifest paths, exact per-file sizes and hashes, no missing or extra
files, aggregate package identity, transaction-contract hash, the unique
allowlisted target binding, adapter-profile identity and module-library GUID.
The successful response therefore confirms an intact inactive server-side
candidate.

No target path, host, account, key, credential, RPC endpoint, ObjectID,
coordinate, tracker identifier or movement history is recorded here. No
provider was contacted.

## Decision boundary

Gate 90-F transfers and validates an inactive candidate only. It does not prove
current quiescence, ownership, live configuration, state or module health and
does not authorize activation.

The remaining sequence stays separately gated:

1. optionally inspect the deployment's sanitized channel `status`;
2. run the target-bound OwnTracks module `preflight`, which contacts local
   Symcon read-only and invokes the pinned module adapter without activation;
3. keep `activate`, independent health and Safari acceptance, rollback
   retention, publication and cleanup as later gates.

Gate 90-F authorizes none of those later actions.
