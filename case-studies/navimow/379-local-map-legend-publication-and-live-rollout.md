# 379 Local Map Legend Publication And Live Rollout

**Case study:** Navimow native IP-Symcon module

**Status:** SAEF and standalone publication, metadata conformance and guarded
live rollout passed

**Date:** 2026-08-28

## 1. Result

The compact Local Map symbol legend from step 378 was published through both
review paths and rolled out to the authorized IP-Symcon installation.

The installation-private zone alias requested by the operator was shortened
at the same gate. Only the display label in one existing private binding
changed. Zone identity, geometry, accepted geometry revision and all other
bindings remained unchanged.

The final map has:

- one compact Dark-Skin legend in the free lower-right area;
- four zone polygons and seven subtle excluded-area polygons;
- one REST-authoritative station marker;
- the requested shortened local zone label;
- the Zone 4 polygon with its label still hidden;
- the existing visible visualization link named `Karte`;
- no retained path, diagnostic point or MQTT mower marker while MQTT is
  disabled.

## 2. SAEF Publication

The clean worktree was based on the current canonical `origin/main`. The
candidate passed the complete SAEF check and a private coordinate-bearing
Dark-Skin render before publication.

SAEF pull request 86 contained one Conventional Commit and eight expected
public paths. Both CI executions passed. The pull request was merged by merge
commit and the canonical `origin/main` tree was verified equal to the reviewed
candidate tree.

    candidate commit: 1de0c1ef3fdba889e49f630e6f8710d3bd8dda30
    canonical merge: fbce3d4ba31253663bd625109ff65267a3818111

No private geometry, local alias, coordinate, credential, hostname or ObjectID
was included.

## 3. Standalone Publication

The general manifest-driven publisher transferred the exact 42-file Navimow
distribution through standalone pull request 4. The pull request changed only:

- the productive Local Map renderer;
- the deterministic fileset hash;
- the deterministic source map.

The standalone repository has no configured CI checks. This remained distinct
from the passing SAEF CI and publisher verification. The publisher integrated
the exact pull-request head through a merge commit and verified the complete
remote tree.

    previous standalone commit: 783b37dbdce13dcbddd738a82fbba76bd72d7c86
    publication head: 8c987a1b31631ed80735d6450122bd83428af5d8
    standalone merge: 1434680254f970cc2488171c050c96d88a1afe0d
    fileset SHA-256: b900e590c1cb0a0ca5697066e1c85f05d5ca548b0cd2c988249061b48a205cdd
    publication SHA-256: 3c42c2686d00d23353ac1c98d52bb888493261debe83737131cde6cea0b0e80d

## 4. Metadata Conformance

All 13 metadata inputs remained byte-identical to the preceding officially
validated standalone publication. They were executed again against the same
unmodified official schemas with AJV 6.10.2, bound to the new clean standalone
merge commit.

| Schema | Inputs | Passed | Failed |
| --- | ---: | ---: | ---: |
| library | 1 | 1 | 0 |
| module | 4 | 4 | 0 |
| form | 4 | 4 | 0 |
| locale | 4 | 4 | 0 |
| **Total** | **13** | **13** | **0** |

No metadata file changed in this release.

## 5. Private Package Revision

A create-once private transformer derived a new accepted package from the
retained active package. It required exactly one old-label match and proved
structural equality of the geometry before and after the change.

The productive offline runtime then validated the revised package with:

- unchanged accepted geometry revision;
- exactly four bindings;
- Dark theme and hidden Zone 4 label;
- bounded active-content-free SVG output;
- exactly one compact legend.

Historical private evidence was not rewritten. The prior package and the
byte-exact pre-mutation Device configuration remain retained as rollback
material.

## 6. Controlled Live Mutation

Symcon MCP was available and was the only live channel. The unchanged active
map postflight first proved:

- exact previous standalone commit on `main`;
- clean and valid repository;
- active Account, Configurator, Device and Receiver;
- inactive MQTT and WebSocket Core instances;
- disabled position and MQTT diagnostics;
- absent Authorization and MQTT credentials;
- operational authentication and REST;
- unchanged 14-variable identity and Archive contracts;
- the exact previous active map package.

A separate read-only operation retained the byte-exact Device configuration.
The hash-bound runner then performed exactly:

    MC_UpdateModule: 1
    module reload: 0
    Device ApplyChanges: 1
    Local Map refresh: 1
    rollback: 0

The runner changed only the installed module revision and the accepted private
map package containing the revised display label. It made no Account, MQTT,
OAuth or mower mutation.

## 7. Immediate And Delayed Verification

Independent immediate and delayed read-only postflights both passed with:

    transportError: null
    executionError: null
    truncated: false

Both observed:

- exact standalone merge on branch `main`;
- clean and valid module repository;
- unchanged instance status topology;
- MQTT disabled and credential-free;
- stable REST and authentication;
- all 14 established variable identities unchanged;
- all user-owned Archive logging and aggregation unchanged;
- exactly one legend and one productive station marker;
- the shortened label exactly once and the previous label absent;
- the existing visualization link still visible and correctly targeted;
- no active or external SVG content.

The rendered SVG hash and variable update timestamp remained stable between
the immediate and delayed checks. The previously proven 300-second timer
lifecycle was not repeated because this release changes only rendering and
presentation data, not timer ownership or scheduling.

## 8. Architecture Decisions

### AD-NAV-379-01: Keep display aliases outside public artifacts

**Decision:** Apply the requested alias only through the retained private map
binding and refer to it generically in public evidence.

**Reason:** User-facing labels are installation data and do not belong to the
reusable module distribution.

### AD-NAV-379-02: Combine closely coupled rollout mutations once

**Decision:** Perform the module update and private label revision in one
preconditioned runner with one Device `ApplyChanges()` and one refresh.

**Reason:** The label requires the new renderer to produce the accepted final
view. One bounded runner reduces partially applied states and repeated approval
without weakening rollback or postflight evidence.

### AD-NAV-379-03: Preserve statistics honesty

**Decision:** Keep MQTT, path and zone-statistics collection disabled during
this presentation release.

**Reason:** A legend explains potential layers but does not establish the
evidence needed for mowed-area statistics.

## 9. Gate Status

| Gate | Status |
| --- | --- |
| complete offline verification | passed |
| SAEF PR and merge | passed |
| standalone PR and merge | passed |
| metadata conformance | passed, 13 of 13 |
| byte-exact rollback backup | retained privately |
| module update | passed once |
| private display-label revision | passed once |
| immediate read-only postflight | passed |
| delayed read-only postflight | passed |
| existing variables and Archive logging | preserved |
| MQTT activation | not performed |
| OAuth action | not performed |
| mower command | not performed |

## 10. Next Step

The presentation gate is closed. Define the additive zone-statistics variable
contract before implementing variables. Keep pass progress, observed area and
future calibrated geometric coverage as distinct metrics, and preserve the
existing 14 variable identities and user-owned Archive configuration.
