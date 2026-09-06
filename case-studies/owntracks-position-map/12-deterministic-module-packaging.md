# Deterministic Module Packaging and Installability Preflight

**Status:** Complete; generated module not installed or published

**Date:** 2026-08-30

## 1. Outcome

The repository candidate now produces one self-contained IP-Symcon module
library at:

```text
dist/symcon/saef-owntracks-position-map-module/
```

The exact generated fileset contains 19 byte-exact payloads plus
`fileset.sources.json` and `fileset.sha256`. Its current identity is:

```text
e2a75773190967dbf663969489449a597743a024a69888c110f43ef366201f25
```

The package contains one `OwnTracksPositionMap` HTML-SDK module, its form and
translations, six case-study-local WGS84/archive/ETA components, the pinned
OpenLayers JavaScript and CSS, renderer markup and all four dependency license
texts. It needs no Composer, npm, CDN or sibling SAEF file at runtime.

## 2. Reused Fileset Path

`deployments/symcon/owntracks-position-map-module.fileset.json` uses the
existing `tools/build-symcon-module-fileset.php`. No separate OwnTracks
packager was introduced.

The shared builder already serves Open-Meteo, MediaCarousel and Navimow. This
gate adds only:

- the two OwnTracks case-study source roots; and
- `.css` and `.txt` target types for the local renderer style and exact license
  inventory.

Existing source allowlists, traversal and symlink rejection, output boundary,
byte-exact copying, atomic writes, source-map generation, fileset hashing and
stale-target checks remain unchanged. The full SAEF check revalidates every
existing consumer.

## 3. Frozen Module Boundary

The package exposes one type-3 module with no parent or child requirement. Its
configuration form accepts exactly the data already covered by the runtime
contract:

- three OwnTracks source mappings;
- one optional external path-anchor instance and its position ident;
- the local calendar time zone and bounded history/render limits; and
- a private ETA target plus explicit geodesic-diagnostic permission.

Provider configuration is deliberately absent from the form. The packaged
runtime rejects a provider or routing mode other than `none`, embeds all
frontend assets, and applies `connect-src 'none'`. It contains no WebHook
registration, archive mutation, logging change, mirror variable or
visualization-link creation.

## 4. Installability Evidence

The preflight proves:

- library and module metadata parse with distinct valid GUIDs;
- module name, PHP class, type and prefix agree;
- the IP-Symcon 8.1 and PHP 8.2 compatibility floors are explicit;
- every generated target matches its canonical source SHA-256;
- two independent temporary builds have the same complete inventory and
  hashes;
- the tracked generated tree matches both independent builds;
- an additional stale target is rejected;
- the module resolves only packaged `libs/OwnTracks` and `assets` at runtime;
- the complete packaged runtime lifecycle/action suite passes;
- the packaged HTML output passes the same desktop and iPad-sized no-tile
  browser diagnostics without overflow or console errors; and
- public payloads contain no private path, address, ObjectID, tracker identity,
  coordinate or movement history.

The generated library has not been submitted to Module Control or a module
validator running against a live installation. That would cross the next live
mutation gate.

## 5. Ownership and Rollback Preconditions

The immutable baseline remains the installed OwnTracks map, its existing hook,
two links, selector behavior, three source instances, external-data instance,
logging and archives. The package owns none of them.

A future live activation must first record privately:

1. the exact fileset hash above and a byte-exact staged-file backup boundary;
2. absence of the new library and module GUIDs, or the exact compatible state
   if they already exist;
3. the three source and external-anchor references selected for the new
   instance;
4. both intended visualization parents and the existing sibling links; and
5. a rollback plan limited to the new links, new instance and exact staged
   library files.

Rollback must hide or remove only the two new links, then remove only the new
module instance, and finally unload/remove only the exact staged fileset after
fresh ownership read-back. The existing map, hook, source instances, selector,
logging, archives and links are never rollback targets.

## 6. Remaining Authorization Gate

The next gate is a parallel no-tile live activation. It would stage the exact
fileset, create one configured module instance and add two links beside the
existing map links. It must keep provider and routing modes at `none` and may
not register a WebHook or alter any existing object.

That gate is a live mutation and remains closed until separately authorized.
Commit and publication also remain separate gates.
