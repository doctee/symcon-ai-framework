# 354 Private Map Cloud Versus Manual Calibration Feasibility

**Case study:** Navimow native IP-Symcon module

**Status:** Offline comparison complete; manual baseline recommended; private
capture remains separately gated

**Date:** 2026-08-27

## 1. Objective And Boundary

This step compares two sources for Navimow zone geometry:

1. manufacturer-shaped geometry obtained through the undocumented private app
   cloud; and
2. installation-owned polygons created through manual calibration.

The comparison targets the future track map, current-position display and
per-zone coverage statistics. It does not perform a private-cloud login,
Symcon read or mutation, OAuth action, MQTT activation, mower command, payload
capture, module publication or standalone publication.

The fixed upstream revision and the transport-neutral reducer from step 353
remain the evidence boundary. No third-party implementation is copied into
SAEF.

The reviewed upstream repository is MIT-licensed. This step records observable
contracts and risks only; it neither imports its implementation nor treats its
license as vendor authorization for the private cloud.

## 2. Required Product Outcomes

The selected geometry path must eventually support:

- stable zone identity and private display names;
- closed zone polygons and explicit excluded areas;
- correlation with Navimow local X/Y positions;
- a defensible area denominator per zone;
- current position and bounded historical mowing tracks;
- independent validation after map edits or coordinate-frame changes;
- future provider-neutral presentation without applying Navimow assumptions to
  OwnTracks WGS84 data.

Neither source alone currently proves actual swept mowing coverage. Position
samples remain center-line observations, and percentage calculations require a
separately declared coverage model or authoritative manufacturer progress.

## 3. Option A: Private App-Cloud Geometry

### 3.1 Evidence And Expected Value

At fixed upstream revision
`f25f418224681f67e2ad68693cded6c17b11dbe6`, `navimow_pro` uses the private
mobile-app cloud to obtain:

```text
get-location or map-list
  -> map id and map base id
  -> map-detail
  -> decoded map_detail
```

The decoded structure can contain zone boundaries, boundary segment flags,
obstacles, VisionFence-off areas, charging-station position and reported area.
It is therefore the strongest known candidate for the configured manufacturer
map and should naturally share the Navimow local world frame with position
telemetry.

The separate `get-path-info-time` endpoint can report per-zone finished area
and percentage. It is useful evidence, but it is not required for the first
geometry-only capture and must not be included merely because it is available.

### 3.2 Authentication And Session Surface

This path does not reuse the official Smart Home OAuth credentials. The
reviewed implementation requires:

- account-region discovery from the account email;
- an initial Passport login using email and password;
- Passport access and refresh tokens plus account UUID;
- a persistent, generated app `device_id`;
- private mower-cloud registration through `user/user/login`;
- the resulting private-cloud UID and a fixed regional mower host;
- encrypted private-cloud request and response envelopes.

Changing the app device identity can re-register the session and disturb other
sessions. The upstream explicitly recommends a dedicated second account to
which the mower is shared. That recommendation is a necessary SAEF precondition
for any future live experiment, not evidence that the private protocol is a
supported public interface.

### 3.3 Credential-Isolation Contract

A future private capture may proceed only if all of these conditions are met:

1. A dedicated shared Navimow account is used. The primary app account is not
   accepted for the first experiment.
2. The password is read from hidden interactive input, remains process-local
   and is neither passed as a command-line argument nor written to a file.
3. The generated app device identity is stable for the bounded experiment and
   is never silently regenerated after partial failure.
4. Access token, refresh token, UUID, UID, serial number, hosts and device
   identity are private evidence. None may enter public fixtures, diagnostics,
   logs or Git history.
5. Any temporary token state is stored only under `private/` with owner-only
   permissions and an explicit retention or deletion decision.
6. The private client is not embedded in `NavimowAccount` and does not share
   state with the official OAuth/MQTT transport.
7. The first capture runs outside Symcon. It cannot create Core instances,
   variables, timers or long-lived background sessions.
8. Cleanup is credential-first and independently verified even when login,
   decryption, parsing or sanitization fails.

Local cleanup means removing retained password input, tokens and raw capture
artifacts according to the approved retention decision. No reviewed source
proves a server-side logout, token-revocation or device-deregistration endpoint.
SAEF must therefore not claim that local deletion closes the server-side app
session. If a second capture could be needed, the private device identity must
be retained deliberately; silently generating a replacement is prohibited.

### 3.4 Read-Only Endpoint Allowlist

The smallest plausible first capture is limited to:

```text
GET  /v3/region
POST /v3/user/login
POST /user/user/login
POST /vehicle/vehicle/auth-list
POST /vehicle/vehicle/get-location
POST /map/index/map-list              only if location lacks map ids
POST /map/index/map-detail
```

The compressed map-detail fallback, station-map endpoint, trail endpoints,
settings endpoints and every `/vehicle/set/` path remain outside the initial
allowlist. There is no retry across a changed device identity and no command
path.

### 3.5 Benefits

- highest expected fidelity to the configured mower map;
- zone ids and polygon geometry originate from the same vendor domain;
- obstacles, excluded areas, dock and boundary attributes may be available;
- minimal manual drawing effort after a successful capture;
- potentially direct comparison between reported and calculated zone area.

### 3.6 Risks And Open Conditions

- undocumented protocol and endpoints may change without notice;
- legal, contractual and vendor-support acceptability is unresolved;
- a private account password is required for initial login;
- session/device-identity behavior may affect the official app or other
  integrations if isolation is wrong;
- real garden geometry and home-relative positions are high-impact private
  data;
- response encryption uses reverse-engineered app protocol constants;
- server-side logout, token revocation and device deregistration are not proven;
- map ids, area units, ring orientation, holes and coordinate stability still
  require private evidence;
- a successful one-time capture does not establish operational suitability for
  a continuously polling Symcon module.

These risks block productive integration even if a bounded capture later
succeeds.

## 4. Option B: Manual Calibration And Digitized Polygons

### 4.1 Source And Ownership

Manual geometry is installation-owned configuration. The user places private
control points and draws zone polygons against either:

- a Navimow-local track view; or
- a geographic base map after a local-to-geographic transform is validated.

Drawing directly in the local track frame is the smaller first step. It avoids
external tiles and geocoding and can support Navimow-only zone correlation.
Geographic calibration is needed later when the mower layer must align with a
base map or other WGS84 sources.

### 4.2 Calibration Contract

At least three non-collinear control-point pairs are mandatory. Four or more
are preferred so one point can validate rather than merely fit the transform.

The first model should be a two-dimensional similarity transform containing
translation, rotation and uniform scale. It is accepted only when residuals
remain within a configured installation-specific tolerance. An affine model is
considered only if repeated evidence shows axis skew; it requires additional
control points and a separate distortion warning.

Every calibration records:

- a private calibration revision;
- coordinate-frame identifier and observed dock reference;
- control-point timestamps and freshness;
- fitted model and residual summary;
- polygon revision and source label;
- invalidation reason after map reset, dock relocation or excessive residual.

The implementation must never silently reuse polygons after the local frame
changes.

### 4.3 Area Contract

For polygons drawn in a calibrated metric frame, geometric area can become the
zone denominator. Until scale and residual checks pass, local polygon area is
unitless and must not be displayed as square metres.

Manual polygons may include explicit holes for obstacles or excluded islands.
That extends beyond the step-353 reducer, whose analysis contract deliberately
declares holes unsupported. Hole support therefore remains a later geometry
model gate.

### 4.4 Benefits

- no new vendor credentials, private API or session identity;
- stable ownership independent of upstream endpoint changes;
- transparent corrections and explicit installation revision history;
- suitable as an independent check of future manufacturer geometry;
- controllable privacy and retention boundary;
- available even if the private-cloud path becomes unavailable.

### 4.5 Risks And Costs

- initial control-point and polygon work is manual;
- misplaced control points can create systematic map and area errors;
- boundaries may differ from the mower's actual configured map;
- obstacles and virtual boundaries must be entered separately;
- geographic alignment may involve an external tile-provider privacy decision;
- every physical or mower-map change requires explicit recalibration.

## 5. Comparative Decision Matrix

| Criterion | Private app cloud | Manual calibration |
|---|---|---|
| Expected configured-map fidelity | High, pending evidence | User-dependent |
| Zone ids from mower domain | Expected | Must be mapped explicitly |
| Same local frame as telemetry | Expected, not yet proven | Explicitly fitted |
| Area denominator | Reported and polygon candidates | Valid after metric calibration |
| Obstacles and excluded areas | Candidate fields exist | Manual entry required |
| New credentials | Password plus private tokens | None |
| Session interference | Material risk | None |
| Protocol stability | Undocumented | Installation-owned |
| Legal/vendor clarity | Unresolved | No private API dependency |
| Setup effort | Lower after capture | Higher initially |
| Ongoing resilience | Low to medium | High after validation |
| Independent validation value | Source under test | High |
| Productive readiness now | **NO-GO** | **Design GO only** |

## 6. Selected Architecture

### AD-NAV-354-01: Establish manual geometry as the baseline

**Decision:** Manual calibrated polygons are the first supported geometry
baseline and permanent fallback.

**Reason:** They avoid a new credential and undocumented-protocol boundary and
provide independent evidence against which manufacturer geometry can later be
checked.

This is a design decision, not authorization to create live calibration
objects or modify a visualization.

### AD-NAV-354-02: Admit private geometry only as a separate optional source

**Decision:** A future private-cloud capture, if authorized and successful,
produces an optional geometry snapshot. It does not replace the official REST
authority, own commands or become a continuously running transport by default.

**Reason:** Geometry acquisition and public mower-state authority have
different stability, security and operational requirements.

### AD-NAV-354-03: Preserve source provenance and comparison

**Decision:** Manufacturer and manual polygons retain separate source,
revision, timestamp, coordinate-frame and quality metadata. A later resolver
may select one for presentation but must not merge vertices silently.

**Reason:** Differences are valuable drift and calibration evidence.

### AD-NAV-354-04: Keep OwnTracks outside this transport decision

**Decision:** The future shared renderer may consume normalized layers, but
OwnTracks authentication, WGS84 archives and instance inventory remain in the
separate OwnTracks study.

**Reason:** A common renderer does not justify a common provider transport or
coordinate model.

## 7. Quality Gates For A Future Private Capture

A separate capture plan may be written only after these planning inputs are
explicit:

| Gate | Required state |
|---|---|
| Dedicated shared account | Confirmed available and accepted |
| Primary-account exclusion | Confirmed |
| Terms/vendor risk | Explicitly reviewed and accepted for private experiment |
| Exact endpoint allowlist | Fixed to section 3.4 |
| Password handling | Hidden, memory-only, no shell history |
| Device identity | Generated once, private and stable |
| Token retention | Explicit delete or bounded private retention decision |
| Raw evidence boundary | Private path, owner-only permissions |
| Sanitizer | Field allowlist plus privacy-marker scan |
| Payload limits | Fixed before transport |
| Failure cleanup | Credential-first and independently verifiable |
| Server-side session closure | Limitation acknowledged; no unsupported claim |
| Productive import | Closed regardless of capture success |

No current evidence closes the dedicated-account or terms/vendor-risk gates.

## 8. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Manual calibration architecture | **GO for offline design** |
| Manual live calibration | **NO-GO pending procedure and UI design** |
| Private capture procedure design | **CONDITIONAL GO** |
| Private live login or map capture | **NO-GO** |
| Productive private-cloud client | **NO-GO** |
| Continuous private-cloud polling | **NO-GO** |
| Official REST/MQTT authority change | **REJECTED** |
| Shared Navimow/OwnTracks renderer | **Deferred to separate comparison** |

The recommended next Navimow step is
`355-private-map-capture-security-and-procedure-plan.md`. It may design the
bounded private capture and sanitizer offline, but it must stop before live
login until the dedicated shared account and terms/vendor-risk statements are
provided through a fresh gate.

In parallel, the separate OwnTracks study may continue its read-only contract
inventory. It does not need to wait for a Navimow private-cloud decision.

## 9. Reviewed Upstream Locations

The static conclusions were derived from these locations at the fixed
`navimow_pro` revision:

- `README.md`: private-cloud disclaimer, dedicated-account recommendation,
  session/device-identity warning and map limitations;
- `custom_components/navimow_pro/config_flow.py`: login sequence, stable
  device identity and persisted session fields;
- `custom_components/navimow_pro/api/passport.py`: region discovery, Passport
  login and token refresh;
- `custom_components/navimow_pro/api/client.py`: encrypted call contract,
  authentication recovery and read endpoint methods;
- `custom_components/navimow_pro/coordinator.py`: decoded map-detail projection;
- `custom_components/navimow_pro/diagnostics.py`: upstream redaction boundary;
- `LICENSE`: MIT terms for the reviewed community implementation.

These paths are provenance references, not SAEF runtime dependencies.
