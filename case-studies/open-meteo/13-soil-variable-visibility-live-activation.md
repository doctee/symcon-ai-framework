# Soil Variable Visibility Live Activation

**Gate:** Public module publication, library update and one-instance presentation activation  
**Result:** PASS after a fail-closed runtime-context correction  
**Date:** 2026-08-02  
**Installed version:** 0.6.1

## Scope

The live gate enabled module-managed soil-variable visibility for one weather
instance whose soil request profile remains disabled. A second soil-enabled
weather instance remained an unchanged control. The gate did not enable new
provider fields, issue a manual provider request, restart IP-Symcon, change
consumer wiring or remove any object.

## Corrected First Attempt

Version 0.6.0 correctly stopped before the first presentation mutation because
the shared validation helper function was unavailable in the module runtime
context while its guard constant was retained. The target instance reported a
configuration error, but all nine soil variables remained visible and retained
their identities, values and metadata.

The target property was restored before further work. The implementation was
then narrowed to module-local validation of a positive object ID, existence,
variable type and direct instance ownership. The shared helper and its bundle
contract remained unchanged. Offline module tests and the complete repository
check passed before version 0.6.1 was published.

## Module Update

The exact public 0.6.1 revision was installed through one controlled Module
Control update. During repository reload, weather instances temporarily entered
configuration-error state before all shared-location module contexts were
available. A single bounded `ApplyChanges()` call per weather instance after
descriptor readiness restored both to active state without changing fetch
timestamps or issuing a provider request. No service reload was used.

## Activation Result

The target instance passed with:

- soil requests still disabled;
- visibility management enabled;
- the presentation toggle enabled;
- exactly nine existing soil variables hidden;
- unchanged variable IDs, parents, types, Idents, positions, icons and profiles;
- unchanged values, value timestamps, archive logging and link targets; and
- unchanged last-fetch and last-success timestamps.

The control instance remained hash-identical across the activation. A second
target `ApplyChanges()` produced hash-identical target and control projections,
proving idempotency.

## Operational Decision

Stable soil-variable identity is retained even when the request profile is
disabled. Visibility remains opt-in presentation ownership. Library reloads
must continue to be followed by descriptor-readiness verification before a
bounded instance reconciliation; `MC_ReloadModule()` and service restarts are
not part of this workflow.
