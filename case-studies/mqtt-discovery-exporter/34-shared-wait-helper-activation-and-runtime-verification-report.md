# Shared Wait Helper Activation and Runtime Verification

**Gate:** Gate C activation and Gate D read-only verification
**Result:** PASS
**Date:** 2026-07-19
**Live mutation:** One atomic bootstrap selection and one supervised clean-process restart

## Activation Result

The approved external Windows transaction completed with exit code zero. The
service returned to ready runlevel `10103` with a strictly newer kernel start
identity. The coordinator reported `activated`; neither rollback nor recovery
restart was attempted.

Before replacing the bootstrap reference, the transaction revalidated the
active and staged filesets, both wait-helper identities, the unique include
token, the byte-exact rollback and the external service preflight. The
equal-length include-token replacement was atomic. The activation wrapper did
not request a device action or invoke an MQTT operation.

## Effective Runtime Identity

Independent read-only source and Reflection probes after the restart proved:

- `System.Locals` retained its exact byte count and contains exactly one new
  fileset reference and no old reference;
- its SHA-256 is the pinned activated identity;
- `SAEF_WaitForVariable()` is defined by the staged MQTT Discovery Exporter
  fileset selected in Gate C; and
- the reflected helper SHA-256 is
  `4b79fb7a7339573f61a84d64e8634d6dc7faa3d161f645277a5e62228b8a7222`.

This closes the load-owner conflict. A subsequently loaded ControlLight
runtime now reuses the corrected authoritative helper instead of retaining the
preceding globally guarded implementation.

## Regression Evidence

The post-restart probe found all 29 installed ControlLight wrapper sources at
their expected byte identities, including the hallway pilot and the selected
Wave 2 candidate. There were no source mismatches. The Wave 2 candidate retained
safe authoritative equality: STATE was false locally and at the target, and
reported DIMMER was 100 on both sides.

The exporter retained its six active triggered events with explicit Run
Automation action binding. Commands, failures and ErrorRingBuffer content did
not change. Two successful exporter executions and eight publications had
occurred after the Gate B snapshot, but their statistic and event timestamps
preceded the new kernel start by approximately 89 minutes. They therefore were
normal pre-maintenance activity, not restart side effects. No exporter counter
changed after the new kernel start.

Both bounded probes completed without transport error, PHP execution error or
output truncation.

## Gate Decision

Gate C and Gate D are **PASS**. The shared wait-helper deployment is complete,
and its process-effective identity is independently proven. This result does
not itself authorize a new device command. The stopped ControlLight functional
sequence remains a separate gate with fresh preflight, explicit approval,
stepwise authoritative confirmation and immediate compensation on the first
discrepancy.
