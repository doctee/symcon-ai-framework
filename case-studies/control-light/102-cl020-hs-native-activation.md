# CL-020 HS-native Activation

**Gate:** Wrapper activation and command-free reconciliation
**Result:** PASSED — DEVICE TEST STILL SEPARATE
**Date:** 2026-07-27
**Device actions:** None

## Outcome

A fresh delta preflight matched every package-bound invariant immediately
before activation. The CL-020 legacy wrapper was then replaced by the exact
HS-native candidate and read back with the expected hash.

Two waiting `RunScript` reconciliations completed successfully. Their
diagnostic totals are:

- two executions;
- zero commands;
- two successes;
- zero errors; and
- zero confirmation timeouts.

The facade and target values remained unchanged throughout activation.

## Ownership and Idempotency

The four existing feedback events retained their identities, target variables,
trigger types, active states and explicit action binding. Reconciliation added
only the ten expected script-owned diagnostics: Registry, bounded error history
and eight statistics/timestamps.

The Registry records reported brightness, target feedback authority, the
HS-native wrapper version and its deterministic configuration fingerprint.
Error history is empty.

No target `RequestAction()`, device action, service restart or global bootstrap
selection occurred.

## Dependency Regression

Target, Alexa, scene and repaired Home Assistant Entity source/configuration
hashes are unchanged. Alexa still owns the four-capability facade mapping, and
the scene still consumes facade brightness and color.

The visible runtime mirror remains byte-identical and still embeds the exact
runtime selected by CL-020. It required no content update.

The complete wrapper scan now reports:

- 22 active v2 wrappers;
- seven retained legacy wrappers;
- zero unknown wrappers; and
- 29 wrappers total.

The sanitized installed-contract fixture and executable expectations were
updated to this current command-free baseline. The fully device-tested count
remains seventeen.

## Remaining Gate

CL-020 is structurally active but not yet fully device- and consumer-tested on
the HS-native contract.

The next gate requires separate approval for a bounded sequence covering
STATE, brightness, Kelvin and color. The color step must prove native HS
confirmation and that the independent target brightness does not change.
Initial values must then be restored exactly before Alexa color/brightness and
scene-consumer regression are closed.

CL-021 remains color-disabled and outside this gate.
