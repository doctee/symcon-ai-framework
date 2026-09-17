# SAEF Step 412: Compact Map Controls Publication and Live Rollout

- **Date:** 2026-09-17
- **Status:** Publication and controlled technical rollout complete; physical
  client confirmation remains pending
- **Scope:** Compact Navimow HTML SDK controls, rendered-zone statistics order
  and pointer-specific host interaction boundaries

## 1. Purpose

This step publishes the reviewed step 411 candidate through the canonical SAEF
and standalone repositories, installs the exact standalone result with one
normal IP-Symcon module update and verifies the assembled visualization tile.
It does not change mower commands, authentication, module configuration or
receive-only transport ownership.

## 2. Canonical SAEF Publication

The isolated candidate passed the complete repository check and both GitHub CI
runs. Pull request 135 was merged with this identity:

```text
candidate: 2ae1e5c879035598f3201612161cbf22176b1c75
merge:     b3f2d7f53010b522bfe1e18cd898c4b1e700ea48
```

The exact browser preview verified four viewport profiles. Fine mouse pointers
start at the conservative `48px` host boundary; touch clients retain the
observed `46px` boundary. A real click at the first half-pixel inside each
button boundary activated and deactivated mower-follow mode.

## 3. Standalone Publication

The generic module publisher derived, hash-bound and byte-verified the complete
standalone module. Pull request 16 was merged with this identity:

```text
previous commit:     d76be97e68ad7102c18964c165423f386f12567b
pull-request head:   5c1f15255a9894de8b15e2f95d031e7a37f48b89
merge commit:        8119e00ef1b8604304cd2e350b230622d702cae1
file count:          47
changed file count:  5
fileset SHA-256:     751310813282d9c4108d5eacc136720c0ed39005b655492a4c58815de45ccf17
publication SHA-256: ad84079cc886e0dedc7815f48d2337faca9009bf13c79550374e2fdd47036509
```

The standalone repository had no configured checks. The publisher recorded
`checkCount: 0` and independently verified the integrated `main` tree against
all 47 candidate files.

## 4. Metadata Conformance

The five changed standalone files are limited to the three Device HTML SDK
assets and two generated fileset manifests. Library, module, form and locale
metadata remain byte-identical to the previously conformant standalone commit.

## 5. Controlled Live Update

A bounded structured Symcon MCP preflight verified the exact preceding commit,
a clean and valid `main`, one available update, ready module instances,
operational REST and authentication, enabled Local Map and zone statistics, a
bounded visualization tile and a coherent existing receive-only transport.

Exactly one `MC_UpdateModule()` call installed standalone commit
`8119e00ef1b8604304cd2e350b230622d702cae1`. It returned success and the module
repository immediately reported clean and valid `main` with no remaining
update.

No `ApplyChanges()`, module reload, restart, OAuth action, transport activation,
credential request or mower command was executed. Existing receive-only
transport state was preserved.

## 6. Immediate and Delayed Postflight

Immediate and delayed read-only checks passed with an identical bounded tile
hash. They verified:

- the exact installed standalone commit;
- clean and valid `main` with no pending update;
- ready module, authentication and REST state;
- coherent receive-only transport diagnostics;
- preserved Local Map and zone-statistics configuration;
- touch `46px` and fine-pointer `48px` host-boundary declarations;
- symmetric compact navigation padding;
- four compact controls without the zone selector;
- rendered-zone statistics ordering and heading-color logic;
- delayed-position time handling; and
- absence of external HTML runtime dependencies.

## 7. Architecture Decisions

### AD-NAV-412-01: Separate mouse and touch host boundaries

**Decision:** Keep the observed `46px` touch inset and apply `48px` only when
both hover and a fine pointer are present.

**Reason:** Earlier Safari mouse evidence showed that treating the approximate
host boundary as an exact shared value is not robust. Touch behavior should not
move when only the mouse path requires the additional margin.

### AD-NAV-412-02: Preserve the active receive-only transport

**Decision:** Permit the frontend-only module update while the existing
receive-only transport is coherent and verify continuity before and after.

**Reason:** The five-file standalone delta does not change Account, Receiver,
MQTT, WebSocket or command ownership and requires no instance reconciliation.

### AD-NAV-412-03: Keep physical confirmation separate

**Decision:** Treat repository publication and live technical verification as
complete while retaining physical Safari and IP-Symcon client interaction as a
separate observation.

**Reason:** The exact browser preview and assembled live tile prove the code
contract. They cannot reproduce every host overlay of a physical client.

## 8. Gate Result

| Gate | Status |
|---|---|
| Exact mouse and touch browser preview | PASS |
| Full repository validation | PASS |
| SAEF PR, CI and merge | PASS |
| Standalone publication and merge | PASS |
| Metadata conformance by byte equivalence | PASS |
| Live preflight | PASS |
| Exactly one module update | PASS |
| Immediate read-only postflight | PASS |
| Delayed read-only postflight | PASS |
| Receive-only transport continuity | PASS |
| Physical Safari and iPad confirmation | OPEN |

## 9. Next Step

Confirm on a physical mouse/Safari client and an iPad that all four controls
remain clickable, the frame padding appears symmetric and the compact layout
does not obscure map content. That observation requires no repository or
Symcon mutation.
