# 28 Dock Transition Verification Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Implemented locally, publication pending
**Date:** 2026-07-09
**Scope:** Dock command verification in the canonical case-study distribution

## 1. Purpose

This step implements the design from
`27-dock-transition-verification-design.md`.

The goal is to make the Dock command safe for real return-to-station
transitions where `isDocking` can remain active for several minutes.

## 2. Implementation Boundary

Changed productive distribution file:

```text
distribution/NavimowDevice/module.php
```

Changed local case-study test:

```text
tests/rest-client-auth.php
```

No OAuth handling, REST endpoint contract, command payload structure, public
module metadata or non-Dock command path was changed.

## 3. Implemented Behavior

The previous implementation performed one status read after five seconds and
reported `Verification Timeout` unless the mower was already `Docked`.

The new implementation:

- stores command start time;
- stores a fixed command verification deadline;
- stores an internal verification state;
- reads status after the initial five-second delay;
- treats `Docking` as valid progress;
- continues read-only status checks every 60 seconds while the mower is
  returning;
- verifies the command only when `Docked` is observed;
- reports timeout only after the 15-minute verification window expires;
- never resends the Dock command.

## 4. Internal State

New module attributes:

| Attribute | Purpose |
| --- | --- |
| `CommandStartedAt` | Records when the command verification window began. |
| `CommandDeadline` | Stores the fixed timeout boundary. |
| `CommandVerificationState` | Records the latest internal verification phase. |

Internal verification states:

| State | Meaning |
| --- | --- |
| `Idle` | No command verification is active. |
| `Accepted` | Command was accepted and awaits status evidence. |
| `Returning` | Status read observed `Docking`. |
| `Verified` | Status read observed `Docked`. |
| `AlreadyInState` | Cloud response indicated the mower was already docked. |
| `TimedOut` | Deadline elapsed without verified `Docked` state. |
| `Failed` | Command transport or command response failed. |

These attributes are module-owned internal diagnostics, not user-facing
configuration.

## 5. Timer Model

Implemented schedule:

| Phase | Timer interval |
| --- | --- |
| accepted command | 5 seconds |
| returning/docking | 60 seconds |
| expired deadline after restart | immediate next verification tick |

The fixed verification deadline is 900 seconds.

The timer runs only while `CommandActive` is true. On `ApplyChanges()`, an
active verification is resumed from the persisted state and deadline. The
module does not resend the command after restart or update.

## 6. Public Variable Behavior

The existing public variables and profiles remain unchanged.

Expected user-visible behavior:

| Condition | `LastCommandResult` |
| --- | --- |
| command sent | `Requested` |
| command accepted | `Accepted` |
| verification running | `Pending Verification` |
| docking observed | `Pending Verification` remains valid |
| docked observed | `Verified` |
| already docked | `Already In State` |
| deadline exceeded | `Verification Timeout` |
| command failed | `Failed` |

No new public variable is introduced in this step. The richer verification
state remains internal until there is user evidence that it should be exposed.

## 7. Safety Properties

The implementation preserves the command safety boundary:

- one Dock command per user action;
- no automatic command retry;
- repeated operations after acceptance are read-only `GetStatus` calls;
- active command verification blocks a second mower command;
- timeout wording avoids claiming that the mower physically failed.

This follows SAEF retry guidance: reads may be bounded and repeated; actuator
commands are not retried automatically.

## 8. Local Verification

Local checks performed:

```text
php -l distribution/NavimowDevice/module.php
php tests/rest-client-auth.php
php tools/validate-distribution.php
```

The fixture-backed test suite now also includes static safety checks:

- 15-minute timeout constant exists;
- 60-second read-only polling constant exists;
- the device module contains only one `SendCommand` path;
- `Docking` is treated as progress;
- the returning state is persisted.

## 9. Architecture Decisions

### AD-NAV-051: Keep long-running verification internal

**Decision:** Persist long-running verification as module attributes rather
than public variables.

**Rationale:** The values are diagnostic and recovery metadata owned by the
module. They are not part of the mower's physical state and should not clutter
the public MVP contract.

**Consequence:** The user sees stable command-result values while the module
retains enough state for restart-safe verification.

### AD-NAV-052: Do not add an `In Progress` profile value yet

**Decision:** Keep the existing command-result profile unchanged and use
`Pending Verification` while `isDocking` continues.

**Rationale:** This avoids a public contract change before direct Symcon
testing confirms that the additional distinction is useful.

**Consequence:** A future UX refinement can add a public `In Progress` state
without changing the command safety model.

## 10. Publication and Test Recommendation

Next steps:

1. publish the updated distribution to the dedicated module repository;
2. update the module in Symcon;
3. run an already-docked Dock test first;
4. run one supervised Running-to-Docked Symcon test;
5. document the result in:

```text
29-dock-transition-verification-symcon-test-report.md
```
