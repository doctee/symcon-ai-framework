# 256 Navimow MQTT Recovery PR Review and Merge Decision

**Case study:** Navimow native IP-Symcon module

**Status:** Pull-request review passed without blocking findings; merge is
recommended only after separate explicit authorization

**Date:** 2026-08-04

**Scope:** Review draft PR 22 against canonical SAEF `main`, assess its checks,
productive delta and safety boundaries, and issue a merge recommendation
without merging or accessing the standalone module or Symcon

## 1. Review Result

No blocking correctness, security, privacy or SAEF-boundary finding was found
in the reviewed pull-request head.

```text
pull request:       doctee/symcon-ai-framework#22
reviewed head:      4ff9028dd38113700e1fe0c9df7211031515fd5e
reviewed base:      7358fa5878869ff43ad30282f744bf78950c081a
state:              open, draft
mergeability:       MERGEABLE
merge state:        CLEAN
review decision:    no external review submitted
CI validate checks: 2 of 2 successful
```

The PR is suitable for integration into the SAEF knowledge-base mainline. This
is not a production-release decision. The standalone module repository,
metadata gate, Symcon update, MQTT activation and mower-command gates remain
closed.

## 2. Findings

### Blocking findings

None.

### Non-blocking residual risks

1. The effective PR is large because it preserves the complete numbered SAEF
   evidence history. The productive distribution is a separately reviewed
   17-file subset, but future review should continue to distinguish those two
   scopes.
2. Navimow cloud and WSS behavior remains vendor-controlled and not covered by
   a public stability guarantee. The private observations therefore support a
   bounded pilot, not a general availability claim.
3. The prior private MQTT pilots observed multiple recovered transport
   episodes. Episode accounting is now corrected and bounded, but the evidence
   does not justify permanent unattended MQTT operation.
4. The PR has no independent GitHub review. Repository CI and the local review
   pass, but a maintainer remains responsible for the explicit merge decision.
5. Public OAuth/vendor clarification, Store readiness and complete mower-command
   coverage remain unresolved and outside this merge decision.

## 3. Pull-Request Scope

The reviewed head contains 210 effective paths against the unchanged base:

```text
paths:       210
insertions: 70489
deletions:     12
```

The productive portion is limited to 17 files below
`case-studies/navimow/distribution/`. The remainder consists of the case-study
history, sanitized fixtures, offline tests, tools and supporting documentation.

Adding this review report produces the expected final PR scope:

| Review bucket | Paths |
|---|---:|
| numbered SAEF reports | 164 |
| Navimow case-study index | 1 |
| installable distribution | 17 |
| sanitized fixtures | 13 |
| forum documentation | 2 |
| offline tests | 11 |
| case-study tools | 3 |
| **Total** | **211** |

All effective paths remain below `case-studies/navimow/`. No deletion or
unrelated workstream path is introduced.

## 4. Productive Delta Review

The 17-file distribution delta was reviewed independently from the historical
reports.

### REST authority

- `NavimowDevice` still obtains public state through the Account REST path.
- MQTT hints schedule bounded targeted REST reconciliation.
- MQTT parsing does not write public mower variables directly.
- Commands remain on the existing REST command path.

### Receive-only MQTT boundary

- no MQTT publish call is present;
- no uplink topic or MQTT mower-command route is present;
- the Receiver implements only inbound `ReceiveData()` processing;
- the Receiver has no `RequestAction()`, variable registration or timer;
- retained messages and oversized or malformed envelopes are rejected;
- Account handoff requires an explicitly paired Account instance.

### Transport and credential boundary

- MQTT remains disabled by default through `EnableMqttShadow=false`;
- WSS endpoints require scheme `wss`, port 443, no URL credentials and bounded
  host, path and query components;
- subscriptions are exact QoS-0 topics derived from discovered device IDs;
- wildcard topics, duplicate topics and subscription-shape drift are rejected;
- adoption requires an inactive, credential-empty native Core chain;
- bearer, username and password presence is represented only as redacted shape
  metadata;
- Receiver debug output contains only a bounded result label and byte count.

### Existing public contract

- the 14-variable device contract remains unchanged;
- the 5 archive-logging contracts remain unchanged;
- `VehicleState` remains REST-authoritative;
- no MQTT-specific public variable or action is added;
- no command is retried through MQTT.

## 5. Validation Evidence

The integrated candidate had already passed before branch publication:

- focused REST and MQTT fixture suites;
- MQTT lifecycle, recovery and pilot-accounting suites;
- private pilot harness checks;
- distribution and metadata checks;
- PHP syntax and PHPStan;
- complete repository `make check`;
- privacy and receive-only source review;
- byte-exact standalone candidate refreeze.

The published reviewed head then passed both GitHub `validate` runs:

```text
CI run 30920380565: SUCCESS
CI run 30920371519: SUCCESS
```

`git diff --check origin/main..HEAD` also passed during this review.

The review-report-only closure push must pass the same CI before any merge is
authorized. A productive change after the reviewed head invalidates this
decision and requires renewed focused and complete validation.

## 6. Merge Decision

```text
SAEF-main merge recommendation: YES, conditional
current merge authorization:    NO
production release approval:    NO
standalone publication approval:NO
Symcon or MQTT approval:        NO
```

The condition for a separate merge authorization is:

1. the report-only closure commit is the PR head;
2. the final PR scope is exactly 211 Navimow-only paths;
3. GitHub reports `MERGEABLE` and `CLEAN`;
4. all required checks for that head are successful;
5. no new productive review finding appears.

Once those conditions are verified, the PR may be taken out of draft and
merged only under a new explicit user gate.

## 7. Architecture Decisions

### AD-NAV-984: Review productive and historical scopes separately

The large evidence history must not obscure the smaller 17-file runtime delta.
Both scopes are reviewed, but productive risk is assessed on the distribution.

### AD-NAV-985: Accept the receive-only implementation boundary

The absence of publish, action and direct public-state paths is a merge
precondition and is satisfied by the reviewed head.

### AD-NAV-986: Keep REST authoritative after merge

MQTT remains a diagnostic and reconciliation hint source. Merging this work
does not promote it to public state authority.

### AD-NAV-987: Treat recovered episodes as residual pilot risk

Correct episode accounting improves evidence quality but does not establish
permanent transport stability.

### AD-NAV-988: Require disabled-by-default operation

The MQTT property default must remain false. Any future default change requires
a new architecture and live-safety decision.

### AD-NAV-989: Keep credentials out of public diagnostics

Only credential-presence shape may be projected. Values, topics, identities and
installation metadata remain private.

### AD-NAV-990: Do not equate CI success with release readiness

CI supports mainline integration of the case study. It does not close OAuth,
Store, standalone publication or live-operation gates.

### AD-NAV-991: Require a fresh final-head check

The report-only closure changes the PR head. Its exact head and checks must be
verified before requesting merge authorization.

### AD-NAV-992: Keep the PR draft during this step

Review and recommendation do not silently convert the PR into an approved
merge action.

### AD-NAV-993: Make merge a separate explicit mutation

Neither this report nor its publication authorizes changing canonical `main`.

## 8. Gate Status

| Gate | Status |
|---|---|
| Gate II branch push and draft PR | PASS |
| productive-delta review | PASS, no blocking findings |
| privacy and receive-only review | PASS |
| reviewed-head GitHub checks | PASS, 2 of 2 |
| report-only final-head verification | PENDING CLOSURE PUSH |
| PR merge recommendation | CONDITIONAL YES |
| PR ready-state change | CLOSED |
| PR merge | CLOSED |
| canonical-main verification | CLOSED |
| standalone publication | CLOSED |
| metadata validation | CLOSED |
| Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |
| mower command | NOT PLANNED |

## 9. Next Step

After the report-only closure push and final check verification, the next gated
artifact is:

```text
257-navimow-mqtt-recovery-pr-merge-and-canonical-verification.md
```

That step requires separate explicit authorization to take PR 22 out of draft
and merge it. It may then verify canonical SAEF `main`, but it must not publish
the standalone module or access Symcon.
