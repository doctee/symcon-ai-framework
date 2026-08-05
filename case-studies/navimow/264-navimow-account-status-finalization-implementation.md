# 264 Navimow Account Status Finalization Implementation

**Case study:** Navimow native IP-Symcon module

**Status:** Narrow corrective candidate implemented and fully validated offline;
publication and live gates remain closed

**Date:** 2026-08-04

**Scope:** Implement the status-finalization contract selected in step 263
without publishing or accessing Symcon

## 1. Result

The Account now explicitly sets instance status `102` after every successful
terminal path in `ApplyChanges()`.

```text
productive files changed: 1
productive insertions:    5
productive deletions:     0
metadata changes:         0
new variables:            0
new timers:               0
new properties:           0
new attributes:           0
```

The shared Navimow test harness now models the Core lifecycle transition from
`101` at `parent::ApplyChanges()` to the module-selected final status. Existing
MQTT lifecycle scenarios verify the new contract across all relevant normal
branches.

All focused and complete offline gates passed.

## 2. Productive Change

Changed file:

```text
case-studies/navimow/distribution/NavimowAccount/module.php
```

The Account defines one private implementation constant:

```php
private const INSTANCE_STATUS_ACTIVE = 102;
```

Each normal terminal branch performs:

```php
$this->SetStatus(self::INSTANCE_STATUS_ACTIVE);
```

The four covered terminal paths are:

1. invalid or incomplete cloud configuration;
2. valid configuration with authorization pending;
3. authenticated configuration with kernel reconciliation deferred;
4. authenticated configuration with normal startup scheduling complete.

Status finalization occurs after branch-specific cleanup, authentication state,
timer scheduling and kernel lifecycle work. It is not placed in `finally` and
does not run before an operation that may still throw.

## 3. Status Semantics

Status `102` means that the PHP module instance completed its configuration
application. It does not claim:

- valid cloud credentials;
- a usable OAuth token;
- successful REST communication;
- enabled MQTT;
- an active MQTT transport.

Those states remain represented by the existing owned variables and bounded
diagnostics. No custom Core status code or form metadata was introduced.

## 4. Harness Correction

Changed file:

```text
case-studies/navimow/tests/harness/SymconRuntime.php
```

The fake runtime now:

- defines `IS_CREATING` and `IS_ACTIVE` for tests;
- starts each instance at `101`;
- resets status to `101` in `parent::ApplyChanges()`;
- captures module `SetStatus()` calls;
- exposes the current value through `testStatus()`.

This closes the exact test gap identified in steps 261 and 262. Before this
change, repeated `ApplyChanges()` tests validated timers, state and transport
but could not observe the Core instance status.

## 5. Focused Test Coverage

Changed file:

```text
case-studies/navimow/tests/mqtt-transport-lifecycle.php
```

The existing high-fidelity lifecycle scenarios now require status `102` for:

| Scenario | Result |
|---|---|
| incomplete default configuration with MQTT disabled | PASS |
| valid configuration with authorization pending | PASS |
| authenticated normal startup | PASS |
| expired-token kernel reconciliation deferral | PASS |
| invalid-configuration kernel reconciliation deferral | PASS |
| authenticated kernel reconciliation deferral | PASS |
| active-to-disabled transport cleanup | PASS |
| repeated disabled `ApplyChanges()` | PASS |

The tests still require the previous transport, credential, timer, Registry and
public-variable behavior. Status assertions are additive and do not replace an
existing lifecycle assertion.

## 6. Candidate Identity

| Artifact | SHA-256 | Git blob |
|---|---|---|
| Account module | `d97068a917ac064d70c8e3d78b3e1912b53cfcd5e2dee4439e743e509cd74b1c` | `ad4432c29613062cd277e44ed161a7877b624da5` |
| Symcon test harness | `8ce3ad73bc40883ec7d62bdbef1f391906e50ec3cb6fbce01989fa80f8fe9a28` | `fbc054e6e1bef2b4c770bdf641d98ebb98e7ce1d` |
| lifecycle test | `75960576f711711134a30f1773c3131f639cc4db9725f7c0879001e5939e4195` | `a957d971c296f2f027bb074a22ea7e8f0e888e2f` |

Current standalone `main@a8481c97` Account SHA-256 remains:

```text
77b39742ef65292abdd63f95145af384d859d9d713dd19dddbce1a6263a5c7d4
```

The corrective candidate preserves the episode-summary implementation and adds
only the explicit status contract.

## 7. Validation

### Focused lifecycle

```text
PHP syntax:                         PASS
MQTT transport lifecycle:          PASS
status finalization scenarios:     8/8 PASS
```

### Navimow suites

```text
REST and authentication:           PASS
MQTT fixtures and envelope:        PASS
partial payload parser:            PASS
Receiver and Account ingestion:    PASS
shadow diagnostics:                PASS
pilot checkpoints and accounting:  PASS
transport lifecycle and recovery:  PASS
distribution validation:           PASS
pilot observation harness:         PASS
private pilot accounting harness:  PASS
PHPCS:                              PASS
PHPStan with 512 MiB:               PASS
```

### Complete repository

```text
make check: PASS
```

The isolated worktree temporarily referenced the existing main-workspace
Composer `vendor/` directory only to execute unchanged tooling. The temporary
link was removed immediately after the check and is absent from Git status.

## 8. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
Account variables:             6
Device variables:              8
Archive Control contracts:     5
pilot summary format:          1
pilot summary maximum:         16384 bytes
```

The change does not alter OAuth, polling, retry, MQTT recovery, command or
variable behavior.

## 9. Architecture Decisions

### AD-NAV-1045: Model the real Core pre-status in tests

Resetting the harness to `101` at every base ApplyChanges call makes missing
status finalization observable instead of assuming a successful Core outcome.

### AD-NAV-1046: Keep productive correction additive

One private constant and four terminal calls are sufficient. No lifecycle code
is reordered or abstracted.

### AD-NAV-1047: Finalize after branch work

The module claims `102` only after each normal branch has completed its owned
work. Exceptions cannot be masked by a final status call.

### AD-NAV-1048: Avoid shared-stub scope expansion

The productive module uses its own explicit status constant. A shared SAEF stub
change is unnecessary for this one-module contract.

### AD-NAV-1049: Preserve metadata byte-for-byte

No status section or custom status code is needed. Authentication and transport
detail remain in existing domain diagnostics.

## 10. Safety Result

This step performed:

```text
standalone publication: 0
repository pushes:      0
Symcon reads:           0
Symcon mutations:       0
MQTT activations:       0
credential requests:    0
service restarts:       0
mower commands:         0
```

Installed Symcon remains on `79686e52` with the previously documented status
`101`. Standalone public `main` remains `a8481c97`.

## 11. Gate Status

| Gate | Status |
|---|---|
| recovery design | PASS |
| status-finalization implementation | PASS |
| focused tests | PASS |
| complete offline validation | PASS |
| corrective candidate freeze | PASS |
| corrective standalone publication | CLOSED |
| metadata conformance | CLOSED |
| corrective Symcon update | CLOSED |
| MQTT staging or activation | CLOSED |

## 12. Next Step

Proceed with:

```text
265-navimow-account-status-correction-publication-plan.md
```

That step should plan exact one-file publication from the frozen candidate to
standalone `main`, followed by separate metadata and corrective Symcon gates.
It must not publish, access Symcon or activate MQTT without their respective
explicit authorizations.
