# 52 Public OAuth and Release Feasibility Analysis

**Case study:** Navimow native IP-Symcon module
**Status:** Broad release blocked pending vendor-supported OAuth model
**Date:** 2026-07-12
**Scope:** Analyze OAuth origin, redistribution, redirect, storage and supportability

## 1. Purpose

This step executes the analysis decision from
`51-post-pilot-roadmap-decision.md`.

It determines whether the private-pilot OAuth implementation can support a
broad public module release without redistributing unauthorized credentials,
misrepresenting secret handling or depending on an undocumented installation
procedure.

The analysis classifies the current module as one of:

- public-ready;
- bring-your-own-client pilot;
- private-only;
- blocked pending vendor support.

No productive PHP code, live credential, module metadata, OAuth registration
or Symcon installation is changed in this step.

## 2. Sources and Versions

### Local engineering evidence

- `01-requirements.md`;
- `16-auth-and-readonly-rest-plan.md`;
- `17-rest-client-and-auth-implementation.md`;
- `18-auth-symcon-test-report.md`;
- `47-passive-token-refresh-observation.md`;
- canonical `NavimowAccount` and OAuth helper implementation.

### Public implementation sources

| Source | Observed revision | Role |
| --- | --- | --- |
| [`TA2k/ioBroker.navimow`](https://github.com/TA2k/ioBroker.navimow) | `8f8f00d7cdac258ea70437c1bb0ed4f6e69e4a42` on `main` | original community implementation source |
| [`segwaynavimow/NavimowHA`](https://github.com/segwaynavimow/NavimowHA) | `2331841f1fbb5b28440228426469d2ceab0cbb28` on `main` | repository describing itself as the official Home Assistant integration |
| [`navimow-sdk` 0.1.2](https://pypi.org/project/navimow-sdk/) | released 2026-04-10 | SDK used by the Home Assistant integration |

### Standards and platform sources

- [RFC 6749: OAuth 2.0 Authorization Framework](https://datatracker.ietf.org/doc/html/rfc6749);
- [RFC 7636: Proof Key for Code Exchange](https://datatracker.ietf.org/doc/html/rfc7636);
- [RFC 8252: OAuth 2.0 for Native Apps](https://datatracker.ietf.org/doc/html/rfc8252);
- [Symcon module data management](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/datenverwaltung/);
- [Symcon module SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/);
- [Symcon Store submission rules](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/store/einreichen/).

The public sources establish observed implementation behavior. They do not by
themselves establish contractual permission to reuse a client registration.

## 3. Current Private-Pilot Flow

The current Account instance uses:

```text
Authorization Code grant
external browser login
manually returned callback URL or code
client ID plus client secret at token exchange
refresh-token grant for renewal
```

Observed endpoint roles:

| Role | Endpoint |
| --- | --- |
| login | `https://navimow-h5-fra.willand.com/smartHome/login` |
| token exchange and refresh | `https://navimow-fra.ninebot.com/openapi/oauth/getAccessToken` |

The configured redirect is the non-listening local URI previously established
by the upstream implementation. The browser therefore fails to load the final
page, and the operator copies the resulting URL or code into Symcon.

Security properties already present:

- browser-based login rather than embedded credential collection;
- random OAuth `state` value;
- callback-state comparison when the complete URL is returned;
- one-time authorization input in the configuration action area;
- no authorization code in persistent module properties;
- no token in public variables or normal diagnostics;
- refresh tokens retained as module-owned persistent state;
- explicit reset and reauthorization state;
- bounded transport retry only for refresh, not code exchange.

This flow is proven for the controlled private installation. It is not yet a
documented public client onboarding model.

## 4. Upstream Client Identity Finding

Both analyzed upstream integrations use the same named Home Assistant OAuth
client and the same static client credential.

The credential is present in public upstream source code. This report
deliberately does not reproduce its value.

The newer `segwaynavimow/NavimowHA` repository:

- labels itself as the official Segway Navimow Home Assistant integration;
- sets Navimow as code owner;
- embeds the static OAuth client configuration;
- requires both client ID and client secret;
- uses Home Assistant's local OAuth implementation;
- exposes no user-facing client registration procedure;
- does not visibly use PKCE in its Navimow-specific authorization code;
- remains a custom HACS repository rather than a default Home Assistant
  integration at the observed revision.

The accompanying Python SDK explicitly states that it does not handle OAuth
and requires an access token to be obtained separately. It therefore does not
provide an alternative registration or authorization contract.

### Interpretation boundary

The official-looking publication is strong evidence that Navimow intentionally
supports a Home Assistant integration using this client identity.

It is not sufficient evidence that:

- third-party projects may reuse that client registration;
- the client secret may be redistributed in an IP-Symcon module;
- Navimow will support IP-Symcon traffic under the Home Assistant client;
- the redirect URI may be changed freely;
- the client registration is intended as a general smart-home developer
  credential;
- the observed API is covered by a public compatibility promise.

Public visibility and third-party authorization are separate questions.

## 5. OAuth Client Classification

RFC 6749 classifies installed applications whose credentials can be extracted
as public clients. RFC 8252 states that a static shared secret distributed to
multiple installations must not be treated as confidential client
authentication.

The practical Navimow flow nevertheless requires a client-secret field at the
token endpoint.

This creates a mismatch:

| Property | Observed behavior | Public-release impact |
| --- | --- | --- |
| distributed application | source is available to users | cannot keep an embedded shared secret confidential |
| token endpoint | expects client secret | client cannot simply omit it |
| PKCE | not observed in current Navimow-specific code | public-client code interception mitigation not established |
| registration | no smart-home client registration route found | project cannot obtain its own supported client |
| redirect | tied to observed client behavior | arbitrary Symcon redirect is unproven |

The word `secret` in the protocol field does not make the published static
value confidential. Conversely, public visibility does not grant this project
permission to reuse the registration.

## 6. Redirect Flow Assessment

### Current callback-copy flow

The current non-listening callback is operational but has limitations:

- browser displays an expected connection failure;
- the operator handles a short-lived authorization URL manually;
- copying only the code bypasses state verification because the callback state
  is no longer available;
- the flow depends on the redirect accepted for the upstream client;
- it is unsuitable as a polished public onboarding experience.

It remains acceptable for supervised private pilot use when the full callback
URL is pasted and never shared.

### Loopback listener

RFC 8252 allows loopback IP redirects with an ephemeral port for native
clients. It recommends an IP literal instead of `localhost` and requires the
authorization server to support the registered loopback pattern.

The current Symcon server and the browser may run on different computers. A
browser-local loopback callback would therefore not naturally reach the
Symcon host. A fixed unused port is not equivalent to a temporary listener.

No evidence shows that the Navimow client registration accepts a new loopback
URI or arbitrary port. This option is **not currently feasible** without
vendor registration changes.

### Symcon-hosted callback

Current Symcon SDK documentation describes native OAuth registration support
in the newer strict module base and an OAuth/Connect bridge capability.

This could provide a better callback experience, but only if:

- the minimum supported Symcon version is raised or a compatible implementation
  is provided;
- a stable HTTPS redirect is registered by Navimow;
- client ownership and server-side secret handling are agreed;
- callback tenancy, state, failure and revocation behavior are designed;
- operating responsibility for any bridge is explicit.

The capability solves callback delivery. It does not independently grant a
Navimow client registration or legal use of a client credential.

### PKCE public-client flow

The preferred public design is an authorization-code flow with:

- external browser;
- registered public client for this integration or a vendor-approved generic
  smart-home client;
- exact supported redirect URI;
- `state` verification;
- PKCE S256 challenge and verifier;
- no confidential shared secret embedded in the module.

No current evidence shows that the Navimow authorization and token endpoints
support PKCE for this client. It must not be implemented speculatively against
the live service.

## 7. Credential Acquisition Options

| Option | Technical state | Distribution state | Decision |
| --- | --- | --- | --- |
| embed observed upstream credential | would likely interoperate | unauthorized reuse and false secrecy unresolved | NO-GO |
| document upstream credential for users | technically equivalent to embedding | redistributes credential and shifts risk to users | NO-GO |
| ask users to extract it from another project | possible for experts | unsupported and evasive distribution model | NO-GO |
| bring own registered client | module already accepts custom values | no registration route for this smart-home API found | NOT CURRENTLY FEASIBLE |
| vendor-approved Symcon client | requires Navimow registration | supportable if terms and redirect are explicit | PREFERRED CONFIDENTIAL OPTION |
| vendor-approved public client with PKCE | requires server support | aligns best with distributed-client guidance | PREFERRED PUBLIC OPTION |
| OAuth bridge holding a confidential secret | technically plausible with current Symcon capabilities | requires vendor and bridge-operator agreement | FEASIBILITY TO CONFIRM |

The module's configurable fields are useful for testing and future migration,
but configurability alone does not make bring-your-own-client onboarding real.

## 8. Secret and Token Storage Assessment

Current Symcon documentation defines properties and attributes as persistent
module data. It cites login data as a property example and tokens as an
attribute example.

Current module use:

| Data | Storage | Visibility boundary |
| --- | --- | --- |
| client ID | string property | configured by user |
| client secret | string property with password-style form input | visually masked in form |
| access token | string attribute | module-owned persistent state |
| refresh token | string attribute | module-owned persistent state |
| expiry | attribute plus non-secret public timestamp | module and diagnostics |
| OAuth state | string attribute | short-lived transaction state |

The password form control masks entry. No reviewed Symcon source states that a
normal string property or attribute is encrypted as a dedicated secret vault.

Therefore the public security claim must be limited:

- secrets and tokens are not intentionally exposed through module variables,
  forms after entry, logs or committed files;
- administrators and installation backups remain inside the trust boundary;
- at-rest cryptographic confidentiality is **not established by this case
  study**;
- a public module must document this boundary accurately;
- credentials must never be described as impossible for an installation
  administrator to inspect.

This storage model is acceptable for the current owner-controlled private
installation. A vendor-approved public model should still prefer a public
client with PKCE or a properly operated confidential backend over embedding a
shared secret in every installation.

## 9. API and Licensing Boundary

The REST and OAuth endpoints used by the module remain undocumented as a
general public smart-home API.

The official Navimow website does describe an API access application process,
developer agreement and approval for the X3 expansion-bay API. That material
does not establish access to the Smart Home cloud endpoints used here and must
not be treated as equivalent authorization.

The official Home Assistant repository and SDK improve source provenance, but
the observed repository does not provide a general third-party OAuth client
registration procedure or explicit statement authorizing other automation
platforms to reuse its client identity.

This is not a legal opinion. It is an engineering release boundary: when
permission and support are unclear, SAEF must not convert an observed
credential into a distributed dependency.

## 10. Supportability Assessment

### Private pilot

The existing installation remains supportable within its current boundary:

- credential entered locally;
- no credential published by this project;
- supervised setup;
- controlled user population;
- explicit undocumented-API warning;
- tested reset, refresh and reauthorization behavior.

### Bring-your-own-client pilot

The module technically supports custom client values, but no user-accessible
registration workflow was found. The classification is therefore not
available in practice.

### Broad public distribution

Broad release is not supportable today because users cannot be given a complete
and authorized onboarding procedure without one of:

- redistributing the observed upstream credential;
- instructing users to obtain it indirectly;
- claiming an unavailable client registration process;
- operating an unapproved OAuth bridge.

All four are unacceptable release foundations.

## 11. Feasibility Classification

| Classification | Result | Rationale |
| --- | --- | --- |
| public-ready | NO | no approved client, redirect and public setup contract |
| bring-your-own-client pilot | NO FOR NOW | configurable technically, but registration unavailable |
| private-only | YES AS CURRENT OPERATIONAL BOUNDARY | existing controlled installation may continue |
| blocked pending vendor support | YES FOR BROAD RELEASE | Navimow must clarify or provision the OAuth model |

### Final classification

**BROAD RELEASE: BLOCKED PENDING VENDOR SUPPORT.**

**CURRENT PRIVATE PILOT: MAY CONTINUE.**

The emergence of the official Home Assistant repository lowers uncertainty
about technical API intent, but it does not close the cross-platform client
authorization and onboarding gap.

## 12. Required Vendor Clarifications

Before public-release implementation, obtain written or publicly documented
answers to:

1. May a third-party IP-Symcon integration use the Smart Home cloud API?
2. May it reuse the Home Assistant client identity and credential?
3. If not, how can the project register its own OAuth client?
4. Is the client confidential, public or only an identifier despite the
   required secret field?
5. Which redirect URIs are supported for server-hosted automation platforms?
6. Can a Symcon HTTPS callback be registered?
7. Does the authorization server support PKCE S256?
8. Can the token endpoint support a public client without a shared secret?
9. Are refresh-token rotation, expiry and revocation semantics documented?
10. Are rate limits, supported regions and API change notifications available?
11. Which mower models and firmware versions are covered?
12. What branding, attribution, privacy and support statements are required?

No secret value, user account or private installation detail is needed in the
request.

## 13. Implementation Impact

No OAuth code change is approved by this analysis.

Depending on vendor response, a later design may choose:

### Vendor-approved public client

- add PKCE state and verifier attributes;
- remove client-secret requirement;
- use registered callback delivery;
- migrate existing private credentials without losing tokens unnecessarily;
- retest code exchange, refresh, restart and reauthorization.

### Vendor-approved confidential client

- define who owns and operates the confidential component;
- keep the secret outside distributed module source;
- use a registered Symcon callback or OAuth bridge;
- define availability, privacy and incident responsibility;
- avoid forwarding refresh tokens through unnecessary systems.

### Vendor-approved bring-your-own-client

- document registration and redirect steps;
- validate client values without logging them;
- preserve local credential ownership;
- provide migration and revocation guidance.

### No vendor-supported model

- retain private-pilot status;
- do not create broad installation instructions;
- do not submit to the Symcon Store;
- continue only controlled use and defect maintenance;
- keep pilot tags distinct from public `v*` releases.

## 14. Store Preparation Impact

The roadmap correction from step 51 remains in force:

- Store work is preparation only at this stage;
- no Store entry or submission is created;
- complete mower command integration remains a prerequisite for final Store
  readiness under this case-study roadmap;
- OAuth feasibility is an independent prerequisite that must also pass;
- current official Store requirements must be rechecked after command
  integration is complete.

Even a positive vendor response would not authorize immediate Store setup.

## 15. Variable Compatibility Impact

This analysis changes no Account or Device variable.

Future OAuth migration must preserve:

- existing Device variable Idents and types;
- user-configured Archive Control logging;
- existing Account diagnostic variables where compatible;
- explicit migration of authentication state rather than instance recreation.

Changing authentication architecture must not require users to recreate the
Navimow Device instance or lose battery and state history.

## 16. Risk Register Update

| Risk | Current rating | Control |
| --- | --- | --- |
| unauthorized client credential redistribution | release blocker | never embed or document observed value |
| no public client registration | release blocker | vendor clarification or registration required |
| no established PKCE support | high | require vendor confirmation before design |
| manual callback handling | medium for private pilot | full URL, state check, supervised setup |
| property/attribute confidentiality overstated | high for public claims | document administrator and backup trust boundary |
| undocumented API compatibility | high | vendor support statement and change policy |
| cross-platform use of HA registration | high | explicit permission required |
| credential revocation | medium | existing reset/reauth behavior; vendor semantics needed |
| private pilot regression | controlled | immutable tag and existing observation harness |

## 17. Architecture Decisions

### AD-NAV-138: Do not redistribute the observed upstream credential

**Decision:** Keep the static credential value out of module source,
documentation, fixtures and setup instructions.

**Rationale:** Public upstream visibility does not establish third-party reuse
permission or confidential handling.

**Consequence:** Broad release remains blocked without another approved client
model.

### AD-NAV-139: Classify broad release as vendor-blocked

**Decision:** Use `blocked pending vendor support` for broad release while
allowing the existing controlled private pilot to continue.

**Rationale:** Technical interoperability is proven, but authorized onboarding
for new users is not.

**Consequence:** No `v*` release or Store submission may be prepared as an
actual publication.

### AD-NAV-140: Prefer public-client PKCE when supported

**Decision:** Prefer a vendor-registered public client using authorization code
plus PKCE over distributing a shared secret.

**Rationale:** This aligns the security model with a module whose source and
installation are under user control.

**Consequence:** PKCE remains a design candidate, not an implementation task,
until Navimow confirms endpoint support.

### AD-NAV-141: Treat a Symcon OAuth bridge as an operated service

**Decision:** Evaluate a Connect/OAuth callback only together with client
ownership, secret custody, availability and privacy responsibility.

**Rationale:** A callback bridge is not merely a module helper; it becomes part
of the authentication trust and operational boundary.

**Consequence:** Native Symcon OAuth capability does not by itself make the
current flow public-ready.

### AD-NAV-142: Preserve private-pilot authentication state

**Decision:** Do not alter the working private OAuth implementation while
public feasibility is unresolved.

**Rationale:** The pilot is stable and its credentials remain local. A
speculative migration could force reauthorization or expose new failure paths.

**Consequence:** `pilot-0.1.0.2` remains the current operational snapshot.

## 18. Gate Decision

**Decision: NO-GO for broad public OAuth implementation and release.**

**Decision: GO for a vendor clarification package that contains no private
credential or installation data.**

The clarification may reference the official Home Assistant repository and
ask for an IP-Symcon-specific client, public-client PKCE support or explicit
cross-platform permission. It must not send the working secret, tokens,
callback captures or account identifiers.

## 19. Recommended Next Step

Create:

```text
53-navimow-oauth-vendor-clarification-plan.md
```

That step should prepare the exact technical questions, evidence-safe context,
contact routes, acceptable response criteria and roadmap branches while making
no external contact until the user approves the final message.
