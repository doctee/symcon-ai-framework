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

`fileset.sources.json` records the source path, SHA-256 and byte count of every
generated module payload. `fileset.sha256` identifies the complete generated
fileset. README and license are publication metadata and are not part of that
payload hash.

## License

[PolyForm Noncommercial License 1.0.0](LICENSE)
