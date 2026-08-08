# Forecast Calibration Collector

**Gate:** Prospective forecast snapshots and read-only actual-value alignment

**Result:** PASS; calibration horizon accumulating

**Date:** 2026-08-03

## Scope

The authorized live gate added one installation-private collector for the two
storage-coupled solar forecasts. It snapshots already cached Open-Meteo power
and daily-energy forecasts and later aligns them with logged PV-harvest and
daily-yield actuals. Existing provider scripts, devices and archive values are
read-only inputs.

Installation-specific ObjectIDs, source-variable names, storage assignments,
paths and snapshot contents remain in ignored private evidence. The public
candidate contains no local identifiers.

## Calibration Contract

The collector deliberately preserves PV-harvest actuals independently of
storage dispatch. Its initial analysis contract treated every sufficiently
covered interval alike. That is not sufficient for a zero-export installation:
when storage is full and house demand is absent, deliberate PV curtailment is
not a weather-model error. The versioned classification extension is defined
in `20-curtailment-aware-calibration.md`.

Each forecast issue creates at most one immutable JSON snapshot and one SHA-256
marker. Repeated execution with the same issue time and configuration is a
byte-stable no-op. The runtime validates module type, active state, Archive
Control uniqueness, logging status, units, interval semantics and bounded data
sizes before writing.

Actual-value alignment is bounded and accounts for change-based archive data:

- non-zero power values are carried only for the configured freshness window;
- an unchanged zero may span a complete forecast interval;
- archive reads are paged with explicit page and result-size bounds; and
- power metrics include coverage, energy, bias, MAE, RMSE and energy ratio.

Daily forecast values are evaluated separately against logged daily energy.
Analysis begins only after a snapshot's complete forecast horizon has elapsed,
so a newly activated collector initially accumulates evidence rather than
claiming calibration quality prematurely.

## Guarded Activation

The live preflight proved an unchanged root presentation, the intended parent
category, two active Solar instances, exactly one Archive Control instance,
logging on all four selected actual-value variables, no managed-object
collision and no pre-existing collector directory.

The generated script was then deployed and read back byte for byte. Its hidden
five-minute cyclic event was created with the canonical Run Automation action
and kept inactive for the first execution. The first run created only one
forecast snapshot and its valid hash marker; the second run preserved both
files byte for byte. The second Solar target initially remained in
`waiting_for_forecast` because its first scheduled forecast was still pending.
It later produced a successful forecast and now accumulates snapshots
independently of the first target.

Only after those checks passed was the cyclic event activated. No provider
request, device command, archive write, service restart, module reload,
consumer migration or change to the existing provider runtimes occurred.
The first regular event then advanced `LastRun` and `NextRun` on schedule while
the deployed source hash, snapshot bytes, hash marker, root presentation and
archive logging remained unchanged.

## Offline Evidence

The public implementation adds:

- a pure snapshot, alignment and metric core;
- a bounded IP-Symcon collector runtime;
- a deterministic builder that embeds only ignored local configuration;
- regression tests for calculations and generated source; and
- canonical Symcon stubs for the cache and archive APIs used by the runtime.

Syntax, executable regression, PHPStan and PHPCS checks cover these artifacts.
The complete repository gate remains the final hand-off check.

## Next Observation

The collector must accumulate at least one complete forecast horizon before a
calibration factor can be assessed. Calibration parameters remain unchanged
until measurement coverage, classification evidence and forecast error have
been reviewed explicitly.
