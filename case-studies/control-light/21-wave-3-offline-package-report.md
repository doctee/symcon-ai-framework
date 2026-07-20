# Wave 3 Offline Package Report

**Gate:** Private package construction
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Not inspected or changed by this gate

## Scope

The selected sequential Wave 3 cohort was packaged without contacting or
mutating the live installation. The private package contains one independently
hash-locked candidate and one byte-exact rollback source for each cohort
member, plus the complete current 29-wrapper source regression baseline.

The package reuses the already active ControlLight fileset and pins both its
aggregate identity and the process-effective corrected variable-wait helper.
It does not duplicate object, event, wait or diagnostics infrastructure in a
wrapper.

## Candidate Contracts

The first cohort member retains STATE and DIMMER. The second is explicitly
STATE-only. Both wrappers select:

- Z2M target semantics;
- authoritative feedback;
- `reported` brightness semantics;
- no temperature or color capability;
- no external triggers; and
- the instance-specific existing alarm polarity.

The wrappers contain configuration only. Device actions remain in the shared
runtime, where controllable targets use `RequestAction()`. Existing visible
objects are reconciled with presentation updates disabled, preserving user
names, positions, icons, visibility and profiles.

## Transaction Boundary

The private activation plan is strictly sequential:

1. run a fresh read-only preflight;
2. activate, configure twice and postflight the first member;
3. advance only after every first-member assertion passes;
4. activate, configure twice and postflight the STATE-only member; and
5. stop and restore the exact current member at the first failure.

The next member is never touched after a failed assertion. Functional device
actions are excluded from activation and require a later explicit test gate.
No PowerShell, Remote Desktop or temporary IP-Symcon object is required; after
approval, direct bounded Symcon source and execution operations can implement
the transaction.

The package's live-mutation switch remains closed. Package construction is not
authorization for preflight, staging, wrapper replacement, script execution or
device actions.

## Offline Verification

The following checks passed:

- exact SHA-256 verification for every packaged artifact;
- exact decoding and hashing of both rollback sources;
- candidate syntax and configuration-scope checks;
- absence of legacy delegation and direct wrapper-side device/state actions;
- read-only token audit of the live preflight;
- complete 29-wrapper baseline coverage;
- ControlLight core, runtime and topology tests; and
- deployment restart coordinator regression tests.

## Next Gate

The next step is a fresh read-only live preflight. It verifies kernel readiness,
the effective wait-helper owner and identity, the complete fileset, all wrapper
sources, candidate ownership/topology, explicit event actions, actionable
targets, authoritative feedback alignment, absence of conflicting consumers
and absence of pre-existing v2 diagnostics.

That read-only preflight may be executed only after a new explicit approval.
Any live source change remains a separate subsequent approval gate.
