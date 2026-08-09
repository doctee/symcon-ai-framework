# Curtailment-Aware Forecast Calibration

**Gate:** Read-only classification before model calibration

**Result:** PASS; live collector active, first mature horizon pending

**Date:** 2026-08-04

## Problem

A zero-export storage system does not always expose the PV energy that could
have been generated. If the battery is full and the house has no matching
demand, the controller can reduce PV harvest. Comparing that reduced harvest
directly with the weather-derived forecast would incorrectly train a physical
loss factor against an operational constraint.

The forecast therefore remains a potential-harvest forecast. Storage behavior
is an observation dimension, not an input that reduces the Open-Meteo result.

## Analysis Contract

Raw forecast snapshots and their SHA-256 markers remain immutable. A completed
post-issue horizon produces a separate schema-v2 analysis whose filename
contains a deterministic hash of the algorithm version and installation-local
policy. A later threshold change creates a new analysis artifact instead of
overwriting earlier evidence.

Every hourly power interval is classified as one of:

- `unconstrained`: valid for physical forecast calibration;
- `curtailed`: strong evidence that zero-export/storage control limited harvest;
- `uncertain`: a possible constraint without sufficient confirmation; or
- `data_gap`: measurement or auxiliary-signal freshness is insufficient.

All measured intervals remain in realized metrics. Only `unconstrained`
intervals enter calibration metrics. Daily energy comparisons are retained and
annotated with the interval classifications; a day is calibration-eligible only
when every represented interval is unequivocally unconstrained.

## Conservative Evidence Rules

The installation-local policy maps archived state of charge, storage power,
system output, house load, grid export, grid import and status alongside the PV
harvest series. Public artifacts contain neither ObjectIDs nor local names.

Curtailment requires all of the following within a sufficiently covered
interval:

1. material forecast power and a material realized shortfall;
2. battery state of charge at the configured full threshold for the required
   fraction of the interval;
3. no material battery power flow;
4. grid export and grid import both near zero; and
5. sufficient archive measurement, auxiliary-signal and heartbeat coverage.

Active charging or discharging, material import/export, a battery that is not
full, or an insignificant forecast shortfall rules out the curtailment label.
Partial full-battery evidence becomes `uncertain`; incomplete evidence becomes
`data_gap`. Status, output and house-load summaries remain preserved as
diagnostic evidence but no undocumented vendor status code is made a mandatory
classifier gate.

## Safety and Migration Boundary

The collector reads existing Archive Control histories and cached forecasts. It
does not request a provider update, write archive values, control a battery,
change a Solar instance or alter an existing provider. The second solar system
uses policy mode `none` and therefore retains the original unconstrained
interpretation.

Existing OpenWeather, SolCast and device integrations remain parallel. A
calibration-factor change, provider deactivation or consumer migration requires
a separate decision after complete horizons have produced reviewable metrics.

## Verification

The pure-PHP tests cover confirmed curtailment, unconstrained operation,
ambiguous partial fullness, stale-data gaps and policy-disabled behavior. The
deterministic private builder validates all positive signal identifiers and
bounded thresholds. PHPStan and PHPCS cover the core, runtime and builder; the
full SAEF repository gate remains mandatory before live source replacement.

The focused Open-Meteo gate and complete `make check` passed on 2026-08-04.
The first fresh live preflight found the first Solar target in IP-Symcon
configuration-error status rather than active status, so the collector was
initially left unchanged. A separately authorized `ApplyChanges` reconciliation
returned that target to active status without changing its configuration hash,
last fetch attempt or last success and without a provider request.

After repeating every precondition, the collector event was made inactive and
the generated source was replaced with byte-exact read-back. The first
controlled run added only a newly available snapshot for the second target.
The second run was an immutable no-op; both analyses correctly remained in
`waiting_for_complete_horizon`. The five-minute event was then reactivated and
its next scheduled run advanced `LastRun` and `NextRun` while source hash,
snapshot manifests, target status, root presentation and the zero-analysis
state remained unchanged. No device action, archive write, provider request,
service restart, module reload or provider migration occurred.

The generated runner suppresses successful JSON output for `TimerEvent`
executions so routine cycles do not create a `Result for Event` message.
Interactive and `RunScript` executions retain their structured result, while
failures remain visible through the bounded generic Symcon log entry.

## Deduplicated Evaluation

Frequent forecast snapshots intentionally overlap. Summing every schema-v2
analysis would therefore count the same realized interval many times and bias a
physical loss factor toward periods with more collector runs.

`SolarCalibrationEvaluationCore` provides two deterministic offline views:

- the operational view selects exactly one forecast per realized interval,
  choosing the shortest non-negative lead time; and
- the lead-time view selects at most one forecast per realized interval inside
  each of `00-06h`, `06-24h`, `24-48h`, `48-72h` and `72h+`.

Realized metrics retain every selected classification. Calibration metrics use
only samples explicitly marked `calibrationEligible`. The evaluator rejects
mixed target identities, negative lead times, unsupported schemas and
unbounded input. It remains a pure candidate component: reading immutable
files, choosing a retention window and publishing or applying a correction
factor stay outside the collector and require a separate installation-specific
workflow and decision.
