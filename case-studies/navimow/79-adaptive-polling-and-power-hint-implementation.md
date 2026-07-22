# 79 Adaptive Polling and Power Hint Implementation

**Case study:** Navimow native IP-Symcon module
**Status:** Implemented, published, installed and passively observed
**Date:** 2026-07-14 through 2026-07-22
**Scope:** Adaptive REST polling and a private installation-specific wake hint

## 1. Purpose

This step improves the timeliness of `VehicleState` without keeping the
private Navimow REST API at a one-minute polling rate while every mower is
docked.

It introduces two deliberately separate mechanisms:

1. module-owned adaptive polling based only on confirmed Navimow API states;
2. a private power hint that may wake polling but never owns mower state.

The implementation must preserve every existing variable Ident, type, profile,
ObjectID and archive history. It adds no mower command and does not change the
Stop or Start gates.

## 2. Evidence

The supervised observation on 2026-07-14 established this sequence:

```text
Running -> Docking -> Docked
```

With a temporary 60-second REST interval, the two-minute `Docking` phase was
visible and archived. With the normal 300-second interval, an equally short
phase can fall completely between two reads.

The private charging-station power measurement also showed two useful physical
hints:

- a low transition within seconds of departure;
- a high transition within seconds of physical dock contact.

The power signal did not indicate the beginning of the return journey. It is
therefore suitable as a wake hint, not as a replacement for Navimow status.

No private ObjectID, device identifier, hostname or credential is included in
this case-study artifact.

## 3. Public Module Contract

The account instance retains `PollInterval` as the normal docked interval and
adds `ActivePollInterval` for confirmed activity.

| Setting | Default | Minimum | Meaning |
| --- | ---: | ---: | --- |
| `PollInterval` | 300 seconds | 60 seconds | Normal interval while no active mower is known. |
| `ActivePollInterval` | 60 seconds | 60 seconds | Interval while a confirmed non-docked state is fresh. |

The effective active interval is never slower than the normal interval.

The account exposes one generic method:

```php
NAVAC_WakePolling($accountInstanceID);
```

The method:

- requires a usable access token;
- opens a bounded three-minute wake window;
- switches the timer to the active interval;
- triggers exactly one immediate read-only poll broadcast;
- sends no mower command;
- never accepts or derives a `VehicleState` value from the caller.

## 4. State Ownership

`VehicleState` remains owned exclusively by successful Navimow REST status
responses.

Known active states are:

```text
Running
Idle
Paused
Docking
Mapping
Lifted
Error
Software Update
Self-Checking
```

`Docked`, `Unknown` and `Offline` are not positive activity evidence.

After each successful status read, the account records only a hash of the
device identifier and the observation timestamp. Raw device identifiers are
not persisted in adaptive-polling metadata.

Fresh active evidence selects `ActivePollInterval`. A confirmed `Docked` state
removes that device from the active set. Stale evidence expires, preventing a
deleted device or prolonged cloud failure from holding the account at the fast
rate indefinitely.

## 5. Restart and Failure Behavior

The active evidence and wake deadline use module attributes so a normal Symcon
restart does not immediately discard fresh state.

The design fails conservatively:

- missing authentication disables polling as before;
- malformed internal metadata is discarded;
- a failed status read cannot invent activity or docking;
- stale active evidence expires;
- a missed power hint leaves the existing 300-second fallback intact;
- duplicate power updates inside the same hysteresis band do not create
  repeated wake calls.

## 6. Private Power Hint

The installation-specific automation remains below the private Symcon layer.
It uses:

- one explicitly configured float power variable;
- one explicitly configured Navimow account instance;
- lower threshold: `2 W`;
- upper threshold: `5 W`;
- hysteresis state owned by the private script;
- one idempotently created variable-change event with explicit Symcon 6+
  action binding.

Transition behavior:

| Previous private state | Power condition | Action |
| --- | --- | --- |
| docked | below 2 W | mark away and call `NAVAC_WakePolling()` once |
| away | above 5 W | mark docked candidate and call `NAVAC_WakePolling()` once |
| either | inside hysteresis or same side | no action |

The private state is only debounce metadata. It is not exposed as mower state
and does not write any Navimow module variable.

## 7. Compatibility Contract

This change must not recreate, rename or reprofile existing variables.

The following remain unchanged:

- all eight device variables and their positions;
- `NAVIMOW.VehicleState` association numbers;
- archive logging configuration and retained history;
- OAuth attributes and token behavior;
- Dock, Pause and Resume command contracts;
- command verification timers;
- Start and Stop NO-GO decisions.

## 8. Verification Plan

Local deterministic tests must cover:

1. normal authenticated startup uses 300 seconds;
2. wake selects 60 seconds and broadcasts one read-only poll;
3. Docked during the wake window does not cancel bounded confirmation reads;
4. expired wake plus Docked restores 300 seconds;
5. Running and Docking select 60 seconds;
6. Docked after active evidence restores 300 seconds;
7. restart preserves fresh active evidence;
8. stale active evidence expires;
9. malformed metadata fails closed;
10. no existing command or recovery test regresses.

Direct Symcon verification must then confirm:

- unchanged instance and variable identity;
- unchanged archive logging;
- `PollStatus=300000 ms` while docked;
- one private low-power transition invokes Wake without a command;
- `PollStatus=60000 ms` while the mower is confirmed active;
- final Docked status returns the timer to `300000 ms`.

## 9. Architecture Decisions

### AD-NAV-272: Separate physical hints from domain truth

**Decision:** Power may wake REST polling but may never set `VehicleState`.

**Rationale:** Charging-station power is installation-specific and can be
ambiguous; the Navimow API remains the domain authority.

**Consequence:** A false hint causes only bounded additional read traffic.

### AD-NAV-273: Use state-adaptive REST cadence

**Decision:** Poll at 60 seconds only during a bounded wake window or while
fresh confirmed non-docked state exists; otherwise poll at 300 seconds.

**Rationale:** This captures short transitions while limiting private API load.

**Consequence:** Start and Docking become more observable without permanent
one-minute polling.

### AD-NAV-274: Keep installation coupling private

**Decision:** The public module exposes a generic Wake method and contains no
power-variable configuration or personal ObjectID.

**Rationale:** A charging-station sensor is useful locally but is not part of
the Navimow cloud contract.

**Consequence:** Other installations can use a different optional wake source
or none at all.

### AD-NAV-275: Bound persisted activity evidence

**Decision:** Persist hashed active-device observations with expiration.

**Rationale:** Restart continuity is useful, but stale or orphaned state must
not force indefinite fast polling.

**Consequence:** Cloud failures eventually return to the conservative base
interval while normal successful reads continuously renew active evidence.

## 10. Step Sequencing

Step 78 originally named the future Stop follow-up as step 79. This newly
approved operational improvement is inserted as step 79 because it is ready
now and does not interact with the Stop safety gate.

The one-time Stop inquiry follow-up is therefore renamed to:

```text
80-stop-vendor-inquiry-follow-up.md
```

Its earliest permitted date remains 2026-07-26.

## 11. Implemented Module Changes

The canonical distribution now implements the contract above in the existing
account module.

Added configuration:

```text
ActivePollInterval = 60 seconds
```

Added persistent internal metadata:

```text
WakePollingUntil
ActiveDeviceObservations
```

`ActiveDeviceObservations` stores at most 64 SHA-256 device-key hashes with
their latest successful active observation timestamp. Evidence expires after
the greater of 15 minutes or four normal polling intervals.

The new public method is:

```php
NAVAC_WakePolling($accountInstanceID);
```

The existing `PollStatus` timer now chooses the configured normal or active
interval. Successful status mapping updates polling metadata inside the account
without changing the device variable contract.

## 12. Private Installation

The private overlay contains a complete local script at:

```text
private/navimow-power-hint/navimow-power-hint.local.php
```

It was installed below the existing Navimow device instance with:

- one stable script Ident;
- one hidden integer hysteresis variable;
- one hidden variable-change event;
- explicit `Run Automation` action binding;
- the real power variable and account instance IDs only in the private copy.

The initial docked execution observed power above the upper threshold, set the
private state to Docked and made no Wake call.

A temporary private float variable then exercised the real OnChange event path.
The event executed the parent script and initialized state correctly. The
temporary variable was deleted and the event was restored to the actual power
source. The actual power variable was never written by the test.

## 13. Validation Results

| Gate | Result |
| --- | --- |
| account module PHP syntax | PASS |
| private power-hint PHP syntax | PASS |
| REST/auth fixture checks | PASS |
| deterministic pilot harness | PASS, 32 cases |
| distribution structure validator | PASS |
| Git whitespace check | PASS |
| official Symcon schemas | PASS, 10 of 10 files |
| canonical/publish tree comparison | PASS |

Post-report stabilization on 2026-07-20 added deterministic checks for cleanup
after an unauthenticated wake request and strict newest-first retention at the
64-entry observation capacity. The current pilot harness passes 33 cases; the
table above preserves the validation result recorded when this report was
created.

The official browser Module Validator still fails before returning a result:

```text
ReferenceError: $ is not defined
```

The console failure occurred independently of cookie consent. The established
fallback used the four current official schemas and AJV `6.10.2`; temporary
validator files and dependencies were not published.

The deterministic additions verify:

- one immediate read-only broadcast per Wake call;
- a bounded three-minute wake window;
- protection against an initially stale Docked read after departure;
- Running and Docking active cadence;
- Docked recovery to the normal cadence;
- restart continuity;
- stale and malformed metadata cleanup.

## 14. Publication

The exact four-file runtime/documentation delta was committed and pushed to the
dedicated module repository:

```text
42cbce6e feat: add adaptive status polling
```

No release tag was created. Existing pilot tags remain immutable.

## 15. Direct Symcon Verification

Before and after the update, the same account and device instances remained
active with status `102`.

Compatibility comparison passed:

- all eight device variable ObjectIDs remained identical;
- all variable types and profiles remained identical;
- all five operator-enabled archive streams remained enabled;
- no variable or archive history was recreated;
- OAuth remained usable;
- no mower command was sent.

Installed repository state:

```text
commit: 42cbce6e
branch: main
update available: false
```

Installed adaptive state while Docked:

```text
PollInterval: 300 seconds
ActivePollInterval: 60 seconds
PollStatus timer: 300000 ms
Wake method: available
```

All temporary pre-update, post-update, validator and event-test objects were
removed from Symcon.

## 16. Remaining Live Observation

The implementation was operational after installation. The remaining gate was
to observe a normal scheduled departure passively and confirm this physical
path:

```text
station power low
-> one Wake call
-> immediate REST refresh
-> Running
-> 60000 ms polling
-> Docking
-> Docked
-> 300000 ms polling
```

No manual mower command, power-variable write or artificial state transition is
required for that observation.

## 17. Passive Observation Result

A bounded read-only review on 2026-07-21 found seven complete archived natural
sequences since installation:

```text
Running -> Docking -> Docked
```

The latest sequence correlated with low station power in the departure window
and high station power in the dock-contact window. The active OnChange hint
retained its explicit automation action binding, returned its private debounce
state to Docked at physical contact and changed no Navimow domain variable.

Account and device status remained active. `VehicleState` returned to `Docked`,
and the account's `PollStatus` timer returned to `300000 ms`. The archived
`Docking` transition lasted only a little over two minutes and was still
captured between `Running` and `Docked`.

The historical timer interval while the mower was active cannot be read back
retrospectively. The `60000 ms` interval is therefore supported by the exact
installed deterministic scheduling contract, its executable tests and the
observed active-state sequence, rather than claimed as a contemporaneous timer
sample. This limitation does not weaken the directly observed power, state and
final cadence evidence.

The MCP checks reported no transport error, execution error or truncation.
They created no object, changed no value, executed no mower command and did not
invoke Wake manually. The passive natural-transition gate is **PASS**.

## 18. Adaptive-Polling Pilot Checkpoint

After the passive gate passed, the canonical and dedicated module READMEs were
updated with the natural-transition evidence. The dedicated repository delta
from the directly tested executable commit is exactly:

```text
M README.md
```

The runtime tree remains identical to:

```text
42cbce6e7a966ad419ff9fd53ef26d94d80abe97
feat: add adaptive status polling
```

Before publication, the current tree passed:

- REST client and authentication checks;
- all 33 deterministic pilot harness cases;
- distribution structure validation;
- syntax checks for all eight productive PHP files;
- JSON parsing for all metadata, forms and locales;
- canonical/publish README byte comparison;
- staged whitespace validation.

The documentation-only commit was pushed to the dedicated module repository:

```text
397b4b0199b2caef963ebc542c84dbda9ca5ade8
docs: record adaptive polling pilot evidence
```

The following annotated tag was created and pushed:

```text
pilot-0.1.0.4
Private pilot 0.1.0.4: adaptive polling
```

Remote verification returned:

```text
tag object: c3927714aeb4c69a777026fbac9c0479fc51e06b
resolved commit: 397b4b0199b2caef963ebc542c84dbda9ca5ade8
remote main: 397b4b0199b2caef963ebc542c84dbda9ca5ade8
```

The object and resolved-commit identities of `pilot-0.1.0.1` through
`pilot-0.1.0.3` remain unchanged. No historical tag was moved or recreated.

The tag is a private-pilot checkpoint, not a public `v*` release, Store
approval or expansion of the supported command set. A Symcon runtime update is
not required for this README-only commit; the installed executable commit
remains the exact live-tested adaptive implementation.

## 19. Decision and Next Gate

**Adaptive polling implementation: PASS.**

**Passive natural-transition observation: PASS.**

**Immutable private-pilot checkpoint: PASS.**

Stop and Start remain disabled. Official SDK issue 22 was still open without a
comment on 2026-07-22. Under the bounded contact cadence from steps 76 and 78,
the next external action is one Stop inquiry follow-up no earlier than
2026-07-26. No Stop capture, API write or implementation is authorized before
the semantic gate is reopened with new evidence.
