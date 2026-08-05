# 269 Navimow Account Status Correction Disabled Symcon Update

**Case study:** Navimow native IP-Symcon module

**Status:** Corrective update and `101`-to-`102` recovery passed; MQTT remains
disabled and credential-free

**Date:** 2026-08-05

**Scope:** Install exact published commit
`eda494513826fa43ccc1b28634b06354356f49a4` through one supported Module
Control update and verify stable Account status finalization without MQTT
activation

## 1. Result

The corrective update passed.

```text
installed before:       79686e52
installed after:        eda49451
Account before:         101
Account after:          102 / 102 / 102
MC_UpdateModule():      1
MC_ReloadModule():      0
explicit ApplyChanges:  0
update retries:         0
MQTT feature:           disabled
MQTT credentials:       absent
REST:                   operational
decision:               PASS
```

The source correction therefore resolved the stale Core instance status
through the normal supported module-update lifecycle. No additional repair
operation was required.

## 2. Authorization Boundary

The user explicitly authorized:

```text
Symcon-Update auf die Navimow-Account-Statuskorrektur mit deaktiviertem MQTT
freigegeben.
```

The authorization permitted exactly one supported module update after all
documented recovery preconditions passed. It did not authorize a reload,
explicit ApplyChanges call, MQTT activation, credential retrieval, service
restart, OAuth action or mower command.

## 3. Structured MCP Contract

All live operations used the bounded structured Symcon MCP channel.

Each of the six calls was checked independently for:

```text
transportError: null
executionError: null
truncated:      false
```

Calls:

```text
read-only preflights:  2
single update:         1
read-only postflights: 3
```

No browser, SSH, PowerShell, temporary Symcon object or alternate live channel
was used.

## 4. Recovery Preconditions

Two read-only preflights proved the same stable state immediately before the
mutation:

| Contract | Initial | Mutation-time |
|---|---|---|
| repository branch `main` | PASS | PASS |
| installed commit `79686e52` | PASS | PASS |
| repository clean and valid | PASS | PASS |
| kernel ready | PASS | PASS |
| Account status `101` | PASS | PASS |
| Configurator, Device and Receiver `102` | PASS | PASS |
| MQTT and WebSocket `104` | PASS | PASS |
| MQTT feature disabled | PASS | PASS |
| Authorization header absent | PASS | PASS |
| MQTT username/password absent | PASS | PASS |
| REST operational | PASS | PASS |
| authentication connected | PASS | PASS |
| reauthentication not required | PASS | PASS |
| 14 variable contracts | PASS | PASS |
| five Archive Control contracts | PASS | PASS |
| archive histories queryable | PASS | PASS |

Both snapshots had identical:

- configuration hash;
- variable identity hash;
- archive contract hash;
- command-evidence hash;
- subscription hash;
- instance hash.

The stale `101` status was accepted only as the exact bounded recovery target
established by steps 261 through 268.

## 5. Single Mutation

The mutation script repeated critical fail-closed checks inside the same MCP
execution before calling Module Control.

Observed result:

```text
all preconditions passed: true
update attempted:         true
operation count:          1
MC_UpdateModule result:   true
target commit observed:   true
repository clean:         true
repository valid:         true
error code:               null
```

The single update changed the installed repository from `79686e52` directly
to `eda49451`.

The procedure did not invoke:

```text
MC_ReloadModule()
IPS_ApplyChanges()
```

No retry path exists in the executed mutation script.

## 6. Post-Update Observations

Three read-only observations were captured after the update:

| Elapsed after update | Commit | Account | Result |
|---:|---|---:|---|
| approximately 20 seconds | `eda49451` | `102` | PASS |
| approximately 70 seconds | `eda49451` | `102` | PASS |
| approximately 121 seconds | `eda49451` | `102` | PASS |

At every observation:

- kernel remained ready;
- Account, Configurator, Device and Receiver were `102`;
- MQTT and WebSocket were `104`;
- repository was clean and valid on `main`;
- REST remained operational;
- authentication remained connected without reauthentication requirement;
- MQTT remained disabled;
- Authorization, MQTT username and MQTT password remained absent;
- no reconnect or Core-resume observation was armed;
- all 14 variable contracts remained present;
- all five Archive Control contracts remained logged and queryable.

The delayed observations exclude a merely transient `102` result.

## 7. Contract Comparison

The following hashes were identical before and after the update:

```text
configurationHash
identityHash
archiveHash
commandEvidenceHash
subscriptionHash
```

The instance hash changed exactly as expected because the Account instance
status changed from `101` to `102`. All other projected instance statuses were
stable.

This proves in particular that the existing logged Navimow variables retained
their identity and Archive Control configuration. Existing archive histories
remain attached to the same variables.

## 8. Preserved Architecture

```text
public device-state authority: REST
MQTT direction:                receive-only
MQTT default:                  disabled
MQTT publish path:             absent
MQTT mower-command path:       absent
reconnect delays:              60 / 300 / 900 seconds
maximum reconnect attempts:    3
Account variables:             6
Device variables:              8
Archive Control contracts:     5
pilot summary format:          1
pilot summary maximum:         16384 bytes
```

The update changed no configuration, variable, profile, archive, action,
OAuth, REST, command or MQTT recovery contract.

## 9. Private Evidence

Machine-readable evidence is retained below:

```text
private/navimow-capture/output/
  navimow-account-status-correction-disabled-symcon-update/
```

It contains:

- two read-only preflights;
- the one-shot update result;
- three read-only postflights;
- the commit-bound evidence closure.

The public report contains no ObjectID, credential, token, topic, hostname,
payload, device identity or private installation value.

## 10. Validation Toolchain Clarification

The isolated Navimow worktree intentionally contained no local `vendor/`
directory. A literal repository `make check` invocation therefore stopped
after the successful Open-Meteo fixture tests when its fixed local
`vendor/bin/phpstan` path was absent.

The remaining checks were then executed against the same Navimow worktree
with the existing canonical Composer executables supplied explicitly from a
separate checkout. This did not change the source under test:

```text
Navimow worktree composer.lock SHA-256:
b108c9f037ca0e575cd827914baf355131205825752b474c1799dfd14f07547c

tool-provider composer.lock SHA-256:
b108c9f037ca0e575cd827914baf355131205825752b474c1799dfd14f07547c

Composer dependency delta: nothing to install, update or remove
```

The successful equivalent gate comprised:

- the complete Composer `check` script in the Navimow worktree;
- repository-wide PHPStan and PHPCS against the Navimow worktree;
- the remaining Open-Meteo PHPStan and PHPCS commands against that worktree;
- Navimow REST, pilot, lifecycle and distribution validation.

No source, configuration or generated artifact was read from a dirty
historical worktree. No `vendor/` link or dependency artifact was created in
the Navimow worktree. Open-Meteo appeared only because it is an unchanged
repository-wide regression target of the SAEF gate.

This distinction supersedes any shorthand statement that the literal
worktree-local `make check` command itself completed successfully during this
step.

## 11. Architecture Decisions

### AD-NAV-1076: Accept 101 only for the designed recovery

The update did not weaken the normal healthy-status contract. It accepted the
exact diagnosed stale state only with every surrounding safety contract
unchanged.

### AD-NAV-1077: Repeat preflight immediately before mutation

Two equal contract projections close the gap between historical diagnosis and
the actual update operation.

### AD-NAV-1078: Enforce critical checks inside the mutation call

Repository, kernel, status, disabled transport, credential and REST conditions
were re-read before the sole update operation.

### AD-NAV-1079: Do not reload or explicitly apply changes

The correction must prove that its normal module-update lifecycle finalizes
status. Additional lifecycle calls would obscure that evidence.

### AD-NAV-1080: Verify delayed status stability

Three post-update observations through `+121 s` prove the Account does not
return to the stale creating state.

### AD-NAV-1081: Preserve archive identity as a hard contract

Unchanged identity and archive hashes protect the user's existing Navimow
logging and historical data.

### AD-NAV-1082: Keep MQTT closed after recovery

Status recovery does not authorize staging, activation, credential retrieval
or a new pilot.

## 12. Safety Result

```text
module updates:        1
module reloads:        0
explicit ApplyChanges: 0
update retries:        0
service restarts:      0
MQTT activations:      0
credential requests:   0
OAuth actions:         0
mower commands:        0
```

## 13. Gate Status

| Gate | Status |
|---|---|
| Gate A standalone publication | PASS |
| Gate B metadata conformance | PASS THROUGH ESTABLISHED FALLBACK |
| Gate C corrective Symcon update | PASS |
| Account `101`-to-`102` recovery | PASS |
| variable and archive preservation | PASS |
| Gate D evidence closure | PASS |
| MQTT staging or activation | CLOSED |
| service restart | CLOSED |
| mower command | CLOSED |

## 14. Next Step

Proceed with:

```text
270-navimow-account-status-correction-integration-review.md
```

That step should consolidate the completed correction, decide SAEF branch
publication and review whether a later disabled restart observation adds useful
evidence. It must not activate MQTT, restart Symcon or issue a mower command
without separate authorization.
