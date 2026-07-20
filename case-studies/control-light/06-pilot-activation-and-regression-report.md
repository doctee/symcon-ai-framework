# ControlLight v2 Pilot Activation and Regression Report

**Gate:** Corrected pilot activation, idempotency and full wrapper regression
**Result:** PASS
**Date:** 2026-07-19
**Live state:** One v2 pilot active; 28 wrappers remain on the legacy core

## Activation Outcome

After a fresh complete preflight and new explicit approval, only the hallway
pilot wrapper was replaced with the reviewed v2 candidate. The wrapper loads
the previously staged, versioned ControlLight runtime directly; the global
bootstrap and shared legacy core remain unchanged.

The first initialization passed all activation gates:

- exact candidate wrapper and runtime identities;
- ten script-owned diagnostics variables with exact ownership and Idents;
- preserved names, positions, icons, visibility and parent relationships;
- unchanged event ownership, triggers and explicit action binding;
- local variables still controlled through the pilot wrapper;
- explicit `reported` brightness semantics in the Registry;
- authoritative state, brightness and temperature equality;
- one successful execution with zero commands and zero errors.

## Idempotency

A second non-commanding synchronization reused the complete child and
diagnostics ID set. The final counters were:

| Statistic | Value |
| --- | ---: |
| Executions | 2 |
| Successes | 2 |
| Commands | 0 |
| Errors | 0 |

No duplicate object, event or diagnostic variable was created. The bounded
error history remained empty.

## Full ControlLight Regression

The independent completion check compared all 29 installed wrapper sources
against the private inventory. The inventory hashes are byte-exact, including
their original line endings; after correcting an initially normalized hash
comparison, the result was:

| Check | Result |
| --- | --- |
| Installed wrappers checked | 29 |
| Pilot wrapper | Exact approved v2 source |
| Other wrappers unchanged | 28 of 28 |
| Wrapper mismatches | 0 |
| Shared legacy core | Exact approved unchanged source |
| Pilot diagnostics | 10 of 10 |
| Pilot event contracts | PASS |
| Authoritative feedback | PASS |
| Global bootstrap modification | None |
| Service restart | None |
| Device command | None |

Pilot and central-core sources were additionally read directly and matched
their approved local artifacts. Successful connector results reported neither
transport nor PHP execution errors and were not truncated.

## Gate Decision

The corrected hallway pilot activation is **PASS**. The pilot now runs
ControlLight v2 with authoritative feedback and `reported` brightness
semantics. The other 28 wrappers and the shared legacy core remain unchanged.

This result authorizes no additional instance migration. Before selecting the
next instance, its DIMMER contract must be decided explicitly as `reported` or
`effective`, and its downstream consumers must be included in the regression
boundary. The Auto-Off-dependent instance remains blocked until that consumer
contract is resolved.
