# ControlLight Wave 2 Package and Preflight Report

**Gate:** Private package integrity and fresh read-only live preflight
**Result:** PASS
**Date:** 2026-07-19
**Live state:** Unchanged; activation not authorized

## Package

The private Wave 2 package targets sanitized contract `CL-023` only. It
contains:

- a complete v2 wrapper enabling only STATE and DIMMER;
- explicit `reported` brightness semantics and authoritative feedback;
- an exact Base64 rollback image of the direct live legacy source;
- fixed object, ownership, event and diagnostic allowlists;
- the approved identities of all 29 wrappers and the shared legacy core;
- a non-mutating live preflight and a local package-integrity test.

The candidate reuses the already active, aggregate-hash-versioned ControlLight
fileset. It does not require another fileset copy, global bootstrap change,
legacy-core replacement or service restart.

## Offline Verification

The candidate wrapper, live preflight and package test passed PHP syntax
validation. The package test additionally proved:

- candidate identity equals the pinned contract hash;
- decoded rollback bytes equal the exact approved legacy source hash;
- the regression inventory contains all 29 wrappers;
- temperature, color and external triggers are disabled;
- authoritative feedback and `reported` semantics are explicit;
- neither wrapper nor preflight contains a device action;
- the preflight contains no Symcon mutation API from its denylist.

## Fresh Live Preflight

The stored preflight completed successfully and reported:

| Check | Result |
| --- | --- |
| Mutation attempted | No |
| Activation authorized | No |
| Shared core | Exact |
| Active versioned fileset | Complete and exact |
| Wrapper regression | 29 checked, 0 mismatches |
| Selected legacy wrapper | Exact |
| Wrapper and parent child allowlists | Exact |
| Local and target actions | Valid |
| Existing events | Active, explicit action binding |
| Downstream script/event/link consumers | None |
| Pre-existing v2 diagnostics | None |
| STATE | Local and target false |
| Retained DIMMER | Local and target 100 |

Transport error, PHP execution error and output truncation were checked
separately and were all absent. No installed script was executed.

## Activation and Rollback Boundary

The first later activation synchronization must issue zero commands and retain
STATE false with non-zero DIMMER, proving `reported` without switching the
device. A second synchronization must reuse the entire owned object set.
All-instance source regression and the complete offline suites remain mandatory.

On failure, rollback restores the exact legacy bytes and original event/action
contracts. Cleanup is restricted to newly created direct diagnostic children
from the fixed allowlist; unknown objects stop automated cleanup.

## Gate Decision

Package construction and read-only preflight are **PASS**. The recorded
preflight is evidence only and expires after fifteen minutes. Live activation
still requires a new explicit approval followed by a complete fresh preflight.
