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

Snapshot schema 2 retains both the operative `system` forecast and the
simultaneous unshaded `baseline` calculated from the same Open-Meteo response.
This avoids extra provider calls and shadow instances. Existing schema-1
snapshots remain valid and analyzable; only newly captured forecasts carry the
comparison pair. Calibration metrics continue to use `system`, while the
baseline series is evidence for separating known horizon effects from residual
equipment, weather and curtailment error.

Completed horizons without any alignable power measurement remain pending for
a six-hour ingestion grace period. After that period the collector writes an
immutable terminal `data_gap` analysis with empty power samples and preserved
daily-energy comparisons. Such a gap is evidence of missing measurement
coverage, never zero generation, and it no longer blocks later snapshots.

Backlog processing is deliberately bounded to four newly written analyses per
target and execution. This lets a scheduled collector catch up after an archive
gap without turning one timer execution into an unbounded history job. The
result reports the created batch size and how many terminal data gaps it
contains.

The limit of 1,200 forecast snapshots per target is a non-destructive
collection ceiling. An already captured issue remains an immutable no-op. A new
issue at the ceiling returns `retention_limit_reached` without failing the
collector, while analysis of existing snapshots continues. No evidence is
deleted automatically; archival or retention cleanup remains a separately
authorized maintenance decision.

The previous 1,000-snapshot ceiling was raised without deleting evidence when
the live horizon comparison began. Starting from the established 720-snapshot
retention baseline, 1,200 leaves about twenty days at one forecast issue per
hour. That covers a preferred fourteen-day observation plus weather-related
selection loss while retaining a finite fail-closed bound.

The first rejected forecast issue also creates one immutable ceiling marker and
one bounded Symcon warning. Later timer executions verify that marker and stay
quiet. Any future retention operation must handle the marker explicitly before
collection can be considered resumed.

Before analysis, the runtime compares the issue timestamp and configuration
hash encoded in every snapshot filename with the immutable JSON content. A
valid hash sidecar therefore proves unchanged bytes, while the additional
identity check rejects a validly hashed file stored under the wrong name.

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

Runtime regression coverage additionally proves bounded backlog draining,
terminal handling of expired measurement gaps and the non-failing snapshot
ceiling.

Syntax, executable regression, PHPStan and PHPCS checks cover these artifacts.
The complete repository gate remains the final hand-off check.

## Next Observation

The collector must accumulate at least one complete forecast horizon before a
calibration factor can be assessed. Calibration parameters remain unchanged
until measurement coverage, classification evidence and forecast error have
been reviewed explicitly.
