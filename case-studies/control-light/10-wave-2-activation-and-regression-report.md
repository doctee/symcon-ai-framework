# ControlLight Wave 2 Activation and Regression Report

**Gate:** Single-wrapper activation, authoritative feedback and full regression
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Two v2 wrappers active; 27 wrappers remain legacy

## Activation Scope

After explicit approval, only sanitized contract `CL-023` was migrated. It
loads the already active aggregate-hash-versioned ControlLight runtime directly.
The shared legacy core, global bootstrap, first v2 pilot and other 27 wrappers
were outside the mutation boundary.

The configuration enables STATE and DIMMER, requires authoritative feedback and
records `brightnessSemantics=reported`. Temperature, color and external
triggers are disabled.

## Protected First Attempt

The first attempt completed one successful v2 synchronization with no command
or runtime error. The activation verifier then incorrectly parsed the permitted
empty initial ErrorRingBuffer string as JSON and reported an unexpected end.

The compensating transaction restored the exact legacy source, executed its
initialization, removed exactly the ten newly created allowlisted diagnostic
variables and verified:

- original source identity;
- original two child-event IDs and OnChange contracts;
- local actions and authoritative feedback;
- parent and wrapper child allowlists;
- shared core and all 29 wrapper identities.

The cause was confined to the external verifier. SAEF's ErrorRingBuffer helper
explicitly defines an empty string as an empty history. The verifier was
corrected to accept both an empty string and an empty JSON array.

## Corrected Activation

A complete fresh preflight passed again before the retry. The corrected
activation then completed two synchronization runs.

| Statistic | Final value |
| --- | ---: |
| Executions | 2 |
| Successes | 2 |
| Commands | 0 |
| Errors | 0 |
| Confirmation timeouts | 0 |

STATE remained false locally and at the target. DIMMER remained 100 locally and
at the target. This proves retained reported brightness while off without
switching the device or issuing any target command.

Both existing feedback event IDs were reused. The STATE event was intentionally
reconciled from the legacy OnChange contract to the v2 OnUpdate contract;
DIMMER remains OnChange. Names, positions, icons, visibility, parents, local
variable IDs and custom actions were preserved.

Ten owned diagnostic variables were created during the first corrected run and
reused with identical IDs during the second. The Registry records the Wave 2
runtime version and `reported` semantics; the ErrorRingBuffer is empty.

## Independent Verification

- direct source read-back exactly matched the approved candidate;
- the shared core remained exact;
- all 29 wrappers matched their expected post-activation identities;
- the candidate package and exact rollback checks passed;
- all ControlLight core, runtime and topology suites passed;
- the deterministic ControlLight fileset test passed;
- transport errors, PHP execution errors and truncation were absent.

## Gate Decision

Wave 2 activation is **PASS**. Two ControlLight v2 instances are now active and
27 legacy wrappers remain. This result authorizes no additional live migration.
The next wave must be selected from the documented risk groups and repeat the
same private package, fresh preflight, zero-command synchronization, rollback
and all-instance regression gates.
