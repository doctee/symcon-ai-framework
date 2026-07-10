# 32 Private Pilot Release Preparation

**Case study:** Navimow native IP-Symcon module
**Status:** Private pilot release preparation completed locally
**Date:** 2026-07-09
**Scope:** Release hygiene, pilot documentation and version policy

## 1. Purpose

This step implements the non-functional stabilization items identified in
`31-rest-mvp-stabilization-and-release-check.md`.

The goal is to make the current REST MVP easier and safer to use as a private
pilot module without expanding its functional scope.

No new mower command, REST endpoint, MQTT/WSS behavior or PHP runtime feature
is introduced in this step.

## 2. Changes Made

Changed files:

| File | Change |
| --- | --- |
| `.gitignore` | Added `.DS_Store` to prevent local macOS metadata from entering commits. |
| `case-studies/navimow/distribution/README.md` | Replaced scaffold text with private-pilot installation, OAuth, safety, limitation and privacy guidance. |
| `case-studies/navimow/README.md` | Added this step to the case-study index and updated status. |

The distribution README now explains:

- current private-pilot status;
- implemented and excluded features;
- private Git installation/update path;
- supervised OAuth notes;
- safe Dock command use;
- long-running Dock verification behavior;
- known limitations;
- privacy boundaries.

## 3. Release Hygiene

### `.DS_Store`

Local `.DS_Store` files were present in the working tree, including below the
case-study distribution directory.

**Decision:** Ignore `.DS_Store` globally in this repository.

**Rationale:** These files are local operating-system metadata and have no
engineering value in SAEF artifacts or the installable Symcon distribution.

**Consequence:** Existing untracked `.DS_Store` files become harmless local
noise for Git status. They should still not be copied into the dedicated
module repository when publishing.

## 4. Private Pilot Documentation

The distribution README is now user-facing enough for controlled private pilot
use.

It deliberately avoids:

- pretending the module is an official Navimow product;
- claiming Symcon Store readiness;
- documenting unsupported Start, Stop, Pause or Resume usage;
- exposing private OAuth details;
- including raw payloads or private installation identifiers.

## 5. OAuth Position

For the private pilot, OAuth remains a supervised setup flow.

Pilot guidance:

- credentials are installation-specific;
- client secrets, authorization codes, access tokens and refresh tokens must
  not be shared;
- if authentication fails, re-run the supervised authorization flow;
- public credential distribution remains unresolved.

This keeps the private pilot usable while preserving the broader release
blocker from step 31.

## 6. Version and Tag Policy

Current metadata remains:

```text
version: 0.1
build: 0
date: 0
```

**Decision:** Do not change version/build/date in this step.

**Rationale:** The current work is still private-pilot preparation, not a
formal public release tag.

Recommended policy for the next publication milestone:

| Milestone | Version metadata | Git tag |
| --- | --- | --- |
| current private pilot | keep `0.1`, build/date `0` | no release tag required |
| first named private pilot | `0.1.0-pilot.1` or equivalent documented scheme | optional annotated tag |
| public pre-release | explicit pre-release version | required tag |
| Symcon Store candidate | stable version/build/date aligned with store requirements | required tag |

The exact metadata format should be checked against Symcon module conventions
before a public or store-oriented release.

## 7. Safety Boundary

The safety boundary remains unchanged:

- Dock is the only enabled mower command.
- One Dock command is sent per user action.
- Verification is read-only after command acceptance.
- `Docking` is valid progress.
- Timeout is an uncertainty result, not proof of physical failure.
- Start, Stop, Pause and Resume remain disabled.

This is documented in the distribution README to prevent private pilot users
from assuming unsupported command coverage.

## 8. Release Readiness After This Step

| Area | Status after step 32 |
| --- | --- |
| Private pilot install documentation | improved |
| OAuth privacy warning | documented |
| Dock safety guidance | documented |
| Known limitations | documented |
| `.DS_Store` Git hygiene | improved |
| Version/tag policy | proposed, not finalized |
| Public release readiness | still blocked |
| Symcon Store readiness | still blocked |

The module remains suitable for controlled private pilot use from the
dedicated repository. It is still not ready for broad public release.

## 9. Remaining Blockers

Still open before broader release:

1. final OAuth client setup/distribution decision;
2. formal version/build/date policy;
3. Symcon Store compatibility review;
4. restart-during-verification test;
5. timeout behavior test or documented simulation;
6. broader user-facing troubleshooting guidance;
7. optional release tag once policy is decided.

## 10. Architecture Decisions

### AD-NAV-058: Pilot documentation before feature expansion

**Decision:** Improve private-pilot documentation before adding additional
mower commands.

**Rationale:** The Dock REST MVP is now functional. The next risk is
misoperation or misunderstanding, not missing command surface.

**Consequence:** Command expansion remains blocked while release preparation
continues.

### AD-NAV-059: Keep release metadata unchanged during preparation

**Decision:** Leave `library.json` version/build/date unchanged in this step.

**Rationale:** Documentation and hygiene changes do not by themselves define a
formal release milestone.

**Consequence:** A later explicit release step must decide metadata and tags.

### AD-NAV-060: Treat private pilot as controlled distribution

**Decision:** The module can continue to be distributed through the dedicated
Git repository for controlled pilot use.

**Rationale:** Direct Symcon evidence supports the MVP path, but public OAuth,
store and packaging questions remain open.

**Consequence:** The recommended next steps should focus on release mechanics
and operational hardening.

## 11. Recommended Next Step

Create:

```text
33-release-metadata-and-tag-plan.md
```

That step should decide:

- version string;
- build/date handling;
- tag naming;
- whether to publish another module commit containing the updated README;
- whether the current private pilot needs an annotated Git tag.
