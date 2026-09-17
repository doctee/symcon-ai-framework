# SAEF Step 410: Responsive Map Publication and Live Rollout

- **Date:** 2026-09-17
- **Status:** Publication and controlled rollout complete; physical client
  confirmation remains pending
- **Scope:** Navimow HTML SDK responsiveness, statistics layout, stale-position
  time and qualitative path-age presentation

## 1. Purpose

This step publishes the reviewed candidate from step 409 through the SAEF and
standalone repositories, installs the exact standalone result with one normal
IP-Symcon module update and verifies the assembled HTML SDK tile without
changing mower, authentication or transport configuration.

## 2. Canonical SAEF Publication

The isolated candidate passed the complete repository check and both GitHub CI
runs. Pull request 133 was merged with this identity:

```text
candidate: 30120362e450f4e0f1288f86da00f893f9b4fa66
merge:     f3296162c3d705b165223f2a8e2834e9a861f68a
```

The candidate retained the 46-pixel Safari and iPad host-interaction boundary
through a deterministic `52px` navigation offset at every viewport width.

## 3. Standalone Publication

The generic module publisher derived, hash-bound and byte-verified the complete
standalone module. Pull request 15 was merged with this identity:

```text
previous commit:     4ab415dd5582bd2f49cec3149ccf4c28a94fcd29
pull-request head:   3975affe0eb1d9e13960c82ec532d5ec29a7416e
merge commit:        d76be97e68ad7102c18964c165423f386f12567b
file count:          47
changed file count:  6
fileset SHA-256:     8403128dd9470fbbae76b20d7d3076e727b548129a8915a7edef2dc7255dbeac
publication SHA-256: 7038f4aaec56f1988cb985ca5ece160ccdece5e5c228386715a34affe3b3ce03
```

The standalone repository had no configured checks. The publisher recorded
`checkCount: 0` and independently verified the integrated `main` tree against
all 47 candidate files.

## 4. Metadata Conformance

The six changed standalone files are limited to Device runtime code, local HTML
SDK assets, the local SVG renderer and generated fileset manifests. All
metadata inputs consumed by the established validator remain byte-identical to
the previously conformant standalone commit:

- `library.json`;
- all four `module.json` files;
- all four `form.json` files; and
- all four `locale.json` files.

Metadata conformance therefore passes by exact input equivalence. No new result
from the known-unreliable browser validator is claimed.

## 5. Controlled Live Update

A bounded structured Symcon MCP preflight verified:

- exact preceding commit on clean and valid `main`;
- the published update was available;
- ready Account, Configurator, Device and Receiver instances;
- ready authentication and operational REST polling;
- enabled Local Map and zone statistics;
- a bounded visualization tile; and
- a coherent existing receive-only MQTT and position transport.

Exactly one `MC_UpdateModule()` call installed standalone commit
`d76be97e68ad7102c18964c165423f386f12567b`. It returned success and the module
repository immediately reported clean and valid `main` with no remaining
update.

No `ApplyChanges()`, `MC_ReloadModule()`, restart, OAuth action, transport
activation, credential request or mower command was executed. The existing
receive-only transport state was preserved.

## 6. Immediate and Delayed Postflight

Immediate and delayed read-only checks both passed and reported the same tile
hash. They verified:

- the exact installed standalone commit;
- clean and valid `main` with no pending update;
- ready module, authentication and REST state;
- coherent receive-only MQTT and position diagnostics;
- preserved Local Map and zone-statistics configuration;
- the `52px` host-boundary offset with no narrow-screen `4px` regression;
- statistics-aware map layout;
- the delayed-position time threshold; and
- absence of external HTML runtime dependencies.

The first private postflight version incorrectly searched the static HTML SDK
tile for server-rendered SVG path-age class names. A second wording correction
still targeted the wrong evidence surface. Both failures were read-only. The
final probe removed that impossible tile assertion and passed without another
module update. The exact standalone commit and publication hashes remain the
evidence for the renderer implementation.

## 7. Architecture Decisions

### AD-NAV-410-01: Preserve the active receive-only transport

**Decision:** Permit the Device-only module update while the existing
receive-only transport is coherent, and verify that state before and after.

**Reason:** The published delta does not change Account, Receiver, MQTT or
WebSocket ownership and requires no instance reconciliation.

### AD-NAV-410-02: Keep tile and renderer evidence separate

**Decision:** Verify HTML SDK behavior through `IPS_GetVisualizationTile()` and
bind server-rendered SVG behavior through the exact standalone commit and
fileset hash.

**Reason:** Path-age classes are emitted in dynamic SVG payloads and cannot be
proven by searching the static tile document.

### AD-NAV-410-03: Require physical client confirmation separately

**Decision:** Keep final Safari and iPad presentation confirmation open after
the repository and live technical gates pass.

**Reason:** Platform APIs prove assembled code and live health, but not the
final visual result in every physical client viewport.

## 8. Gate Result

| Gate | Status |
|---|---|
| SAEF PR, CI and merge | PASS |
| Standalone publication and merge | PASS |
| Metadata conformance by byte equivalence | PASS |
| Live preflight | PASS |
| Exactly one module update | PASS |
| Immediate read-only postflight | PASS |
| Delayed read-only postflight | PASS |
| Receive-only transport continuity | PASS |
| Physical Safari and iPad confirmation | OPEN |
