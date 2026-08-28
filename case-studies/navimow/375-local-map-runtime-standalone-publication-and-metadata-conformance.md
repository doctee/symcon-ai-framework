# 375 Local Map Runtime Standalone Publication And Metadata Conformance

**Case study:** Navimow native IP-Symcon module

**Status:** Exact standalone publication and metadata conformance passed;
disabled Symcon rollout recorded separately

**Date:** 2026-08-28

## 1. Result

The generic manifest-driven publisher transferred the canonical 42-file
Navimow distribution to the dedicated standalone repository through pull
request #3 and verified the complete merged tree.

    canonical SAEF main: 13e9b6dd601ccda70b4546c019e249ada015ecf7
    standalone baseline: 790f6106c160130bb1931eb3e45f8c027ea9d772
    publication PR head: 28f16689f048fc6e12d088bddb55b78e93525bd6
    standalone merge: 783b37dbdce13dcbddd738a82fbba76bd72d7c86
    changed standalone paths: 13
    published inventory: 42
    standalone checks configured: 0

No direct push to standalone `main`, force push, tag or release was used.

## 2. Immutable Publication Contract

The publisher recomputed and required these exact values:

    filesetSha256
    a89dd1cce971342093cf70520f9d9e626106acbc0a2180dbeea192c78684c826

    publicationSha256
    c3e61fc36468728845db08ea462e2ec4ddd264b52d5534c9f29f48a0d3ef1633

The remote base, PR head, pull-request number, integration confirmation and
both hashes were explicit mutation preconditions. The generic publisher then
cloned and verified standalone `main` after the merge.

The 13 changed paths are limited to:

- Device and Account integration needed by the local-map runtime;
- Device form and locale metadata;
- six bounded map, path and zone helper classes;
- the reused SAEF configuration-hash helper;
- deterministic fileset sidecars.

No private geometry, coordinate, device identity, credential, hostname,
ObjectID or capture output is part of the publication.

## 3. Validation Before Publication

The exact canonical candidate passed:

    complete Navimow offline suite: PASS
    REST and authentication checks: PASS
    receive-only MQTT checks: PASS
    geometry, path and zone reducers: PASS
    local-map lifecycle and restart checks: PASS
    variable-stability checks: PASS
    distribution and fileset checks: PASS
    PHPCS: PASS
    PHPStan: PASS, no errors
    generic publication check: PASS, no mutation

The release worktree was clean and based directly on canonical `origin/main`.
The primary checkout's lock-identical Composer installation supplied tools
only; no source crossed worktree boundaries.

## 4. Metadata Conformance

The official Module Validator UI could not be operated because no browser
binding was available in the current Codex session. No browser, Computer Use
or another live-system fallback was substituted.

The established exact-schema fallback downloaded fresh, unmodified assets
from the official validator page and its referenced URLs. The page still
references AJV 6.10.2 and `/assets/files/validation/<type>Schema.json`.

| Asset | SHA-256 |
| --- | --- |
| validator page | `9e4ba1a35d8da4407272b3439b5e9af7519879b96519e97835f5b10e873f6622` |
| library schema | `6e665dadeedfca891c9eabd6f74d03bce2bb477f6d88bba90903919b9a9bb16a` |
| module schema | `7d628bbd57b20112f63f7a439355beeecb26a0bf441ac8d92528c1e63dca3fa4` |
| form schema | `b06a1090d42e42d703e3b97bebb00f1706b4f33cf8e85781e62e154cddfe52f7` |
| locale schema | `fe013b9036f1c29f9ec76f02f760168fb63b58b4ad035529d9fbd0d50b48f3b2` |
| AJV 6.10.2 | `25ed94e422941346a247a08672ac1fce9702728df86fa788e4ae0ca8d6ff0549` |

All 13 metadata inputs from exact standalone commit `783b37d` passed their
official schema with empty error arrays:

| Schema | Inputs | Passed | Failed |
| --- | ---: | ---: | ---: |
| library | 1 | 1 | 0 |
| module | 4 | 4 | 0 |
| form | 4 | 4 | 0 |
| locale | 4 | 4 | 0 |
| **Total** | **13** | **13** | **0** |

The unavailable UI is recorded as environment unavailability, not as a
candidate pass or failure. The metadata gate passes through the established
fresh official-schema execution.

## 5. Architecture Decisions

### AD-NAV-375-01: Use the generic publisher end to end

**Decision:** Perform both PR publication and integration through the common
manifest-driven tool.

**Reason:** Its immutable remote, fileset, publication and PR-head gates avoid
the former manual copy and merge sequence.

### AD-NAV-375-02: Keep validator channels distinguishable

**Decision:** Report the unavailable browser UI separately from the successful
official-schema execution.

**Reason:** Tool unavailability is neither metadata rejection nor permission
to overstate an unexecuted UI result.

### AD-NAV-375-03: Preserve runtime gates

**Decision:** Treat standalone source availability as independent from Symcon
installation and feature activation.

**Reason:** Published default-disabled code cannot access credentials, MQTT or
the mower until a separately controlled live operation occurs.

## 6. Gate Status

| Gate | Status |
| --- | --- |
| canonical SAEF candidate | passed |
| generic publication preflight | passed |
| standalone PR #3 publication | passed |
| standalone PR #3 integration | passed |
| complete remote-tree verification | passed |
| official UI execution | unavailable, no candidate result |
| fresh official-schema execution | passed 13 of 13 |
| metadata conformance | passed through established fallback |
| disabled Symcon rollout | recorded in step 376 |
| local-map activation | closed |

## 7. Next Step

Record the already authorized disabled Symcon rollout of exact standalone
commit `783b37d`, including two equal preflights, the single supported update,
immediate and delayed read-only evidence and the explicit retention decision.
