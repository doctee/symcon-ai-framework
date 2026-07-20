# 54 Navimow OAuth Vendor Inquiry Execution

**Case study:** Navimow native IP-Symcon module
**Status:** Vendor inquiry sent; response pending
**Date:** 2026-07-12
**Scope:** Execute the approved public OAuth clarification contact

## 1. Purpose

This step executes the first contact route prepared in
`53-navimow-oauth-vendor-clarification-plan.md` after explicit user approval.

It records:

- duplicate-issue review;
- selected contact route;
- exact public issue reference;
- publication verification;
- privacy boundary;
- follow-up dates;
- current release and implementation impact.

No productive PHP code, OAuth credential, module metadata, Store entry or live
Symcon configuration is changed.

## 2. User Authorization

The user explicitly approved external contact and requested execution of this
step.

Approved action:

```text
Open the prepared evidence-safe clarification issue in the official
segwaynavimow/NavimowHA repository.
```

The approval did not authorize:

- sending credentials or private installation data;
- opening parallel requests through other channels;
- accepting new agreements;
- registering an OAuth client;
- changing the current private-pilot authentication.

## 3. Duplicate Review

Open and closed issues in `segwaynavimow/NavimowHA` were searched for:

- OAuth client;
- PKCE;
- third-party integration;
- client registration;
- redirect URI;
- IP-Symcon.

No issue was found that asked whether an independent automation platform may
use the Smart Home API or how it can obtain an approved OAuth client.

Related issue:

```text
https://github.com/segwaynavimow/NavimowHA/issues/45
```

Issue 45 discusses the ownership and trust context of the Willand login domain.
Its comments state that Willand is the parent company of the Segway Navimow
brand. It does not answer client reuse, registration, PKCE, redirect or
cross-platform permission questions.

Creating a separate issue was therefore not a duplicate.

## 4. Selected Contact Route

Selected route:

```text
Official Navimow Home Assistant integration issue tracker
```

Repository:

```text
https://github.com/segwaynavimow/NavimowHA
```

Rationale:

- the repository identifies itself as the official Navimow integration;
- its maintainers own the analyzed OAuth implementation;
- the question is technical and cross-platform;
- a public answer is citable and reusable;
- no account or mower support data is required.

The official business inquiry route remains the bounded fallback.

## 5. Execution Path

The connected GitHub API integration could read the external repository but
returned an authorization error when asked to create an issue there.

The approved action was therefore completed through the authenticated GitHub
browser session owned by the user.

No permission workaround, credential transfer or alternative GitHub account
was used.

## 6. Published Inquiry

Issue:

```text
https://github.com/segwaynavimow/NavimowHA/issues/82
```

Issue number:

```text
82
```

Title:

```text
OAuth client guidance for an independent IP-Symcon integration
```

Author:

```text
doctee
```

Creation timestamp:

```text
2026-07-12T11:18:11Z
```

Initial state:

```text
open, no comments, no labels, no assignee
```

## 7. Transmitted Content

The published issue states:

- an independent open-source IP-Symcon integration is under development;
- the current state is a controlled private pilot;
- current scope is authentication, discovery, read-only status and supervised
  Dock;
- broad distribution requires an explicitly supported client and redirect;
- the project will not assume Home Assistant client reuse;
- the project will not copy or publish the observed credential;
- public-client PKCE and confidential-client alternatives should be clarified;
- API terms, limits, models, branding and change notifications are relevant;
- the public module repository is available for context.

Questions sent:

1. whether Navimow permits an independent IP-Symcon integration;
2. whether it should receive a dedicated OAuth client and how to apply;
3. whether Authorization Code with PKCE is supported for a public client;
4. whether a confidential client with HTTPS callback is supported;
5. which redirect and refresh semantics apply;
6. which terms, limits, models, branding and notification rules apply.

The exact public body is retained by GitHub at the issue URL. It is not
duplicated into this report beyond the bounded summary.

## 8. Privacy Verification

The issue contains no:

- client-secret value;
- access token or refresh token;
- authorization code;
- raw callback URL;
- Navimow account identifier;
- mower serial number or private device ID;
- Symcon object ID, hostname or private IP address;
- REST or MQTT payload;
- map, garden or location data;
- private file path;
- authenticated screenshot or log extract.

The only identity disclosed is the GitHub account used to create the issue and
the already public project repository.

Independent API read-back verified the title, body, author, issue number,
creation timestamp and open state after publication.

## 9. Follow-Up Schedule

Initial inquiry:

```text
2026-07-12
```

Earliest first follow-up after 14 calendar days:

```text
2026-07-26
```

Earliest business-route escalation after one follow-up and another 14 days:

```text
2026-08-09
```

Rules:

- respond sooner if Navimow asks a clarifying question;
- do not post a reminder before 2026-07-26;
- use one concise follow-up in issue 82;
- do not open another GitHub issue;
- use the official business route only if the bounded GitHub path does not
  produce an actionable answer;
- classify silence as `no response`, never as permission.

## 10. Response Handling

Until a response arrives:

- do not comment speculatively on the issue;
- do not post current credentials even if requested publicly;
- do not expose private captures to demonstrate interoperability;
- do not promise a public release date;
- do not change OAuth implementation based on an informal third-party comment;
- preserve `pilot-0.1.0.2` as the current operational snapshot.

When a response arrives, classify it using Classes A through F from step 53.

If a response requests confidential project information, pause and obtain
explicit user approval before transmitting it through an appropriate private
channel.

## 11. Release Impact

Current decisions remain:

| Scope | Decision |
| --- | --- |
| controlled private pilot | continue |
| broad public OAuth implementation | blocked |
| broad public release | blocked |
| Store preparation | planning only |
| Store entry or submission | blocked |
| current credential publication | prohibited |
| current private authentication migration | not approved |

Opening the inquiry does not reduce any release gate.

## 12. Parallel Engineering Boundary

Waiting for a vendor response does not require the case study to remain idle.

Allowed in parallel:

- passive private-pilot observation;
- analysis of the intended command-integration sequence;
- command-specific static source and fixture planning;
- deterministic safety design;
- preservation of existing variable and archive contracts.

Not allowed merely because the inquiry is open:

- public OAuth implementation;
- client-secret embedding;
- Store setup or submission;
- batch implementation of Start, Stop, Pause and Resume;
- unsupervised live command tests;
- claim of vendor approval.

## 13. Architecture Decisions

### AD-NAV-147: Use a public, citable technical inquiry

**Decision:** Open one issue in the official integration repository after the
duplicate review found no equivalent request.

**Rationale:** A public maintainer answer can establish reusable technical
guidance without transmitting account data.

**Consequence:** Issue 82 is the canonical first-route reference.

### AD-NAV-148: Fall back from connector to authenticated browser

**Decision:** Use the user's authenticated GitHub browser session after the
connected API integration lacked write permission for the external repository.

**Rationale:** The user explicitly authorized the exact external action, and
the browser performed it without changing message content or credentials.

**Consequence:** Publication succeeded under the user's GitHub identity and was
independently verified afterward.

### AD-NAV-149: Apply a bounded contact cadence

**Decision:** Wait 14 days before one follow-up and another 14 days before
business escalation.

**Rationale:** Maintainers need reasonable response time, while repeated
cross-channel contact would create noise and inconsistent evidence.

**Consequence:** No reminder is due before 2026-07-26.

### AD-NAV-150: Continue analysis while release remains blocked

**Decision:** Permit command-program planning in parallel without inferring any
OAuth approval.

**Rationale:** OAuth distribution and command safety are independent gates.

**Consequence:** The next SAEF step may sequence command integration while
issue 82 remains open.

## 14. Gate Decision

**Inquiry execution: PASS.**

**Vendor response: PENDING.**

**Broad release: REMAINS BLOCKED.**

The approved message was published once, contains only public project context
and is traceable through issue 82.

## 15. Recommended Next Step

Create:

```text
55-command-integration-sequence-and-safety-plan.md
```

That step should decide the order and separate evidence gates for Start, Stop,
Pause and Resume without implementing any command yet. A vendor response may
interrupt that sequence and should then be recorded in a dedicated response
review.
