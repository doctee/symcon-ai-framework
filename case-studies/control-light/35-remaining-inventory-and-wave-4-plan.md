# Remaining Inventory and Wave 4 Plan

**Gate:** Fresh read-only assessment after current-fileset closure
**Result:** PASS WITH EXCLUSIONS — WAVE 4 CANDIDATES SELECTED
**Date:** 2026-07-20
**Live impact:** None

## Repository baseline

The sanitized inventory now records the four proven v2 instances with explicit
`reported` brightness semantics and leaves exactly 25 legacy instances as
`pending`. The ControlLight overview, fixture assertions and active/legacy
counts agree.

Core, runtime, topology, managed-mirror and fileset tests all pass. The generated
fileset remains byte-current after the fixture correction because the fixture is
test input, not part of the deployed runtime artifact.

## Read-only live result

A bounded live probe checked the complete installed inventory without executing
a wrapper or requesting an action. Transport and PHP execution completed without
error or truncation.

- all 29 wrapper objects retained their parent and expected source identity;
- all four v2 wrappers selected the current hash-addressed fileset;
- all 25 legacy wrappers retained their captured baseline source and were not
  broken;
- all captured local variables retained type, parent, Ident and action contract;
- all captured legacy events retained parent, trigger, active state and explicit
  event action binding; and
- no object, source, event, variable or device was changed.

An initial compatibility probe used two unsuitable read APIs for variable
references and historical event records. It produced warnings but performed no
mutation and was discarded as evidence. The corrected probes used scalar event
IDs, object-type guards and the established ownership parents; they completed
cleanly and provide the result above.

## Current exclusions

Three off-state contracts are not non-commanding migrations under the agreed
`reported` default: CL-024, CL-026 and CL-028 currently expose local DIMMER zero
while their targets retain non-zero brightness. Configuration would therefore
change visible local state even without a device command. Each needs a separate
presentation-sync gate or an explicitly justified `effective` exception.

Two target contracts need diagnosis before candidacy:

- CL-009 exposes its Matter color target without a variable action, although its
  state, brightness and temperature targets are actionable;
- CL-014 still cannot resolve a compatible Homematic STATE target through the
  configured v2 contract.

CL-006 remains a non-operational template with no enabled capability. It is not
a live migration candidate.

## Remaining-cohort ranking

| Treatment | Contracts | Count |
| --- | --- | ---: |
| Wave 4: operationally reachable Z2M STATE/DIMMER/temperature, feedback aligned | CL-004, CL-017 | 2 |
| First full-color live pilot after Wave 4 | CL-011 | 1 |
| Link-dependent STATE/DIMMER, feedback aligned | CL-005, CL-008, CL-010 | 3 |
| Link-dependent temperature, feedback aligned | CL-001, CL-007, CL-012, CL-013 | 4 |
| Link-dependent full color, feedback aligned | CL-003, CL-016, CL-018, CL-020, CL-021, CL-029 | 6 |
| External-trigger contracts | CL-002, CL-015 | 2 |
| Off-state reported-sync decision | CL-024, CL-026, CL-028 | 3 |
| Target-action diagnosis | CL-009, CL-014 | 2 |
| Hard-powered-off operational exclusion | CL-019 | 1 |
| Inert template | CL-006 | 1 |

The counts cover all 25 remaining legacy wrappers exactly once.

## Wave 4 decision

CL-004 followed by CL-017 is the next recommended sequential wave. Both use the
same Z2M STATE/DIMMER/color-temperature shape, have actionable targets and
currently match authoritative reported feedback. CL-017 has one existing
presentation link; the activation gate must preserve its target and presentation
metadata exactly. Its fresh target updates provide stronger operational evidence
than CL-019, which the installation owner reports is currently hard-powered off.
Stale values retained in Symcon do not prove that CL-019 can provide authoritative
feedback, so it is excluded until power and reachability are restored.

The hallway pilot already supplies bounded live evidence for the same three
capabilities. Wave 4 extends that evidence to two additional independent wrappers
without combining it with external triggers, color conversion or an off-state
presentation change.

The private, hash-locked Wave 4 package and rollback manifest were subsequently
completed in report 36. Live staging, wrapper source selection, configuration
execution and device tests remain separate gates. Each candidate must pass a
fresh immediate delta preflight, sequential activation with a stop boundary,
two-run idempotency, presentation preservation, zero-command synchronization,
complete 29-wrapper regression and a separately approved bounded functional
sequence covering every enabled capability.
