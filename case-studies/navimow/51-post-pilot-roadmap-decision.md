# 51 Post-Pilot Roadmap Decision

**Case study:** Navimow native IP-Symcon module
**Status:** Roadmap decided; public authentication feasibility is next
**Date:** 2026-07-12
**Scope:** Prioritize post-pilot work without starting implementation

## 1. Purpose

This step selects the next legitimate engineering track after the
recovery-hardened second private pilot was consolidated in
`50-second-pilot-case-study-consolidation.md`.

It compares:

- continued passive pilot observation;
- public-release and OAuth distribution readiness;
- preparatory Symcon Store requirements review;
- MQTT/WSS research;
- complete integration of the intended mower commands under separate gates.

The result is a sequencing decision, not approval to implement another feature
or publish a broad release.

No productive PHP code, module metadata, Git tag or live Symcon configuration
is changed in this step.

## 2. Current Baseline

The current immutable pilot snapshot is:

```text
pilot-0.1.0.2
```

Its executable content is the recovery-hardened REST MVP from:

```text
db36ea37cb40298278307e88d65ae8c450603f18
fix: harden Dock and token recovery
```

The published tag resolves through the documentation-only commit:

```text
937113e522f7a5323a8265b5b255855fcee7f19f
docs: refresh recovery-hardened pilot guidance
```

The current technical baseline provides:

- OAuth authorization and scheduled token refresh;
- mower discovery;
- REST-polled read-only status;
- a supervised Dock-only command;
- long-running read-only Dock verification;
- deterministic timeout and REST-failure behavior;
- restart recovery without command replay;
- bounded token-refresh transport recovery;
- a complete private-pilot observation matrix.

## 3. Remaining Product Questions

The completed technical pilot does not answer:

- how a user can lawfully and supportably obtain OAuth client credentials;
- whether the current redirect and secret handling can support more than the
  private test installation;
- which compatibility, migration and support promises a public release makes;
- whether repository metadata and documentation meet Symcon Store policy;
- how upstream undocumented API changes are detected and handled;
- whether the module works across additional mower models and firmware;
- whether MQTT/WSS credentials, reconnect behavior and payload contracts are
  stable enough for a native implementation;
- whether another physical command has a sufficiently understood safety and
  state contract.

These questions differ in urgency and dependency. They must not be addressed
as one combined feature phase.

## 4. Decision Criteria

Each candidate track is evaluated by:

| Criterion | Meaning |
| --- | --- |
| blocker removal | closes a current release or operational blocker |
| safety exposure | can cause mower movement or credential disruption |
| evidence availability | can proceed from public or sanitized sources |
| dependency order | must be decided before another track is meaningful |
| reversibility | can be researched without changing the pilot build |
| user value | improves supportability, reliability or useful capability |

Release blockers and low-risk decisions take precedence over capability
expansion.

## 5. Candidate Track Assessment

| Track | Blocker removal | Safety exposure | Dependency | Decision |
| --- | --- | --- | --- | --- |
| passive pilot observation | operational evidence only | low when read-only | independent | CONTINUE IN BACKGROUND |
| public OAuth and release feasibility | closes primary public-release blocker | low if analysis-only | precedes Store/public release | NEXT ACTIVE TRACK |
| Symcon Store preparation | identifies later distribution gaps | low during analysis | depends on auth model; submission depends on complete command integration | PREPARATION ONLY |
| MQTT/WSS research | adds realtime capability | credential and schema risk | not required for REST MVP release | DEFER |
| complete mower command integration | completes intended actuator scope | high | each command requires separate API and safety evidence | SEQUENCED; EACH COMMAND BLOCKED UNTIL APPROVED |

## 6. Track A: Passive Pilot Observation

### Decision

**CONTINUE IN BACKGROUND.**

Normal private-pilot use may continue under the published supervision and
command boundaries.

Observation should be event-driven rather than scheduled as repeated physical
testing. Record only when one of these naturally occurs:

- token reauthorization request;
- repeated REST outage or recovery;
- verification timeout;
- Symcon or module update;
- unexpected state mapping;
- failed or duplicate Dock behavior;
- new mower model or firmware in the pilot population.

### Evidence boundary

Retain only sanitized summaries containing:

- pilot tag or commit identity;
- error category rather than raw response;
- relevant public state transition;
- bounded timestamps or elapsed durations;
- whether a command was sent exactly once;
- cleanup confirmation.

Do not retain tokens, raw payloads, account identifiers, device identifiers,
map data or private Symcon object IDs.

### Escalation rule

Any duplicate command, credential exposure or unexplained command replay
immediately suspends further live command use and reopens the relevant safety
gate.

Passive observation does not require a new SAEF step for every uneventful
period.

## 7. Track B: Public OAuth and Release Feasibility

### Decision

**NEXT ACTIVE TRACK.**

The next engineering work should determine whether a supportable public
installation model exists before preparing Store requirements or implementing
new features.

### Required analysis

The track must establish:

1. the origin and permitted use of the current OAuth client identity;
2. whether each installation must supply its own client ID and secret;
3. whether a client secret can be treated as confidential in an open-source
   Symcon module;
4. whether a supported redirect URI can replace the current local callback
   workflow;
5. whether authorization can be completed without exposing codes or callback
   URLs in logs;
6. how secrets and refresh tokens are stored, reset and migrated;
7. what happens when client credentials, refresh tokens or upstream policy are
   revoked;
8. which setup can be documented for users without redistributing private or
   third-party credentials;
9. whether the undocumented cloud API permits a responsible public support
   promise at all.

### Required outcomes

The analysis must choose one of:

| Outcome | Meaning |
| --- | --- |
| public-ready model | supportable user setup exists; proceed to release design |
| bring-your-own-client pilot | technically possible, but remains advanced/private |
| private-only integration | no supportable public credential model exists |
| blocked pending vendor support | official registration or documentation is required |

No OAuth implementation change should begin until this decision is documented.

## 8. Track C: Symcon Store Preparation

### Decision

**PREPARATION ONLY.**

An early Store review may prepare a requirements and gap list after Track B
identifies a supportable authentication model. It must not create a Store
entry, submit the module or imply Store-release readiness.

Concrete Store setup, submission and release review are deferred until all
intended mower commands have been implemented and individually passed their
API, deterministic and supervised safety gates.

The later Store review should cover:

- current official module validator output;
- library and module metadata;
- semantic version, build and date policy;
- update and migration behavior;
- localization and form completeness;
- repository and release structure;
- public setup, privacy and troubleshooting documentation;
- compatibility floor and tested Symcon versions;
- third-party API disclosure and branding;
- Store-specific review requirements current at that time.

The result of this early work is planning input only. Because Store
requirements can change, a final review must repeat the checks against current
official Symcon documentation after command integration is complete.

## 9. Track D: MQTT/WSS Research

### Decision

**DEFER.**

MQTT/WSS is not required to preserve or release the current REST MVP. It adds a
second transport with separate credentials, reconnect behavior, topic
ownership, payload drift and diagnostics.

Research may start only after the authentication/release model is known, or
earlier if REST polling proves operationally insufficient during passive
observation.

A future analysis must remain read-only and determine:

- current MQTT credential endpoint behavior;
- broker, WSS and TLS requirements;
- authorization-header and username/password roles;
- topic ownership and wildcard safety;
- reconnect behavior after token refresh;
- state, event, attribute and location payload contracts;
- stale-location detection;
- privacy impact of location and map-related data;
- REST/MQTT reconciliation and source precedence.

No productive subscriber or location variable should be introduced during the
research step.

## 10. Track E: Complete Mower Command Integration

### Decision

**SEQUENCED, WITH EACH COMMAND INITIALLY BLOCKED.**

Start, Stop, Pause and Resume remain outside the approved module contract.

Completing Start, Stop, Pause and Resume is a prerequisite for concrete Store
setup under this roadmap. This does not approve implementing them as a batch.

Each additional command is not a small extension of Dock. It requires:

- static source analysis for the exact command payload;
- sanitized real response evidence;
- precondition and already-in-state behavior;
- accepted, rejected and ambiguous response handling;
- command-specific state transitions;
- physical hazards and abort criteria;
- proof of no automatic retry;
- deterministic transport and restart tests;
- one separately approved supervised live procedure.

Commands must be analyzed, implemented and released one at a time. Evidence
from one command cannot approve another command.

No command should be selected merely because it exists in the source adapter.
Each command must have a concrete user need and a safe, testable contract.

## 11. Ordered Roadmap

The decided sequence is:

```text
1. continue passive private-pilot observation in the background
2. analyze public OAuth and release feasibility
3. decide public, bring-your-own-client, private-only or vendor-blocked model
4. if supportable, prepare a non-binding Symcon Store requirements and gap list
5. integrate Start, Stop, Pause and Resume individually under separate gates
6. only after complete command integration, perform final Store readiness review
7. only then consider Store setup, submission or public release
8. consider MQTT/WSS only from an evidenced operational need
```

Tracks 4 through 8 are conditional. They are not automatically authorized by
completion of the preceding track.

## 12. Public Variable and Archive Compatibility

Existing public variables are persistent installation contracts. In
particular, the current Device variables use stable Idents and types:

| Ident | Type | Current registration |
| --- | --- | --- |
| `VehicleState` | integer | stable |
| `Online` | boolean | stable |
| `BatteryLevel` | integer | stable |
| `LastStatusUpdate` | integer | stable |
| `LastCommand` | integer | stable |
| `LastCommandAt` | integer | stable |
| `LastCommandResult` | integer | stable |
| `LastCommandError` | string | stable |

The current module re-registers these Idents idempotently during
`ApplyChanges()`. It does not unregister them and does not manage Archive
Control logging. Existing variable objects and user-enabled logging are
therefore expected to remain intact across current updates.

Future work must preserve this behavior:

- no public Ident rename without migration;
- no type change without migration;
- no delete-and-recreate update path;
- no reset of user-configured archive logging;
- no silent loss of historical data;
- explicit compatibility review before profile or contract changes.

Display-name, position or profile refinements must preserve the existing
variable object. If a future requirement cannot preserve object identity, it
is a breaking migration and requires a separate decision and user procedure.

## 13. Release and Version Impact

This roadmap does not create another module release.

Until Track B, complete command integration and the final Store review pass:

- `pilot-0.1.0.2` remains the current immutable snapshot;
- `main` may receive only separately reviewed pilot fixes;
- `library.json` remains `version 0.1`, `build 0`, `date 0`;
- no `v*` tag is created;
- broad public installation guidance is not claimed;
- Dock remains the only command.

An urgent pilot defect may interrupt the roadmap. Such work requires a bounded
incident report, targeted fix, regression evidence and a new immutable pilot
tag rather than moving an existing tag.

## 14. Stop and Reassessment Conditions

Reassess this roadmap immediately if:

- Navimow changes or disables an endpoint;
- OAuth credentials are revoked or their permitted use becomes unclear;
- a token or secret appears in logs or public artifacts;
- a command is duplicated or replayed;
- observed mower state cannot be reconciled safely;
- a new model or firmware changes payload semantics;
- official vendor or Symcon guidance changes the release assumptions;
- REST polling no longer provides adequate operational behavior.

Until reassessment, the safest response to authentication uncertainty is to
disable affected operations and require explicit reauthorization, not to add
credential or command retries.

## 15. Architecture Decisions

### AD-NAV-132: Resolve distribution feasibility before capability expansion

**Decision:** Make public OAuth and release feasibility the next active work
track.

**Rationale:** Authentication distribution is the primary unresolved release
blocker. New telemetry or commands would increase scope without resolving it.

**Consequence:** The next SAEF step is analysis-only and changes no module code.

### AD-NAV-133: Treat passive pilot operation as background evidence

**Decision:** Continue observation through normal use rather than scheduling
additional physical test cycles.

**Rationale:** The technical observation matrix is complete and artificial
movement would add risk without a specific hypothesis.

**Consequence:** New live reports are created only for meaningful events or
new environments.

### AD-NAV-134: Separate Store preparation from Store submission

**Decision:** Permit only a preparatory Store gap review after authentication
viability is known; defer Store setup, submission and final readiness until all
intended mower commands are integrated and evidenced.

**Rationale:** Early requirements knowledge is useful, but a Store release
should represent the intended complete command scope rather than the Dock-only
pilot.

**Consequence:** Early Store findings are planning input only and must be
revalidated after command completion.

### AD-NAV-135: Require operational need before MQTT/WSS expansion

**Decision:** Do not add a second transport solely for feature parity with the
source adapter.

**Rationale:** REST already supports the approved MVP, while MQTT/WSS adds
credential, lifecycle and privacy complexity.

**Consequence:** MQTT/WSS remains research-only until justified by pilot
evidence or an approved product requirement.

### AD-NAV-136: Keep every physical command independently gated

**Decision:** Block command expansion until a command-specific analysis and
safety contract are approved.

**Rationale:** Actuator commands have different preconditions, hazards and
terminal states and cannot inherit Dock evidence.

**Consequence:** Dock remains the sole controllable mower action after this
roadmap decision.

### AD-NAV-137: Preserve public variable identity and archive ownership

**Decision:** Treat existing public Symcon variables and their user-configured
archive logging as persistent installation state.

**Rationale:** Deleting or recreating a variable can break automations and
disconnect its historical archive series.

**Consequence:** Future releases require stable Idents and types or an explicit
non-destructive migration; the module must not reset Archive Control settings.

## 16. Gate Decision

**Decision: GO for analysis of public OAuth and release feasibility.**

The analysis may inspect public source material, current official guidance and
existing sanitized implementation evidence. It must not expose credentials,
change the live pilot installation or alter productive module behavior.

All other capability tracks remain deferred or blocked as stated above.

## 17. Recommended Next Step

Create:

```text
52-public-oauth-and-release-feasibility-analysis.md
```

That step should determine the origin, redistribution boundary, storage model,
redirect-flow options and supportability of the current OAuth setup, then
classify the module as public-ready, bring-your-own-client, private-only or
blocked pending vendor support.
