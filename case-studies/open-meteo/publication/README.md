# SAEF Open-Meteo Forecast

Preview IP-Symcon module library for provider-independent locations,
Open-Meteo weather, soil and photovoltaic forecasts, and a direct DWD radar
precipitation nowcast.

## Current status

This repository contains four modules:

- `SharedLocation`, a provider-neutral, read-only location descriptor without
  variables, timers or network access
- `OpenMeteoWeather`
- `OpenMeteoSolarForecast`, a manual-first solar runtime whose automatic
  updates default to disabled
- `DwdPrecipitationNowcast`, a direct DWD RV radar nowcast with native
  five-minute points and a configurable 5-to-120-minute evaluation window

The weather module contains a bounded Open-Meteo runtime with last-good cache
and can optionally reference `SharedLocation`; its existing direct coordinate
properties remain a compatible fallback. Automatic polling can be disabled
without disabling explicit manual updates, including all transport-error retry
timers. Soil request selection and soil-variable visibility are separate;
visibility management is opt-in, and managed disabled soil variables remain
stable but hidden instead of being deleted.
Installing the library alone does not configure a location, start an
inactive instance or migrate a consumer. OpenWeather and SolCast are not
modified by installing this preview.

The DWD module uses the open `dwd:Niederschlagsradar` WMS layer directly. It
does not require Home Assistant, Python or a local HDF5 adapter. The complete
120-minute native horizon is cached; the selected window limits only the
published rain summary.

## Installation

Add the following URL in the IP-Symcon Module Control:

```text
https://github.com/doctee/saef-open-meteo
```

The current preview targets PHP 8.2 and IP-Symcon 6.2 or newer. Installation
does not authorize productive location, PV or consumer configuration.

## Integrity

### 0.8.16 — float horizon-loss profile

`CurrentHorizonLossPercent` now uses the float profile `OPENMETEO.Percent`
(0–100 %, one decimal place) instead of the integer profile `~Intensity.100`.
Reapplying the module configuration updates the default profile in place;
variable identity, values and archive settings are retained. No archive repair
or reaggregation is needed. User-defined custom profiles remain user-owned.

### 0.8.15 — kernel startup compatibility

Weather, DWD and solar configuration now defer calls to other instances until
the kernel is ready. A one-shot recovery after the kernel-start notification
restores configured polling without discarding the last-good forecast cache.
When updating the entire library through Module Control, let the reload finish
before applying any instance configuration that reports an unavailable
interface. Kernel lifecycle regression tests cover the deferred recovery;
deployment validation does not by itself prove a complete service restart.

`fileset.sources.json` records the source path, SHA-256 and byte count of every
generated module payload. `fileset.sha256` identifies the complete generated
fileset. README and license are publication metadata and are not part of that
payload hash.

## License

[PolyForm Noncommercial License 1.0.0](LICENSE)
