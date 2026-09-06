# Complete Provider Tile Live Correction

**Status:** Repository correction, exact live activation and browser acceptance complete

**Date:** 2026-09-02

## Symptom And Evidence

Long fitted paths could still show abrupt white rectangles or isolated map
strips. After the transport gaps were removed, some rectangles remained with a
different raster appearance even though every corresponding protected request
had returned a valid PNG.

The evidence separated two causes:

1. an accepted viewport could rebuild the protected tile layer while requests
   from the preceding layer were still active or queued; and
2. provider-fallback mode preferred locally rendered static XYZ content over
   fresh provider content, producing visible seams between two valid but
   different raster styles.

Neither condition was a static coverage calculation error. Successful HTTP and
PNG validation alone also did not prove a visually coherent final layer.

## Correction

The renderer now defers every protected basemap rebuild until both the active
request set and request queue are empty. A pending rebuild remains bounded to
one timer and exposes only aggregate drain diagnostics. Disabling the protected
basemap cancels that timer explicitly.

Provider-fallback composition now uses this order inside the authorized
viewport:

1. fresh provider-aware cache;
2. bounded provider request; and
3. immutable static XYZ tile as the availability fallback.

The static authority is still read-only and remains the complete fallback when
the provider is unavailable, denied or over budget. Provider-disabled and pure
offline operation are unchanged. No cache purge, static-tile mutation or
configuration expansion was required.

## Verification

The generated OpenLayers bundle, the complete OwnTracks suite, deterministic
36-file module fileset and repository whitespace checks passed. Runtime tests
prove provider priority, fresh-cache reuse and static fallback after a provider
failure.

The final authorized live browser test used the previously problematic long
historical fit. It completed with:

- 32 protected tile requests started and 32 succeeded;
- 32 tile images loaded;
- one identical authorized and requested zoom level;
- no missing tile, failed request or retry; and
- a visually continuous map without white gaps, strips or mixed-style islands.

## Controlled Activation And Rollback

The active fileset identity is
`9a00040d6f4491cc1f330365c20d06d01abd0c8d0da179b1f1632e74e56dd97c`.
Windows independently verified the 198,651-byte upload, all 36 regular files,
all 34 provenance payload sizes and hashes, and the unchanged complete
configuration.

One intermediate activation stopped at an overly strict minified-symbol
postflight and automatically restored the preceding package. After correcting
only that postflight assertion, the drain-aware package activated normally.
The final provider-priority package then activated through the same guarded
workflow. Its immediate complete rollback package is
`62846115a0870d1942a7b4284ea9d15b1848d56caaf0808bf3c5989ce1e50918`.

The visual acceptance ran against that immediate rollback identity. The final
package changes only source formatting required by the repository style gate;
its generated renderer, provider-priority behavior and live configuration are
unchanged. It therefore required structural postflight but no additional
provider-reaching browser call.

An independent read-only postflight confirmed healthy status, byte-identical
configuration, all active and rollback payload hashes, no stage or failed
candidate residue, and retained verified upload and configuration backup.

No ObjectID, tracker identifier, coordinate, tile index, movement history,
private origin or host detail is recorded here. No commit, publication, cache
purge, static revision change or retained-artifact cleanup was performed.
