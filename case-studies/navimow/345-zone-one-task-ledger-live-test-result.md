# 345 Zone One Task Ledger Live Test Result

**Case study:** Navimow native IP-Symcon module

**Status:** Passed; Zone 1 correlated, Zone 2 and Zone 3 remain pending

**Date:** 2026-08-24

## 1. Objective

Execute the first short natural-run gate from step 343 and verify that the
installed receive-only task ledger retains an operator-confirmed zone pass,
progress change and area change across mandatory transport cleanup.

The run used the official schedule. The module issued no mower command.

## 2. Authorization And Preconditions

The operator confirmed the scheduled Zone 1 run, no intervening manual OAuth,
login or token action, and temporary credential persistence for exactly one
bounded receive-only activation with mandatory cleanup.

The fresh preflight passed on standalone commit
`865ed9230973aa3a84af4464bae2f3f59de0fab9`:

- REST was operational;
- MQTT and position diagnostics were disabled and credential-free;
- the mower was docked;
- the restart-free token horizon was 2179 seconds;
- variable, archive, topology and command contracts were unchanged.

Exactly one activation was performed. It used one Account `ApplyChanges`, made
no retry and converged from the accepted asynchronous reconnect state to
`ShadowActive`.

## 3. Natural Run Evidence

The official schedule selected operator-confirmed Zone 1 at approximately
17:15 local time. The retained ledger then showed:

- one privacy-safe area correlation and one partition;
- a transition from `Docked` to REST-authoritative `Running`;
- pass-local progress increasing from 49.09 percent to 50.00 percent;
- subtotal and weekly area candidates both increasing during the same pass;
- eleven task observations in one retained pass;
- 101 position samples with 100 coordinate changes.

This passes the early-closure criteria from step 343. The result establishes
the first installation-private correlation between an app-confirmed zone and
a stable privacy-safe ledger handle. No correlation handle, coordinate,
device identifier or private topic is published here.

The observed percentage remains pass-local progress. It is not yet evidence
for geometrically covered zone area or a configured zone-area denominator.

## 4. Cleanup Verification

The bounded cleanup was invoked once. Its large result could not be inspected
directly, so it was not retried. The first read-only postflight already exposed
the safe cleanup state but its aggregate result was false because the private
projection expected connection value `2` instead of established `Connected`
value `3` and expected ledger entries below a nonexistent `observation` member.
No live correction was necessary. After correcting only the private read-only
projection, two postflights proved the postconditions:

- MQTT and position diagnostics disabled;
- MQTT and WebSocket inactive with status `104`;
- Authorization header, MQTT username and MQTT password absent;
- MQTT lifecycle `Disabled`;
- REST remained connected and operational;
- the mower continued its natural scheduled run;
- the Zone 1 ledger pass remained available after cleanup.

For both corrected MCP results, `transportError` and `executionError` were null
and `truncated` was false. This keeps transport success, PHP execution and
projection semantics explicitly separate.

## 5. Decision

**PASS** for the first natural zone-correlation observation.

REST remains authoritative, MQTT remains strictly receive-only and disabled by
default, and no productive variables, archive identities or command contracts
changed.

The next live evidence gates are one natural Zone 2 run and one natural Zone 3
run under the same short-test and cleanup contract. Their private handles must
be compared for separation before the path-map and per-zone statistics design
can move into implementation.
