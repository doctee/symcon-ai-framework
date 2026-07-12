# 40 Pilot Recovery Hardening Design

**Case study:** Navimow native IP-Symcon module
**Status:** Hardening design complete; implementation pending
**Date:** 2026-07-10
**Scope:** Correct deterministic timeout, read-cadence and token-recovery gaps

## 1. Purpose

This step defines narrowly scoped corrections for the three recovery findings
from `39-pilot-observation-harness-implementation.md`:

- F-01: stale Docking evidence bypasses the deadline;
- F-02: an initial failed verification read retains five-second cadence;
- F-03: a token-refresh transport failure disables automatic recovery.

No productive PHP code is changed in this design step.

## 2. Hardening Boundary

The implementation may change:

- internal Dock verification state and transition order;
- private status-refresh result handling;
- command verification timer calculation;
- internal token-refresh retry metadata and scheduling;
- deterministic harness cases and assertions.

It must not change:

- the Dock REST payload;
- the one-command-per-user-action rule;
- public variables or profiles;
- account or device configuration properties;
- OAuth credentials or storage format;
- module metadata;
- non-Dock command availability;
- live API endpoints.

## 3. Required Safety Invariants

Every correction must preserve:

- no automatic mower command retry;
- no command replay after restart;
- one final read opportunity at the exact verification deadline;
- read-only operations after command acceptance;
- bounded timer intervals and terminal states;
- no automatic retry of an authorization code exchange;
- no automatic retry after confirmed credential rejection;
- no secret values in diagnostics or harness output.

## 4. F-01 Root Cause

Current transition order after a verification read is:

```text
Docked?
-> Docking?
-> before deadline?
-> timeout
```

A failed read leaves the previous successful `VehicleState` and
`LastStatusUpdate` in place. Once Docking has been observed, the stored Docking
branch can therefore win forever, including after the deadline.

The scheduler then notices the elapsed deadline and repeatedly selects a
one-millisecond interval.

## 5. F-01 Corrected Transition Order

The corrected verification flow must distinguish the current read result from
stored device variables.

Required order:

```text
verification inactive?
-> invalid or already elapsed deadline?
-> perform one read if started at or before deadline
-> fresh Docked?
-> deadline reached after final read?
-> fresh Docking?
-> unresolved read state
-> schedule bounded next read
```

Detailed rules:

1. Stop the current timer at method entry.
2. Return immediately when no command verification is active.
3. Read the persisted deadline and current time.
4. If the deadline is missing, terminate as `Verification Timeout` without a
   status request.
5. If current time is later than the deadline, terminate as
   `Verification Timeout` without another status request.
6. If current time is equal to or before the deadline, perform one read-only
   status request.
7. Accept `Verified` only when that current read succeeded and returned
   `Docked`.
8. If the deadline is reached and the current read did not return `Docked`,
   terminate as `Verification Timeout`.
9. Before the deadline, treat current-read `Docking` as `Returning`.
10. Treat failed or non-terminal current reads as `WaitingRead`.

Stored `VehicleState == Docking` from an earlier read must never count as
current progress after a later read failure.

## 6. Structured Internal Status Result

The public method contract must remain:

```php
public function RefreshStatus(): string
```

Introduce a private internal method that performs the existing operation and
returns a bounded structure:

```text
success: boolean
message: sanitized string
```

Recommended shape:

```php
private function refreshStatusInternal(): array
```

`RefreshStatus()` returns only the internal result's message. The command
verification path uses `success` to decide whether `VehicleState` belongs to
the current read.

This avoids:

- comparing user-facing success strings;
- relying on second-resolution timestamps to identify freshness;
- adding a public diagnostic variable;
- persisting raw responses.

## 7. F-02 Internal Waiting State

Add one private verification state:

```text
WaitingRead
```

Recommended numeric value:

```text
7
```

Existing internal values remain unchanged. The new value is not a public
profile association.

Transition rules:

| Current event | Internal state | Next interval |
| --- | --- | --- |
| command accepted, no read attempted | `Accepted` | 5 seconds |
| current read is Docking | `Returning` | 60 seconds |
| current read failed | `WaitingRead` | 60 seconds |
| current read succeeded with another non-terminal state | `WaitingRead` | 60 seconds |
| current read is Docked | `Verified` | timer stopped |
| deadline reached | `TimedOut` | timer stopped |

`WaitingRead` means that verification remains active but the current read did
not provide physical progress evidence. It must not reuse stale Docking as its
meaning.

## 8. Deadline-Aware Timer Calculation

The next timer must not overshoot the deadline by a full polling interval.

Recommended calculation:

```text
normal interval:
  Accepted -> 5 seconds
  Returning or WaitingRead -> 60 seconds

remaining milliseconds:
  max(1, (deadline - now) * 1000)

next interval:
  min(normal interval, remaining milliseconds)
```

If `ApplyChanges()` restores an active command at or after its deadline, it
may schedule one immediate tick at one millisecond. That tick must terminate;
it must not schedule another immediate tick.

This produces no more than:

```text
one initial read plus fifteen bounded follow-up reads
```

within a 900-second window.

## 9. F-03 Token Retry Scope

Automatic token-refresh retry is allowed only for a transport-class failure
during `RefreshAuthentication()`.

It is not allowed for:

- authorization-code exchange;
- API-confirmed invalid OAuth information;
- missing refresh token;
- invalid configuration;
- malformed token response;
- unsupported token type;
- other unclassified exceptions.

HTTP and API retry expansion is deferred until endpoint-specific evidence
justifies it. This first correction addresses only the reproduced transport
failure.

## 10. Bounded Token Retry Policy

Recommended constants:

```text
TOKEN_REFRESH_RETRY_DELAY_SECONDS = 60
TOKEN_REFRESH_RETRY_MAX_ATTEMPTS = 5
```

Add one internal persistent attribute:

```text
RefreshRetryCount
```

Policy:

1. Start at zero.
2. On token-refresh transport failure, increment the count, capped at five.
3. While count is below five, schedule `RefreshToken` after 60 seconds.
4. At count five, stop the timer and remain visibly `Offline`.
5. Keep `ReauthRequired == false` for transport failure.
6. On successful refresh, reset count to zero and restore normal refresh
   scheduling.
7. On confirmed authentication rejection, reset count to zero, stop refresh
   and set `ReauthRequired == true`.
8. On `ResetAuthentication()`, reset count to zero.
9. On restart, preserve an active retry count and resume the 60-second retry
   schedule without resetting the count.

Five total failed attempts provide a bounded recovery window aligned with the
existing five-minute early-refresh margin. Manual refresh remains possible
after automatic retry exhaustion.

## 11. Authentication Failure Classification

`recordAuthenticationFailure()` currently handles both authorization-code
exchange and refresh failures.

Change the private call contract so the caller explicitly states whether
transport retry is allowed, for example:

```php
private function recordAuthenticationFailure(
    Throwable $exception,
    bool $allowTransportRetry
): void
```

Call policy:

| Caller | `allowTransportRetry` |
| --- | --- |
| `ExchangeAuthorizationCode()` | `false` |
| `RefreshAuthentication()` | `true` |

Only `ApiException` with kind `transport` and an allowed caller may schedule
the bounded retry.

This keeps an ambiguous or one-time authorization code from being reused
automatically.

## 12. Restart Behavior for Token Retry

`ApplyChanges()` must inspect `RefreshRetryCount` when tokens are present.

Required behavior:

| Retry count | Connection state | Refresh timer |
| --- | --- | --- |
| `0` | normal existing evaluation | normal token schedule |
| `1` through `4` | `Offline`, no reauth | 60 seconds |
| `5` | `Offline`, no reauth | stopped |

Restart must not reset the count or incorrectly display `Connected` while a
transport-recovery sequence remains active.

Successful refresh after restart returns to `Connected`, resets the count and
restores polling.

## 13. Diagnostics Boundary

No new public retry variable is required for the pilot correction.

Existing diagnostics remain:

- `ConnectionState`;
- `ReauthRequired`;
- `RestErrorCount`;
- bounded sanitized debug entries.

The internal retry count exists only for deterministic scheduling and restart
recovery. If pilot operation later shows a support need, public diagnostics can
be reviewed separately.

Debug output must not contain:

- token values;
- client credentials;
- request bodies;
- complete authorization headers;
- private account or device identifiers.

## 14. Harness Changes

The existing twelve cases retain their target expectations and must all become
green.

Update the harness to assert:

### Deadline and restart

- stale Docking cannot bypass the deadline;
- elapsed restart terminates on its immediate tick;
- no status read occurs when restart happens after the deadline;
- Docked from a read started exactly at the deadline can still verify;
- no one-millisecond timer loop remains;
- command count remains one.

### Read cadence

- first failed verification read selects 60 seconds;
- later failed reads remain at 60 seconds;
- the final interval is shortened to the remaining deadline;
- total reads remain at or below sixteen;
- later Docking and Docked still recover to `Verified`.

### Token retry

- transport failures one through four schedule 60 seconds;
- failure five stops the timer;
- restart during attempts one through four resumes at 60 seconds;
- restart after attempt five keeps the timer stopped;
- successful retry resets the count and normal timers;
- authentication rejection never schedules retry;
- authorization-code transport failure never schedules retry;
- debug output remains secret-safe.

## 15. Expected Harness Result

After implementation, the original summary must change from:

```text
8 passed, 4 failed
```

to:

```text
12 passed, 0 failed
```

Additional retry-bound and restart assertions may increase the total case
count. Every added case must also pass.

The success line remains:

```text
Navimow pilot observation harness checks passed.
```

## 16. Regression Gates

The hardening implementation is accepted only when all of these pass:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
php -l for all productive and harness PHP files
git diff --check
no-network and private-data scans
```

The productive diff must remain restricted to account and device recovery
logic plus the already approved internal seams.

## 17. Publication and Live-Test Gate

After local implementation passes:

1. review the productive diff against this design;
2. copy only the canonical distribution changes to the dedicated module
   repository;
3. validate the publish clone;
4. publish one traceable hardening commit;
5. update the module in Symcon;
6. perform read-only OAuth and status smoke checks;
7. only then reconsider the supervised `OBS-02` restart transition.

Do not start a live restart transition while any deterministic deadline or
command-count assertion is red.

## 18. Architecture Decisions

### AD-NAV-086: Current-read evidence controls transitions

**Decision:** Use a structured internal read result to distinguish current
evidence from stored device variables.

**Rationale:** Stored Docking remains useful for display but cannot safely prove
that the latest verification read succeeded.

**Consequence:** Stale progress cannot bypass the deadline.

### AD-NAV-087: Deadline precedes non-terminal progress

**Decision:** After the allowed final read, evaluate timeout before Docking or
WaitingRead transitions.

**Rationale:** A bounded state machine must reach its terminal boundary even
when the last known physical state was in progress.

**Consequence:** The one-millisecond post-deadline loop is removed.

### AD-NAV-088: Separate WaitingRead from Returning

**Decision:** Add an internal state for failed or unresolved current reads.

**Rationale:** A read failure is not evidence of physical Docking, but it still
requires bounded verification.

**Consequence:** Diagnostics remain semantically accurate and use 60-second
polling.

### AD-NAV-089: Align the last timer with the deadline

**Decision:** Shorten the next interval when less than the normal poll interval
remains.

**Rationale:** A 15-minute maximum should not drift by another minute because
of timer cadence.

**Consequence:** Timeout is deterministic at the configured boundary.

### AD-NAV-090: Retry only refresh transport failures

**Decision:** Add bounded automatic retry only for transport failures during
token refresh.

**Rationale:** This is the reproduced temporary failure class. Authentication
rejection and authorization-code exchange have different safety semantics.

**Consequence:** Retry scope remains narrow and reviewable.

### AD-NAV-091: Persist and cap refresh retries

**Decision:** Store an internal retry count and stop after five failed attempts.

**Rationale:** Recovery must survive restart without becoming an infinite
background loop.

**Consequence:** The module can recover from short outages and visibly stops
after a bounded sequence.

## 19. Recommended Next Step

Create:

```text
41-pilot-recovery-hardening-implementation.md
```

That step should implement this transition order, WaitingRead state,
deadline-aware timer and bounded refresh retry, then run the expanded harness
to a fully green result before publication.
