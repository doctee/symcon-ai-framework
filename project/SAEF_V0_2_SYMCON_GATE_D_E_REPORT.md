# SAEF v0.2 Symcon Gates D and E Report

**Gates:** Atomic owner activation and independent read-only runtime verification
**Result:** PASS
**Date:** 2026-07-20
**Behavior state:** No MQTT publication, configuration run or device action

## Scope

Gate D selected the immutable v0.2.0 MQTT Discovery Exporter fileset as the
global SAEF owner through the reviewed external Windows activation transaction.
Gate E then inspected the restarted process independently through bounded,
read-only Symcon probes.

Installation paths, object IDs, private source content, runtime metadata and
the byte-exact rollback remain outside this repository.

## Activation Result

The external transaction:

- revalidated the active and candidate filesets and the rollback immediately
  before activation;
- replaced exactly one equal-length bootstrap reference atomically;
- restarted IP-Symcon through the state-based external coordinator;
- observed the ready runlevel with a newer kernel start identity; and
- completed without rollback or recovery restart.

The wrapper returned exit code `0`. It explicitly reported that neither a
device action nor an MQTT action was attempted.

## Effective Runtime Identity

The first independent post-restart probe confirmed:

- the active bootstrap contains the final v0.2.0 owner reference exactly once
  and no former owner reference;
- the selected fileset marker, manifest and ordered source map match the
  immutable release artifact;
- the framework version is `0.2.0`;
- every inspected global SAEF helper resolves to the selected final fileset;
- the MQTT exporter core and runtime match their release source identities;
- no source from the former candidate is loaded; and
- the kernel is ready after a clean process restart.

The verified helper responsibilities include validation, object Ensure
helpers, event helpers, variable waiting and all four Runtime Diagnostics
building blocks.

## Runtime Diagnostics

The bounded Gate E probe verified the existing exporter-owned structure without
calling an Ensure, configuration, reconcile or dispatch path:

- one diagnostics category with the expected eleven variables;
- a valid small Registry JSON document;
- a valid ErrorRingBuffer list within its fixed capacity of 20 entries;
- six integer counters and three integer Unix-timestamp statistics with the
  expected profiles;
- consistent counter relationships; and
- no diagnostics variable update since the new kernel start.

A second read-only snapshot found the Registry, ErrorRingBuffer and counters
unchanged. Registry contents and error entries were intentionally not returned
or recorded in this public report.

## Event And Object Topology

The exporter retained its established object count and type distribution. Its
six events are active, owned by the exporter, have stable Idents, target
existing variables and use only the supported update or change triggers. Every
event retains the explicit IP-Symcon Run Automation action binding.

The read-only inspection also found no links, archive logging changes or custom
variable actions below the exporter owner. Existing standard variable actions
remained present in their established topology.

## Safety Boundary

All successful MCP probes reported:

- no transport error;
- no PHP execution error;
- no output truncation;
- no Symcon mutation;
- no MQTT action; and
- no device action.

The initial metadata-schema probe produced assertion failures only because it
assumed the standard profile field instead of the effective custom profile and
only one of the two supported event trigger types. It changed no state. The
corrected probe used the documented effective profile and both supported
update/change trigger types and passed without failures.

## Gate Decision

Gates D and E are **PASS**. The final v0.2.0 MQTT fileset is now the verified
process-effective global SAEF owner. The prepared rollback remains available.

Gate F is not authorized by this result. Repeated MQTT configuration/reconcile,
publication, command-path testing and selection of the staged ControlLight
fileset remain separate supervised actions. Physical device tests require their
own explicit approval.

## Related Artifacts

- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `project/SAEF_V0_2_SYMCON_GATE_B_REPORT.md`
- `project/SAEF_V0_2_SYMCON_GATE_C_REPORT.md`
- `deployments/symcon/windows/README.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
