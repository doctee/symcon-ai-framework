# 76 Stop Vendor and Upstream Clarification Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Evidence-safe Stop clarification prepared; no inquiry sent
**Date:** 2026-07-12
**Scope:** Plan one public SDK inquiry without credentials or mower actuation

## 1. Purpose

This step prepares the targeted clarification approved by
`75-stop-support-and-semantics-analysis.md`.

It defines:

- the exact technical decision requested from the Navimow SDK maintainers;
- the preferred public contact route;
- a duplicate-search procedure;
- evidence-safe project context;
- the proposed issue title and body;
- privacy and terminology constraints;
- response classifications and engineering consequences;
- bounded follow-up and escalation rules;
- the gates for a later Stop capture plan or formal exclusion.

This step does not open an issue, send a message, use an OAuth credential,
invoke an API, change code or send a mower command. External contact requires
explicit user approval after review of this plan.

## 2. Clarification Objective

The objective is not to request general product support, ask maintainers to
debug the IP-Symcon module or obtain permission to experiment on a mower.

The objective is to classify the official SDK's existing
`MowerCommand.STOP` contract:

1. Is it a supported public SDK operation or an internal/legacy artifact?
2. Does it pause the current mowing task or end that task?
3. Which raw and normalized mower state is expected after success?
4. Is Resume valid afterward, or is a new Start required?
5. Does task progress remain available?
6. Which models and firmware families support it?
7. What does `alreadyInState` mean for Stop?
8. Why is Stop absent from the SDK README and high-level client wrappers?

An answer that only repeats the boolean payload is insufficient because the
payload is already confirmed from official source.

## 3. Current Public Evidence

The inquiry may cite these public facts:

- official SDK revision
  `6596aa0a65dcf05ed248da87c36975f2ea236ab8` defines
  `MowerCommand.STOP`;
- the official low-level API maps it to
  `action.devices.commands.StartStop` with JSON boolean `on=false`;
- the mapping has existed since initial public SDK release commit
  `af44444f65eae199eeba5b3d4657108f1997ddc8`;
- the SDK README and high-level `MowerClient` omit Stop;
- the official Home Assistant integration exposes Start, Pause, Resume and
  Dock but no Stop;
- the official state model contains no Stopped state;
- IP-Symcon currently rejects Stop and has not tested it.

Public references:

- [official SDK repository](https://github.com/segwaynavimow/navimow-sdk)
- [SDK command enum](https://github.com/segwaynavimow/navimow-sdk/blob/main/mower_sdk/models.py)
- [SDK low-level command mapping](https://github.com/segwaynavimow/navimow-sdk/blob/main/mower_sdk/api.py)
- [SDK high-level client](https://github.com/segwaynavimow/navimow-sdk/blob/main/mower_sdk/client.py)
- [official Navimow Home Assistant integration](https://github.com/segwaynavimow/NavimowHA)
- [public IP-Symcon module repository](https://github.com/doctee/symcon-navimow)

## 4. Evidence-Safe Project Context

The inquiry may disclose:

- project: independent open-source Navimow module for IP-Symcon;
- maturity: controlled private pilot;
- current enabled commands: Pause, Resume and Dock;
- current Stop state: deliberately rejected and not tested;
- intent: determine whether Stop should receive a supervised evidence path or
  be explicitly excluded;
- exact public source references and revision identities;
- existing state vocabulary in symbolic terms;
- commitment to one-write, no-retry and later read-only verification.

The inquiry must not disclose:

- client ID or client secret values;
- access token, refresh token or authorization code;
- callback URL or authenticated session detail;
- account email, phone number or account identifier;
- mower serial number, device ID, model serial or private firmware record;
- Symcon ObjectID, hostname, IP address or installation topology;
- raw private REST/MQTT request or response;
- garden, map, location or archive data;
- private file paths, screenshots or captures;
- assumptions presented as observed mower behavior.

## 5. Preferred Contact Route

Primary route:

```text
https://github.com/segwaynavimow/navimow-sdk/issues
```

Rationale:

- the question concerns an enum and mapping owned by that repository;
- the repository is manufacturer-owned;
- a public answer is citable by other SDK consumers;
- no account or private mower information is required;
- the Home Assistant entity model cannot represent a separate Stop and is
  therefore not the best first route.

Do not add the question to the existing OAuth issue in `NavimowHA`. OAuth
distribution and Stop semantics are independent engineering decisions.

## 6. Duplicate-Search Procedure

Immediately before publication, search open and closed SDK issues,
discussions, pull requests and commit history for:

```text
STOP
MowerCommand.STOP
StartStop
on false
end task
pause versus stop
resume after stop
client wrapper stop
alreadyInState
```

Also inspect:

- issues in `segwaynavimow/NavimowHA` mentioning Stop or End Task;
- pending SDK pull requests changing command exposure;
- SDK README or client changes after the revision reviewed in step 75.

Classification:

| Search result | Action |
| --- | --- |
| exact semantic answer exists | do not open issue; document and classify it |
| open equivalent question exists | subscribe/reference it; do not duplicate |
| related but incomplete issue exists | comment only if the prepared questions fit its scope |
| no equivalent exists | open one new SDK issue |
| current source removed/deprecated Stop | stop and perform exclusion review |

The duplicate review must record URLs and a bounded summary without copying
private or unrelated issue content.

## 7. Proposed GitHub Issue

### Title

```text
Clarify MowerCommand.STOP task and state semantics
```

### Body

```text
Hello Navimow SDK team,

we are developing an independent open-source Navimow integration for
IP-Symcon. It is currently a controlled private pilot. Pause, Resume and Dock
are implemented with current-state preconditions, one command write, no
automatic command retry and later read-only state verification.

We are reviewing whether Stop should be implemented or explicitly excluded.
The current official SDK defines MowerCommand.STOP and maps it in
mower_sdk/api.py to:

action.devices.commands.StartStop
{ "on": false }

At the same time, Stop is not listed in the SDK README, has no high-level
MowerClient wrapper, is not exposed by the official Navimow Home Assistant
integration, and the documented SDK state model has no stopped state.

We have not sent this command and do not want to use a mower to discover
ambiguous task semantics.

Could you please clarify:

1. Is MowerCommand.STOP a supported public SDK operation, or is the enum/API
   mapping internal, legacy or deprecated?
2. Does StartStop with on=false pause the current mowing task or end it?
3. Which raw mower state and normalized SDK state should follow a successful
   Stop command?
4. Can MowerCommand.RESUME continue the same task afterward, or must
   MowerCommand.START create/start a task again?
5. Is mowing progress retained after Stop?
6. What does alreadyInState mean for Stop from mowing, paused, idle or docked?
7. Are there model or firmware restrictions?
8. If Stop is supported, should high-level client wrappers and README
   documentation be added, or is its omission intentional?

Project repository:
https://github.com/doctee/symcon-navimow

We are not requesting account troubleshooting and will not post credentials,
tokens, device identifiers or private payloads. A documented semantic
contract would let us either design one supervised, no-retry evidence capture
or keep Stop deliberately disabled.

Thank you.
```

## 8. Terminology Rules

The inquiry and all follow-ups must distinguish:

| Term | Meaning |
| --- | --- |
| cloud Stop | `MowerCommand.STOP` / `StartStop on=false` |
| Pause | `PauseUnpause on=false` |
| End Task | official-app wording whose cloud mapping is not assumed |
| physical STOP | local safety control, not equivalent to a cloud API action |
| emergency stop | safety state/control; never a name for the cloud command |

Do not describe cloud Stop as:

- emergency stop;
- guaranteed blade stop;
- safer Pause;
- task cancellation;
- return to station.

Those meanings require evidence.

## 9. Pre-Publication Privacy Review

Before sending, require all checks:

| Check | Requirement |
| --- | --- |
| credentials or tokens | none |
| private client values | none |
| account/device identifiers | none |
| private URLs, hosts or paths | none |
| raw captures or logs | none |
| garden/location/archive data | none |
| unverified behavior claims | none |
| public source links | valid |
| project repository | public and intentional |
| issue target | official SDK repository |

If any maintainer requests private credentials or captures in the public
issue, do not provide them. Pause and obtain explicit user approval for an
appropriate private channel and a minimized disclosure decision.

## 10. Publication Authorization Gate

Opening the issue requires explicit user approval after:

1. the duplicate search is complete;
2. current source has not materially changed;
3. the final title and body are shown without private substitutions;
4. the target repository is confirmed;
5. the privacy review passes;
6. no parallel Stop inquiry is active.

Approval to create the SAEF plan is not approval to publish the issue.

## 11. Response Classification

### Class S1: Supported and semantically complete

Required answer elements:

- public support confirmed;
- pause-versus-end-task meaning;
- expected state;
- Resume/Start behavior;
- applicable model/firmware scope.

Consequence:

- conditional GO for a dedicated Stop evidence and capture plan;
- no immediate command or implementation;
- remaining already-in-state or timeout gaps fail closed.

### Class S2: Supported but model- or firmware-specific

Consequence:

- compare explicit support scope with the private pilot mower privately;
- plan a capture only if the model/firmware is included;
- future module exposure requires capability configuration or documented
  compatibility policy.

### Class S3: Equivalent to Pause

Consequence:

- default decision is formal Stop exclusion as duplicate UI;
- retain Pause as the supported stationary-task action;
- implement Stop only if a distinct evidenced user contract remains.

### Class S4: End Task with defined state

Consequence:

- treat Stop as destructive task-lifecycle control;
- require stronger confirmation text and explicit task-loss warning;
- prepare a separate capture plan with the documented terminal state and
  Start-only recovery;
- do not reuse Pause policy.

### Class S5: Internal, unsupported or deprecated

Consequence:

- formal exclusion;
- retain account allowlist rejection and absent form action;
- document the observed SDK mapping as non-public;
- close the Stop command gate without a live capture.

### Class S6: Incomplete or ambiguous response

Consequence:

- ask one bounded clarification in the same issue;
- do not interpret silence or partial wording as support;
- remain NO-GO for capture and implementation.

### Class S7: No response

Consequence:

- one follow-up after the waiting period;
- remain blocked;
- no experimental command;
- later choose continued exclusion unless authoritative evidence emerges.

### Class S8: Third-party-only opinion

Consequence:

- retain it as a lead;
- require manufacturer confirmation or independently attributable sanitized
  transition evidence before changing readiness.

## 12. Follow-Up Cadence

After publication:

- wait 14 calendar days before one concise follow-up;
- respond sooner only to a maintainer clarification request;
- do not open a second SDK issue;
- after another 14 days without an actionable answer, classify `no response`;
- a support/business route may be proposed only in a new SAEF decision step;
- never send credentials or a mower command to accelerate the answer.

If the issue is closed as out of scope, record whether the maintainer provides
another responsible technical channel. Closure without an answer is not
support confirmation.

## 13. Interruption Rules

Stop the inquiry process and reassess if:

- the SDK removes `MowerCommand.STOP` before publication;
- an exact existing issue or documentation answer is found;
- the repository is archived or ownership changes;
- a maintainer requests account or mower identifiers publicly;
- answering would require accepting legal/API terms;
- the OAuth vendor inquiry produces a broader API-support decision affecting
  command use;
- a private pilot incident suspends live command testing generally.

## 14. Capture-Gate Consequences

An authoritative response does not itself authorize a mower command.

A future capture-planning GO additionally requires:

- exact successful terminal state;
- permitted transient state set;
- clear task lifecycle after Stop;
- current-state precondition;
- model/firmware applicability;
- bounded deadline and no-retry rule;
- already-in-state fallback or explicit rejection;
- official-app recovery procedure;
- physical supervision and abort criteria;
- explicit user approval for exactly one write.

If these cannot be derived from the response, the capture gate remains closed.

## 15. Public Reporting Boundary

An execution report may publish:

- duplicate-search result;
- issue URL, number, title and public state;
- exact public question or bounded summary;
- response class;
- resulting GO/NO-GO decision;
- public source revisions.

It must not publish private identifiers, captures, authentication material,
installation details or inferred mower behavior.

## 16. Architecture Decisions

### AD-NAV-257: Ask the SDK owner rather than the HA surface

**Decision:** Direct the first Stop inquiry to
`segwaynavimow/navimow-sdk`.

**Rationale:** The ambiguity originates in its enum, low-level API, README and
high-level client.

**Consequence:** The answer can define SDK semantics independently of Home
Assistant's limited entity model.

### AD-NAV-258: Ask for semantics, not the known payload

**Decision:** Center the inquiry on task lifecycle, state and support scope.

**Rationale:** Official source already confirms the transport mapping.

**Consequence:** A useful answer can directly govern capture readiness.

### AD-NAV-259: State explicitly that Stop has not been sent

**Decision:** Avoid presenting inference as operational evidence.

**Rationale:** Maintainers must understand the safety-first reason for asking.

**Consequence:** The issue does not normalize experimental actuator probing.

### AD-NAV-260: Separate cloud Stop from physical safety controls

**Decision:** Enforce precise terminology in issue and follow-ups.

**Rationale:** Conflation could create an unsafe product expectation.

**Consequence:** Future UI cannot market the cloud operation as emergency
stopping.

### AD-NAV-261: Require explicit publication approval

**Decision:** Planning does not authorize external contact.

**Rationale:** The issue is a user-owned public communication under the user's
GitHub identity.

**Consequence:** Execution waits for a separate user instruction.

### AD-NAV-262: Bound follow-up and preserve silence as NO-GO

**Decision:** Use one 14-day follow-up and classify unresolved silence without
experimental fallback.

**Rationale:** Repetition adds noise but no protocol evidence.

**Consequence:** No response leaves Stop disabled.

### AD-NAV-263: Keep response and capture authorization separate

**Decision:** Even a supportive answer opens only a capture-planning step.

**Rationale:** Physical execution still requires a command-specific safety
procedure and explicit approval.

**Consequence:** No vendor response can directly activate productive code.

## 17. Decision

**Clarification package: PREPARED.**

**Duplicate review and issue publication: NOT YET EXECUTED.**

**External contact: REQUIRES EXPLICIT USER APPROVAL.**

**Stop capture and implementation: REMAIN NO-GO.**

## 18. Recommended Next Step

After explicit user approval, execute
`77-stop-vendor-and-upstream-inquiry-execution.md` to:

1. perform and record the current duplicate search;
2. revalidate official source revisions;
3. privacy-review the exact issue text;
4. publish once in the official SDK issue tracker;
5. verify the public issue by independent read-back;
6. record follow-up dates and preserve all Stop implementation gates.
