# 80 Stop Vendor Inquiry Follow-up

**Case study:** Navimow native IP-Symcon module
**Status:** One-time follow-up published; second waiting window active; Stop remains blocked
**Date:** 2026-07-27
**Scope:** Execute and verify the single permitted follow-up in official SDK issue 22

## 1. Purpose

This step executes the bounded follow-up authorized by
`76-stop-vendor-and-upstream-clarification-plan.md` and scheduled by
`78-stop-vendor-response-and-gate-decision.md`.

It:

- rechecks the canonical issue for a maintainer response;
- revalidates the official Stop mapping and documentation gap;
- searches for a duplicate semantic answer;
- posts exactly one concise follow-up;
- verifies the public comment independently;
- starts the second waiting window;
- preserves every Stop safety and implementation gate.

No Navimow API request, OAuth operation, Symcon mutation, fixture change,
productive PHP change or mower command occurs in this step.

## 2. Pre-Publication Revalidation

Immediately before publication, official SDK issue 22 was:

| Field | Result |
| --- | --- |
| state | open |
| comments | 0 |
| maintainer response | absent |
| update after creation | absent |
| duplicate issue | not found |

The official SDK `main` revision remained:

```text
6596aa0a65dcf05ed248da87c36975f2ea236ab8
```

Current public source retained:

- `MowerCommand.STOP`;
- the low-level `StartStop` request with JSON boolean `on=false`;
- no high-level `MowerClient` Stop wrapper;
- no Stop entry in the README command list;
- no documented terminal state or task-lifecycle contract.

A repository issue search found issue 21 mentioning start/stop integration as a
use case, but it provides no Stop terminal-state, task-lifecycle or Resume
semantics. It is not a duplicate answer.

The evidence therefore remained unchanged: transport mapping exists, while the
semantic contract required for safe verification is absent.

## 3. Timing Gate

The original inquiry was published on 2026-07-12. The approved earliest
follow-up date was 2026-07-26. Publication on 2026-07-27 therefore satisfies
the minimum 14-calendar-day waiting period.

No earlier reminder, duplicate issue or alternate-route contact was made.

## 4. Published Follow-up

Exactly one comment was published:

> Friendly follow-up after the initial waiting period.
>
> We are still keeping cloud Stop disabled and have not sent it. Could a
> maintainer please confirm whether `MowerCommand.STOP` / `StartStop` with
> `{"on": false}` is a supported operation, and whether it pauses or ends the
> current mowing task?
>
> The expected post-command state and whether Resume can continue the same task
> are the key safety points we need before considering a supervised, no-retry
> capture.
>
> Thank you.

Public comment:

```text
https://github.com/segwaynavimow/navimow-sdk/issues/22#issuecomment-5092369674
```

Publication timestamp:

```text
2026-07-27T14:07:22Z
```

The text contains no credentials, tokens, account or device identifiers,
private URLs, payloads, logs, garden data, Symcon ObjectIDs or unsupported
behavior claims.

## 5. Publication Path

The GitHub connector write attempt returned:

```text
403 Resource not accessible by integration
```

It created no comment. The authenticated GitHub CLI then performed the one
authorized write. This fallback did not increase the intended action count:

```text
intended comments: 1
created comments: 1
```

No retry was sent after successful publication.

## 6. Independent Read-back

Independent connector read-back confirmed:

| Check | Result |
| --- | --- |
| issue state | open |
| total comments | 1 |
| comment author | expected project account |
| comment ID | `5092369674` |
| exact body | matched |
| duplicate follow-up | absent |
| maintainer response after publication | not yet present |

The issue remains the single canonical Stop clarification thread.

## 7. Classification

The current process classification is:

```text
PENDING-SECOND-WINDOW
```

This is not response class S7 yet. The approved cadence requires another 14
calendar days after the actual follow-up before mature no-response
classification.

Because the comment was published on 2026-07-27, the earliest no-response
decision date is:

```text
2026-08-10
```

An actionable maintainer response may be reviewed immediately before that
date.

## 8. Gate Matrix

| Gate | Decision | Reason |
| --- | --- | --- |
| official transport mapping | PASS | unchanged official source |
| semantic contract | BLOCKED | no maintainer response |
| expected terminal state | BLOCKED | unknown |
| Pause versus task termination | BLOCKED | unknown |
| Resume after Stop | BLOCKED | unknown |
| model/firmware scope | BLOCKED | unknown |
| private Stop capture | NO-GO | verifier contract incomplete |
| productive Stop implementation | NO-GO | terminal behavior undefined |
| public Stop action | NO-GO | support remains unconfirmed |
| second follow-up | PROHIBITED | one-time cadence exhausted |
| formal exclusion | DEFER | second waiting window active |

## 9. Runtime and Release Impact

The current immutable pilot remains:

```text
pilot-0.1.0.4
```

Enabled commands remain:

```text
Pause
Resume
Dock
```

Disabled commands remain:

```text
Stop
Start
```

No module file, variable, profile, archive setting, OAuth state, polling
configuration or Symcon object changed. No module publication, tag or Symcon
update is required.

## 10. Evidence Closure

Exact publication and verification evidence is retained privately in:

```text
private/navimow-stop-follow-up-20260727.local.json
```

The private artifact records authorization scope, preflight state, the failed
connector write channel, the successful single CLI write, independent
read-back and the zero device-action count.

No regression fixture changes are required because this step changes neither
the API contract nor runtime behavior. No changelog entry is required because
the module capability and deployed rollout state remain unchanged. This
sanitized report and the case-study index are the current-status artifacts.

## 11. Validation

After evidence closure, the repository passed:

| Check | Result |
| --- | --- |
| private evidence JSON parsing | PASS |
| private evidence ignore rule | PASS |
| public diff whitespace validation | PASS |
| Navimow REST/auth checks | PASS |
| deterministic Navimow pilot harness | PASS, 33 cases |
| Navimow distribution validation | PASS |
| complete repository `make check` | PASS |

The complete gate includes syntax checks, generated-artifact checks, helper and
case-study tests, PHPStan and PHPCS. No runtime fixture changed as a result of
the external inquiry.

## 12. Architecture Decisions

### AD-NAV-276: Exhaust the one-time follow-up without widening scope

**Decision:** Ask only for the minimum semantic contract needed to evaluate a
future supervised capture.

**Rationale:** Repeating every original question would add noise without
increasing the chance of an actionable answer.

**Consequence:** The issue remains concise and Stop remains disabled.

### AD-NAV-277: Base the second window on actual publication time

**Decision:** Count the second 14-day period from 2026-07-27.

**Rationale:** The follow-up occurred one day after its earliest permitted
date.

**Consequence:** Mature no-response classification is not permitted before
2026-08-10.

### AD-NAV-278: Treat the connector rejection as a zero-write attempt

**Decision:** Record the connector failure separately from the successful CLI
publication.

**Rationale:** A transport or authorization failure must not be confused with
a duplicated external action.

**Consequence:** Exactly one public follow-up exists.

### AD-NAV-279: Keep silence non-authoritative

**Decision:** Do not infer Stop support, rejection or behavior from the absence
of a reply during the second window.

**Rationale:** Silence supplies no terminal-state or task-lifecycle evidence.

**Consequence:** Capture and implementation remain NO-GO.

## 13. Decision

**Pre-publication revalidation: PASS.**

**One-time follow-up publication: PASS.**

**Independent read-back: PASS.**

**Current classification: PENDING-SECOND-WINDOW.**

**Stop capture and implementation: REMAIN NO-GO.**

## 14. Recommended Next Step

Monitor [SDK issue 22](https://github.com/segwaynavimow/navimow-sdk/issues/22)
without posting another reminder.

If an actionable response arrives, create a successor response-and-gate
decision immediately.

If no actionable response exists on or after 2026-08-10, create
`81-stop-no-response-classification-and-scope-decision.md` to assign mature S7
and decide whether Stop remains indefinitely excluded from the module scope.
