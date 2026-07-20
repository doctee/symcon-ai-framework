# 77 Stop Vendor and Upstream Inquiry Execution

**Case study:** Navimow native IP-Symcon module
**Status:** Official SDK inquiry published; response pending
**Date:** 2026-07-12
**Scope:** Execute the approved Stop semantic clarification without actuation

## 1. Purpose

This step executes the public clarification prepared in
`76-stop-vendor-and-upstream-clarification-plan.md` after explicit user
approval.

It records:

- the current duplicate search;
- source revision revalidation;
- selected contact route;
- publication and fallback path;
- exact public issue reference;
- independent read-back and privacy verification;
- bounded follow-up dates;
- unchanged Stop capture and implementation gates.

No OAuth credential, private API call, mower command, productive PHP, module
metadata, Symcon configuration or Git tag is changed.

## 2. User Authorization

The user explicitly approved execution of:

```text
77-stop-vendor-and-upstream-inquiry-execution.md
```

Authorized action:

```text
Perform the duplicate review and publish the prepared evidence-safe issue
once in segwaynavimow/navimow-sdk.
```

The approval did not authorize:

- posting credentials or private installation data;
- sending a Stop command;
- opening parallel issues;
- accepting agreements or API terms;
- implementing or exposing Stop;
- promising a release date.

## 3. Duplicate Review

Open and closed SDK issues, related Home Assistant issues and pull-request
searches were checked for:

```text
STOP
MowerCommand.STOP
StartStop
end task
pause versus stop
resume after stop
client wrapper stop
alreadyInState
```

No existing issue answered the combined questions of public support, task
lifecycle, terminal state and Resume/Start behavior.

Related but non-duplicate findings:

| Reference | Relevance | Why not equivalent |
| --- | --- | --- |
| [SDK issue 21](https://github.com/segwaynavimow/navimow-sdk/issues/21) | mentions a consumer exposing start/stop/pause/resume/dock | asks about realtime data and blade height, not Stop semantics |
| [NavimowHA issue 54](https://github.com/segwaynavimow/NavimowHA/issues/54) | reports that a start worked and a loosely named stop did not | transient-error report; no exact Stop opcode, state or task contract |
| [NavimowHA issue 53](https://github.com/segwaynavimow/NavimowHA/issues/53) | user wants to stop around irrigation | requests progress data; does not define SDK Stop |
| [NavimowHA issue 68](https://github.com/segwaynavimow/NavimowHA/issues/68) | discusses stopping before channel transit | proposes a new safety workflow, not `MowerCommand.STOP` semantics |

The SDK pull-request search found no pending Stop semantic or wrapper change.
Opening one dedicated SDK issue was therefore not a duplicate.

## 4. Source Revision Revalidation

Immediately before publication, official SDK `main` still resolved to:

```text
6596aa0a65dcf05ed248da87c36975f2ea236ab8
```

The source assumptions from step 75 therefore remained valid:

- `MowerCommand.STOP` present;
- low-level mapping to `StartStop` with boolean `false` present;
- no high-level client Stop wrapper;
- no README Stop capability;
- no normalized Stopped state.

No source change invalidated or answered the planned inquiry.

## 5. Selected Contact Route

Target:

```text
https://github.com/segwaynavimow/navimow-sdk/issues
```

Rationale:

- the ambiguity originates in the manufacturer-owned SDK;
- the question concerns public enum, API, client and documentation contracts;
- Home Assistant has no separate lawn-mower Stop feature;
- a public answer is reusable and citable;
- no account or mower data is needed.

The existing OAuth inquiry in `NavimowHA` remains separate.

## 6. Execution Path

The connected GitHub integration could search and read the external
repository but returned HTTP `403 Resource not accessible by integration` for
issue creation.

The approved action was therefore completed through the user's authenticated
GitHub browser session, matching the established evidence-safe fallback from
the OAuth inquiry.

No permission workaround, credential transfer, alternate account or content
change was used.

## 7. Published Inquiry

Issue:

```text
https://github.com/segwaynavimow/navimow-sdk/issues/22
```

Issue number:

```text
22
```

Title:

```text
Clarify MowerCommand.STOP task and state semantics
```

Author:

```text
doctee
```

Creation timestamp:

```text
2026-07-12T17:54:38Z
```

Initial state:

```text
open, zero comments, no labels, no assignee, no milestone
```

## 8. Transmitted Content

The published issue states:

- an independent open-source IP-Symcon integration is a controlled private
  pilot;
- Pause, Resume and Dock use current-state, one-write, no-retry and read-only
  verification rules;
- Stop is under review for implementation or explicit exclusion;
- the official SDK contains the exact `MowerCommand.STOP` mapping;
- README, high-level client, Home Assistant and state-model exposure are
  inconsistent or incomplete;
- no Stop command has been sent;
- ambiguous behavior will not be discovered by unplanned mower actuation.

Questions sent:

1. whether Stop is public, internal, legacy or deprecated;
2. whether it pauses or ends the task;
3. expected raw and normalized state;
4. Resume versus Start behavior afterward;
5. progress retention;
6. `alreadyInState` semantics by source state;
7. model and firmware restrictions;
8. whether README/client omission is intentional.

The exact public body is retained at the issue URL and independently readable.

## 9. Independent Read-Back

After browser publication, the connected GitHub read API independently
confirmed:

| Field | Result |
| --- | --- |
| repository | `segwaynavimow/navimow-sdk` |
| issue number | 22 |
| title | exact match |
| body | exact approved content |
| author | `doctee` |
| state | open |
| comments | zero |
| labels/assignee/milestone | none |
| creation/update timestamp | present and equal |

Publication verification: **PASS**.

## 10. Privacy Verification

The issue contains no:

- client ID or client secret value;
- access token, refresh token or authorization code;
- callback URL or authenticated session data;
- account email, phone number or identifier;
- mower serial number, private device ID or private firmware record;
- Symcon ObjectID, hostname, IP address or topology;
- private REST/MQTT payload, capture, screenshot or log;
- garden, map, location or archive data;
- private file path;
- claim that Stop has already been tested.

The intentionally disclosed identity and context are limited to the GitHub
author and already public module repository.

Privacy verification: **PASS**.

## 11. Follow-Up Schedule

Initial inquiry:

```text
2026-07-12
```

Earliest one-time follow-up after 14 calendar days:

```text
2026-07-26
```

Earliest no-response classification after another 14 days:

```text
2026-08-09
```

Rules:

- respond sooner only to an actual maintainer clarification request;
- post no reminder before 2026-07-26;
- use one concise follow-up in issue 22;
- open no second SDK or Home Assistant issue;
- classify silence as no response, never as support;
- propose another contact route only in a new SAEF decision step;
- never send credentials, private captures or a mower command to accelerate a
  response.

## 12. Response Handling

Until a response arrives:

- do not speculate publicly in the issue;
- do not add private model/firmware details;
- do not accept a third-party opinion as manufacturer confirmation;
- do not map generic words such as stop, End Task or physical STOP onto the
  cloud command;
- do not implement a synthetic Stopped state;
- do not create a Stop fixture from inferred data;
- do not change the account allowlist or device form.

Any response is classified as S1 through S8 from step 76. A supportive answer
can authorize only a later capture-planning step, never immediate actuation.

## 13. Current Engineering Impact

| Scope | Decision |
| --- | --- |
| `pilot-0.1.0.3` private use | continue |
| Pause, Resume and Dock | unchanged |
| Stop static mapping | confirmed |
| Stop semantic contract | pending |
| Stop capture plan | blocked |
| Stop API write | prohibited |
| Stop fixture/implementation | blocked |
| Start evidence track | not advanced |
| Store preparation | planning only |
| Store submission/public release | blocked |

Opening the issue does not change runtime behavior or lower any physical
safety gate.

## 14. Architecture Decisions

### AD-NAV-264: Publish one canonical SDK issue

**Decision:** Open issue 22 after the duplicate review found no equivalent
semantic question.

**Rationale:** One public thread gives maintainers a precise and reusable
contract question.

**Consequence:** No parallel Stop inquiries are permitted.

### AD-NAV-265: Record related reports without treating them as evidence

**Decision:** Classify issues 21, 54, 53 and 68 as related non-duplicates.

**Rationale:** Their use of the word stop does not identify the exact cloud
operation or terminal state.

**Consequence:** They cannot unlock capture or implementation.

### AD-NAV-266: Reuse the authenticated browser fallback

**Decision:** Publish through the user's browser after connector write access
was denied.

**Rationale:** The user explicitly authorized the exact external action and
the browser uses the user's own GitHub identity.

**Consequence:** Publication succeeded without permission expansion or content
drift.

### AD-NAV-267: Verify publication through an independent channel

**Decision:** Read issue 22 back through the GitHub connector.

**Rationale:** Browser success alone is weaker than structured confirmation of
the persisted remote record.

**Consequence:** Title, body, author, state and privacy boundary are auditable.

### AD-NAV-268: Freeze Stop engineering while the answer is pending

**Decision:** Allow response monitoring only; keep every implementation and
actuation gate closed.

**Rationale:** Contact is evidence collection, not protocol authorization.

**Consequence:** The current module continues to reject Stop.

## 15. Decision

**Duplicate review: PASS.**

**Official SDK inquiry publication: PASS.**

**Independent read-back and privacy review: PASS.**

**Vendor/maintainer response: PENDING.**

**Stop capture and implementation: REMAIN NO-GO.**

## 16. Recommended Next Step

Wait for an actionable response on
[SDK issue 22](https://github.com/segwaynavimow/navimow-sdk/issues/22).

When a response arrives, create
`78-stop-vendor-response-and-gate-decision.md` to classify it against S1
through S8 and choose either:

- a conditional supervised Stop capture-planning GO;
- one bounded clarification;
- or formal Stop exclusion.

If no response arrives, no follow-up is due before 2026-07-26.
