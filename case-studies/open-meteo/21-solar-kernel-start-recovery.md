# Open-Meteo Solar Kernel-Start Recovery

## Problem

A valid Solar instance can receive `ApplyChanges()` while its configured
Weather instance exists but does not yet expose the location descriptor during
kernel startup. The original lifecycle treated every exception from the Solar
runtime context as a permanent configuration error, disabled normal polling and
did not reconcile the dependency again. A separate calibration collector then
reported the resulting inactive Solar target on every scheduled run.

This is a dependency-ordering failure, not evidence that private PV or location
configuration is invalid.

## Recovery Contract

The Solar module registers the documented `IPS_KERNELSTARTED` message and one
initially stopped `StartupRecovery` module timer in `Create()`.

On kernel start:

1. `MessageSink()` accepts only sender `0` and `IPS_KERNELSTARTED`;
2. it schedules the recovery timer for five seconds without reading a provider
   or changing forecast data;
3. `ProcessStartupRecovery()` stops its own timer before reconciliation;
4. it reuses the existing configuration, Weather reference, cache-state and
   normal-polling reconciliation; and
5. it returns `startup_reconciled` only when the complete configuration is
   valid after `KR_READY`.

There is exactly one post-ready attempt per kernel start. A still-invalid
dependency remains status `200`, the recovery timer remains stopped and one
stable `configuration_invalid` diagnostic is logged. Configuration values,
location data and provider responses are not logged.

## Safety Boundary

The recovery path never calls `UpdateData()` or a transport function. It does
not perform HTTP, clear the last-good cache, modify private configuration,
create Symcon objects or change calibration evidence. Normal automatic polling
is scheduled only after the same complete runtime-context validation already
used by `ApplyChanges()`.

The kernel message handler only schedules the one-shot timer. It does not
perform dependency reconciliation synchronously inside `MessageSink()`.

## Offline Proof

The module scaffold verifies:

- registration for sender `0` and `IPS_KERNELSTARTED`;
- an initially disabled recovery timer;
- rejection of unrelated messages;
- idempotent scheduling for duplicate kernel-start messages;
- recovery after a previously unavailable Weather descriptor becomes ready;
- restoration of active status and the Weather reference;
- zero provider requests during recovery; and
- fail-closed behavior, a stopped timer and exactly one final diagnostic when
  the dependency remains invalid.

Publication, installed-library update and a supervised restart observation are
separate gates. No private installation identifiers or live evidence are part
of this artifact.
