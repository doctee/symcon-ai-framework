# Gate 80 — security correction live activation

Status: exact package activation, fresh security acceptance and independent
postflight complete, 2026-09-03.

## Scope

This gate activates the repository correction from
[Gate 78](78-security-correction-and-supplemental-review.md) after the targeted
Windows ACL hardening from [Gate 79](79-windows-acl-hardening.md). It changes
only the immutable OwnTracks position-map package selected by the existing
module installation.

No OwnTracks source instance, ObjectID-based binding, archive, logging setting,
visualization link, map configuration, provider policy, static tile revision or
cache was changed. No repository or provider publication was performed.

## Transitional transfer and activation

Symcon MCP remained the only live channel. Because the current restricted
deployment gateway has no standalone-module package binding, one explicitly
authorized temporary script was created below the verified positive module
owner. It was not treated as a reusable deployment API.

The 199,500-byte archive was transferred in nine ordered blocks. Every offset
was read back before the next block, and Windows verified the final archive
digest before extraction. The transaction then:

- required one healthy owner and byte-identical configuration;
- verified the active package, the immediate rollback package and the absence
  of the candidate;
- accepted exactly 37 unique regular archive entries;
- extracted through explicit streams into the protected OwnTracks state
  boundary and verified all 35 payload sizes and hashes;
- copied the already reviewed protected package DACL and exhaustively rejected
  reparse points, untrusted owners or untrusted write permissions;
- held all five runtime locks and required zero active leases while switching;
- retained private byte-exact configuration and version-1 miss-state evidence;
- moved the previous active package to an identity-qualified rollback boundary;
  and
- performed one interface-discovered, package-local Module Control reload.

The active identity is
`7354982680611b90a220ad6ffc618907ffb0f82c184d77fdb6ac2964e0406b61`.
The immediately preceding identity
`82c148a5f1e7789db1850641f5ea45f44322f8f4e8b2c84ea354b53982d2de9c`
is the direct rollback package. The earlier complete rollback identity
`9a00040d6f4491cc1f330365c20d06d01abd0c8d0da179b1f1632e74e56dd97c`
also remains retained. Automatic rollback was armed but not needed.

The upload, stage, failed-candidate path, initialization marker and temporary
Symcon script were removed and their absence read back. Private transaction
evidence remains retained with the rollback packages; removing either side is
a separate retention gate.

## Fresh security acceptance

Four live-code negative cases covered invalid method/body, missing capability,
invalid capability and out-of-range tile addressing. Their public responses
were identical nine-byte `404` results with `no-store` and `nosniff`; none
reached the tile reader.

After those local checks, exactly one non-position-derived synthetic world tile
was requested from the configured OSM Standard provider. The request used the
configured identifiable user agent and the privately configured Connect origin
as Referer under the earlier explicit disclosure approval. The response was a
bounded PNG over verified TLS from a public peer, with no redirect. Neither the
tile body nor the private Referer was persisted in the acceptance record.

The check proves this configured transport transaction, not general provider
availability or a pre-TCP network sandbox. The accepted boundary remains:
native asynchronous DNS and the cURL prerequisite callback reject a non-public
connected peer after TCP/TLS setup but before HTTP headers or body are sent.

## Independent postflight and rollback

A separate read-only MCP postflight confirmed:

- healthy module status and byte-identical complete configuration;
- exactly one 35-payload active package and both complete rollback packages;
- all five runtime locks free and no active lease;
- protected ACLs on the new package and private miss-state boundary, with no
  untrusted writer in their bounded inventories;
- PHP 8.4 or later, asynchronous cURL DNS and prerequisite-callback support;
- complete activation, rollback and one-request acceptance evidence; and
- no upload, stage or failed-candidate residue.

The miss-state was still format 1 at the calm postflight. Before the first
candidate tile write, the immediate rollback package can therefore consume the
current state. Once candidate runtime use migrates the state to format 2, a
later rollback must quiesce all writers, capture and hash the fresh state, run
`tools/prepare-miss-state-rollback.php --prepare-legacy`, and switch the
converted state and previous package consistently. A stale version-1 backup,
cache purge or budget reset is not a valid rollback.

## Framework consequence

The temporary MCP script was the last case-study-local transition mechanism.
It is intentionally not standardized. The recurring requirement is a
manifest-driven standalone-module package transaction behind SAEF's existing
restricted deployment channel, retaining the five verbs `probe`, `stage`,
`preflight`, `activate` and `status`. That framework change belongs to its own
clean workstream and must preserve separate activation and retention gates.

No ObjectID, tracker key, coordinate, movement history, private URL, host name
or installation path is recorded in this report.
