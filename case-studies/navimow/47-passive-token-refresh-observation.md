# 47 Passive Token Refresh Observation

**Case study:** Navimow native IP-Symcon module
**Status:** Passive scheduled token-refresh observation passed
**Date:** 2026-07-12
**Scope:** Close `OBS-04` without manual authentication action

## 1. Purpose

This step closes the remaining private-pilot observation gate identified in
`46-private-pilot-observation-status-review.md`.

It verifies on the published hardening build that:

- token expiry moved forward after the prior checkpoint;
- no manual refresh or OAuth action caused that change;
- account state remains Connected;
- reauthorization is not required;
- read-only polling continues;
- REST errors have not accumulated;
- no mower command is needed.

## 2. Tested Build

Published module commit:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

The comparison checkpoint is the completed active-restart test from
2026-07-10 in `45-pilot-restart-observation-live-retest.md`.

No productive module update occurred between that checkpoint and this
observation.

## 3. Passive Observation Boundary

The observation did not invoke:

- `RefreshAuthentication()`;
- OAuth authorization;
- account reset;
- `ApplyChanges()`;
- manual status refresh;
- Dock or another mower command.

It read only public account and device variables plus the Symcon change
timestamp of the public `TokenExpiresAt` variable.

No internal token value or refresh token was read.

## 4. Operator Confirmation

After the technical read-back, the user explicitly confirmed:

> Ich habe nach dem Restart-Test am 10.07. weder „Token aktualisieren“, eine
> erneute OAuth-Anmeldung noch eine andere manuelle Authentifizierungsaktion in
> Symcon ausgeführt.

This confirmation excludes a manual authentication operation as the cause of
the observed expiry update.

## 5. Sanitized Result Channel

The established temporary-script pattern was used:

1. create one temporary Symcon script;
2. read only bounded public state and variable metadata;
3. compare change times to the prior checkpoint internally;
4. write only boolean PASS markers to the script name;
5. read the marker through MCP;
6. delete the script.

Observed marker:

```text
Navimow Passive Refresh PASS A3 R0 X1 U1 L1 D1 E0 S2 O1
```

Decoded:

| Marker | Meaning |
| --- | --- |
| `A3` | account is `Connected` |
| `R0` | reauthorization is not required |
| `X1` | published token expiry is still safely in the future |
| `U1` | token-expiry variable changed after the prior checkpoint |
| `L1` | account REST success is recent |
| `D1` | device status update is recent |
| `E0` | REST error count is zero |
| `S2` | mower state is `Docked` |
| `O1` | mower is online |

No absolute timestamp or private identifier is included in the report.

## 6. Token Expiry Evidence

The test asserted:

- `TokenExpiresAt` is an integer timestamp;
- expiry is more than five minutes in the future;
- the Symcon variable change timestamp is later than the restart-test
  checkpoint;
- the account remains authenticated after that change.

Combined with the operator confirmation, the only expected module path that
updates `TokenExpiresAt` is the scheduled token-refresh flow.

This provides passive live evidence that the hardened account timer executed
successfully without manual intervention.

## 7. Polling Continuity

The observation also required:

- `LastRestSuccess` later than the prior checkpoint;
- account REST success within the current polling window;
- `LastStatusUpdate` later than the prior checkpoint;
- device status update within the current polling window;
- device remains online;
- account remains Connected;
- reauthorization remains false.

This proves that read-only polling continued after the scheduled token refresh.

## 8. Error and Privacy Review

Observed:

- `RestErrorCount == 0`;
- no reauthorization state;
- no offline or API-warning state;
- no token value in variables inspected through MCP;
- no raw HTTP header or payload;
- no private ObjectID, device ID, account name or hostname in the result;
- no mower command;
- no manual authentication action.

The available MCP interface does not expose the complete Symcon log stream.
The zero error counter, Connected state and recent successful polling provide
the bounded runtime evidence used by this gate.

## 9. Cleanup

The temporary passive-observation script was deleted after read-back.

No retained script, event, variable or category was created.

## 10. OBS-04 Decision

**`OBS-04`: PASS.**

Evidence now includes:

- deterministic successful refresh and expiry movement;
- deterministic expired-token handling;
- deterministic authentication rejection;
- bounded transport retry and retry exhaustion;
- restart persistence of retry state;
- no retry of authorization-code exchange;
- passive live scheduled expiry movement;
- continued authenticated read-only polling;
- no manual authentication intervention;
- no REST error accumulation.

## 11. Observation Matrix Closure

The current matrix is now:

| Scenario | Status |
| --- | --- |
| `OBS-01` verification timeout | PASS |
| `OBS-02` active restart | PASS |
| `OBS-03` temporary read failures | PASS |
| `OBS-04` token expiry and refresh | PASS |
| `OBS-05` repeated Dock operation | PASS WITH LIMITATION |

All release-blocking technical observation criteria from step 37 are met.

`OBS-05` retains its documented sample limitation, but has no duplicate-command
or state-leak finding.

## 12. Release Impact

### Controlled private pilot

**Decision: CONTINUE.**

### Second immutable pilot tag

**Decision: READY FOR RELEASE REVIEW.**

The technical observation prerequisite for considering `pilot-0.1.0.2` is now
complete.

### Broad public release

**Decision: remains separate and not yet approved.**

Open product-level concerns remain:

- installation-specific OAuth client-secret handling;
- undocumented external cloud API;
- Dock-only command scope;
- no MQTT/WSS support;
- no Symcon Store readiness review.

## 13. Architecture Decisions

### AD-NAV-117: Pair variable metadata with operator confirmation

**Decision:** Accept expiry change as passive refresh evidence only after the
operator confirms that no manual authentication action occurred.

**Rationale:** Variable change time alone cannot identify whether a manual or
scheduled refresh caused the update.

**Consequence:** The evidence distinguishes automation from operator action.

### AD-NAV-118: Keep token values outside observation

**Decision:** Use public expiry and state metadata without reading access or
refresh tokens.

**Rationale:** Token contents are unnecessary for lifecycle verification and
remain security-sensitive.

**Consequence:** The observation is privacy-preserving and reproducible.

### AD-NAV-119: Require polling continuity after refresh

**Decision:** Pair expiry movement with recent account and device success.

**Rationale:** A refreshed expiry alone does not prove that normal read-only
operation continued.

**Consequence:** `OBS-04` covers both authentication and downstream polling.

### AD-NAV-120: Close the technical pilot matrix before tagging

**Decision:** Mark the observation matrix technically complete before deciding
on a second immutable pilot tag.

**Rationale:** A tag should represent an evidence-backed snapshot rather than
only a code commit.

**Consequence:** Tagging moves to a separate release review.

## 14. Recommended Next Step

Create:

```text
48-private-pilot-release-review-and-tag-decision.md
```

That step should:

- verify remote `main` still identifies the tested hardening commit;
- review all technical observation gates;
- confirm documentation and metadata policy;
- decide whether to create `pilot-0.1.0.2`;
- keep broad public release and command expansion as separate decisions.
