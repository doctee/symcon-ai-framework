# 97 Distribution/Main Drift Consolidation

**Case study:** Navimow native IP-Symcon module
**Status:** Completed; distribution/main drift closed and post-update
compatibility passed
**Date:** 2026-07-28
**Scope:** Close the pre-existing distribution-to-main drift without including
MQTT shadow implementation

## 1. Purpose

This step executes `WP-0` from
`96-native-mqtt-shadow-implementation-plan.md`.

It separates three pre-existing productive-file differences from all later
MQTT work, publishes the reviewed result to the standalone module `main`,
updates the existing Symcon installation and verifies that the update preserves
the productive instance, variable and archive contract.

No MQTT shadow source, probe module or productive MQTT topology belongs to this
step.

## 2. Authorization and Safety Boundary

The user authorized:

- publication of the already reviewed three-file consolidation;
- update of the existing Navimow module through Module Control;
- one bounded, read-only post-update compatibility probe.

The step did not authorize:

- creation or replacement of productive instances;
- recreation of variables;
- archive changes;
- a mower command;
- MQTT publish;
- installation of the earlier receive probe;
- `MC_ReloadModule()`.

The post-update probe reads only module-instance, instance-configuration,
variable-metadata and archive-configuration APIs. It does not read variable
values and returns no ObjectID, object name, configuration content or private
topic.

## 3. Drift Revalidation

The fresh comparison retained exactly the previously classified delta:

```text
NavimowAccount/module.php
NavimowDevice/module.php
libs/Navimow/PayloadMapper.php
```

Classification:

| File | Classification | Decision |
| --- | --- | --- |
| `NavimowAccount/module.php` | Functional adaptive-polling state-bound hardening | Publish as the behavior-bearing part of this isolated consolidation. |
| `NavimowDevice/module.php` | Formatting only | Consolidate with no contract change. |
| `libs/Navimow/PayloadMapper.php` | Formatting only | Consolidate with no mapping change. |

No additional file entered the standalone publication diff. In particular,
`NavimowMqttReceiveProbe/` and every MQTT shadow candidate remained absent from
`main`.

## 4. Offline Verification

Before publication, the following gates passed:

- Navimow REST client and authentication regression;
- the complete Navimow pilot observation harness, including command recovery
  and adaptive polling;
- distribution structure validation;
- PHP syntax validation;
- the complete repository `make check`.

After publication, the focused gates were repeated on 2026-07-28:

```text
composer test:navimow-rest-auth
composer test:navimow-pilot
composer test:navimow-distribution
php -l private/navimow-capture/post-update-compatibility-probe.php
```

All passed.

## 5. Standalone Publication

The three-file consolidation was committed independently to the standalone
module:

```text
repository:
doctee/symcon-navimow

branch:
main

commit:
2c32b868dda3ca5683b86715c44ea4f3291472ab

message:
feat: harden adaptive polling state bounds
```

The commit was created on 2026-07-28 at `05:32:08Z`.

No pilot or release tag was created.

## 6. Byte-Equality Closure

The Git blob IDs of all 19 productive files were compared between:

```text
case-studies/navimow/distribution/
```

and:

```text
doctee/symcon-navimow main
```

Every blob ID matched. This covers:

- `library.json` and `README.md`;
- all Account, Configurator and Device metadata and module files;
- all five files below `libs/Navimow/`.

The canonical SAEF distribution and standalone `main` are therefore
byte-identical for the complete productive module tree at commit
`2c32b868dda3ca5683b86715c44ea4f3291472ab`.

## 7. Symcon Update

The user updated the existing Navimow module through Module Control while
retaining branch `main`.

The update did not use `MC_ReloadModule()` and did not delete or recreate an
Account, Configurator or Device instance.

The last closed private compatibility baseline remains the comparison
authority for:

- exactly one productive instance of each of the three module roles;
- healthy productive instance status;
- the three instance-configuration hashes;
- all 14 existing variable contracts;
- archive logging and aggregation for those variables.

## 8. Read-Only Compatibility Probe

The private bounded probe is retained at:

```text
private/navimow-capture/post-update-compatibility-probe.php
```

It was executed through `symcon_run_script_text_ex` with a 4,096-byte output
limit. SAEF requires separate evaluation of:

- MCP `transportError`;
- PHP `executionError`;
- output `truncated`;
- decoded aggregate `pass`.

The public acceptance result may record only sanitized aggregate facts. Exact
installation metadata remains private.

## 9. Final Compatibility Result

The configured Symcon MCP tool executed the exact private probe on 2026-07-28.
It returned:

| Invariant | Result |
| --- | --- |
| MCP transport | `success = true`; `transportError = null` |
| PHP execution | `executionError = null` |
| bounded output | `truncated = false` |
| productive instances | exactly one per role; all status `102` |
| instance configuration | all three hashes equal |
| variables | 14 of 14 verified |
| variable metadata | equal |
| archive configuration | equal |
| aggregate result | `pass = true` |

The five variables expected to retain active archive logging were:

```text
BatteryLevel
LastCommand
LastCommandResult
Online
VehicleState
```

The result contained no errors. No mutation or device action was attempted.

The exact machine-readable closure is retained only in the private overlay:

```text
private/navimow-capture/output/
  distribution-main-drift-consolidation/evidence-closure.json
```

## 10. Decision

**Three-file drift classification: CLOSED.**

**Isolated standalone publication: PASS.**

**Distribution/standalone byte equality: PASS.**

**MQTT implementation contamination: NONE.**

**Focused offline regression: PASS.**

**Post-update Symcon compatibility: PASS.**

**`WP-0`: CLOSED.**

**Step 97: COMPLETE.**

The first offline MQTT shadow increment may now begin with `WP-1` through
`WP-3`. This decision does not authorize publication, a live topology change,
MQTT publish or a mower command.
