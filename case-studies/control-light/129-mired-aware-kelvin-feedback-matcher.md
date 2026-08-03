# Mired-Aware Kelvin Feedback Matcher

**Date:** 2026-08-03
**Scope:** Shared offline ControlLight candidate and regression suite
**Result:** IMPLEMENTED OFFLINE — LIVE RUNTIME UNCHANGED

## Finding

The CL-003 hard-cycle restoration exposed a valid 3900 K request reported as
3906 K. The target module internally quantizes color temperature to an integer
Mired value: both Kelvin values represent 256 Mired. The previous fixed
five-Kelvin comparison rejected that legitimate feedback by one Kelvin.

A globally wider Kelvin tolerance would be incorrect because the Kelvin width
of one Mired step increases with color temperature. It would either remain too
narrow at the high end or become unnecessarily permissive at the low end.

## Contract

The normalized configuration now contains an explicit
`colorTemperatureFeedbackQuantization` contract:

- Z2M defaults to `mired` because its Kelvin feedback is derived from an
  integer-Mired device representation;
- Matter, Home Assistant and Homematic retain `none` and therefore the existing
  bounded target-unit tolerance; and
- callers may explicitly override Z2M to `none` when a target does not have the
  Mired-derived Kelvin contract.

The matcher first preserves the configured fixed tolerance. If that does not
match and the target is explicitly Mired-quantized Kelvin, it converts expected
and actual positive Kelvin values to their nearest integer Mired values and
accepts only equality in that representation.

The quantization mode is rejected when the target variable itself is configured
as Mired, because comparing such target values as Kelvin would be ambiguous.

## Regression Matrix

Offline tests cover:

- the observed 3900-to-3906 K confirmation;
- rejection of a different-Mired 3922 K response;
- every requested integer Kelvin value from 2000 through 6500 and its exact
  integer-Mired round trip;
- explicit Z2M opt-out;
- unchanged Matter behavior for the same 3900/3906 pair;
- every installed-instance fixture selecting the expected preset contract;
- runtime dispatch, facade synchronization and zero false timeouts for the
  observed normalization; and
- invalid or ambiguous quantization configuration.

The deterministic ControlLight fileset is rebuilt and verified from the same
candidate source. No live wrapper, fileset, runtime or device was changed.

The current checkout already contains earlier unreleased ControlLight work.
The generated fileset is deterministic for that complete working tree, but it
is not yet approved as a matcher-only live deployment package. Before staging,
the exact intended source set must be isolated on a clean, pinned revision and
the resulting fileset delta reviewed independently.

## Next Gate

CL-003 and the other live Z2M wrappers still use the previously activated
runtime. A fresh immutable fileset package, command-free activation and targeted
3900 K regression remain separate explicitly approved gates.
