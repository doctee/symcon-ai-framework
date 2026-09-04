# Tile Cache Runtime Adapter

**Status:** Repository implementation complete; live WebHook integration and
activation remain closed

**Date:** 2026-08-31

## Outcome

`OwnTracksTileFileCache` provides the case-study-local persistence boundary for
the protected gateway. Separate adapter objects reuse the same bounded cache
between simulated WebHook calls without serializing all tile bytes into module
state on every request.

The adapter is optional. A gateway caller that supplies `null` remains in the
provider-free, cache-disabled mode. Nothing in the current runtime module
constructs the adapter, registers a WebHook or enables a basemap.

## Reuse Before Extend

The SAEF Registry and Statistics helpers were reviewed first. They remain the
correct choice for small metadata and counters, but not for up to 16 MiB of PNG
payloads. IP-Symcon module buffers are volatile and limited to 1 MiB, while
string variables are likewise not a binary cache boundary. Reusing either
would violate their documented responsibility and the performance objective.

No general SAEF filesystem-cache helper is introduced. The implementation
stays beside the OwnTracks gateway until another concrete use case proves a
stable recurring contract.

## Storage Contract

The Symcon factory selects a private process-temporary directory outside the
WebServer `user` tree, scoped to the owning module instance. The cache is:

- non-authoritative and rebuildable;
- persistent across separate PHP/WebHook calls while the temporary directory
  remains available;
- allowed to disappear on service restart, operating-system cleanup or module
  replacement;
- protected by an exclusive file lock;
- represented by one hashed filename per tile and one small JSON manifest; and
- never addressable through a public URL.

The manifest stores only hashes, byte counts, timestamps, access sequence and
bounded counters. It contains no capability, provider identity, coordinates,
tracker identity or tile bytes.

## Write And Recovery Rules

Tile and manifest writes use a private temporary file followed by atomic
replacement. File and directory permissions are restricted where supported.
The adapter rejects linked cache roots and linked tile files.

On every operation it removes only exact cache-owned temporary files and
orphaned 64-hex PNG names. A malformed or oversized manifest resets only those
owned files and continues as a cache miss. Unknown files are never deleted.

The same 300-second, 256-entry, 16-MiB and 512-KiB bounds remain authoritative.
LRU order uses a monotonic sequence, so concurrent requests within one second
do not create timestamp ties.

## Verification

Repository tests reconstruct the adapter between two authorized gateway calls
and prove that the second request is a cache hit. They additionally cover:

- cache-disabled gateway operation;
- revision isolation and TTL expiry;
- capability rejection before any cache operation;
- manifest privacy;
- entry and byte eviction;
- deterministic LRU behavior;
- malformed-manifest recovery; and
- exact temporary-directory cleanup.

The deterministic 26-file repository package identity is:

```text
4b41004e610a4be0b756b20f61f5a008cd5d629fd88533772dce9ef908d38296
```

## Follow-up Gate

Capability issuance, request translation and this optional cache are now
composed by the separately reviewed default-disabled adapter recorded in
`24-webhook-adapter-security-review.md`. The exact synthetic Connect acceptance
test with verified cleanup remains separate. No live object, provider,
publication or existing OwnTracks configuration is changed by either repository
result.
