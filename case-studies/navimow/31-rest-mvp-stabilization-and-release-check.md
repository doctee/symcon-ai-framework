# 31 REST MVP Stabilization and Release Check

**Case study:** Navimow native IP-Symcon module
**Status:** Private pilot ready; broader release not yet ready
**Date:** 2026-07-09
**Scope:** REST MVP stabilization, release boundary and remaining gates

## 1. Purpose

This document reviews the REST MVP after the successful direct Symcon live
transition test in `30-dock-transition-verification-live-test.md`.

It answers:

- what is implemented and evidence-backed;
- what is safe enough for continued private pilot use;
- what blocks a broader public release;
- which stabilization steps should happen before versioning or wider testing.

No new productive PHP code is introduced in this step.

## 2. Current MVP Scope

The REST MVP currently includes:

| Area | Status | Evidence |
| --- | --- | --- |
| Module metadata and loader structure | implemented | Symcon module load passed |
| OAuth authorization-code flow | implemented | direct Symcon OAuth test passed |
| Token refresh | implemented | direct Symcon auth test passed |
| Account discovery | implemented | direct Symcon discovery test passed |
| Dynamic configurator | implemented | direct Symcon configurator test passed |
| Read-only device status | implemented | fixture and direct Symcon tests passed |
| Dock command while already docked | implemented | direct Symcon test passed |
| Dock command from running state | implemented | direct Symcon live transition passed |
| Long-running Dock verification | implemented | `Docking -> Verified` live evidence |

The MVP intentionally excludes:

- Start;
- Stop;
- Pause;
- Resume;
- MQTT/WSS;
- location/map data;
- multi-device live transition evidence;
- Symcon Store submission.

## 3. Current Publication State

Dedicated module repository:

```text
https://github.com/doctee/symcon-navimow
```

Current published module commit:

```text
a6178dc feat: add long-running Dock verification
```

The current publication is suitable for controlled private testing. It should
not yet be treated as a polished public release.

## 4. Local Verification Run

Before this release check, the local validation suite was rerun:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
```

Observed result:

| Check | Result |
| --- | --- |
| REST/Auth/fixture/static checks | passed |
| Distribution structure validator | passed |

The validator confirms that the installable distribution root remains
structurally loadable by Symcon.

## 5. Direct Symcon Evidence Summary

Direct Symcon evidence now covers:

| Step | Evidence |
| --- | --- |
| Loader | Navimow library and three module types visible |
| OAuth | official login handoff succeeded |
| Discovery | account discovered the mower |
| Configurator | device instance could be created/used |
| Status refresh | `Docked`, `Running`, `Docking` and final `Docked` observed |
| Dock command | `Already In State` and `Accepted` paths observed |
| Verification | `Pending Verification` during `Docking`, then `Verified` |

This is enough to call the REST Dock path MVP-ready for private pilot use.

## 6. Release Decision

### Decision

The REST MVP is approved for continued private pilot use and controlled
testing from the dedicated module repository.

It is not yet approved for broad public release or Symcon Store submission.

### Rationale

The core user journey is now evidence-backed:

```text
OAuth -> discovery -> status refresh -> Dock command -> read-only verification
```

The module also preserves the main safety boundary:

- Dock is the only enabled command;
- exactly one Dock command is sent per user action;
- verification uses read-only status calls;
- `Docking` is treated as progress;
- non-Dock commands remain blocked.

Broader release still needs user-facing hardening and packaging decisions.

## 7. Release Blockers

The following items block a broader public release:

| Blocker | Impact | Required action |
| --- | --- | --- |
| OAuth client-secret distribution unresolved | Public users need a lawful and supportable auth setup | Decide documented private-client flow, user-provided credentials, or another supported model. |
| Public README is minimal | Users cannot install, authorize and operate safely from docs alone | Add installation, OAuth, update, safety and troubleshooting instructions. |
| Version metadata is still `0.1`, build/date `0` | Release traceability is weak | Define version/tag/build/date policy before tagging. |
| `.DS_Store` files exist in the local working tree | Commit hygiene risk | Ignore or remove local OS metadata before committing/release tagging. |
| Symcon Store compatibility not reviewed | Store submission could fail metadata or policy checks | Run a dedicated store-readiness review later. |
| Timeout and restart scenarios are not live-tested | Edge cases remain theoretical | Keep as known limitations for MVP, test before wider rollout. |

## 8. Known Limitations for Private Pilot

Private pilot notes should explicitly say:

- Dock is the only supported mower command.
- Start, Stop, Pause and Resume are deliberately disabled.
- MQTT/WSS and location/map updates are not implemented.
- Status is REST-polled and may lag behind the official app.
- Dock verification waits up to 15 minutes.
- A timeout means that `Docked` was not verified in time; it does not prove
  that the mower physically failed.
- The implementation depends on an undocumented Navimow cloud API and may need
  adjustment if Segway changes the API.

## 9. Stabilization Work Items

Recommended stabilization items before a public release tag:

1. Add `.DS_Store` to ignore rules or otherwise remove local OS metadata before
   committing.
2. Update `distribution/README.md` with user-facing installation and safety
   guidance.
3. Add a concise public limitations section.
4. Decide whether release metadata remains `0.1` or moves to an explicit
   pre-release version.
5. Set non-zero build/date metadata only when a real release tag is created.
6. Add a manual update procedure for private Git-based Symcon installation.
7. Document that OAuth credentials and captured payloads must never be shared.
8. Keep non-Dock commands absent until separate command-specific evidence
   gates pass.

## 10. Diagnostics Review

The current public variables are adequate for the MVP:

- `VehicleState`;
- `Online`;
- `BatteryLevel`;
- `LastStatusUpdate`;
- `LastCommand`;
- `LastCommandAt`;
- `LastCommandResult`;
- `LastCommandError`.

Long-running verification metadata remains internal. This is still the right
choice for MVP because the public result variable already shows the user-facing
phase:

```text
Accepted -> Pending Verification -> Verified
```

Do not expose internal verification attributes unless repeated pilot feedback
shows that users need that detail.

## 11. Security and Privacy Review

The public case-study and distribution must continue to exclude:

- OAuth access tokens;
- refresh tokens;
- authorization codes;
- client secrets;
- private device IDs;
- private ObjectIDs;
- local paths;
- raw payloads;
- garden or map data.

The fixture workflow now includes command-number sanitization
(`COMMAND_001`) and device placeholder IDs (`DEVICE_001`).

The release check does not identify a new public data leak, but it does
identify local `.DS_Store` files as commit-hygiene noise.

## 12. Architecture Decisions

### AD-NAV-055: REST MVP is private-pilot ready

**Decision:** Approve the REST MVP for controlled private pilot use.

**Rationale:** The core REST path is implemented, fixture-backed and directly
tested in Symcon, including the live Dock transition.

**Consequence:** Work can shift from feature proof to packaging, documentation
and release hygiene.

### AD-NAV-056: Broad release remains blocked

**Decision:** Do not declare the module broadly release-ready yet.

**Rationale:** Public installation and OAuth setup are not yet documented well
enough, and version/build/tag policy is still unresolved.

**Consequence:** The next work should stabilize packaging and documentation
rather than adding more mower commands.

### AD-NAV-057: Do not expand command scope before MVP stabilization

**Decision:** Keep Start, Stop, Pause and Resume disabled through the release
stabilization phase.

**Rationale:** Dock is now evidence-backed. Other commands have different
physical safety semantics and need separate live evidence.

**Consequence:** The next release-oriented work remains documentation and
hardening, not command expansion.

## 13. Recommended Next Step

Create a dedicated release-preparation step:

```text
32-private-pilot-release-preparation.md
```

That step should implement the non-functional stabilization items:

- ignore or remove local `.DS_Store` noise;
- improve `distribution/README.md`;
- document private Git installation/update;
- document OAuth setup and safety limitations;
- define version/tag policy for the next module publication.
