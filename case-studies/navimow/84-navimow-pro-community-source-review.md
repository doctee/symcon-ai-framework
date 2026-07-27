# 84 Navimow Pro Community Source Review

**Case study:** Navimow native IP-Symcon module
**Status:** Community evidence classified; no protocol adoption
**Date:** 2026-07-27
**Scope:** Review `ilguala/navimow_pro` without authentication, device access or implementation

## 1. Purpose

This step reviews the community Home Assistant integration
[`ilguala/navimow_pro`](https://github.com/ilguala/navimow_pro) for information
that can refine the Navimow case study.

It determines:

- which protocol and authentication model the repository uses;
- which status, map, zone and command semantics it adds;
- whether it contains MQTT/WSS evidence;
- how its evidence affects the current generic Start analysis;
- whether any implementation may be copied into the productive Symcon module.

No Navimow authentication, API request, MQTT connection, Symcon mutation,
fixture promotion or productive PHP change occurs.

## 2. Reviewed Revision and Trust Class

| Item | Result |
| --- | --- |
| repository | `ilguala/navimow_pro` |
| revision | `ff63db98ee5154062aa3c00c811d0a90a3a38c61` |
| revision date | 2026-07-27 |
| release | `v0.2.0` |
| license | MIT |
| repository history at review | 8 commits |
| automated tests found | none |
| raw or sanitized capture fixtures found | none |
| classification | experimental community interoperability source |

The code contains detailed comments identifying individual claims as proven,
deduced or best-effort. That is useful engineering context, but it is not a
substitute for reproducible fixtures or independent verification.

The source is therefore suitable for:

- static hypothesis generation;
- cross-checking endpoints, fields and state codes;
- designing bounded private captures.

It is not sufficient by itself for:

- productive command enablement;
- public API support claims;
- authentication or cryptographic implementation adoption;
- Store or broad-release readiness.

## 3. Protocol and Authentication Model

The repository does not use the Smart Home OAuth/OpenAPI contract implemented
by the current Symcon module.

It uses two private mobile-app services:

| Service | Role |
| --- | --- |
| `api-passport-fra.willand.com` | email/password login and token refresh |
| `navimow-fra.ninebot.com` | encrypted private mower-cloud calls |

The mower-cloud protocol is described as `p:101`. Business identity and request
data are packed into an encrypted JSON envelope. The source embeds mobile-app
identity, version, signing and cryptographic constants and registers a
persistent synthetic device identity.

The repository itself warns that another login can disturb a mobile-app
session and recommends a dedicated account to which the mower is shared.

### Assessment

This authentication model must not be merged into the existing Symcon account
module:

- it is a different protocol and credential lifecycle;
- it requires the user's account password during setup;
- it relies on app-wide private protocol constants;
- device registration may affect concurrent sessions;
- its support and permission model is undocumented;
- adopting it would bypass the existing public OAuth feasibility decision.

The current OAuth/OpenAPI transport remains the only approved productive
transport.

## 4. Relevant Read Endpoints

The source identifies the following private-cloud reads:

| Endpoint | Observed purpose |
| --- | --- |
| `/vehicle/vehicle/auth-list` | vehicle discovery |
| `/vehicle/vehicle/index2` | primary status snapshot |
| `/vehicle/vehicle/get-device-info` | static model and device information |
| `/vehicle/vehicle/set-list` | current settings |
| `/vehicle/vehicle/get-vehicle-config` | vehicle configuration |
| `/vehicle/vehicle/get-today-plan` | current daily plan |
| `/vehicle/vehicle/get-location` | position and mowing progress |
| `/vehicle/vehicle/get-hint-error-compress` | errors |
| `/vehicle/vehicle/get-component-maintenance` | maintenance |
| `/map/index/map-list` | available maps |
| `/map/index/map-detail` | uncompressed map geometry |
| `/map/index/map-detail-compress` | compressed map geometry |
| `/map/index/get-station-map` | station and approach geometry |
| `/vehicle/trail/get-path-info-time` | per-zone coverage and timing |

These endpoints are useful discovery candidates, especially for future map,
zone and maintenance research. They are not part of the currently approved
Smart Home API and must not be added to `ApiClient` without a separate protocol
and privacy decision.

Location, map, station and trail data can reveal a private garden layout. Raw
responses must remain private and must not be promoted as public fixtures.

## 5. State Model

The repository maps the following empirical private state codes:

| Private code | Meaning | Current Symcon equivalent |
| --- | --- | --- |
| `0101` | docked | Docked |
| `0102` | docked after task | Docked |
| `0210` | mowing | Running |
| `0211` | paused | Paused |
| `0220` | returning to dock | Docking |

This independently supports the distinction between Running, Paused, Docking
and Docked that the current Symcon profile already exposes. It does not require
a profile change.

The integration polls every:

- 30 seconds while docked or idle;
- 12 seconds while returning;
- 3 seconds while mowing.

Its own source states that the private API has no push channel. This is not an
MQTT implementation and does not prove anything about the separate Smart Home
MQTT service.

## 6. Command Evidence

The private protocol uses:

```text
POST /vehicle/set/send
```

Behavior commands are represented as:

| Operation | `cmdCode` | type |
| --- | --- | --- |
| Pause | `c:behavior` | `1` |
| Dock | `c:behavior` | `2` |
| Resume | `c:behavior` | `3` |

The request sends the type both as a top-level string and as an integer inside
`data`. A command result can be queried through:

```text
POST /vehicle/set/response
```

This is useful independent corroboration of the three operations already
implemented through the Smart Home API. It does not alter their productive
payloads or verification contracts.

## 7. Zone and Ordered Start Evidence

The most relevant new finding is the private mowing command:

```text
cmdCode: s:mower
data:
  partitionSetup: <bit field>
  partitionIds: <encoded zone list>
```

The source documents `partitionSetup` as two nibbles:

| High nibble | Meaning |
| --- | --- |
| `1` | continue remaining work |
| `2` | restart progress |

| Low nibble | Meaning |
| --- | --- |
| `1` | mower chooses zone order |
| `2` | preserve supplied zone order |

It encodes each zone ID as little-endian unsigned 16-bit hexadecimal and
concatenates the encoded values. The current release claims three isolated live
runs for the captured combinations; one combination remains deduced.

### Effect on step 82

Step 82 remains correct for the official Smart Home API:

```text
Generic Smart Home Start: available
Zone-specific Smart Home Start: unavailable
```

The broader conclusion is now refined:

```text
Zone-specific mowing exists in an experimental private mobile-app protocol,
but is not available through the current official Smart Home API.
```

The private command is not eligible for the current Start capture or productive
implementation. It may become a future research track only if:

1. the official API adds equivalent support; or
2. a separately approved private-protocol architecture is justified and its
   authentication, safety, legal and session-impact risks are accepted.

## 8. MQTT/WSS Finding

Static searches found no:

- MQTT client;
- WSS/WebSocket client;
- broker credential request;
- topic subscription;
- publish operation;
- push callback.

`navimow_pro` therefore contributes no direct MQTT transport or payload
evidence. Its aggressive active polling is evidence of the operational value
that a reliable push channel could provide.

## 9. Adopt, Research or Reject Matrix

| Finding | Decision | Reason |
| --- | --- | --- |
| private state codes | retain as secondary evidence | corroborates current states |
| adaptive 30/12/3 polling | retain as comparison | demonstrates latency/cost trade-off |
| private map/location endpoints | research candidate | useful but privacy-sensitive |
| private maintenance endpoints | research candidate | outside current MVP |
| zone ID encoding | hypothesis for future private capture | not in official API |
| ordered zone Start | do not implement | different private protocol and insufficient fixtures |
| email/password passport login | reject for current module | conflicts with approved OAuth architecture |
| embedded app identity/crypto | reject for current module | unsupported private protocol dependency |
| MQTT/WSS | no evidence | absent from repository |

## 10. Risks

| Risk | Impact | Treatment |
| --- | --- | --- |
| young source with limited history | behavior may change rapidly | pin revision and classify as experimental |
| no automated tests | regressions are not independently bounded | require local fixtures before reuse |
| self-reported live proof only | claims cannot be replayed | treat as hypotheses |
| private app protocol | vendor change or policy can break it | do not adopt productively |
| account password and device registration | credential and session impact | keep outside current module |
| map/location exposure | private property geometry disclosure | keep raw data private |
| movement commands | physical hazard | preserve independent command gates |

## 11. Architecture Decisions

### AD-NAV-292: Classify `navimow_pro` as experimental community evidence

**Decision:** Use the pinned source for hypotheses and cross-checking only.

**Rationale:** The repository is detailed and current but young, untested and
fixture-free.

**Consequence:** No productive contract is approved from this source alone.

### AD-NAV-293: Preserve the OAuth/OpenAPI transport boundary

**Decision:** Do not add the private `p:101` passport protocol to the current
account module.

**Rationale:** It has a different credential, session, support and risk model.

**Consequence:** Current authentication and release decisions remain valid.

### AD-NAV-294: Refine rather than reverse the zone-start conclusion

**Decision:** Record private zone and ordering evidence while keeping zone Start
unavailable in the current Smart Home API contract.

**Rationale:** The two repositories use different cloud protocols.

**Consequence:** Generic Start remains the only eligible future Start scope.

### AD-NAV-295: Keep current variables and archives unchanged

**Decision:** Do not add state, zone, map or maintenance variables from this
review.

**Rationale:** Source discovery is not a variable-contract migration.

**Consequence:** Existing variable ObjectIDs, Idents and user-configured archive
logging remain untouched.

## 12. Decision

**Additional relevant information found: YES.**

**Direct MQTT/WSS information found: NO.**

**Private protocol adoption: NO-GO.**

**Zone/start finding:** relevant future evidence, but not available through the
current productive API.

## 13. Recommended Next Step

Proceed with a separate MQTT/WSS reprioritization and evidence plan based on the
official SDK and the current ioBroker implementation.
