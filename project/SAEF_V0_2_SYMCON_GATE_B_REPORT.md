# SAEF v0.2 Symcon Gate B Report

**Gate:** Backup and inactive release-fileset staging
**Result:** PASS
**Date:** 2026-07-20
**Activation state:** Final v0.2.0 candidates present but unselected

## Scope

Gate B created a recoverable private backup and staged the immutable v0.2.0
MQTT Discovery Exporter and ControlLight filesets on the authorized IP-Symcon
installation.

The gate did not change the active bootstrap, restart IP-Symcon, execute a
configuration script, change a Symcon object, publish MQTT data or invoke a
device action. Installation paths, object identities and private bootstrap
content remain outside this repository.

## Preflight

The preflight confirmed:

- the kernel was ready;
- the previously inventoried MQTT development fileset remained the active
  global SAEF owner;
- the active bootstrap source existed with its captured identity;
- final and temporary candidate destinations did not exist; and
- the private backup destination was collision-free.

Any collision or owner drift would have stopped the operation before the first
write.

## Recoverable backup

The private backup contains:

- all 15 files from the active MQTT fileset;
- the byte-exact active `System.Locals` source; and
- private restore metadata with the complete relative file map and SHA-256
  identities.

Every copied file was compared with its source before the backup directory was
atomically finalized. Independent readback later reconfirmed all 15 files,
`System.Locals` and the metadata contract. The active source identities remained
unchanged.

## Inactive staging

The release filesets were transferred into isolated temporary directories:

| Candidate | Files | Fileset identity |
| --- | ---: | --- |
| MQTT Discovery Exporter | 15 | `553518512dfabdebf0f24fc668f4ce35234fe578f69e0a5eae22687a334d039c` |
| ControlLight | 14 | `434d0ad0cfd2789214e98e5ff843c7a3218612e6b4b4d130f3aee679a1abc8be` |

Each file was:

1. compressed only for bounded transport;
2. decoded inside its temporary candidate tree;
3. checked for exact byte length and SHA-256 identity;
4. written through a temporary file; and
5. atomically finalized only after readback matched.

Before directory finalization, both complete trees were compared with the exact
repository-relative file maps. Their manifests had to declare framework
version `0.2.0` and the expected bootstrap and aggregate identities. Both trees
were then finalized under hash-addressed names. A paired rename rollback was
available if the second finalization failed.

## Independent postflight

The separate readback established:

| Contract | Result |
| --- | --- |
| MQTT exact file map and per-file hashes | PASS, 15 of 15 |
| ControlLight exact file map and per-file hashes | PASS, 14 of 14 |
| Temporary candidate directories absent | PASS |
| Private backup map and active-bootstrap copy | PASS |
| Active MQTT fileset identity unchanged | PASS |
| Active `System.Locals` identity unchanged | PASS |
| Effective wait-helper identity unchanged | PASS |
| Candidate files loaded into the PHP context | 0 |
| Candidate-token references outside staged trees | 0 |
| Bounded external-reference scan | PASS, 1,743 files |
| IP-Symcon restart | None |
| Symcon object or script execution | None |
| MQTT publication or device action | None |

All successful MCP operations reported no transport error, no PHP execution
error and no output truncation.

## Legacy decision

No legacy object was recreated or normalized during Gate B. This preserves the
rollout plan's distinction:

- deployment artifacts advance as complete immutable units;
- compatible runtime and domain state remains in place;
- the shared legacy function library remains available while callers exist;
- individual callers migrate only after their own contract and observation
  gates; and
- obsolete functions may be retired only after static and dynamic caller
  audits.

## Gate decision

Gate B is **PASS**. Both final v0.2.0 candidates are present, immutable by their
verified content and safely inactive.

The next step is Gate C, a fresh non-activating maintenance preflight using the
existing external restart coordinator. This report does not authorize changing
the active bootstrap, restarting IP-Symcon, selecting a ControlLight consumer,
executing a configuration path or testing a physical device.

## Related artifacts

- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `deployments/symcon/windows/README.md`
- `dist/symcon/saef-mqtt-discovery-exporter/fileset.sources.json`
- `dist/symcon/saef-control-light/fileset.sources.json`
