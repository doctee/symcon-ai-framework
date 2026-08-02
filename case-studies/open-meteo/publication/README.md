# SAEF Open-Meteo Forecast

Preview IP-Symcon module library for a provider-independent weather, soil and
photovoltaic forecast model based on Open-Meteo.

## Current status

This repository contains two deliberately inactive module scaffolds:

- `OpenMeteoWeather`
- `OpenMeteoSolarForecast`

They register their configuration and presentation contracts, but currently
contain no HTTP transport, update timer or productive provider migration.
OpenWeather and SolCast are not modified by installing this preview.

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
