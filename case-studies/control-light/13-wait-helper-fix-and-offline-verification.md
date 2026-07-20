# Wait Helper Fix and Offline Verification

**Gate:** Shared-helper correction and deterministic regression
**Result:** OFFLINE PASS
**Date:** 2026-07-19
**Live state:** Unchanged; active Symcon fileset still contains the old helper

## Reuse Boundary

The defect was fixed in the existing `SAEF_WaitForVariable()` helper rather
than by adding a ControlLight-specific polling loop. This keeps one confirmation
contract for both current consumers:

- ControlLight authoritative command feedback;
- MQTT Discovery Exporter command feedback.

No public function signature or new helper API was introduced.

## Corrected Detection

The helper now captures whether the optional expected-value or predicate
condition matches at baseline. During polling it accepts either:

1. an advanced selected metadata timestamp with a matching value; or
2. an observed condition transition from false to true, even if the
   second-resolution timestamp is unchanged.

A value that already matched before waiting is not accepted as new evidence
without a valid lookback. Timestamp-only callers preserve their original
change/update contract.

## Performance Contract

The implementation avoids redundant polling reads:

| Wait type | Per-poll metadata reads | Per-poll value reads |
| --- | ---: | ---: |
| Timestamp only | 1 | 0 |
| Expected value or predicate | 1 | At most 1 |

The conditioned path performs one initial baseline value read. It introduces no
sleep, retry or action beyond the caller's existing bounded interval and
timeout.

## Regression Coverage

The new fake-Symcon suite verifies:

- predicate transition with unchanged second timestamp;
- exact expected-value transition with unchanged timestamp;
- rejection of a value that already matched before the wait;
- wrong-value timestamp update followed by a same-second correct transition;
- timestamp-only change detection with zero value reads;
- rejection of a transition outside the timeout;
- valid lookback confirmation without polling.

Call counters assert the polling-cost contract directly.

## Filesets and Full Verification

Both deterministic repository filesets were regenerated because both consume
the helper. Their new aggregate identities are:

- ControlLight: `9c85e83d1664afb22d0390d77cd200329dc19d12d2d8c84c6a0a221b595d767d`;
- MQTT Discovery Exporter:
  `591acf8ff4418aec0fdbb711efa291254f6718935795c6b56be91fce0fdb755e`.

The helper source identity in both filesets is
`4b79fb7a7339573f61a84d64e8634d6dc7faa3d161f645277a5e62228b8a7222`.

The complete repository check passed: syntax, reproducible bundles and
filesets, helper tests, MQTT suites, ControlLight core/runtime/topology suites,
PHPStan and PHPCS.

## Gate Decision

The fix is **OFFLINE PASS**. It has not been copied to or selected by the live
installation. Live fileset staging, wrapper selection and another real-device
test remain separately approved gates. Additional ControlLight migrations stay
blocked until the corrected helper passes that test.
