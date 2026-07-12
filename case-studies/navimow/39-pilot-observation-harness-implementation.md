# 39 Pilot Observation Harness Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Harness implemented; recovery gate failed with four findings
**Date:** 2026-07-10
**Scope:** Deterministic execution of private-pilot recovery scenarios

## 1. Purpose

This step implements the harness designed in
`38-pilot-observation-harness-design.md` and executes the deterministic parts
of `OBS-01` through `OBS-04` from
`37-private-pilot-observation-plan.md`.

The first run is intentionally a characterization gate. Expected values were
not changed to match current behavior.

No live mower, private Symcon installation or real Navimow account was used.

## 2. Implemented Files

Productive files with narrow internal testability seams:

```text
distribution/NavimowAccount/module.php
distribution/NavimowDevice/module.php
```

Non-productive harness files:

```text
tests/harness/FakeClock.php
tests/harness/SymconRuntime.php
tests/pilot-observation-harness.php
```

No public variable, action, property, module metadata or REST endpoint was
added or changed.

## 3. Productive Seams

### Controlled time

Both module classes now use a protected `currentTimestamp()` method instead of
direct module-level `time()` calls.

Production behavior remains:

```php
protected function currentTimestamp(): int
{
    return time();
}
```

Harness subclasses override the method with `FakeClock::now()`.

### Account API client factory

`NavimowAccount::createApiClient()` is now protected instead of private.

Its production implementation is unchanged. The harness subclass returns the
existing `ApiClient` with a mandatory scripted callable transport and the
reserved `.invalid` test domain.

## 4. CLI Symcon Runtime

The case-study-local runtime double implements only the Symcon behavior used by
the account and device modules:

- property registration and reading;
- attribute registration, reading and writing;
- variable registration and values;
- timer registration and interval inspection;
- parent and child data routing;
- bounded debug capture;
- profile and semaphore shims;
- persistent state snapshot and restoration.

Unknown properties, attributes, variables, timers, parent functions or empty
response queues throw immediately.

The runtime is not a general IP-Symcon emulator and is not exposed as a SAEF
helper.

## 5. No-Network Controls

The harness enforces these boundaries:

- account requests use the existing injected callable transport;
- the account Base URL is `https://navimow.invalid`;
- device parent calls stay in an in-memory scripted queue;
- unscripted requests throw;
- only placeholder device identifiers are accepted;
- captured API metadata excludes bodies and complete authorization headers;
- no private capture directory or environment credential is read;
- no cURL operation is needed.

The harness run completed without network access.

## 6. Implemented Scenarios

The harness contains twelve deterministic cases:

| Scenario | Case | Result |
| --- | --- | --- |
| `OBS-01` | timeout after previously observed Docking | failed |
| `OBS-01` | final Docked read at deadline | passed |
| `OBS-01` | missing deadline fails closed | passed |
| `OBS-02` | restart resumes and verifies Docked | passed |
| `OBS-02` | restart at expired deadline | failed |
| `OBS-03` | transient failures recover through Docking to Docked | passed |
| `OBS-03` | failed initial read uses bounded cadence | failed |
| `OBS-03` | continuous failure without prior Docking times out | passed |
| `OBS-04` | token refresh success | passed |
| `OBS-04` | rejected refresh token | passed |
| `OBS-04` | expired access token blocks read transport | passed |
| `OBS-04` | refresh transport failure schedules recovery | failed |

Summary:

```text
8 passed
4 failed
```

## 7. Passed Safety Properties

The harness confirmed:

- a normal restart reconstructs active verification from persisted state;
- the original command timestamp and deadline survive restart;
- restart does not replay Dock;
- later Docked evidence produces `Verified`;
- a final Docked read at the deadline is accepted;
- a missing deadline terminates rather than looping indefinitely;
- transient reads can recover through Docking to Docked;
- continuous failure without stale Docking evidence reaches timeout;
- successful token refresh uses controlled time and reschedules timers;
- API token rejection requests reauthorization;
- an expired access token fails before transport;
- transport diagnostics do not expose test token or secret values.

No deterministic case observed a second `SendCommand` call.

## 8. Finding F-01: Deadline Is Bypassed by Stale Docking

**Severity:** High
**Affected scenarios:** `OBS-01`, expired branch of `OBS-02`

### Observation

After `Docking` has been observed once, a later failed status read leaves the
last successful `VehicleState` and `LastStatusUpdate` unchanged.

`VerifyCommand()` evaluates the stored Docking branch before evaluating the
deadline. At the deadline, the stale Docking state therefore remains
`Pending Verification` instead of becoming `Verification Timeout`.

Observed deterministic result:

```text
expected: Verification Timeout
actual: Pending Verification
```

### Additional impact

`scheduleNextCommandVerification()` recognizes that the deadline has elapsed
and schedules a one-millisecond timer. The next verification can again accept
the stale Docking state before the deadline branch.

This can create an effectively unbounded rapid read loop after the deadline.
It does not resend Dock, but it violates the bounded verification contract and
can generate unnecessary cloud traffic.

### Required correction

After the final read opportunity, deadline evaluation must occur before any
non-terminal progress branch. Only fresh `Docked` evidence may complete as
`Verified` at the boundary.

## 9. Finding F-02: Initial Read Failure Uses Five-Second Cadence

**Severity:** Medium
**Affected scenario:** `OBS-03`

### Observation

When the first verification read fails before `Docking` has ever been
observed, internal state remains `Accepted`.

The scheduler associates `Accepted` with the initial five-second delay.
Repeated failures can therefore schedule reads every five seconds throughout
the 15-minute window.

Observed deterministic result:

```text
expected next interval: 60000 ms
actual next interval: 5000 ms
```

### Impact

The path remains time-bounded when no stale Docking state exists, and it never
replays the actuator command. However, it can perform roughly an order of
magnitude more read requests than the approved pilot bound.

### Required correction

After the first verification attempt, unresolved or failed reads must enter a
state that uses the 60-second bounded read cadence without claiming physical
Docking progress.

## 10. Finding F-03: Token Refresh Transport Failure Has No Retry Timer

**Severity:** Medium
**Affected scenario:** `OBS-04`

### Observation

A transport exception during `RefreshAuthentication()` is correctly
classified as `Offline` and does not mark credentials as rejected.

`recordAuthenticationFailure()` also sets the refresh timer to zero and does
not schedule a bounded retry.

Observed deterministic result:

```text
expected: positive bounded refresh retry interval
actual: refresh timer disabled
```

### Impact

No secret is exposed and no mower command is sent. Automatic authentication
recovery may nevertheless stop after a temporary network outage until another
lifecycle or manual action reschedules refresh.

### Required correction

Transport failures should retain a bounded retry path while credentials remain
potentially valid. Authentication rejection must continue to stop automatic
refresh and require reauthorization.

## 11. Root-Cause Mapping

The four failed cases map to three independent implementation findings:

| Failed case | Root cause |
| --- | --- |
| timeout after Docking | F-01 stale Docking precedes deadline |
| expired restart | F-01 stale Docking precedes deadline |
| failed-read cadence | F-02 Accepted keeps five-second delay |
| refresh transport recovery | F-03 refresh timer remains disabled |

This distinction prevents the same deadline defect from being counted as two
separate architecture problems.

## 12. Validation Commands

Implemented harness:

```text
php case-studies/navimow/tests/pilot-observation-harness.php
```

Result:

```text
exit 1: four expected recovery findings reproduced
```

Existing checks:

```text
php case-studies/navimow/tests/rest-client-auth.php
php case-studies/navimow/tools/validate-distribution.php
php -l for productive and harness PHP files
```

The existing REST checks and distribution validator continue to pass. PHP
syntax checks pass for the productive modules and harness files.

## 13. Gate Decisions

### Harness implementation gate

**Decision: PASS.**

The harness is deterministic, non-actuating, no-network, executes the
productive module classes and reveals the intended boundary defects.

### Recovery behavior gate

**Decision: FAIL.**

The current implementation does not satisfy the approved deadline, read-rate
and refresh-recovery contracts.

### Live restart gate

**Decision: BLOCKED pending hardening.**

Although normal restart reconstruction passed, the deadline defect can create
a rapid post-deadline read loop. The supervised `OBS-02` live restart test
must wait until F-01 is corrected and all deterministic restart cases pass.

## 14. Publication Decision

Do not publish the local seam changes by themselves to the dedicated module
repository.

The published pilot already contains the underlying recovery behavior. A new
module publication should combine:

- reviewed fixes for F-01 through F-03;
- green deterministic harness results;
- existing REST and distribution validation;
- an updated pilot tag or traceable pilot commit.

Controlled private-pilot use may continue under the existing supervision
boundary, but broad release remains blocked.

## 15. Architecture Decisions

### AD-NAV-082: Accept a red first harness run

**Decision:** Treat the harness implementation as successful even though four
product behavior cases fail.

**Rationale:** The harness reproduced real contract violations and preserved
the target expectations defined before implementation.

**Consequence:** Recovery fixes are based on deterministic evidence rather than
live experimentation.

### AD-NAV-083: Group failed cases by root cause

**Decision:** Record three findings rather than four unrelated defects.

**Rationale:** Both deadline failures result from the same transition order and
stale Docking evidence.

**Consequence:** One deadline correction must close both `OBS-01` and the
expired restart branch of `OBS-02`.

### AD-NAV-084: Block live restart testing

**Decision:** Do not run the supervised restart observation while F-01 remains.

**Rationale:** A one-millisecond post-deadline timer loop is an avoidable cloud
load risk even though it does not replay the mower command.

**Consequence:** Deterministic hardening precedes any additional physical test.

### AD-NAV-085: Keep testability seams internal

**Decision:** Retain the protected time and client-factory seams as the only
productive harness support.

**Rationale:** They enabled complete deterministic evidence without public test
configuration or duplicated state logic.

**Consequence:** Hardening should use the existing harness and should not add a
productive test mode.

## 16. Recommended Next Step

Create:

```text
40-pilot-recovery-hardening-design.md
```

That step should define narrowly scoped corrections for:

- deadline precedence and stale Docking handling;
- a distinct bounded state after the first unresolved verification read;
- transport-only token-refresh retry scheduling;
- regression expectations that turn all twelve harness cases green.

No productive fix should be published before that design is reviewed.
