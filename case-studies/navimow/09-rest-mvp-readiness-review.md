# 09 REST MVP Readiness Review

**Case study:** Navimow native IP-Symcon module  
**Status:** REST MVP readiness decision  
**Date:** 2026-07-08  
**Build boundary:** This document is a readiness review only. No productive PHP code is introduced.

## 1. Purpose

This review decides whether the Navimow case study has enough evidence to move
from analysis and fixture collection into REST MVP implementation planning.

It evaluates the fixture gate from `05-fixture-plan.md`, the implementation
readiness criteria from `04-implementation-plan.md` and the variable/action
contract from `03-variable-and-action-contract.md`.

## 2. Decision Summary

**Recommendation:** Go for REST MVP implementation planning.

The available fixtures are sufficient for:

- OAuth token response handling;
- device discovery;
- read-only status polling;
- `VehicleState` mapping for `isDocked` and `isRunning`;
- battery mapping from `capacityRemaining`;
- invalid-token auth diagnostics;
- command response parsing for the important `alreadyInState` case.

The missing true command-success transition is not a blocker for starting the
REST MVP because command implementation is explicitly defensive: commands must
not directly set domain state, and verification happens through later status
polling.

## 3. Evidence Reviewed

| Evidence | Status | Impact |
| --- | --- | --- |
| `fixtures/rest/auth-token-success.json` | available | Confirms token fields and `expires_in`. |
| `fixtures/rest/auth-list-success.json` | available | Confirms discovery path and device metadata. |
| `fixtures/rest/vehicle-status-docked.json` | available | Confirms docked status, battery source and payload shape. |
| `fixtures/rest/vehicle-status-mowing.json` | available | Confirms active running status uses same payload shape. |
| `fixtures/rest/command-dock-already-in-state.json` | available | Confirms command result nesting and `alreadyInState` behavior. |
| `fixtures/rest/auth-invalid-token.json` | available | Confirms auth failure can be API-code based with HTTP 200. |
| `08-fixture-validation-report.md` | available | Summarizes field validation and remaining gaps. |

## 4. Fixture Gate Review

| Gate item | Status | Review result |
| --- | --- | --- |
| Discovery fixture exists | satisfied | `auth-list-success.json` confirms `data.payload.devices[]`. |
| At least one status fixture exists | satisfied | Docked and running status fixtures exist. |
| State field understood | satisfied for MVP | `isDocked` and `isRunning` are fixture-backed; other states remain defensive. |
| Battery field understood | satisfied | `capacityRemaining[].rawValue` with `unit == "PERCENTAGE"` is fixture-backed. |
| Online field understood | not satisfied | No dedicated online field appears in docked or running fixtures. |
| Successful command response exists | partially satisfied | `alreadyInState` command response exists; true transition success is missing. |
| Failure or auth-error fixture exists | satisfied | Invalid-token API error fixture exists. |
| Fixtures pass redaction checklist | satisfied | Current public fixtures contain placeholders only. |

Gate result:

- Sufficient for read-only REST MVP.
- Sufficient for defensive command implementation design.
- Not sufficient for claiming complete command behavior coverage.

## 5. Accepted Residual Risks

### RISK-NAV-001: No dedicated online field is fixture-backed

**Decision:** Accept for REST MVP with conservative behavior.

**Mitigation:** `Online` should not be treated as a proven cloud field. Until a
fixture proves a dedicated online indicator, derive it conservatively from
successful status freshness and known `Offline` state if observed. Mark the
variable semantics as "connection/status freshness" during implementation
review if no explicit online field emerges.

### RISK-NAV-002: True command-success transition is not fixture-backed

**Decision:** Accept for starting implementation planning.

**Mitigation:** Command actions must not directly set `VehicleState`. A command
with top-level API success enters command diagnostics and then waits for status
poll verification. `alreadyInState` is explicitly handled as non-fatal.

### RISK-NAV-003: Other vehicle states are not fixture-backed

**Decision:** Accept with defensive mapping.

**Mitigation:** Fixture-backed states are `isDocked` and `isRunning`. Other
states from static source analysis remain mapped but must fall back to
`Unknown` if unexpected values appear.

### RISK-NAV-004: Temporary cloud failure schema is not fixture-backed

**Decision:** Accept for MVP planning.

**Mitigation:** Treat unknown non-success API codes and transport errors as
diagnostic failures with bounded retry behavior. Do not infer device state from
failure responses.

## 6. Required Implementation Constraints

The REST MVP implementation must:

- start with account authentication and discovery;
- implement read-only status before commands;
- map battery primarily from `capacityRemaining[].rawValue` where
  `unit == "PERCENTAGE"`;
- map `isDocked` and `isRunning` from fixtures;
- preserve defensive handling for all other states;
- treat API code `4005` / `CODE_OAUTH_INFO_ILLEGAL` as authentication failure;
- interpret command-level `errorCode == "alreadyInState"` as `Already In State`
  even when command-level `status == "ERROR"`;
- never write command results directly into `VehicleState`;
- keep `RawStatusJson` optional and disabled by default;
- avoid MQTT, map rendering and location in the REST MVP.

## 7. Readiness Against Implementation Plan

| Readiness criterion from `04-implementation-plan.md` | Status |
| --- | --- |
| MVP variable/action contract exists | satisfied |
| Sanitized fixtures exist for discovery | satisfied |
| Sanitized fixtures exist for status | satisfied |
| Token storage approach chosen | partially satisfied; protected state required, exact Symcon mechanism still pending |
| Module structure decision recorded | satisfied at design level; Splitter vs parent-child detail still pending |
| Test harness approach defined | partially satisfied; fixture-based tests are planned but not scaffolded |
| Private data handling rules explicit | satisfied |
| Implementation starts with REST MVP | satisfied by plan |

Conclusion:

- Ready for implementation planning/scaffold design.
- Not yet ready for unreviewed production module release.

## 8. Go/No-Go Decision

| Scope | Decision | Reason |
| --- | --- | --- |
| REST read-only implementation planning | Go | Discovery and status fixtures are sufficient. |
| REST command implementation planning | Go with constraints | `alreadyInState` is known; true success remains defensive. |
| Productive REST MVP code scaffold | Conditional Go | Requires module structure and test harness plan next. |
| MQTT/WSS implementation | No-Go | MQTT fixtures and WSS feasibility are not validated. |
| Map/location implementation | No-Go | Out of MVP and not fixture-backed. |

## 9. Next SAEF Step

Create:

```text
case-studies/navimow/10-module-scaffold-plan.md
```

That document should define:

- exact module folder structure;
- account vs device instance communication;
- profile and variable creation order;
- protected token state mechanism;
- timer responsibilities;
- fixture-based parser test targets;
- which files may be created when implementation begins.

After that, creating a real module scaffold becomes justified, provided it
stays REST-first and fixture-driven.
