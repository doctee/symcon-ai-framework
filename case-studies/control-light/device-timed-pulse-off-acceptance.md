# Device-timed manual-on / remote-off adapter

Status: bounded live acceptance completed; publication does not migrate other consumers.

## Contract

The existing manual-on / pulse-off adapter now accepts an optional trusted
installation callback. It sends one device-timed supply interruption rather
than the default relay `RequestAction()` call. This supports a manually started
load that stays off when supply returns. The callback is PHP wiring, not a
configurable callable name from external input; device bindings stay private.

The optional `powerOnThresholdInclusive` Boolean selects `power >= threshold`.
Omission preserves the existing strict comparison and normalized configuration
shape. Existing callers without the callback retain their transport behavior.

## Confirmation and safety

- The existing owner semaphore serializes command dispatch.
- Device-timed dispatch requires supply feedback to be on before sending.
- Confirmation observes supply off, power below threshold and supply restored
  within one bounded deadline. A cached on value is not pulse confirmation.
- Dispatch failure never triggers a fallback, repeated pulse or software-on
  compensation. Manual-on and already-off requests do not send a pulse.
- Observed load reactivation during restoration is reported as failure.
- Existing Registry, Statistics, ErrorRingBuffer and variable-wait helpers are
  reused; no new shared helper or platform API is introduced.

## Verification

Offline regressions cover the optional threshold, default compatibility,
exclusive callback dispatch, lock rejection/release, dispatch exceptions,
manual-on/idempotent-off behavior and missing power, restoration or pulse
feedback. The generated fileset is byte-consistent with the canonical sources;
the repository Composer check passed before staging. Private integration tests
also exercised facade, adapter, alarm and rollback boundaries.

A supervised appliance pilot passed light-only and light-plus-low-fan OFF
tests. The operator confirmed both remained off after supply restoration and
manual activation remained possible. An operator-issued Alexa OFF test also
passed. Independent readback agreed with the observed off state; command/pulse
counters were consistent with the three tests and no new errors or timeouts
were recorded. One earlier activation error remains preserved in diagnostics.

The initial cutover rolled back after a verifier incorrectly classified planned
topology changes as drift. A concurrent feedback callback exposed an entry
reconciliation race. The private installation entrypoints subsequently gained
maintenance serialization and a current-source retirement guard, qualified with
delayed-callback tests before the successful controlled retry. Those guards are
installation wiring, not a claimed generic runtime transaction facility.

This acceptance does not cover all fan speeds, remote activation, restart
recovery or long-term reliability. Qualitative light/fan classification remains
the existing heuristic. Mirror maintenance and retention remain separate gates;
no deletion or further device commands are implied by publication.
