# 53 Navimow OAuth Vendor Clarification Plan

**Case study:** Navimow native IP-Symcon module
**Status:** Clarification package prepared; no contact made
**Date:** 2026-07-12
**Scope:** Prepare evidence-safe vendor contact and response classification

## 1. Purpose

This step prepares the vendor clarification approved by
`52-public-oauth-and-release-feasibility-analysis.md`.

It defines:

- the exact decision requested from Navimow;
- appropriate contact routes and escalation order;
- the information that may be disclosed;
- the information that must remain private;
- a concise technical inquiry;
- follow-up limits;
- response acceptance criteria;
- roadmap branches for each response class.

This step does not send a message, open a GitHub issue, disclose a credential,
register an OAuth client or change productive module behavior.

External contact requires explicit user approval after the final text and
selected channel have been reviewed.

## 2. Clarification Objective

The objective is not to request general product support or ask Navimow to
debug the private installation.

The objective is to establish whether Navimow supports an independent native
IP-Symcon integration using the Smart Home cloud API and, if so, which OAuth
client and redirect architecture it authorizes.

The minimum useful answer must resolve:

1. API use permission for an independent IP-Symcon integration;
2. OAuth client ownership or registration;
3. permitted redirect model;
4. public-client, confidential-client or bridge classification;
5. whether PKCE is supported;
6. credential redistribution restrictions;
7. supported API and lifecycle boundary.

A generic statement that OAuth 2.0 is supported is insufficient.

## 3. Evidence-Safe Project Context

The inquiry may disclose:

- project name: Navimow native IP-Symcon module;
- platform: IP-Symcon home automation server;
- project type: independent open-source integration;
- current maturity: controlled private pilot;
- repository: `https://github.com/doctee/symcon-navimow`;
- implemented scope: OAuth, discovery, read-only status and supervised Dock;
- intent: determine a supportable public authentication path before broader
  distribution;
- reference to the official Home Assistant repository;
- observed protocol shape without endpoint payloads or secret values;
- preference for vendor-approved public-client PKCE or a dedicated registered
  client.

The inquiry must not disclose:

- the observed static client-secret value;
- access token, refresh token or authorization code;
- raw callback URL;
- account email, phone number or internal account identifier;
- mower serial number or private device ID;
- private Symcon object IDs, hostname or IP address;
- raw REST or MQTT payload;
- map, garden or location data;
- private capture paths or files;
- screenshots from the authenticated installation;
- claims that the current Home Assistant client is already authorized for
  IP-Symcon.

## 4. Public Reference Set

The inquiry may reference only public sources:

- [official Navimow Home Assistant repository](https://github.com/segwaynavimow/NavimowHA);
- [official repository issue tracker](https://github.com/segwaynavimow/NavimowHA/issues);
- [Navimow contact page](https://navimow.com/pages/contact);
- [Navimow support center](https://navimow.com/pages/support-center);
- [Navimow X3 API application page](https://ca.navimow.com/pages/navimow-openapi);
- [RFC 8252](https://datatracker.ietf.org/doc/html/rfc8252) if a public-client
  security rationale is useful.

The X3 expansion-bay API application must be described only as evidence that
Navimow has an approval process in another API domain. It must not be presented
as registration for the Smart Home cloud API.

## 5. Contact Route Order

### Route 1: Official integration maintainers

Preferred first route:

```text
https://github.com/segwaynavimow/NavimowHA/issues
```

Use this route for the technical questions because:

- the repository identifies itself as the official integration;
- its maintainers own the observed OAuth implementation;
- the discussion can remain public and citable;
- future integrators can reuse the answer;
- no private account information is needed.

Before opening an issue:

1. search existing open and closed issues for OAuth client registration,
   third-party integrations, PKCE and redirect URIs;
2. do not create a duplicate;
3. use a neutral interoperability title;
4. include no credential value even if it is already present elsewhere in the
   repository;
5. ask whether the maintainers are the correct contact for cross-platform API
   authorization.

### Route 2: Official business inquiry

If Route 1 redirects the request, receives no answer within the bounded
follow-up period or cannot authorize API use, use:

```text
https://navimow.com/pages/contact
```

Select the business inquiry path and request forwarding to the Smart Home API
or integration owner.

This route is appropriate for:

- client registration ownership;
- developer or partner agreement questions;
- cross-platform permission;
- private response details that should not be posted publicly.

If Navimow provides confidential onboarding material, retain it only in the
private overlay and publish solely the resulting permission boundary unless
Navimow explicitly allows the material to be quoted.

### Route 3: Product support fallback

The support center or in-app support may be used only to locate the responsible
integration team.

Do not ask first-line product support to validate OAuth security architecture
or approve credential reuse. Request escalation instead.

## 6. Inquiry Strategy

Use one canonical inquiry. Do not send different technical claims to several
teams simultaneously.

Sequence:

```text
existing-issue search
-> one technical maintainer inquiry
-> wait for response
-> one bounded follow-up
-> business escalation if needed
-> classify response
-> update SAEF decision
```

The inquiry should seek a durable public answer first. Private discussion is
acceptable when Navimow needs company or registration details, but private
credentials must never be returned to a public issue.

## 7. Proposed GitHub Issue

### Title

```text
OAuth client guidance for an independent IP-Symcon integration
```

### Body

```text
Hello Navimow integration team,

we are developing an independent open-source Navimow integration for
IP-Symcon, a self-hosted home automation platform. The current project is a
controlled private pilot and supports account authorization, mower discovery,
read-only status, and a supervised Dock command.

Before considering broader distribution, we want to use the Navimow Smart
Home cloud API only through a client and redirect flow that Navimow explicitly
supports.

We found the official Navimow Home Assistant repository and observed that its
OAuth flow uses a Home Assistant client registration. We do not want to assume
that this client registration may be reused by another platform, and we will
not copy or publish its credential in our module.

Could you please clarify:

1. Does Navimow permit an independent IP-Symcon integration to use the Smart
   Home cloud API?
2. Should this integration receive its own OAuth client registration? If so,
   what is the application process and the responsible contact?
3. Is a public OAuth client using Authorization Code with PKCE supported, so
   that no shared client secret has to be distributed with the module?
4. Alternatively, does Navimow support a confidential server-side client with
   a registered HTTPS callback for this type of integration?
5. Which redirect URI patterns and token refresh semantics are supported?
6. Are there API terms, rate limits, supported mower models, branding rules,
   or change-notification channels that an integration must follow?

Project repository:
https://github.com/doctee/symcon-navimow

We do not need account support and will not post tokens, client credentials,
device identifiers, or private API payloads. A pointer to the correct API or
partner contact would already be very helpful.

Thank you.
```

## 8. Proposed Business Inquiry

### Subject

```text
Smart Home OAuth client request for an open-source IP-Symcon integration
```

### Body

```text
Hello Navimow team,

I am requesting the appropriate technical or partner contact for an
independent open-source Navimow integration for IP-Symcon, a self-hosted home
automation platform.

The integration is currently a controlled private pilot. Before any broader
distribution, we need written guidance on whether Navimow permits use of the
Smart Home cloud API by this platform and which OAuth client model should be
used.

In particular, we would like to know whether Navimow can provide either:

- a registered public client supporting Authorization Code with PKCE and an
  approved redirect URI; or
- a dedicated confidential client and approved HTTPS callback model.

We are not requesting account troubleshooting and will not send existing
client credentials, tokens, device identifiers, callback captures, or private
API payloads.

Public project repository:
https://github.com/doctee/symcon-navimow

Could you please forward this request to the team responsible for the Navimow
Smart Home API or official Home Assistant integration?

Thank you.
```

## 9. Optional Technical Appendix

Include this appendix only if the recipient asks for protocol detail:

```text
Observed integration shape:
- browser-based OAuth Authorization Code flow;
- token exchange and refresh through the Navimow Smart Home cloud;
- current upstream client requires client_id and client_secret;
- distributed IP-Symcon module source cannot keep a shared embedded secret
  confidential;
- preferred design is a vendor-registered public client with PKCE S256;
- alternative is a vendor-approved confidential backend with an HTTPS
  callback and explicit operating responsibility.

No credential value or user token is required to evaluate this request.
```

Do not attach source files or screenshots. Link to the public repository if
implementation context is requested.

## 10. User Approval Gate

Before any contact, the user must approve:

- selected route;
- final title or subject;
- final body;
- sender identity and any company affiliation;
- whether the project repository is ready to be shared;
- whether a public GitHub discussion is acceptable;
- whether private replies may be retained in the private overlay.

The agent may prepare and revise text. It must not open an issue, submit a form
or send an email without explicit approval for that exact action.

## 11. Follow-Up Policy

Recommended cadence:

1. send one initial inquiry;
2. wait 14 calendar days;
3. send one concise follow-up in the same thread;
4. wait another 14 calendar days;
5. if unanswered, use the next route once;
6. record `no response`, not `permission denied`;
7. keep broad release blocked.

Do not post repeated issue comments, contact individual contributors privately
or send the same request to unrelated regional support teams.

An explicit redirection to another Navimow team resets the follow-up period for
that new route.

## 12. Response Evidence Rules

Acceptable evidence:

- public maintainer response in the official repository;
- response from an official Navimow domain or contact system;
- public developer documentation;
- issued client registration and accompanying terms;
- explicit partner or developer agreement.

Insufficient evidence:

- third-party forum comment;
- inference from publicly visible source code;
- successful token exchange alone;
- anonymous message;
- statement that Home Assistant works without addressing cross-platform use;
- credential value without permission and redirect terms;
- verbal approval with no reproducible record.

Private vendor responses may establish permission, but the SAEF public record
must summarize only the permitted conclusion and retain no confidential
material.

## 13. Response Classification

### Class A: Vendor-approved public client with PKCE

Required answer content:

- integration is permitted;
- public client can be registered or assigned;
- PKCE S256 is supported;
- redirect URI rules are explicit;
- refresh behavior is defined;
- no distributed shared secret is required.

Roadmap result:

```text
GO for public OAuth architecture design
```

### Class B: Vendor-approved dedicated confidential client

Required answer content:

- integration is permitted;
- dedicated client is assigned or registrable;
- secret custody and redistribution rules are explicit;
- approved HTTPS callback is available;
- operating responsibility is understood.

Roadmap result:

```text
GO for confidential bridge feasibility and threat model
```

No client secret may enter the distributed module repository.

### Class C: Vendor-approved bring-your-own-client

Required answer content:

- user or integrator registration process exists;
- redirect registration rules are explicit;
- terms allow self-hosted integrations;
- support boundary is documented.

Roadmap result:

```text
GO for bring-your-own-client onboarding design
```

### Class D: Explicit cross-platform reuse of Home Assistant client

Required answer content:

- Navimow explicitly permits IP-Symcon use;
- credential redistribution policy is explicit;
- client classification is explained;
- redirect use is approved;
- support and revocation implications are understood.

Roadmap result:

```text
REVIEW REQUIRED; no automatic GO
```

Even explicit permission does not make a static distributed secret
confidential. SAEF must still review whether the design is acceptable.

### Class E: Integration not supported

Roadmap result:

```text
PRIVATE PILOT ONLY; no broad release or Store submission
```

Review whether continued private use is permitted or should also stop.

### Class F: Ambiguous, partial or no response

Roadmap result:

```text
BROAD RELEASE REMAINS BLOCKED
```

Do not infer permission from silence.

## 14. Response Recording Template

Create a later report with:

```text
Contact route:
Date sent:
Public reference or private-record location:
Responding organization/team:
Response date:
Response class:
Permission scope:
OAuth client model:
Redirect model:
PKCE support:
Secret custody rule:
Refresh/revocation notes:
API/model/support boundary:
Confidential material excluded from public report: yes/no
Decision:
Required next SAEF step:
```

Do not paste a full private email into the public case study. Quote only short
statements when Navimow permits publication; otherwise paraphrase the decision.

## 15. Roadmap Branches

| Response | Next design step | Store impact | Pilot impact |
| --- | --- | --- | --- |
| Class A | public-client PKCE design | preparation may continue; no submission | preserve current pilot until migration tested |
| Class B | confidential bridge threat model | preparation may continue; no submission | preserve current pilot until bridge proven |
| Class C | BYO-client onboarding design | preparation may continue; no submission | existing configurable properties may aid migration |
| Class D | security and permission review | no automatic Store progress | no credential publication |
| Class E | private-only boundary review | Store path closed | assess permission for continued pilot |
| Class F | wait or stop clarification effort | Store path remains blocked | pilot continues under current boundary |

Complete mower command integration remains a separate prerequisite for final
Store readiness regardless of OAuth response class.

## 16. Stop Conditions

Stop the clarification process and reassess immediately if:

- a recipient asks for the current client secret in an insecure channel;
- account credentials or tokens are requested;
- a public issue receives private credential content;
- Navimow requests removal or suspension of the integration;
- repository maintainers state they cannot authorize API use;
- a security incident is identified in the current OAuth flow;
- sender identity or project ownership cannot be represented accurately.

If sensitive content is accidentally posted publicly, do not copy it into the
case study. Request removal through the platform's security process and treat
the credential as potentially compromised.

## 17. Architecture Decisions

### AD-NAV-143: Ask maintainers before business escalation

**Decision:** Use the official integration issue tracker as the first technical
route, then escalate to the official business contact if needed.

**Rationale:** Maintainers can answer implementation questions publicly, while
business contacts can authorize client registration and agreements.

**Consequence:** The inquiry follows one traceable path without parallel or
contradictory requests.

### AD-NAV-144: Use one canonical evidence-safe inquiry

**Decision:** Reuse the same factual request across routes and exclude all
working credentials and private installation evidence.

**Rationale:** Consistency improves answer quality and prevents accidental
credential disclosure.

**Consequence:** Route changes do not change the technical claim or requested
permission.

### AD-NAV-145: Do not interpret silence as permission

**Decision:** Classify ambiguous or absent responses as continuing release
blockers.

**Rationale:** Technical interoperability and public source visibility do not
establish authorization.

**Consequence:** The private pilot may continue, but no broad release follows
from a missing answer.

### AD-NAV-146: Require explicit approval before external contact

**Decision:** Prepare the package in SAEF but require the user to approve the
exact route and message before sending.

**Rationale:** External communication represents the project and may disclose
the user's identity or affiliation.

**Consequence:** This step ends before opening an issue or submitting a form.

## 18. Gate Decision

**Decision: CLARIFICATION PACKAGE READY.**

**External-contact status: NOT SENT.**

The preferred next action is user review of the proposed GitHub issue. No
engineering implementation, Store setup or credential change should wait on
the mechanics of preparing the message, but broad release remains blocked
until a sufficient response is classified.

## 19. Recommended Next Step

After the user approves a route and exact message, create:

```text
54-navimow-oauth-vendor-inquiry-execution.md
```

That step should perform only the approved contact action, record the public
reference or private evidence location, confirm that no sensitive data was
sent and set the bounded follow-up date. If the user chooses not to contact
Navimow now, retain this plan and continue the command-integration roadmap
without claiming public OAuth readiness.
