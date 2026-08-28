# 377 Local Map Runtime Private Activation And Live Verification

**Case study:** Navimow native IP-Symcon module

**Status:** Installation-private Local Map activated and verified; automatic
idle refresh passed while MQTT remained disabled and credential-free

**Date:** 2026-08-28

## 1. Result

The retained private map revision was validated offline, imported into the
Device instance and rendered successfully on standalone commit
`783b37dbdce13dcbddd738a82fbba76bd72d7c86`.

The final live state is:

- Local Map enabled and visible as the stable `LocalMap` HTMLBox;
- Dark-Skin presentation active;
- all four map zones rendered;
- the current Zone 4 label hidden without removing its polygon;
- exactly one REST-authoritative station-state marker rendered;
- no script, event handler, external reference or remote asset in the SVG;
- automatic idle refresh observed after exactly 300 seconds;
- MQTT and position diagnostics disabled;
- transport credentials absent;
- REST and authentication operational;
- all 14 established variable and Archive contracts unchanged.

No module update, reload, service restart, OAuth action or mower command was
performed in this step.

## 2. Authorization And Privacy Boundary

The user authorized the complete private configuration, activation,
verification and documentation sequence. Symcon MCP was available and used as
the only live channel.

The accepted projection, geometry fingerprint, zone bindings, aliases, device
identity and byte-exact configuration backup remain ignored private evidence.
The public case study records only coordinate-free structure and contract
results.

MQTT remained outside this gate. The activation therefore renders accepted
geometry and the current REST-authoritative station state without retrieving
MQTT credentials or adding new path samples.

## 3. Package And Backup Gate

Before live mutation, the private package passed the productive runtime
offline with:

- bounded format version and serialized size;
- four validated zone bindings;
- explicit frame-correlation approval;
- recomputed accepted geometry revision;
- Dark-Skin theme;
- presentation-only Zone 4 label suppression;
- docked station rendering;
- active-content-free bounded SVG output.

A read-only Symcon probe then captured the complete Device configuration as a
byte-exact private backup. A second immediate preflight proved the exact
standalone commit, disabled credential-free MQTT, healthy REST, stable
configuration hashes and the unchanged 14-variable contract.

## 4. Controlled First Attempt And Rollback

The first activation attempt configured and rendered the map, but the private
runner additionally required the registered module timer to appear as an
event object below the Device instance. That predicate failed and the runner
immediately restored the byte-exact configuration with one rollback
`ApplyChanges()`.

The corrected disabled-state readback then passed completely. A separate
read-only inventory showed that neither the Device timers nor the established
and functioning Account timers appear in `IPS_GetEventList()` on this
installation. The failure therefore concerned private timer observability,
not product scheduling or map rendering.

No mutation was retried until the rollback configuration, repository,
credentials, REST state, variables and Archive contracts had all been
reverified.

## 5. Corrected Activation

The corrected runner changed only these Device-owned map properties:

- the Local Map master gate;
- the accepted private projection and revision;
- hidden label sequences;
- map theme;
- bounded track retention;
- active and idle refresh intervals.

It then called `IPS_ApplyChanges()` exactly once and performed one bounded map
refresh. The refresh returned the expected inactive-MQTT result while still
rendering the accepted geometry and REST station state.

The immediate postflight verified:

| Contract | Result |
| --- | --- |
| repository branch, commit, clean and valid state | passed |
| Account, Configurator, Device and Receiver | active |
| MQTT and WebSocket Core | inactive |
| MQTT and position diagnostics | disabled |
| Authorization header and MQTT credentials | absent |
| authentication and REST | operational |
| private package and geometry revision | exact match |
| Local Map variable | visible String with `~HTMLBox` |
| theme and geometry | dark, four zone polygons |
| Zone 4 presentation | label hidden, polygon retained |
| station state element | exactly one |
| active content | absent |

## 6. Automatic Refresh Evidence

Module timers are Core-internal on this installation, so automatic operation
was verified through the owned map variable instead of an event object.

After the immediate manual render, the test waited beyond the configured
300-second idle interval without another refresh call or configuration
mutation. The delayed readback observed the map variable's update timestamp
advance by exactly 300 seconds while its valid SVG hash remained unchanged.

This proves that the module timer executed naturally and that deterministic
rerendering did not alter a stable docked scene.

## 7. Existing Contract Preservation

The delayed postflight repeated the full read-only projection. The hashes from
step 376 remained unchanged:

| Contract | SHA-256 |
| --- | --- |
| 14 established variable identities | `02c2973d5a8d914f33d950b1ac73cb90894807a8178a68661403a2e0869a8ffc` |
| Archive logging and aggregation | `ca553115285c5c5336650ee2d635896df4cbdd109208c00a6f53aecc7f825d81` |
| Account configuration | `43b7c6c99b6f8d0b5a941c5ecb5343ce06c5f228bdb882a3d5e30391bcc458ad` |
| Device configuration excluding map properties | `1317df8adccda9dc4e30ae55c7d634aeb9c38fed23ac6a8264109e7ab4067cb5` |
| MQTT subscription structure | `9baf072cbd4986458357e3700203ad06bfbed01f8d7fa6b4ba38d98d59efd6e4` |

The user's enabled Battery and other mower-variable logging therefore remains
intact. The map is additive and does not replace, recreate or repurpose those
variables.

## 8. Architecture Decisions

### AD-NAV-377-01: Prove module timers through owned effects

**Decision:** Verify the registered timer by a natural bounded update of the
owned map variable when Core timers are not exposed as event objects.

**Reason:** An object-tree assumption must not turn a healthy internal module
timer into a false product failure.

### AD-NAV-377-02: Keep map activation independent from MQTT

**Decision:** Activate accepted static geometry and REST station presentation
without activating the receive-only MQTT transport.

**Reason:** Geometry presentation, transport credentials and live path
collection have different privacy, recovery and mutation boundaries.

### AD-NAV-377-03: Retain private rollback material while active

**Decision:** Keep the exact private package and byte-exact previous Device
configuration while the feature remains enabled.

**Reason:** Configuration-first rollback must remain reproducible without
reconstructing private geometry or installation values.

## 9. Mutation Counts

    module updates: 0
    module reloads: 0
    service restarts: 0
    first activation ApplyChanges: 1
    first-attempt rollback ApplyChanges: 1
    corrected activation ApplyChanges: 1
    bounded manual map refreshes: 2
    automatic map refreshes observed: 1
    MQTT activations: 0
    credential requests: 0
    OAuth actions: 0
    mower commands: 0

Every MCP result separately satisfied:

    transportError: null
    executionError: null
    truncated: false

## 10. Gate Status

| Gate | Status |
| --- | --- |
| private package and revision validation | passed |
| byte-exact rollback backup | retained |
| installation-private Local Map activation | passed |
| immediate map and runtime postflight | passed |
| automatic 300-second idle refresh | passed |
| existing variables and Archive logging | preserved |
| MQTT activation | closed |
| mower command | not performed |
| destructive private-evidence cleanup | deferred while active |

## 11. Next Step

First perform a visual operator check of the Local Map in the Symcon Dark Skin.
After that acceptance, define a separate receive-only MQTT evidence-feed gate
so new position samples can populate revision-bound paths without changing
REST state authority. Changes made later in the official app remain an
explicit private map refresh, difference review and revision-acceptance
workflow.
