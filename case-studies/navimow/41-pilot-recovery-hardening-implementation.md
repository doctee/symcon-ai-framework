# 41 Pilot Recovery Hardening Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Implemented locally; deterministic recovery gate passed
**Date:** 2026-07-10
**Scope:** Close timeout, read-cadence and token-refresh recovery findings

## 1. Purpose

This step implements the corrections designed in
`40-pilot-recovery-hardening-design.md` for findings F-01 through F-03 from
the first harness run.

The implementation goal is a fully green deterministic recovery suite without
changing the public module contract or expanding mower command scope.

No live mower or private Symcon installation was used in this step.

## 2. Changed Productive Files

```text
distribution/NavimowDevice/module.php
distribution/NavimowAccount/module.php
```

Changed non-productive test file:

```text
tests/pilot-observation-harness.php
```

The CLI runtime double and fake clock from step 39 required no behavior change.

## 3. Public Contract

The following remain unchanged:

- account, configurator and device module IDs;
- configuration properties;
- public variables and profiles;
- action names;
- Dock request payload;
- REST endpoints;
- OAuth token storage attributes;
- module metadata;
- 15-minute verification window;
- Dock-only command scope.

The implementation adds only internal recovery state and scheduling behavior.

## 4. Device Status Result Refactoring

`RefreshStatus()` retains its public string return contract.

The existing status operation is now performed by a private structured method
that returns:

```text
success: boolean
message: sanitized string
```

The public method returns the message. Command verification uses the boolean
to determine whether `VehicleState` came from the current read.

This removes the previous dependency on stored state and timestamp comparison
when deciding current command progress.

## 5. F-01 Deadline Correction

The verification flow now:

1. stops the current timer;
2. rejects a missing deadline immediately;
3. rejects a deadline already passed before the verification starts;
4. permits one read when verification starts at or before the deadline;
5. accepts fresh `Docked` as `Verified`;
6. evaluates the deadline before any non-terminal transition;
7. accepts fresh `Docking` only before the deadline;
8. otherwise enters bounded `WaitingRead`.

A stored Docking value from an earlier successful read can no longer bypass
timeout after a later read failure.

An active command restored after its deadline schedules one immediate timer
tick. That tick terminates without another status read or timer loop.

## 6. F-02 WaitingRead and Timer Bound

New internal verification state:

```text
COMMAND_STATE_WAITING_READ = 7
```

It is selected after the first verification attempt when the current read:

- fails; or
- succeeds without Docked or Docking terminal/progress evidence.

Both `WaitingRead` and `Returning` use a 60-second read-only interval.
`Accepted` retains the initial five-second delay.

The scheduler now shortens the final interval to the exact remaining deadline:

```text
min(normal interval, remaining deadline milliseconds)
```

This prevents both five-second failure polling and up to one minute of timeout
drift.

## 7. F-03 Bounded Token-Refresh Retry

New internal constants:

```text
TOKEN_REFRESH_RETRY_DELAY_SECONDS = 60
TOKEN_REFRESH_RETRY_MAX_ATTEMPTS = 5
```

New persistent internal attribute:

```text
RefreshRetryCount
```

Implemented policy:

- only transport failures from `RefreshAuthentication()` increment the count;
- attempts one through four schedule another refresh after 60 seconds;
- attempt five stops the refresh timer;
- transport failures set `Offline` without requiring reauthorization;
- retry count survives `ApplyChanges()` and restart;
- restart resumes attempts one through four at 60 seconds;
- restart after attempt five remains stopped and Offline;
- successful refresh resets the count and normal scheduling;
- confirmed authentication rejection clears retry state and requires reauth;
- reset authentication clears retry state;
- authorization-code exchange never starts this retry sequence.

The policy remains transport-only. HTTP and general API retry behavior were
not expanded.

## 8. Harness Expansion

The deterministic harness now contains sixteen cases.

### `OBS-01`

- timeout after previously observed Docking;
- final Docked read at the exact deadline;
- missing deadline fails closed.

### `OBS-02`

- normal restart resumes and verifies Docked;
- restart after deadline terminates without post-deadline read.

### `OBS-03`

- transient failures recover to Docked;
- first failed read uses 60-second cadence;
- continuous failure reaches timeout;
- final interval aligns with the remaining deadline.

### `OBS-04`

- successful token refresh;
- authentication rejection during active retry;
- expired access token blocks transport;
- first transport failure schedules retry;
- fifth transport failure stops retry;
- retry state survives restart and later success;
- retry exhaustion survives restart;
- authorization-code transport failure is not retried.

## 9. Deterministic Result

Executed:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
```

Result:

```text
16 passed
0 failed
Navimow pilot observation harness checks passed.
```

The four previously failing cases now pass without weakening their expected
values.

## 10. Finding Closure

| Finding | Previous result | Current result | Status |
| --- | --- | --- | --- |
| F-01 stale Docking bypasses deadline | two failed cases | both pass | closed deterministically |
| F-02 failed read uses five seconds | failed | 60-second assertion passes | closed deterministically |
| F-03 refresh transport has no retry | failed | bounded retry assertions pass | closed deterministically |

Closure is deterministic and local. Direct Symcon smoke evidence for the new
build remains pending until publication.

## 11. Safety Verification

Confirmed by the harness:

- command count remains one across timeout and restart;
- no post-restart command replay occurs;
- all verification operations after acceptance remain reads;
- post-deadline restart performs no cloud read;
- timer scheduling terminates and remains bounded;
- token retries cannot exceed five automatic failures;
- OAuth authorization code is not automatically replayed;
- authentication rejection does not continue retrying;
- no token or client secret appears in captured diagnostics;
- no harness path uses a real network endpoint.

## 12. Regression Checks

Executed checks:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tests/pilot-observation-harness.php
php case-studies/navimow/tools/validate-distribution.php
php -l for all distribution and test PHP files
git diff --check
no-network and private-data scans
```

Results:

| Check | Result |
| --- | --- |
| existing REST/Auth/fixture/static checks | passed |
| deterministic pilot observation harness | 16 of 16 passed |
| distribution structure validator | passed |
| PHP syntax | passed |
| whitespace | passed |
| harness no-network scan | passed |
| private-data scan | passed |

## 13. Publication Boundary

The canonical case-study distribution now differs intentionally from the
published `pilot-0.1.0.1` module snapshot.

Do not copy harness files to the dedicated module repository. Publication must
include only the changed productive distribution files:

```text
NavimowAccount/module.php
NavimowDevice/module.php
```

The dedicated repository should receive one traceable hardening commit after
its copied tree passes the distribution comparison and validator.

No new pilot tag is created in this step.

## 14. Gate Decisions

### Deterministic recovery gate

**Decision: PASS.**

All original and expanded harness assertions are green.

### Publication gate

**Decision: GO for controlled hardening publication.**

The productive diff is limited to the reviewed recovery policy and internal
testability seams.

### Direct Symcon gate

**Decision: read-only smoke test required after publication and update.**

The first Symcon test should verify module loading, account status, token timer
continuity and device status refresh without sending a mower command.

### Supervised restart transition gate

**Decision: not yet executed.**

It may be reconsidered only after the published hardening build passes the
read-only Symcon smoke test.

## 15. Architecture Decisions

### AD-NAV-092: Preserve public RefreshStatus contract

**Decision:** Add private structured status execution while retaining the
public string result.

**Rationale:** Verification needs current-read evidence, but callers and forms
must not receive a breaking method change.

**Consequence:** Freshness is explicit internally and public behavior remains
compatible.

### AD-NAV-093: Terminate before stale progress

**Decision:** Deadline and current-read success control every non-terminal
transition.

**Rationale:** Stored device variables are display state, not proof that the
latest verification read succeeded.

**Consequence:** Stale Docking cannot create an unbounded timer loop.

### AD-NAV-094: Keep unresolved reads semantically separate

**Decision:** Persist `WaitingRead` rather than reusing `Accepted` or
`Returning`.

**Rationale:** A failed read is neither initial command acceptance nor physical
return progress.

**Consequence:** Retry cadence and recovery state are explicit.

### AD-NAV-095: Bound refresh recovery by attempts

**Decision:** Stop automatic refresh after five transport failures.

**Rationale:** A persistent retry must survive short outages without becoming
an infinite background process.

**Consequence:** Manual recovery remains possible after a visible Offline
terminal retry state.

### AD-NAV-096: Publish fixes and seams together

**Decision:** Do not publish the testability seams separately from the
deterministically verified recovery fixes.

**Rationale:** The dedicated module repository should contain a coherent
behavioral improvement, not an intermediate red-harness state.

**Consequence:** The next module commit contains both internal seams and all
three hardening corrections.

## 16. Recommended Next Step

Create:

```text
42-pilot-recovery-hardening-publication.md
```

That step should:

- copy the canonical productive files to the dedicated publish clone;
- verify that only the two intended module files changed;
- rerun publication validation;
- create and push one hardening commit;
- record the remote commit;
- prepare the subsequent read-only Symcon smoke test.
