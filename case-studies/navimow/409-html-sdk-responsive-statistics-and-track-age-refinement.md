# SAEF Step 409: HTML SDK Responsive Statistics and Track-Age Refinement

- **Date:** 2026-09-16
- **Status:** Implemented and fully verified offline; publication and live
  rollout remain closed
- **Scope:** Navimow Local Map presentation and read-only statistics projection

## 1. Purpose

Physical use on smaller iPhone and iPad visualization surfaces exposed six
presentation defects after the fixed-size legend rollout:

- the navigation occupied too much horizontal space;
- the bottom statistics strip covered part of the map;
- an older current position had no exact time indication;
- retained paths did not communicate their relative age;
- zone labels were too prominent in Dark Skin; and
- the statistics strip remained empty when mowing analytics were disabled or
  had not yet produced a compatible run projection.

This step corrects those defects without changing accepted geometry, retained
path data, REST authority, receive-only MQTT transport or mower commands.

## 2. Implementation

### 2.1 Responsive navigation and map viewport

The navigation remains a single compact row and uses smaller deterministic
button and selector tracks. Narrow and coarse-pointer breakpoints reduce the
control footprint while retaining usable touch targets. Its upper edge remains
at `52px`, preserving the established 46-pixel Safari and iPad host-interaction
boundary plus a six-pixel gap at every viewport width.

The statistics strip now reports its rendered height through one CSS custom
property. The map surface is bounded above that reserved height, so fitting,
zooming and fixed-size legend placement operate only inside the actually
available map viewport. The statistics strip is therefore part of layout
rather than an overlay over zone geometry.

### 2.2 Position timestamp

The passive SVG contains an empty, escaped timestamp placeholder beside a
visible mower outside the docked state. The HTML SDK resolves the retained
`receivedAt` timestamp in browser-local time and shows `HH:MM` only when the
position is more than 60 seconds old. It updates every 15 seconds without a
server request.

The existing freshness halo remains the semantic warning. The time label adds
precision and does not reinterpret transport or device state.

### 2.3 Qualitative path-age steps

Every retained path point keeps its existing timestamp. The renderer derives
four relative age classes across the currently retained sequence:

| Class | Meaning | Dark-Skin presentation |
|---|---|---|
| `path-age-0` | newest retained quarter | near white |
| `path-age-1` | recent | light gray |
| `path-age-2` | older | medium gray |
| `path-age-3` | oldest retained quarter | dark gray |

Adjacent points remain connected at age-class transitions. No geometry,
sampling, retention or area calculation changes. The legend shows the same
four-step direction from old to new.

### 2.4 Statistics presentation fallback

The existing analytics contract remains unchanged. A separate bounded
visualization projection now combines:

- ordinary revision-compatible zone pass progress and observed area; and
- optional analytics recency, run coverage and weekly estimated area.

When analytics data are available, the strip shows their richer values. When
they are absent, ordinary progress and observed area still produce useful
cards. This is deliberately a presentation projection, not a second analytics
state or persistence owner.

### 2.5 Zone-label contrast

Dark-Skin zone labels use a softer neutral foreground, reduced opacity and a
thinner contrast stroke. Warning outlines, zone identity and percentage text
remain unchanged.

## 3. Architecture Decisions

### AD-NAV-409-01: Reserve layout space for statistics

**Decision:** Resize the map surface by the measured statistics height instead
of compensating only when positioning the legend.

**Reason:** Every map layer, fit operation and interaction must share one
truthful viewport. Overlay-specific offsets would continue to let zones or
markers disappear behind the strip.

### AD-NAV-409-02: Keep analytics and presentation statistics separate

**Decision:** Add a non-persistent visualization projection rather than making
the optional mowing-analytics reducer mandatory.

**Reason:** Existing task-progress statistics are useful before geometric
coverage analytics are enabled. Reusing them in the tile avoids empty UI while
preserving reducer ownership and compatibility gates.

### AD-NAV-409-03: Derive path age without mutating retained evidence

**Decision:** Assign four renderer-only classes from the oldest and newest
timestamps in the retained path set.

**Reason:** The request is qualitative. Relative steps remain meaningful for
any configured retention window and require no new state, migration or clock
policy.

### AD-NAV-409-04: Render position time only outside the docked state

**Decision:** Do not emit the timestamp placeholder for a docked mower.

**Reason:** Position age is operationally useful while the mower is away. At
the station it would add persistent visual noise without helping movement
diagnosis.

## 4. Verification

Focused offline checks prove:

- all four path-age classes and their legend representation;
- preserved one-point path segments and boundary continuity;
- timestamp presence for non-docked states and absence for the docked state;
- the 60-second display threshold and local-time formatting;
- useful statistics with analytics both enabled and disabled;
- a statistics-height-reserved map surface;
- compact navigation contracts for normal, narrow and coarse-pointer clients;
- preservation of the 52-pixel Safari and iPad interaction offset;
- unchanged local-map statistics variables; and
- valid PHP and JavaScript syntax.

Controlled synthetic browser previews at desktop, iPad and iPhone viewport
sizes confirm that the compact navigation remains on one row and the populated
statistics strip no longer overlays the map surface. The preview contains no
private installation geometry or identifiers.

## 5. Safety and Mutation Boundary

This step performs no live or external mutation:

- no standalone module publication or pull request;
- no `MC_UpdateModule()`, `ApplyChanges()` or module reload;
- no OAuth, token or MQTT-credential action;
- no MQTT activation, restart or device command; and
- no change to existing Symcon variables, Archive logging or ObjectIDs.

## 6. Gate Status

| Gate | Status |
|---|---|
| Local implementation | PASS |
| Focused renderer and Device checks | PASS |
| Responsive synthetic browser review | PASS |
| Generated fileset and full repository validation | PASS |
| SAEF commit, push and pull request | CLOSED |
| Standalone publication | CLOSED |
| Symcon update and physical-client validation | CLOSED |

## 7. Next Gate

Review the exact candidate diff and, after separate authorization, create the
SAEF commit and pull request. Standalone publication and live rollout remain
separately authorized gates.
