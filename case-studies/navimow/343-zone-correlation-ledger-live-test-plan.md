# 343 Zone Correlation Ledger Live Test Plan

**Case study:** Navimow native IP-Symcon module

**Status:** Prepared; natural Zone 2 and Zone 3 observations pending

**Date:** 2026-08-24

## 1. Objective

Correlate at least two operator-confirmed app zones with distinct private
boundary or partition hashes and prove that the retained ledger separates
resumed passes, progress wraps and area changes across MQTT pilot sessions.

The public evidence records only anonymized zone labels and equality or
inequality findings. The installation-local mapping remains private.

## 2. Preconditions

Each short test requires:

- the official schedule naturally selected the intended app zone;
- operator confirmation of that zone in the app;
- mower supervision and no module-issued mower command;
- installed standalone commit `865ed9230973aa3a84af4464bae2f3f59de0fab9`;
- REST operational and task ledger structurally valid;
- MQTT and position diagnostics initially disabled and credential-free;
- fresh restart-free token horizon of at least 1200 seconds;
- explicit contextual acceptance of temporary Core credential persistence;
- exactly one receive-only activation and mandatory cleanup.

If any precondition fails, the run stops before mutation. There is no activation
retry.

## 3. Evidence Window

The test can close early after all of the following are observed:

1. at least one task observation with a privacy-safe area correlation;
2. one progress or phase change within the same inferred pass;
3. a retained ledger projection after the transient shadow has changed;
4. optional position evidence, explicitly marked unavailable if absent.

The hard maximum is the end of the natural mowing window or two hours,
whichever occurs first. This is not a new 72-hour stability pilot.

## 4. Cleanup

Independent of result, disable MQTT and position diagnostics, execute exactly
one Account ApplyChanges and verify immediately plus after at least 60 seconds:

- MQTT and WebSocket inactive;
- Core credentials absent;
- REST operational;
- public variable and archive contracts unchanged;
- retained task ledger still available.

## 5. Pending Natural Gates

The currently available run belongs to a previously observed app zone. The two
remaining high-value observations are the other scheduled zones on their next
natural runs. They cannot be completed offline and must not be induced with a
Start or zone-selection command.
