# SAEF v0.2 Symcon Gate C Report

**Gate:** Fresh drift validation and external maintenance preflight
**Result:** PASS
**Date:** 2026-07-20
**Activation state:** Final v0.2.0 candidates remain unselected

## Scope

Gate C revalidated the active global SAEF owner, the Gate B rollback, both
staged release filesets and the external Windows restart coordinator. The
coordinator ran only with `-PreflightOnly` from an administrative Windows
PowerShell process outside IP-Symcon.

The PowerShell execution policy was set to `Bypass` only for that process. No
user or machine policy was changed.

## Drift correction

The first read-only preflight found one safe staging defect: the MQTT candidate
directory used a 17-character aggregate-hash prefix while the active directory
used the established 16-character prefix. File contents and manifests were
correct, but the resulting include tokens had different byte lengths and could
not support the reviewed atomic replacement.

The still unloaded and unreferenced candidate directory was renamed to the
correct 16-character prefix. A complete repeat then proved:

- both include tokens are exactly 54 UTF-8 bytes;
- the active token occurs exactly once;
- the candidate token does not occur;
- the MQTT candidate still contains exactly 15 verified files;
- the ControlLight candidate still contains exactly 14 verified files;
- both manifests, bootstrap files, source maps and aggregate markers match the
  immutable v0.2.0 artifacts;
- the complete active-fileset backup and `System.Locals` rollback match their
  recorded identities; and
- no candidate file is loaded into the current PHP context.

This correction changed no active source and did not require a restart.

## External preflight

The private Gate C package contained:

- the unchanged standard restart coordinator and policy;
- a preflight-only wrapper pinned to active, candidate and rollback hashes;
- the byte-exact `System.Locals` rollback;
- the sanitized MCP drift result; and
- a complete package SHA-256 inventory.

All eight package files and the decoded rollback were verified before the
package was executed externally.

The external result was:

| Contract | Result |
| --- | --- |
| Wrapper result | `SAEF v0.2.0 GATE C EXTERNAL PREFLIGHT PASS` |
| Exit code | `0` |
| Service state | `Running` |
| Symcon runlevel | `10103` |
| Active fileset identity | PASS |
| MQTT candidate identity | PASS |
| ControlLight candidate identity | PASS |
| Active `System.Locals` identity | PASS |
| Rollback identity | PASS |
| Active token count | `1` |
| Candidate token count | `0` |
| Restart attempted | No |
| Rollback attempted | No |
| Activation attempted | No |

## Independent postflight

A separate bounded MCP probe after the external PowerShell run confirmed:

- the kernel remained ready;
- active `System.Locals` and the active MQTT fileset were unchanged;
- the active token still occurred once and the candidate token zero times;
- no candidate source was loaded; and
- the effective wait helper still resolved to the active development fileset.

The probe reported no transport error, PHP execution error or truncation.

## Gate decision

Gate C is **PASS**. The installation and rollback are ready for the separately
authorized Gate D owner activation.

This report does not authorize changing `System.Locals`, restarting IP-Symcon,
executing MQTT configuration or publication paths, selecting a ControlLight
wrapper or invoking a device action.

## Related artifacts

- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `project/SAEF_V0_2_SYMCON_GATE_B_REPORT.md`
- `deployments/symcon/windows/README.md`
