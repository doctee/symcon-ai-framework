# Private Windows Tileset Staging

**Date:** 2026-08-31

## Outcome

The separately authorized private transfer and Windows staging gate is
complete. The previously validated raster revision and deterministic module
package now reside in a new private Symcon-readable staging directory. This
gate did not install or update a library, register a WebHook, change module
configuration, activate the basemap, or mutate an existing OwnTracks object.

Exact addresses, ports, storage paths and capability material remain private
installation evidence and are intentionally absent from this document.

## Bounded Transfer Boundary

The temporary transfer endpoint was restricted to:

- one LAN interface and one allowlisted source address;
- one exact Host value, request path and random one-time capability header;
- `GET` with a mandatory single byte range only;
- a fixed maximum response range, no directory listing and no query string;
- `application/octet-stream`, `nosniff` and `no-store`; and
- one immutable archive whose size and SHA-256 were checked before serving.

The first large-block attempt reached a per-request time boundary. The receiver
detected the premature end, retained only the last confirmed prefix and proved
that prefix against the source archive. Transfer then continued in 8 MiB
blocks. Every block required the exact `206`, `Content-Range`, content length
and current destination length. A failed block was truncated back to its
starting offset before another attempt was permitted.

The completed 684,721,747-byte archive matched its expected SHA-256 before ZIP
processing began.

## Archive And Extraction Safety

The ZIP was inspected before extraction and passed these gates:

| Measure | Result |
| --- | ---: |
| Archive entries | 28,722 |
| Tile files | 28,692 |
| Tile bytes | 681,116,404 |
| Largest tile | 82,281 bytes |
| Module files | 29 |
| Extracted bytes | 681,718,093 |

Entry names were unique, relative, slash-normalized and confined to the exact
tile, module and transfer-manifest roots. Absolute paths, drive prefixes,
parent traversal, links, non-regular Unix entries, encryption and entries
outside the allowlist were rejected. File counts and compressed/uncompressed
sizes remained inside the manifest budgets.

Extraction used explicit per-entry streams into a new `.incoming` directory,
not an unrestricted archive `extractTo()` call. Each destination was opened as
new, checked for containment, written under a batch-state boundary and checked
against its declared size. Tile entries also had to carry the PNG signature.
The completed directory was renamed atomically only after validation.

## Independent Content Postflight

All extracted tiles were enumerated as numeric `z/x/y` tuples, bounded to zoom
0 through 14 and hashed in deterministic numeric order. Tile count, total
bytes, largest file and the complete inventory SHA-256 matched the private
build manifest.

The module source map contained 27 payloads. Each payload matched its declared
target, byte count and SHA-256; the source map and sidecar completed the exact
29-file package and matched the expected fileset identity. No extra file
remained in the final staging root.

Read-only postflight found the already registered version-0.1.0 library,
module and one existing pilot instance. The transfer path invoked no Symcon
object, Module Control, configuration or WebHook mutation API, so those live
objects were not updated by this gate.

## Cleanup And Remaining Gate

After the final content postflight:

- the temporary archive and extraction marker were deleted;
- the `.incoming` path was absent;
- the one-time transfer server was stopped;
- its capability token was deleted; and
- the temporary port no longer listened.

The private source archive and macOS build artifacts remain retained for the
later live acceptance and rollback boundary. Their cleanup is still a separate
gate.

The next live gate must back up and hash the current pilot configuration, prove
the exact staged tile authority and WebHook settings, then perform one bounded
configuration transaction with rollback. Basemap/WebHook activation,
publication, commit and changes to existing OwnTracks objects remain closed.
