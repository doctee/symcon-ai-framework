# 58 Pause Command Private Capture Report

**Case study:** Navimow native IP-Symcon module
**Status:** Private Pause capture passed; productive implementation remains gated
**Date:** 2026-07-12
**Scope:** Evaluate one supervised Running-to-Paused REST transition

## 1. Purpose

This report evaluates the private live run defined by
`57-pause-command-private-capture-procedure.md`.

The run was limited to one Pause command. It did not test Resume, Stop, Start,
Dock, MQTT/WSS communication or productive IP-Symcon action handling.

## 2. Execution Result

The private evidence shows the following bounded sequence:

| Stage | Observed result |
| --- | --- |
| current pre-state check 1 | `isRunning` |
| current pre-state check 2 | `isRunning` |
| local command-attempt marker | present before evaluation |
| Pause command attempts | exactly one procedure-controlled attempt |
| top-level API code | `1` |
| nested command status | `SUCCESS` |
| nested command error | none |
| first bounded post-state read | `isPaused` at 2 seconds |

The evidence therefore satisfies the capture acceptance criteria:

- Running was current and stable across two consecutive reads;
- the one-shot safety marker proves that another run cannot silently repeat the
  command in the same private output set;
- HTTP 200 alone was not used as success evidence;
- the nested command result accepted the command;
- a later read independently confirmed the Paused state;
- no command retry was performed;
- no cleanup command was sent by the capture tool.

## 3. Command Contract Evidence

The statically validated request contract remains:

```json
{
  "commands": [
    {
      "command": "PauseUnpause",
      "params": {
        "on": false
      }
    }
  ]
}
```

The live response confirms that this contract is accepted for a mower whose
current state is `isRunning`. The separate status response confirms that the
resulting device state is `isPaused`.

This is evidence for Pause only. It does not prove that the inverse boolean is
a safe or supported Resume command.

## 4. Timing Finding

The first scheduled read-only observation, two seconds after command dispatch,
already reported `isPaused`.

For the future productive implementation this supports a short asynchronous
verification window. It does not justify treating the command response itself
as the final device state. The module must continue to distinguish:

- command acceptance;
- observed state transition;
- verification timeout or ambiguity.

No general service-level timing guarantee can be inferred from one live run.

## 5. Sanitized Evidence

Private sanitized candidates were generated for:

- both Running pre-state reads;
- the neutral command response;
- the accepted command response;
- the two-second post-state read;
- the canonical Paused-state observation.

All candidates:

- parse as valid JSON;
- passed the targeted secret and identifier scan;
- remain under the Git-ignored private capture directory;
- contain no evidence that requires publishing raw payloads.

Raw captures, OAuth material and installation-specific identifiers remain in
`private/` and must not be committed.

The candidates are not yet canonical case-study fixtures. Promotion requires a
separate structural and privacy review followed by deliberate copying into the
public fixture set.

## 6. Safety Record

The operator completed the explicitly supervised procedure. The tool itself:

- required stable Running evidence and typed confirmation;
- sent one Pause command only;
- did not retry the command;
- did not send Resume, Stop, Start or Dock;
- stopped after observing `isPaused`.

The capture does not independently record garden conditions or the operator's
later physical cleanup. Those remain operational observations rather than API
evidence and must be recorded explicitly if required by the next review.

## 7. Risks and Limits

| Risk or limitation | Consequence |
| --- | --- |
| single mower and single live run | payload support is not yet broadly established |
| private, undocumented cloud API | behavior may change without notice |
| no Resume evidence | Pause must not imply a reversible toggle contract |
| no rejected or ambiguous Pause case | error mapping remains incomplete |
| no productive Symcon execution | module lifecycle and action safety are untested |
| public OAuth remains unresolved | broader release remains vendor-blocked |

## 8. Architecture Decisions

### AD-NAV-166: Pause acceptance and Paused observation are separate evidence

The nested `SUCCESS` proves command acceptance. A subsequent current
`isPaused` read proves the device transition. Productive logic must preserve
this distinction.

### AD-NAV-167: Pause is not modeled as an inferred toggle

Only `PauseUnpause` with boolean `false` has live evidence. The inverse value
must pass its own Resume evidence and safety gate before use.

### AD-NAV-168: Existing variable identity remains stable

Future command work must extend actions and diagnostics without recreating or
renaming existing device variables. This protects enabled IP-Symcon archive
logging and accumulated history.

### AD-NAV-169: Private evidence is promoted deliberately

Sanitized private candidates do not become public fixtures automatically. A
separate review must validate structure, placeholders and data minimization.

## 9. Readiness Decision

The private Pause evidence capture is **passed**.

Productive Pause implementation is still **No-Go** in this step. The evidence
is sufficient to begin fixture validation and implementation-readiness review,
but not to bypass the independent SAEF gate or add PHP action code directly.

## 10. Recommended Next Step

Create SAEF step
`59-pause-command-fixture-validation-and-implementation-readiness.md` to:

1. compare sanitized candidates with the established REST fixture schema;
2. perform a deliberate privacy review and promote only minimal fixtures;
3. define Pause action semantics, eligibility and diagnostics;
4. define asynchronous verification and timeout behavior;
5. verify preservation of all existing variable Idents and archive history;
6. issue the explicit Go/No-Go decision for productive Pause implementation.
