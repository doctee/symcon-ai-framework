# 81 OAuth Vendor Response and Release Gate Review

**Case study:** Navimow native IP-Symcon module
**Status:** Official evaluation acknowledged; public OAuth and broad release remain blocked
**Date:** 2026-07-27
**Scope:** Classify the official response in NavimowHA issue 82

## 1. Purpose

This step reviews the first official response to the OAuth inquiry published in
`54-navimow-oauth-vendor-inquiry-execution.md`.

It determines:

- whether the response author is authoritative;
- which response class from step 53 applies;
- whether a supported OAuth client model is now known;
- whether public OAuth architecture, Store preparation or broad release may
  advance;
- whether the existing private pilot may continue unchanged.

No OAuth credential, module source, Store entry, Symcon configuration or mower
state is changed.

## 2. Public Response

Canonical inquiry:

```text
https://github.com/segwaynavimow/NavimowHA/issues/82
```

Response:

```text
https://github.com/segwaynavimow/NavimowHA/issues/82#issuecomment-5053954261
```

Response timestamp:

```text
2026-07-23T03:24:15Z
```

The comment was posted by the official `segwaynavimow` account. It acknowledges
the request, states that integration with IP-Symcon requires evaluation and
promises a later result.

The issue remains open. No later response was present during the review on
2026-07-27.

## 3. Authority Assessment

The response satisfies the source-authority requirement because it originates
from the official organization account in the official Navimow integration
repository.

It is authoritative evidence that:

- the request reached the responsible project channel;
- cross-platform integration is subject to vendor evaluation;
- no final decision has yet been communicated.

It is not authoritative evidence that:

- IP-Symcon integration is approved;
- continued API use is contractually permitted;
- a client registration will be issued;
- Home Assistant client credentials may be reused;
- PKCE, a confidential bridge or bring-your-own-client is supported.

## 4. Response Matrix

| Requested clarification | Response result |
| --- | --- |
| independent IP-Symcon integration permitted | unanswered; evaluation pending |
| dedicated client registration process | unanswered |
| public client with PKCE | unanswered |
| confidential server-side client | unanswered |
| redirect URI rules | unanswered |
| token refresh semantics | unanswered |
| terms and rate limits | unanswered |
| model and branding scope | unanswered |
| responsible partner contact | unanswered |

The response therefore cannot satisfy Class A, B, C, D or E from step 53.

## 5. Classification

The response is classified as:

```text
Class F: ambiguous or partial response
Process state: VENDOR-EVALUATION-PENDING
```

This is more informative than silence because the vendor explicitly confirms
an evaluation process. It still provides no usable OAuth architecture or
distribution permission.

The roadmap result remains:

```text
BROAD RELEASE REMAINS BLOCKED
```

## 6. OAuth Gate Matrix

| Gate | Decision | Reason |
| --- | --- | --- |
| official request received | PASS | official account acknowledged inquiry |
| independent integration permission | BLOCKED | evaluation not complete |
| dedicated OAuth client | BLOCKED | no registration offered |
| public client with PKCE | BLOCKED | unsupported by current evidence |
| confidential bridge | BLOCKED | no client or callback agreement |
| bring-your-own-client | BLOCKED | no registration process |
| Home Assistant client reuse | PROHIBITED | no cross-platform permission |
| public OAuth implementation | NO-GO | client model unknown |
| credential publication | PROHIBITED | unchanged security boundary |
| broad public release | NO-GO | vendor-backed auth model absent |
| Store submission | NO-GO | OAuth and command gates incomplete |

## 7. Private Pilot Impact

The response does not request suspension, removal, credential rotation or
disclosure of private account data.

The controlled private pilot may therefore continue under its existing
boundary:

- installation-specific OAuth configuration;
- no distributed client secret;
- no claim of vendor support;
- no public onboarding promise;
- no Store submission;
- no widening of model or firmware claims.

The working private OAuth implementation must not be changed merely to
anticipate a possible vendor decision.

## 8. Store and Release Impact

Preparatory Store work remains limited to preserving a requirements backlog.
No Store entry, submission package or final readiness review is authorized.

The user's standing sequence remains:

1. obtain a supportable vendor-backed OAuth model;
2. complete or explicitly exclude all intended mower commands;
3. revalidate current Store requirements;
4. only then consider Store setup or broad release.

The response closes none of these gates.

## 9. Contact Cadence

No immediate reply or reminder is useful because the vendor explicitly states
that evaluation is in progress.

The issue should be monitored without additional contact. A review may occur
immediately when a substantive answer arrives. If no result arrives, the
project may reassess the clarification route on or after 2026-08-20, four weeks
after the vendor acknowledgement. Any new outbound contact requires a separate
SAEF decision and explicit user approval.

## 10. Evidence and Validation Boundary

All evidence is public and credential-free. No private installation artifact
is required because this step performs no live mutation and reads no private
runtime state.

No fixture, executable expectation or changelog entry changes:

- the OAuth implementation is unchanged;
- the private-pilot capability is unchanged;
- public OAuth and Store remained blocked before and after the response.

Validation after documentation closure passed:

- Navimow REST/auth checks;
- all 33 deterministic pilot harness cases;
- Navimow distribution validation;
- complete repository `make check`, including PHPStan and PHPCS.

## 11. Architecture Decisions

### AD-NAV-280: Treat evaluation acknowledgement as Class F

**Decision:** Record the official reply as a partial response rather than an
approval or rejection.

**Rationale:** The vendor confirms process but answers none of the technical or
permission questions.

**Consequence:** Broad release remains blocked without misrepresenting the
vendor's position.

### AD-NAV-281: Preserve the working private OAuth implementation

**Decision:** Make no speculative OAuth code change while evaluation is
pending.

**Rationale:** No target client, redirect or secret-custody model exists yet.

**Consequence:** The private pilot stays operational and auditable.

### AD-NAV-282: Do not convert Store preparation into setup

**Decision:** Keep Store activity at backlog level until OAuth and command
gates pass.

**Rationale:** An acknowledged evaluation is not a distributable
authentication model.

**Consequence:** No Store identity or submission is created prematurely.

### AD-NAV-283: Give vendor evaluation a bounded quiet period

**Decision:** Monitor without another message and reassess no earlier than four
weeks after acknowledgement.

**Rationale:** The vendor explicitly promised a later result and should not
receive an immediate duplicate request.

**Consequence:** The next possible route decision is 2026-08-20 unless a
substantive answer arrives first.

## 12. Decision

**Official response authenticity: PASS.**

**Response classification: CLASS F / VENDOR-EVALUATION-PENDING.**

**Private pilot continuation: GO under unchanged boundary.**

**Public OAuth implementation: NO-GO.**

**Store setup and broad release: NO-GO.**

## 13. Recommended Next Step

Monitor issue 82 for the promised evaluation result. Continue only independent
private-pilot engineering tracks that neither distribute OAuth credentials nor
claim vendor approval.
