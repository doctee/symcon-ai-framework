# 353 Map Source Readiness And Offline Geometry Reducer

**Case study:** Navimow native IP-Symcon module

**Status:** Offline reducer complete; MQTT map probe rejected; private REST
live gate deferred

**Date:** 2026-08-27

## 1. Objective And Boundary

This step determines whether the logical MQTT label `mapChange` can support a
bounded geometry capture and prepares only transport-neutral offline geometry
processing.

The step performs no Symcon read or mutation, OAuth action, private-cloud
login, MQTT activation, mower command or module publication. Public artifacts
contain synthetic geometry only.

## 2. Fixed Upstream Evidence

The static review fixes these source revisions:

- `TA2k/ioBroker.navimow`:
  `516986b0a5d91d05c705e888209c0463e205f015`;
- `ilguala/navimow_pro`:
  `f25f418224681f67e2ad68693cded6c17b11dbe6`.

The first source uses the official Smart Home REST and MQTT paths. The second
source is an unofficial interoperability implementation of the private mobile
app cloud. These are different trust, authentication and stability boundaries.

## 3. MQTT MapChange Finding

The MQTT credential response contains logical subscription labels named
`realtime` and `mapChange`. A logical label is not an exact broker topic.

The current ioBroker implementation subscribes only to four exact per-device
downlink topics:

```text
state
event
attributes
location
```

Its source contains no `mapChange` handler or additional exact map topic. The
existing SAEF transport deliberately uses the same four-topic allowlist and no
wildcards.

Consequently, a bounded `mapChange` probe cannot currently satisfy the SAEF
exact-topic requirement. Adding the logical label as a topic or using a
wildcard would be guesswork and could expose unrelated private traffic.

**Decision:** An MQTT `mapChange` live probe is **NO-GO** until an exact topic
and payload contract are independently proven.

## 4. Private REST Geometry Finding

The current `navimow_pro` source obtains map geometry from the private app
cloud, not from the four official MQTT realtime topics. Its structural flow is:

```text
get-location or map-list
  -> map id and map base id
  -> map-detail
  -> decoded map_detail object
```

An optional compressed map-detail path is a fallback. The decoded object
contains:

- `sub_maps` with zone ids, names, areas and elements;
- `BOUNDARY` point lists with optional segment attributes;
- an optional `CHARGING_PILE` in the map world frame;
- obstacle point lists;
- VisionFence-off point lists.

Per-zone progress is obtained separately from a trail information endpoint.
The source also states that exact swept-stripe geometry is not available on
its reference firmware and reconstructs a trail from sampled positions.

This is the first concrete manufacturer-map-shaped geometry contract found in
the case study. It is not part of the official Smart Home API and is not yet
approved for productive SAEF transport use.

## 5. Security And Stability Classification

Using the private app cloud would introduce a new authentication stack and
risk boundary:

- account login and refresh tokens distinct from the existing OAuth flow;
- a persistent app device identity whose replacement may disturb sessions;
- reverse-engineered encrypted request envelopes and app-wide constants;
- undocumented endpoints that may change without notice;
- map, home and garden geometry with higher privacy impact;
- a possible conflict with the official app session when the primary account
  is reused.

A future private REST experiment therefore requires its own feasibility,
legal/terms, credential-isolation, dedicated-account and cleanup decisions. It
must not be smuggled into the existing official REST/MQTT account module.

## 6. Offline Geometry Contract

`candidate/MapGeometryReducer.php` accepts only an already decoded
`map_detail` object or JSON string. It contains no endpoint, authentication,
encryption, compression or network code.

The reducer:

- bounds decoded JSON to 4 MiB;
- accepts at most 32 zones, 128 elements per zone and 8,192 total points;
- accepts at most 1,024 points per ring;
- validates finite bounded local coordinates;
- requires a stable integer zone id and one boundary per zone;
- removes consecutive duplicate points and closes every ring;
- rejects self-intersecting or zero-area rings;
- preserves boundary segment flags separately from coordinates;
- projects optional obstacles, VisionFence-off areas and charging station;
- reports both source area and calculated polygon area;
- declares holes unsupported instead of silently flattening them.

The output remains `decoded-private-map-payload` authority in the
`navimow-local-map` frame. It does not claim WGS84, metric calibration or
geometric mowing coverage.

## 7. Synthetic Regression Evidence

The public fixture contains two synthetic zones, one obstacle, one
VisionFence-off area and a synthetic charging station. Tests prove:

- object and JSON-string detail inputs produce the same projection;
- open and already closed source rings normalize identically;
- polygon area and aligned boundary flags are retained;
- malformed JSON is rejected;
- self-intersecting polygons are rejected;
- out-of-range coordinates are rejected;
- over-limit zone lists are rejected;
- no private labels, device identities or topics enter the projection.

The reducer is an analysis candidate only. It is not copied into the Navimow
distribution or standalone module publication fileset.

## 8. Architecture Decisions

### AD-NAV-353-01: Do not infer broker topics from logical labels

**Decision:** Keep the exact four-topic allowlist unchanged.

**Reason:** The logical `mapChange` label is not a verified topic and no
upstream handler proves its broker path.

### AD-NAV-353-02: Separate official and private cloud transports

**Decision:** Treat private app-cloud feasibility as a new gated workstream,
not an extension of the existing OAuth/MQTT client.

**Reason:** Authentication, credentials, stability and privacy differ
materially from the existing official Smart Home contract.

### AD-NAV-353-03: Reduce decoded geometry before transport adoption

**Decision:** Validate geometry offline against synthetic input before any
private live payload is requested.

**Reason:** Geometry correctness and boundedness can be proven independently
of credentials and undocumented network behavior.

## 9. Gate Decision And Next Step

| Gate | Result |
|---|---|
| Exact MQTT map topic | **FAIL / unknown** |
| MQTT `mapChange` live capture | **NO-GO** |
| Transport-neutral geometry reducer | **PASS offline** |
| Productive module integration | **NO-GO** |
| Private app-cloud feasibility analysis | **READY for separate design gate** |
| Manual calibrated polygon fallback | **Still available** |

The next Navimow step should compare two paths without live mutation:

1. a private app-cloud **feasibility and credential-isolation analysis** based
   on the fixed source revision; and
2. the lower-risk manual calibration fallback from step 351.

Only that comparison may recommend a separately authorized, read-only private
map capture. No MQTT map activation is warranted by current evidence.
