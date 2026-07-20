# Wave 3 Live Preflight Report

**Gate:** Fresh read-only live preflight
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Inspected only; unchanged

## Execution Result

The packaged Wave 3 preflight was executed directly in IP-Symcon with bounded
structured output. Transport and PHP execution both succeeded independently,
and the returned result was not truncated. The script reported no mutation
attempt and did not run a ControlLight wrapper or issue a device action.

## Verified Dependencies

- the kernel was ready;
- the process-effective shared variable-wait helper resolved to the corrected
  hash-locked implementation;
- the complete ControlLight fileset and every ordered source matched the
  packaged identities; and
- all 29 installed ControlLight wrapper sources matched the expected current
  regression baseline.

## Candidate Result

Both sequential Wave 3 candidates retained their exact legacy wrapper source,
parent and child allowlists, target link, alarm dependency, local ownership and
custom action contracts. Every configured target was actionable.

The first member's STATE and DIMMER values matched their authoritative target
feedback. The second member's STATE value also matched. All existing target
events were active, used the expected trigger variables and retained explicit
Run Automation action binding.

No installed script source, exact event trigger or presentation link consumed
either candidate's local variables. No v2 diagnostic Ident already existed
below either wrapper. The preflight also captured the current user-editable
presentation metadata so it can be compared after reconciliation.

## Gate Decision

Wave 3 live preflight is **PASS**. This does not authorize activation.

The next gate is activation of the first cohort member only. That transaction
must set only its wrapper source, run two non-commanding configuration passes,
then prove source identity, object reuse, user-presentation preservation,
STATE-event reconciliation to OnUpdate, diagnostics invariants, authoritative
feedback equality, zero command/error/timeout deltas and complete 29-wrapper
source regression. Any failure stops and restores the exact legacy wrapper.

The second cohort member remains untouched until a separate successful
first-member postflight decision.
