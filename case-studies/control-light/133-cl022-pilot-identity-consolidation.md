# CL-022 Pilot Identity Consolidation

**Date:** 2026-08-04

**Scope:** Original Munich hallway authoritative-feedback pilot

**Result:** PASS

## Change Boundary

The original ControlLight v2 pilot already used authoritative feedback,
`reported` brightness semantics, the inverse Munich alarm contract and the
same immutable v2 runtime as several later wrappers. Its remaining difference
was diagnostic identity: the wrapper still reported a generic pilot version
instead of its stable installed-contract key.

The activation therefore changed only the wrapper version to the keyed
`CL-022` identity and normalized equivalent path and call formatting. Runtime,
capabilities, alarm behavior, target selection, local variables, presentation,
events and confirmation bounds remained unchanged. Existing MQTT discovery
exporter entity IDs and variable references were deliberately preserved.

## Verification

A fresh MCP preflight verified the exact preceding source, ready kernel,
target link, three active target events with explicit action binding, local
variable actions, facade-to-target equality and the complete diagnostics
baseline. A byte-exact rollback source was retained before mutation.

Post-write source readback matched the reviewed candidate. Three successful
command-free reconciliations completed with:

- no device-command increment;
- no error or confirmation-timeout increment;
- unchanged bounded error history;
- unchanged child-object and event ID sets;
- unchanged local and target state, brightness and color temperature; and
- the keyed `CL-022` version plus updated configuration hash in Registry.

One nested reconciliation was observed asynchronously and completed before the
final readback; the final counter deltas account for all three successful runs.
No restart, MQTT publication or physical-device test was required.

## Result

The pilot is now the regular keyed `CL-022` v2 wrapper without changing its
proven functional or exporter contracts. The remaining legacy wrappers are
`CL-018`, `CL-019` and `CL-029`.
