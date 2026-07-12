# 38 Pilot Observation Harness Design

**Case study:** Navimow native IP-Symcon module
**Status:** Harness design complete; implementation pending
**Date:** 2026-07-10
**Scope:** Deterministic non-actuating tests for private-pilot recovery paths

## 1. Purpose

This step designs the non-productive harness required by
`37-private-pilot-observation-plan.md`.

The harness must execute the real Navimow account and device module classes in
a deterministic local CLI environment without:

- a live IP-Symcon installation;
- network access;
- real OAuth material;
- private device identifiers;
- any connection to a mower.

No productive PHP code is changed in this design step.

## 2. Design Goals

The harness must prove or characterize:

- the exact 15-minute Dock verification boundary;
- timer scheduling before and after the deadline;
- restart reconstruction without command replay;
- transient and continuous read-failure behavior;
- command and read call counts;
- token-refresh scheduling and failure classification;
- secret-safe diagnostics.

The harness must test the productive module logic itself. It must not create a
second implementation of the verification state machine merely to make tests
easy.

## 3. Existing Reusable Test Capability

The existing `ApiClient` already accepts an optional callable transport.

This capability is used by `tests/rest-client-auth.php` to:

- capture request envelopes;
- return deterministic response bodies;
- classify HTTP and transport failures;
- verify that exception messages do not expose tokens.

The new harness must reuse this transport injection. A second REST client,
HTTP mock framework or local web server is not justified.

No reusable SAEF helper currently provides an `IPSModule` CLI runtime or a
controllable clock for module tests. The first implementation therefore keeps
the harness inside the Navimow case study rather than adding a public helper.

## 4. Current Testability Gaps

The productive modules currently depend directly on:

| Dependency | Current form | Test impact |
| --- | --- | --- |
| Symcon object state | inherited `IPSModule` methods | unavailable in plain CLI PHP |
| current time | direct `time()` calls | exact boundary tests are not deterministic |
| device parent communication | `SendDataToParent()` | needs scripted account responses |
| account API creation | private `createApiClient()` | fake transport cannot be injected into the account class |
| timers | `RegisterTimer()` and `SetTimerInterval()` | schedule decisions need observable state |
| restart persistence | Symcon attributes and variables | process reconstruction needs a state snapshot |

These gaps should be addressed with narrow seams, not by changing public
module configuration or variables.

## 5. Proposed Harness Structure

Recommended files:

```text
tests/
  harness/
    SymconRuntime.php
    FakeClock.php
  pilot-observation-harness.php
```

### `tests/harness/SymconRuntime.php`

Provide a case-study-local CLI double for the minimum Symcon runtime used by
the account and device modules.

It should contain:

- a minimal global `IPSModule` base class;
- property registration and reading;
- attribute registration, reading and writing;
- variable registration, `SetValue()` and `GetValue()`;
- timer registration and observable intervals;
- scripted `SendDataToParent()` behavior;
- captured `SendDataToChildren()` messages;
- bounded captured `SendDebug()` entries;
- minimal profile and semaphore function shims.

Unsupported Symcon operations must throw immediately. Silent no-op behavior is
allowed only where the module contract does not depend on the result, such as
profile presentation details.

### `tests/harness/FakeClock.php`

Provide one mutable integer timestamp with these operations:

```text
now()
advance(seconds)
set(timestamp)
```

The clock must reject negative movement during a scenario. Restart
reconstruction may reuse the same clock instance.

### `tests/pilot-observation-harness.php`

Contain:

- test-only subclasses of `NavimowDevice` and `NavimowAccount`;
- scripted parent response queues for the device;
- scripted `ApiClient` transports for the account;
- command, status-read and token-request counters;
- state snapshot and reconstruction helpers;
- scenario assertions for `OBS-01` through the deterministic part of
  `OBS-04`.

Scenario-specific helpers should remain in this test file until repeated use
justifies a separate abstraction.

## 6. Minimal Productive Seams

The harness requires exactly two narrow productive seams.

### Controllable time

Add this protected method to both productive module classes:

```php
protected function currentTimestamp(): int
{
    return time();
}
```

Replace direct module-level `time()` calls with
`$this->currentTimestamp()`.

The test subclass overrides the method and returns `FakeClock::now()`.

This method:

- is not a public Symcon action;
- is not configuration;
- does not alter production time semantics;
- avoids global function replacement or real-time waiting.

### Injectable account client factory

Change `NavimowAccount::createApiClient()` from private to protected while
preserving its production implementation and return type.

The test subclass overrides it to return the existing `ApiClient` with a
scripted callable transport.

No transport property, debug switch or public test mode may be added to the
productive module.

## 7. Explicitly Rejected Designs

### Live Base URL substitution

Do not point the productive Symcon instance at a local proxy or invalid Base
URL to create failures.

Reason: it mutates live configuration and can affect real authentication or
polling state.

### Environment-controlled production test mode

Do not add a property, environment variable or hidden form field that enables
fake responses in the installable module.

Reason: a test mode in the published module creates an unnecessary operational
and security surface.

### Duplicated pure state machine

Do not copy the Dock transition rules into a test-only evaluator.

Reason: tests could pass while the productive module behaves differently.

### Real-time 15-minute test

Do not wait 15 minutes in CI or a local CLI run.

Reason: a fake clock gives stronger boundary evidence without slow or flaky
execution.

### General SAEF helper extraction

Do not add a public framework-level Symcon runtime mock in this step.

Reason: reuse has not yet been demonstrated outside this case study. The
harness may become a helper candidate only after a second module needs the
same behavior.

## 8. Runtime Double Contract

The CLI `IPSModule` double should store state in typed associative arrays:

| Store | Example key | Required behavior |
| --- | --- | --- |
| properties | `DeviceId` | default registration plus test override |
| attributes | `CommandDeadline` | persistent snapshot and typed access |
| variables | `LastCommandResult` | registered default plus value updates |
| timers | `CommandVerification` | latest interval in milliseconds |
| debug entries | `StatusRefreshFailure` | bounded sanitized messages |
| parent calls | `GetStatus` | ordered decoded message capture |
| child calls | `PollStatus` | ordered decoded message capture |

The runtime double should expose test-only inspection methods with a clear
prefix, for example:

```text
testSetProperty()
testReadAttribute()
testTimerInterval()
testSnapshotPersistentState()
testRestorePersistentState()
testDebugEntries()
```

These methods exist only in the CLI base class and cannot appear in a real
Symcon installation.

## 9. Restart Simulation Model

A restart must be simulated by constructing a new module object, not by
calling `ApplyChanges()` repeatedly on the same object.

Procedure:

1. run `Create()` and `ApplyChanges()` on the first device object;
2. send one fake accepted Dock command;
3. return one `Docking` status;
4. snapshot registered properties, attributes and variables;
5. discard the first device object;
6. construct a second device object with the same instance identity;
7. restore the persistent snapshot;
8. run `Create()` and `ApplyChanges()` in runtime-compatible order;
9. assert that only a read-only verification timer is scheduled;
10. return `Docked` and assert `Verified`.

The scripted parent transport counter must outlive both objects. Its command
count must remain one.

Timer definitions themselves may be recreated by `Create()`. Timer intervals,
properties, attributes and variables represent persisted or reconstructed
runtime state according to their Symcon ownership.

## 10. Scripted Device Parent

The fake parent should decode every `SendDataToParent()` message and route by
`Function`:

| Function | Counter | Allowed scripted result |
| --- | --- | --- |
| `SendCommand` | command count | accepted, already-in-state or error |
| `GetStatus` | read count | Docking, Docked or sanitized error envelope |

Rules:

- unknown functions fail the test;
- response queues are consumed in order;
- an empty queue fails the test;
- every response is JSON encoded exactly as a real parent result;
- only placeholder device IDs are accepted;
- no URL or cURL operation exists in this layer.

This parent tests device behavior independently from account transport
behavior. Account behavior is tested separately with the existing
`ApiClient` fake transport.

## 11. Scripted Account Transport

The test account subclass should return an `ApiClient` configured with:

```text
https://navimow.invalid
```

and a mandatory scripted callable transport.

The transport records only sanitized request metadata:

- operation name;
- request path;
- method;
- call count;
- whether an authorization header was present.

It must not retain complete authorization headers or form bodies after the
assertion that secrets are not leaked into diagnostics.

Supported scripted outcomes:

- OAuth refresh success;
- HTTP rejection;
- API authentication rejection;
- transport exception;
- malformed response envelope.

If a scenario attempts an unscripted request, the transport throws before any
network operation is possible.

## 12. Deterministic Scenario Suite

### `OBS-01`: Verification timeout

Required assertions:

- one fake Dock call;
- first verification timer equals 5 seconds;
- `Docking` changes the next timer to 60 seconds;
- at `deadline - 1`, result remains `Pending Verification`;
- at `deadline`, one final read is allowed;
- a non-Docked final read produces `Verification Timeout`;
- timer becomes inactive;
- command count remains one.

Boundary variants:

- `Docked` on the final deadline read produces `Verified`;
- continuous read errors produce timeout, not command failure;
- missing or zero deadline fails closed and must not create an infinite loop.

### `OBS-02`: Restart reconstruction

Required assertions:

- `CommandActive`, original start time and deadline survive reconstruction;
- `ApplyChanges()` schedules verification according to persisted state;
- restart does not call `SendCommand`;
- a deadline already elapsed at restart schedules an immediate verification
  tick;
- `Docked` after restart produces `Verified`;
- command count remains one across both objects.

### `OBS-03`: Temporary read failures

Required assertions:

- two read failures do not end active verification before the deadline;
- later `Docking` and `Docked` results recover to `Verified`;
- continuous read failure ends at the deadline;
- all repeated calls after command acceptance are `GetStatus`;
- command count remains one;
- read cadence remains within the approved bound.

Approved read bound for a 900-second window:

```text
one initial read plus no more than fifteen 60-second follow-up reads
```

The current implementation may reveal a five-second retry cadence when the
first status reads fail while verification is still `Accepted`. The harness
must report this as a failed bound, not encode the current behavior as the
expected contract.

### `OBS-04`: Token lifecycle

Required assertions:

- stored token expiry uses fake time plus `expires_in`;
- refresh timer uses the documented margin and minimum delay;
- successful refresh advances expiry and keeps polling enabled;
- API token rejection sets `ReauthRequired`;
- expired access token blocks status reads;
- transport failure does not expose token values;
- no authentication failure can call `SendCommand`.

The suite must characterize whether refresh scheduling recovers automatically
after a transport failure. The current implementation stops the refresh timer
in `recordAuthenticationFailure()`. If no bounded automatic recovery exists,
the scenario must fail the broader-release criterion and create a separate
implementation decision; the harness must not conceal the result.

## 13. No-Network Safety Gate

The harness is accepted only if all of these controls exist:

- every account test uses an injected callable transport;
- the test Base URL uses the reserved `.invalid` domain;
- every device test uses the in-memory scripted parent;
- unscripted calls throw immediately;
- test fixtures use only `DEVICE_001` and other documented placeholders;
- no test reads environment credentials;
- no raw private capture directory is referenced;
- no cURL call is required for a passing harness run.

Recommended static checks should confirm that the harness contains none of:

```text
navimow-fra.ninebot.com
navimow-h5-fra.willand.com
private/navimow-capture
Authorization: Bearer <real value>
```

The productive distribution may contain real endpoint defaults; the static
no-network scan applies specifically to harness files and scripted test data.

## 14. Assertions and Output

Reuse the lightweight assertion style from `tests/rest-client-auth.php` unless
the implementation demonstrates a concrete need for a test framework.

The harness should:

- exit non-zero on the first failed invariant;
- identify the scenario and assertion in the exception message;
- print one final success line;
- avoid dumping complete state arrays or HTTP messages;
- never print tokens, device identifiers or raw payloads.

Recommended successful output:

```text
Navimow pilot observation harness checks passed.
```

## 15. Implementation Sequence

1. Add the CLI Symcon runtime double.
2. Add `FakeClock`.
3. Introduce `currentTimestamp()` in account and device modules.
4. Make the existing account client factory protected.
5. Add test subclasses and scripted device parent.
6. Implement `OBS-01` timeout tests.
7. Implement `OBS-02` restart reconstruction tests.
8. Implement `OBS-03` failure and call-count tests.
9. Implement `OBS-04` token lifecycle tests.
10. Run existing REST tests and distribution validation unchanged.
11. Record every revealed behavior gap before changing recovery policy.

The first harness run is an observation gate. It may legitimately fail and
identify required hardening work. Do not adjust expected values merely to make
the current implementation pass.

## 16. Acceptance Gate

Harness implementation is accepted when:

- it runs in plain local PHP;
- it makes no network request;
- it executes the productive module classes;
- time advances without sleeping;
- restart creates a new module object;
- command and read counts are explicit;
- timeout and token boundaries are deterministic;
- existing REST and distribution checks still pass;
- productive seams do not change public module behavior;
- all findings are documented, including expected failures.

No supervised restart test from `OBS-02` should begin before the deterministic
restart and no-command-replay assertions pass.

## 17. Architecture Decisions

### AD-NAV-076: Use a case-study-local Symcon runtime double

**Decision:** Implement the minimum required `IPSModule` behavior under
`case-studies/navimow/tests/harness/`.

**Rationale:** SAEF requires reproducible tests where possible, but no reusable
runtime double exists and cross-case reuse has not been demonstrated.

**Consequence:** The harness remains owned by Navimow until another case study
proves a general helper boundary.

### AD-NAV-077: Introduce only time and client-factory seams

**Decision:** Limit productive testability changes to a protected time method
in both modules and a protected account client factory.

**Rationale:** Symcon state and device parent communication can be supplied by
the CLI base class. Additional production test APIs are unnecessary.

**Consequence:** No public property, action, variable or test mode is added.

### AD-NAV-078: Reuse the existing callable REST transport

**Decision:** Use `ApiClient` transport injection for account tests.

**Rationale:** The existing transport already provides deterministic request
capture and response scripting with no network dependency.

**Consequence:** No second REST abstraction or mocking dependency is added.

### AD-NAV-079: Fail closed on unscripted harness operations

**Decision:** Throw immediately when a parent function, API request or runtime
method is not explicitly supported by the scenario.

**Rationale:** Silent defaults can hide unintended production paths and weaken
the no-network guarantee.

**Consequence:** Harness maintenance must explicitly acknowledge new module
dependencies.

### AD-NAV-080: Test restart through object reconstruction

**Decision:** Simulate restart by restoring persistent state into a new module
object.

**Rationale:** Reusing the same object would not demonstrate that verification
depends only on persisted state and lifecycle methods.

**Consequence:** The restart test can detect hidden in-memory state.

### AD-NAV-081: Treat observed recovery gaps as findings

**Decision:** Keep target retry bounds and recovery expectations independent
from current implementation behavior.

**Rationale:** A characterization harness should reveal excessive read cadence
or missing token-refresh recovery, not normalize it.

**Consequence:** Initial harness execution may be red and lead to a separate
hardening decision before live testing.

## 18. Recommended Next Step

Create:

```text
39-pilot-observation-harness-implementation.md
```

That step should implement the approved runtime double, narrow productive
seams and deterministic scenarios, then report actual pass/fail findings.

Do not publish productive changes or start the supervised restart test until
the harness results and any discovered recovery gaps have been reviewed.
