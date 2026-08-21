# MediaCarousel Deterministic Distribution

## Outcome

The canonical module sources produce a standalone IP-Symcon library tree at:

```text
dist/symcon/saef-media-carousel-module/
```

The generated tree has `library.json` at its root and contains one complete
`MediaCarousel` module plus `fileset.sources.json` and `fileset.sha256`. Every
payload is copied byte-exactly from the explicit manifest; generated files are
never edited manually.

## Contract

`deployments/symcon/media-carousel-module.fileset.json` is the complete sorted
allowlist. The shared module-fileset builder accepts this case-study source
root and the HTML-SDK `.html` and `.js` payload types in addition to its
existing PHP and JSON contract. It still rejects absolute paths, traversal,
symlinks, unapproved roots, duplicate targets and output outside
`dist/symcon/`.

Build and verify with:

```console
make media-carousel-fileset-build
make media-carousel-fileset-check
```

The distribution validator freezes the single-module inventory, metadata
shape, GUID syntax, version, compatibility floor, frontend integration markers
and absence of common private path/address markers.

The fileset regression builds twice in independent bounded temporary roots,
compares every path and hash, compares the tracked generated tree, verifies
each target against its canonical source and rejects stale additional files.
It also freezes the future standalone publication inventory consisting of the
generated tree, the canonical public README and the repository license.

## Remaining Gates

No public repository identity is assumed by this offline closure. Creating or
updating a remote repository, committing, pushing and installing the library
remain separate approvals. A later live pilot must install the exact immutable
fileset in parallel and leave the native content switcher untouched until its
own acceptance gate passes.
