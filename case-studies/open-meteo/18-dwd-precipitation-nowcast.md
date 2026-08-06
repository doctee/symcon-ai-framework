# DWD Precipitation Nowcast

## Status

Offline implementation candidate. No public publication, module update, live
instance creation or timer activation is authorized by this artifact.

## Purpose

`DwdPrecipitationNowcast` adds a genuine radar-based short-range precipitation
forecast beside the Open-Meteo model forecasts. It consumes the open DWD RV
radar layer directly and does not depend on Home Assistant, Python, HDF5 or a
local adapter service.

The provider boundary remains explicit:

- Open-Meteo owns weather, soil and photovoltaic model forecasts;
- DWD owns the five-minute radar nowcast; and
- `SharedLocation` supplies the common, provider-neutral coordinates.

## Direct DWD Contract

The module uses the queryable WMS layer `dwd:Niederschlagsradar` at
`https://maps.dwd.de/geoserver/wms`. One `GetFeatureInfo` request selects the
configured point and a bounded ISO-8601 time interval. `REFERENCE_TIME=current`
selects the newest product cycle. The response contains all native forecast
steps required for the point, so the module does not download the national
HDF5 raster archive.

The parser accepts only a complete product horizon:

- forecast leads `+5` through `+120` minutes;
- one point every five minutes;
- one common and newest `REFERENCE_TIME`;
- finite precipitation intensity from `RV_ANALYSIS`; and
- exactly 24 unique lead times.

The GeoServer representation uses `-0.001` for a dry zero value. Only the
narrow interval from `-0.01` to zero is normalized to `0.0`; more negative,
non-finite or implausibly high values fail the complete candidate response.

## Time and Unit Semantics

The native five-minute points remain authoritative. The WMS value is an
intensity in `mm/h`; the corresponding five-minute amount is calculated as
`intensity / 12` and stored separately in `mm`.

The module deliberately does not claim native one-minute accuracy. A future
visual interpolation may be added only as an explicitly marked presentation
series while the native points remain available unchanged.

`ForecastWindowMinutes` controls evaluation, not provider resolution. Valid
values are multiples of five from 5 through 120 minutes. The complete
120-minute horizon is retained in the bounded cache, while these outputs use
only the selected window:

- whether rain is expected;
- rain start and end in minutes;
- precipitation sum;
- maximum intensity;
- next interval intensity; and
- evaluated point count.

Rain start denotes the beginning of the first wet five-minute interval. Rain
end denotes the end of the last wet interval. `-1` means that the selected
window contains no wet interval at or above the configured threshold.

## Runtime Safety

The runtime follows the existing forecast state reducer and last-good pattern:

- one instance semaphore prevents overlapping updates;
- HTTP timeout and response size are bounded;
- malformed or incomplete candidates never replace the last-good cache;
- retries are bounded at 1, 2 and 5 minutes before normal polling resumes;
- stale state is explicit; and
- changing location, window or threshold invalidates the incompatible cache.

The module requires a valid `SharedLocation` instance. It does not contain
fallback coordinate properties, private ObjectIDs or installation-specific
location data.

## Ownership and Gates

The module owns only its registered variables, timer, attributes and reference
to the selected `SharedLocation`. It performs no device action and changes no
OpenWeather, SolCast, Open-Meteo or Home Assistant consumer.

Publication, module-library update, live instance creation, live HTTP testing,
timer activation and later consumer migration remain separate explicit gates.

## Verification

The offline contract covers:

- deterministic WMS request construction and EPSG:4326 axis order;
- complete-horizon and latest-product selection;
- dry-zero normalization and interval amount conversion;
- configurable-window projection;
- module lifecycle, reference reconciliation and idempotent variables;
- last-good behavior and bounded retry scheduling; and
- deterministic generated fileset reproduction.

Relevant provider documentation:

- [DWD popular radar layers](https://www.dwd.de/DE/leistungen/radarprodukte/radarlayer.html)
- [DWD GeoServer WMS capabilities](https://maps.dwd.de/geoserver/wms?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetCapabilities)
- [DWD Open Data terms](https://opendata.dwd.de/README.txt)
