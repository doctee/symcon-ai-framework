# Wave 3 CL-025 Activation Report

**Gate:** First sequential Wave 3 member
**Result:** PASS
**Date:** 2026-07-19
**Live state:** CL-025 active on v2; CL-027 unchanged

## Transaction Result

The first Wave 3 wrapper was re-read immediately before mutation and matched
its byte-exact rollback source. Only that wrapper source was replaced. The
candidate source then matched its packaged identity on direct readback.

Two explicit configuration runs completed successfully. Both were
non-commanding synchronizations: diagnostics recorded two executions, two
successes, zero commands, zero errors and zero confirmation timeouts. No
functional device action was attempted, and rollback was not needed.

## Postflight Result

The complete postflight passed:

- the candidate wrapper retained its exact source identity;
- all 29 ControlLight wrapper sources matched the expected mixed v2/legacy
  baseline;
- the parent child allowlist and target link were unchanged;
- existing local variables were reused with their names, positions, icons,
  visibility, profiles and custom actions preserved;
- the existing STATE event was reconciled from OnChange to OnUpdate;
- the existing DIMMER event remained OnChange;
- both events remained active with explicit Run Automation action binding;
- exactly the ten allowlisted script-owned diagnostics variables were created;
- Registry, Statistics and ErrorRingBuffer invariants passed; and
- local STATE and DIMMER remained equal to authoritative target feedback.

The Registry records the versioned Wave 3 configuration, deterministic
configuration fingerprint and the agreed `reported` brightness semantics.

## Sequential Stop Gate

The second Wave 3 member was not readied, replaced or executed during this
transaction. The sequence stops here even though the first member passed.

The next step is a fresh read-only delta preflight for the STATE-only second
member against the new mixed baseline. Its activation requires another
explicit approval. A functional CL-025 device sequence is not required to
establish configuration idempotency and remains outside this activation gate.
