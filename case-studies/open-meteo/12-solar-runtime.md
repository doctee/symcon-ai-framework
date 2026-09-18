# Open-Meteo Solar Runtime

## Outcome

The solar module now has an offline-verified, read-only runtime candidate. It
resolves its location through a configured `OpenMeteoWeather` instance, fetches
one Open-Meteo DWD ICON response per unique PV orientation and calculates the
system AC-power and daily-energy forecast only after every response is valid.

This increment does not publish a module revision, update an installed library,
perform a live request or contain installation-specific PV configuration.

## Safe Activation Contract

`ApplyChanges()` never performs HTTP. `EnableAutomaticUpdates` defaults to
`false`, including for instances that already existed as inactive scaffolds.
This prevents a library update from silently activating network traffic. A
valid manual-only instance is active and exposes `UpdateData()`, but its normal
and retry timer remain at zero.

Automatic polling is a later explicit configuration choice. When enabled, the
normal interval is at least 30 minutes and failures use the same bounded
5/15/30-minute retry sequence as the weather runtime.

## Kernel-Start Recovery

The solar module observes the documented `IPS_KERNELSTARTED` message and uses a
separate initially stopped one-shot timer to reconcile its Weather dependency
five seconds after the kernel reaches `KR_READY`. This closes a startup-ordering
gap in which an early `ApplyChanges()` could classify a valid Solar instance as
permanently invalid before the Weather module exposed its location descriptor.

The recovery path disables its own timer before doing any work and reuses the
same configuration, reference, cache-state and normal-polling reconciliation as
`ApplyChanges()`. It never calls `UpdateData()` and therefore performs no HTTP
request. A dependency that is still invalid after this single post-ready attempt
remains status `200`, schedules no further startup retry and emits one stable
final diagnostic without configuration or location details.

## Atomic Forecast Run

Each update:

1. validates the linked weather module, bounded location descriptor, PV JSON
   and runtime policy before transport;
2. acquires an instance-scoped semaphore;
3. requests `temperature_2m`, `global_tilted_irradiance` and
   `direct_normal_irradiance` serially for each unique `(tilt, azimuth)` pair;
4. validates every response and interval before calculating power;
5. applies temperature correction and static derating, followed in
   `direct_ac` mode by inverter efficiency and the configured AC limit; and
6. atomically replaces the last-good cache and curated variables.

A missing or invalid orientation response rejects the complete candidate. No
partial multi-orientation forecast is published. URLs, coordinates, response
bodies and PV configuration are not logged.

The current-day energy value uses the `~Electricity` profile and supports the
daily-counter convention required by stacked energy charts. The first complete
successful forecast of each configured local day publishes `0` followed by the
actual day forecast with a bounded pause that preserves two distinct archive
timestamps. The local day is recorded only after all curated values were
published, so same-day recalculations do not repeat the reset and failed updates
do not create an artificial archive transition. The module does not
enable logging or select an archive aggregation type; those remain
installation-owned settings.

A request-relevant configuration change produces a new deterministic hash,
hides the incompatible cache and resets the curated forecast values until
a complete forecast for the new configuration succeeds. The cache schema
version participates in that hash, so a module revision that changes the cache
contract also fails closed instead of exposing a structurally stale cache.

## Cache and Consumer API

The configuration-bound cache exposes:

- `GetPowerForecastJson(from, to, breakdown)`;
- `GetDailyEnergyForecastJson(from, to, breakdown)`;
- `GetIrradianceForecastJson(from, to, breakdown)`; and
- `GetSolarInputForecastJson(from, to)`.

Ranges are limited to ten days. `system` is the operative forecast and
`baseline` is calculated from the same provider response before the optional
local-horizon adjustment. With the horizon disabled both series are identical.
Array and inverter breakdowns fail with `breakdown_unsupported` instead of
returning an ambiguous approximation. Public values contain operative and
simultaneous baseline power, weighted system and baseline GTI, the resulting
current horizon loss plus today's and tomorrow's operative energy. Exact DNI
and air temperature series remain bounded cache data for calibration evidence.

## Storage-Coupled Systems

`ForecastOutputMode` separates two physically different forecast boundaries:

- `direct_ac` estimates immediate inverter output and applies its AC limit;
- `pv_harvest` estimates PV harvest before battery dispatch, applies a separately
  configured `PvInputLimitKw` and does not apply the grid-output clipping limit.

For a DC-coupled battery, PV harvest can exceed simultaneous house-grid output
while the surplus charges the battery. Conversely, house-grid output can come
from the battery when current irradiance is low. The first runtime therefore
does not claim to forecast storage dispatch, state of charge or actual feed-in.

## Calibration and Horizon Boundary

`EnableCalibration` continues to fail closed. The local-horizon model is a
separate optional layer described in `22-local-horizon-model.md`; it has no
hidden learning, performs no archive mutation and is not a flat loss factor.

Later calibration must preserve immutable forecast snapshots and compare them
with exact UTC measurement intervals. In `pv_harvest` mode, a Solarbank PV-input
measurement is the primary comparison. Local house-feed measurements describe
the separate storage-dispatch/AC-output path and must not be interpreted as PV
loss. Charge, discharge, state of charge, clipping, outages and incomplete
intervals must be classified explicitly. Calibration starts with a bounded
static factor. The known local horizon is applied before later residual
calibration, while storage curtailment remains a separate classification.

## Offline Proof

The synthetic module harness verifies default-manual migration safety,
idempotent lifecycle behavior, weather-reference validation and restoration
after runtime registry drift, post-kernel recovery from a temporarily missing
Weather descriptor, bounded fail-closed recovery, two serialized orientation requests, separate
direct-AC and PV-input clipping, storage-coupled PV harvest, bounded cache
access, last-good
retention, manual-mode retry suppression, automatic polling and the first retry
interval. It also verifies that `system` and `baseline` share exact intervals,
that they remain identical without a horizon and that a blocking horizon only
reduces `system`. The harness additionally verifies the first-publication reset,
same-day idempotence, the next local-day reset and the failure path that must not
advance the reset state. The deterministic fileset includes
`SolarForecastProjector` and the separate local-horizon classes.

Publication, installed-library update, private configuration, one controlled
manual request, observation and later SolCast consumer migration remain
separate authorization gates.
