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

## Atomic Forecast Run

Each update:

1. validates the linked weather module, bounded location descriptor, PV JSON
   and runtime policy before transport;
2. acquires an instance-scoped semaphore;
3. requests `temperature_2m` and `global_tilted_irradiance` serially for each
   unique `(tilt, azimuth)` pair;
4. validates every response and interval before calculating power;
5. applies temperature correction and static derating, followed in
   `direct_ac` mode by inverter efficiency and the configured AC limit; and
6. atomically replaces the last-good cache and curated variables.

A missing or invalid orientation response rejects the complete candidate. No
partial multi-orientation forecast is published. URLs, coordinates, response
bodies and PV configuration are not logged.

A request-relevant configuration change produces a new deterministic hash,
hides the incompatible cache and resets the three curated forecast values until
a complete forecast for the new configuration succeeds.

## Cache and Consumer API

The configuration-bound cache exposes:

- `GetPowerForecastJson(from, to, breakdown)`; and
- `GetDailyEnergyForecastJson(from, to, breakdown)`.

Ranges are limited to ten days. The first runtime supports the explicit
`system` breakdown; array and inverter breakdowns fail with
`breakdown_unsupported` instead of returning an ambiguous approximation.
Public values contain current power in kW plus today's and tomorrow's energy
in kWh for the configured output mode.

## Storage-Coupled Systems

`ForecastOutputMode` separates two physically different forecast boundaries:

- `direct_ac` estimates immediate inverter output and applies its AC limit;
- `pv_harvest` estimates PV harvest before battery dispatch, applies a separately
  configured `PvInputLimitKw` and does not apply the grid-output clipping limit.

For a DC-coupled battery, PV harvest can exceed simultaneous house-grid output
while the surplus charges the battery. Conversely, house-grid output can come
from the battery when current irradiance is low. The first runtime therefore
does not claim to forecast storage dispatch, state of charge or actual feed-in.

## Deferred Calibration Boundary

`EnableCalibration` and `EnableShadingProfile` continue to fail closed. The
first runtime has no hidden learning, no archive mutation and no flat horizon
correction.

Later calibration must preserve immutable forecast snapshots and compare them
with exact UTC measurement intervals. In `pv_harvest` mode, a Solarbank PV-input
measurement is the primary comparison. Local house-feed measurements describe
the separate storage-dispatch/AC-output path and must not be interpreted as PV
loss. Charge, discharge, state of charge, clipping, outages and incomplete
intervals must be classified explicitly. Calibration starts with a bounded
static factor; a time- or sun-position-dependent shading model requires a
separate contract and evidence gate.

## Offline Proof

The synthetic module harness verifies default-manual migration safety,
idempotent lifecycle behavior, weather-reference validation and restoration
after runtime registry drift, two serialized orientation requests, separate
direct-AC and PV-input clipping, storage-coupled PV harvest, bounded cache
access, last-good
retention, manual-mode retry suppression, automatic polling and the first retry
interval. The deterministic fileset includes `SolarForecastProjector`.

Publication, installed-library update, private configuration, one controlled
manual request, observation and later SolCast consumer migration remain
separate authorization gates.
