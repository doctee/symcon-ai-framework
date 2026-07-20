# 78 Stop Vendor Response and Gate Decision

**Case study:** Navimow native IP-Symcon module
**Status:** No response yet; waiting window active; Stop remains blocked
**Date:** 2026-07-12
**Scope:** Review official SDK issue 22 and preserve the response gate

## 1. Purpose

This step reviews the current public state of the official SDK inquiry opened
in `77-stop-vendor-and-upstream-inquiry-execution.md` and applies the response
classification from `76-stop-vendor-and-upstream-clarification-plan.md`.

It determines:

- whether an actionable maintainer response exists;
- whether any S1 through S8 response class can be assigned;
- whether Stop may advance to capture planning;
- whether a clarification or follow-up is due;
- which engineering gates remain unchanged.

No issue comment, follow-up, credential use, API actuation, fixture, productive
code, Symcon configuration or mower command occurs in this step.

## 2. Reviewed Inquiry

Official issue:

```text
https://github.com/segwaynavimow/navimow-sdk/issues/22
```

Title:

```text
Clarify MowerCommand.STOP task and state semantics
```

Created:

```text
2026-07-12T17:54:38Z
```

The inquiry asks for public support classification, pause-versus-end-task
meaning, terminal state, Resume/Start behavior, progress retention,
`alreadyInState` semantics, model/firmware scope and documentation intent.

## 3. Current Remote State

Independent GitHub issue and comment reads returned:

| Field | Current result |
| --- | --- |
| state | open |
| author | `doctee` |
| comments | 0 |
| labels | none |
| assignees | none |
| milestone | none |
| issue update after creation | none |
| maintainer response | absent |

The title and body remain unchanged from the approved, privacy-reviewed
publication.

## 4. Response Evidence

There is currently no response to classify.

Therefore, the issue provides no new evidence for:

- public Stop support;
- task pause or task termination;
- raw or normalized terminal state;
- Resume or Start behavior after Stop;
- progress retention;
- `alreadyInState` handling;
- model or firmware restrictions;
- intentional SDK client/README omission.

The static evidence from step 75 remains the strongest available source:

- official enum and low-level transport mapping are present;
- high-level and documentation exposure are absent;
- terminal semantics remain unknown.

## 5. Classification Decision

No S1 through S6 or S8 response class applies because no response exists.

S7 `No response` is also **not yet assigned**. Step 76 defines S7 only after:

1. the initial 14-day waiting period;
2. one bounded follow-up;
3. another 14-day waiting period.

The inquiry was opened on the same date as this review. Treating immediate
silence as S7 would violate the approved contact cadence.

Current classification:

```text
PENDING-WINDOW
```

This is a process state, not a vendor response class and not evidence of
support or rejection.

## 6. Timing Gate

| Milestone | Earliest date | Current decision |
| --- | --- | --- |
| initial inquiry | 2026-07-12 | completed |
| one-time follow-up | 2026-07-26 | not yet permitted |
| no-response classification | 2026-08-09 | not yet permitted |

No reminder, duplicate issue or alternate-route contact is due now.

If a maintainer responds before 2026-07-26, the response may be reviewed
immediately in an amendment or successor decision step.

## 7. Gate Matrix

| Gate | Decision | Reason |
| --- | --- | --- |
| official transport mapping | PASS | manufacturer SDK source |
| semantic contract | BLOCKED | no response or transition evidence |
| expected terminal state | BLOCKED | unknown |
| task lifecycle | BLOCKED | pause versus end task unresolved |
| Resume/Start recovery | BLOCKED | unknown |
| model/firmware applicability | BLOCKED | unknown |
| one-shot capture plan | NO-GO | safety contract incomplete |
| private Stop API write | NO-GO | no authorized procedure |
| fixture promotion | NO-GO | no real response/state evidence |
| productive implementation | NO-GO | verification target undefined |
| form/action publication | NO-GO | Stop remains intentionally absent |
| formal exclusion | DEFER | authoritative classification still pending |

## 8. Runtime Impact

The current tagged pilot remains unchanged:

```text
pilot-0.1.0.3
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

No account allowlist, device action, profile, variable, archive setting,
fixture or test is changed by the inquiry or this review.

## 9. Operator Guidance

Until an actionable response exists:

- do not send cloud Stop through a script, terminal or modified module;
- do not use the mower to discover the terminal state;
- do not equate cloud Stop with Pause, End Task or physical STOP;
- do not create a synthetic Stopped state;
- do not infer support from the enum alone;
- do not post private model, firmware or account details to issue 22;
- continue private-pilot use only within the tagged Pause/Resume/Dock scope.

## 10. Response Interruption Rule

When a new issue comment or authoritative source change appears:

1. read the exact public content;
2. identify whether the author represents the SDK/vendor or is a third party;
3. classify it against S1 through S8;
4. separate explicit statements from inference;
5. record remaining unanswered safety questions;
6. authorize at most a capture-planning step, never immediate actuation;
7. preserve privacy and no-retry boundaries.

A code change that removes or documents Stop may be stronger evidence than an
informal comment and must be reviewed by exact revision.

## 11. Architecture Decisions

### AD-NAV-269: Distinguish pending silence from no response

**Decision:** Use `PENDING-WINDOW` while the approved waiting period is still
active.

**Rationale:** Maintainers require a reasonable opportunity to respond.

**Consequence:** Immediate silence is neither support, rejection nor S7.

### AD-NAV-270: Preserve the bounded contact cadence

**Decision:** Post no follow-up before 2026-07-26.

**Rationale:** The timing contract prevents noisy or inconsistent external
contact.

**Consequence:** Issue 22 remains the only canonical Stop inquiry.

### AD-NAV-271: Keep static and semantic evidence separate

**Decision:** Retain official transport PASS while semantic readiness remains
blocked.

**Rationale:** A correct payload does not define a safe terminal verifier.

**Consequence:** No implementation can be built around an inferred state.

### AD-NAV-272: Avoid premature formal exclusion

**Decision:** Do not exclude Stop solely because no same-day response exists.

**Rationale:** The approved process explicitly includes two waiting periods.

**Consequence:** Formal exclusion remains available after S5 or mature S7
classification.

### AD-NAV-273: Allow immediate review of real new evidence

**Decision:** Do not wait until the follow-up date if a maintainer responds
earlier.

**Rationale:** The cadence limits outgoing reminders, not incoming evidence
processing.

**Consequence:** A future response can reopen the decision promptly without
lowering capture safety gates.

## 12. Decision

**Issue publication state: HEALTHY AND OPEN.**

**Maintainer/vendor response: NOT YET RECEIVED.**

**Current classification: PENDING-WINDOW.**

**Follow-up now: NO-GO.**

**Stop capture and implementation: REMAIN NO-GO.**

**Formal Stop exclusion: DEFERRED.**

## 13. Recommended Next Step

Monitor [SDK issue 22](https://github.com/segwaynavimow/navimow-sdk/issues/22)
for an actual response.

If a response arrives, update the response decision with an S1 through S8
classification before any further engineering work.

If no response arrives, the next permitted action is
`80-stop-vendor-inquiry-follow-up.md` on or after 2026-07-26. Step 79 was
subsequently assigned to the independently approved adaptive-polling work.
The Stop follow-up step must
recheck duplicates and source changes, post exactly one concise reminder and
keep Stop capture and implementation blocked.
