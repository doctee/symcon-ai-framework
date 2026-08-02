# 01 Module Design

**Case study:** Open-Meteo weather, soil and photovoltaic forecasts

**Status:** Design draft before implementation

**Date:** 2026-08-01

**Build boundary:** This document defines contracts and verification gates only.
No productive PHP code, Open-Meteo request or IP-Symcon mutation is introduced.

## 1. Purpose

This document defines a SAEF-aligned architecture for obtaining model weather,
soil and solar-radiation forecasts from Open-Meteo for multiple locations and
deriving photovoltaic forecasts for configured installations.

The target is a gradual replacement of existing OpenWeather and SolCast
dependencies. Replacement is not part of this design step. Both providers
remain authoritative for their current consumers until a later dependency
inventory, shadow comparison and explicitly authorized migration have passed.

The design uses the DWD ICON Open-Meteo endpoint as its first fixed provider
profile because the initial target locations are in Germany and a fixed model
family is easier to evaluate than a silently changing provider selection.

Official references:

- <https://open-meteo.com/en/docs/dwd-api>
- <https://open-meteo.com/en/docs>
- <https://open-meteo.com/en/pricing>
- <https://community.symcon.de/t/script-solcast-com-vs-open-meteo-com/132613/86?u=doctee>

## 2. Design Goals

The solution should:

- support one independently owned weather instance per location;
- expose curated, stable and typed variables instead of mirroring arbitrary
  response JSON;
- distinguish model conditions from local physical measurements;
- expose timestamp-based forecast series without making rolling horizon slots
  the canonical data model;
- support optional modelled soil values without presenting them as local
  sensor truth;
- derive PV power and energy from global tilted irradiance for every unique
  module orientation;
- preserve explicit preceding-interval semantics for radiation and
  precipitation;
- preserve the last valid forecast during temporary provider failure and mark
  it stale instead of clearing domain values;
- keep configuration, cache, diagnostics and domain outputs separate;
- make request building, parsing, interval alignment and PV calculation
  deterministic and testable without IP-Symcon or network access;
- support provider-parallel evaluation before any consumer is migrated; and
- keep private coordinates, ObjectIDs and installation characteristics out of
  public artifacts.

## 3. Non-Goals for the First Implementation

The first implementation should not:

- disable, modify or replace OpenWeather or SolCast;
- create or change live IP-Symcon objects;
- control irrigation, heating, shading, storage or another device;
- treat Open-Meteo `current` values as local measurements;
- expose one permanent variable for every timestamp and field;
- copy the OpenWeather object tree one-to-one;
- use arbitrary `+1 hour` or `-1 hour` display corrections;
- implement online calibration from one daily `actual / forecast` ratio;
- promise SolCast-equivalent probability bands before uncertainty has been
  designed and validated;
- load Chart.js or another remote presentation dependency from the forecast
  domain layer;
- introduce a new public SAEF helper before repeated reuse is demonstrated; or
- store full API responses in Registry or ErrorRingBuffer diagnostics.

## 4. Proposed Module Family

The target is a small native module family backed by shared pure-PHP classes.

| Component | Responsibility | First implementation |
| --- | --- | --- |
| `OpenMeteoWeather` | One location, weather request profile, forecast cache, curated variables and weather diagnostics. | Required after offline core |
| `OpenMeteoSolarForecast` | One PV system, orientation requests, PV calculation, bounded forecast cache and solar diagnostics. | Required after weather contract |
| `OpenMeteoApiClient` | HTTP request execution and transport result envelope. | Offline interface first; live transport later |
| `OpenMeteoRequestBuilder` | Deterministic endpoint, query and field selection. | Required offline |
| `OpenMeteoResponseParser` | Strict schema, unit and series validation. | Required offline |
| `WeatherForecastSeries` | Canonical timestamp-based weather and soil series. | Required offline |
| `SolarForecastCalculator` | Pure GTI-to-PV calculation and inverter-group clipping. | Required offline |
| `ForecastIntervalAligner` | Explicit interval-start and interval-end mapping for model and actual values. | Required offline |

An `OpenMeteoConfigurator` is not justified for the first version. Locations
are explicit user configuration, not provider-discovered devices.

A generic provider facade is also deferred. Public variable Idents and method
semantics are designed to be provider-neutral, but a reusable provider facade
or compatibility module should be added only after the real consumer inventory
demonstrates a recurring need.

### SAEF Decision AD-OM-001: Location and PV ownership stay separate

**Decision:** `OpenMeteoWeather` owns one location and its weather/soil data.
`OpenMeteoSolarForecast` owns one PV system and references exactly one weather
instance for location and time-zone configuration.

**Rationale:** Weather consumers and PV installations have different field,
calculation, update and migration contracts. Combining them would make soil or
general weather changes affect the solar contract and would make multiple PV
systems at one location awkward.

**Consequence:** The solar instance needs a small read-only location contract
from the weather instance. It must not write weather variables or configuration.

### SAEF Decision AD-OM-002: Independent location instances before batching

**Decision:** Each location performs an independent weather request in the
first runtime design. Shared multi-coordinate batching is deferred.

**Rationale:** Two hourly location requests remain far below the documented
free non-commercial quota. Independent instances isolate configuration,
failures and optional field sets and avoid premature coordinator complexity.

**Consequence:** The shared client and parser must still remain location-neutral
so a later coordinator can batch identical profiles without changing the
forecast-domain contract.

## 5. Ownership and Object Model

The normal object-tree projection is intentionally small:

```text
Open-Meteo
|-- Weather location A (`OpenMeteoWeather`)
|   |-- curated current and today variables
|   `-- diagnostics
|-- Weather location B (`OpenMeteoWeather`)
|   |-- curated current and today variables
|   `-- diagnostics
|-- PV system A (`OpenMeteoSolarForecast` -> weather location A)
|   |-- current and daily forecast variables
|   `-- diagnostics
`-- PV system B (`OpenMeteoSolarForecast` -> weather location B, optional)
    |-- current and daily forecast variables
    `-- diagnostics
```

Display names and positions are presentation state. Stable Idents, instance
links, variable types and profile semantics are contract state.

### `OpenMeteoWeather` owns

- public location configuration;
- one fixed endpoint/model profile;
- field-profile selection;
- polling and retry state;
- the bounded last-good weather forecast cache;
- current and today summary variables;
- weather freshness and request diagnostics; and
- bounded methods for retrieving hourly and daily forecast series.

It does not own:

- local weather-station variables;
- consumer actions;
- PV-system configuration;
- provider-independent consumer links; or
- user-managed Archive Control configuration.

### `OpenMeteoSolarForecast` owns

- the configured weather-instance reference;
- PV arrays and inverter-group configuration;
- one GTI series per unique orientation;
- PV calculation and interval semantics;
- the bounded last-good solar forecast cache;
- current and daily solar forecast variables; and
- solar calculation and freshness diagnostics.

It does not own:

- the location coordinates directly;
- actual production variables;
- archive correction;
- calibration state in the first implementation; or
- inverter, battery or grid commands.

## 6. Weather Configuration Contract

| Property | Type | Required | Private | Initial decision |
| --- | --- | --- | --- | --- |
| `Latitude` | float | yes | installation-specific | WGS84, validated to `-90..90` |
| `Longitude` | float | yes | installation-specific | WGS84, validated to `-180..180` |
| `ElevationMode` | enum | yes | no | `dem` or `explicit`; default `dem` |
| `Elevation` | float | conditional | installation-specific | Required only for `explicit` |
| `Timezone` | string | yes | no | Default `Europe/Berlin` |
| `ProviderProfile` | enum | yes | no | First supported value `dwd_icon` |
| `ForecastDays` | integer | yes | no | Default `7`, allowed `1..10` for DWD profile |
| `PollingIntervalMinutes` | integer | yes | no | Default `60`, minimum `30` |
| `EnableSoilProfile` | boolean | no | no | Default `true` for the target design |
| `EnableRawDiagnostics` | boolean | no | no | Default `false` |
| `HttpTimeoutSeconds` | integer | yes | no | Explicit, conservative bounded value |
| `StaleAfterMinutes` | integer | yes | no | Default derived from polling interval |

Coordinates and explicit elevations in public examples must use obvious
placeholders. A town-center coordinate should not silently substitute for the
actual forecast location in a private installation.

Changing location, elevation, time zone, provider profile or enabled fields
changes the configuration hash and invalidates the previous cache for public
output until a valid response for the new configuration has been parsed.

### SAEF Decision AD-OM-003: Model profile is explicit

**Decision:** The first profile uses `/v1/dwd-icon`, not `/v1/forecast` with
implicit `best_match`.

**Rationale:** A fixed model family provides more stable provenance for
weather/PV comparisons and calibration. DWD ICON also supplies the required
weather, soil and radiation fields for the target region.

**Consequence:** A future `best_match` profile is a contract extension and must
be evaluated separately. It must not replace `dwd_icon` silently.

## 7. Solar Configuration Contract

| Property | Type | Required | Private | Initial decision |
| --- | --- | --- | --- | --- |
| `WeatherInstanceId` | integer | yes | installation-specific | Must reference a compatible `OpenMeteoWeather` instance |
| `ForecastDays` | integer | yes | no | Default `4`, allowed `1..7` initially |
| `PollingIntervalMinutes` | integer | yes | no | Default `60`, minimum `30` |
| `Arrays` | list | yes | installation-specific | At least one validated array |
| `Inverters` | list | yes | installation-specific | At least one validated inverter group |
| `EnableShadingProfile` | boolean | no | installation-specific | Default `false`; later extension |
| `EnableCalibration` | boolean | no | installation-specific | Fixed `false` in first implementation |
| `EnableRawDiagnostics` | boolean | no | no | Default `false` |
| `HttpTimeoutSeconds` | integer | yes | no | Explicit bounded value |
| `StaleAfterMinutes` | integer | yes | no | Default derived from polling interval |

Each array entry contains:

| Field | Type | Validation | Meaning |
| --- | --- | --- | --- |
| `Ident` | string | stable Symcon-compatible Ident | Stable array identity |
| `PeakPowerKw` | float | finite and `> 0` | Installed DC peak power |
| `TiltDegrees` | float | finite and `0..90` | `0` horizontal, `90` vertical |
| `AzimuthDegrees` | float | finite and `-180..180` | Open-Meteo convention: `0` south, `-90` east, `90` west |
| `TemperatureCoefficientPctPerC` | float | finite and normally negative | Module data-sheet coefficient |
| `NoctDeltaCAt800Wm2` | float | finite and non-negative | Cell-temperature rise at 800 W/m2 |
| `DerateFactor` | float | finite and `0..1` | Static DC/system loss factor |
| `InverterIdent` | string | must resolve | Owning inverter group |

Each inverter entry contains:

| Field | Type | Validation | Meaning |
| --- | --- | --- | --- |
| `Ident` | string | unique stable Ident | Inverter-group identity |
| `AcLimitKw` | float | finite and `> 0` | Group AC clipping limit |
| `EfficiencyFactor` | float | finite and `0..1` | Optional fixed DC-to-AC efficiency |

Clipping is applied after summing all arrays assigned to the same inverter.
Per-array clipping would overstate output when several arrays share one
inverter and under-model shared inverter constraints.

## 8. Canonical Forecast Data Contract

The canonical data model is a field-oriented timestamp-based series, not
rolling numbered variables. Field orientation is required because values at
the same provider timestamp can have different semantics: temperature is an
instantaneous sample while precipitation and radiation describe a preceding
interval.

Each series has:

| Field | Meaning |
| --- | --- |
| `field` | Whitelisted provider or derived field name |
| `unit` | Validated unit for this field |
| `points` | Strictly ordered typed points |

Each point has:

| Field | Meaning |
| --- | --- |
| `validFrom` | Inclusive start of the model interval |
| `validTo` | Exclusive end of the model interval |
| `sourceTimestamp` | Timestamp supplied by Open-Meteo |
| `field` | Same field as the owning series |
| `unit` | Same validated unit as the owning series |
| `semantics` | `instant`, `preceding_interval` or `local_day` |
| `value` | One finite typed numeric value |

`providerProfile` and `locationHash` belong to the surrounding cache envelope,
not to every individual point. The location hash is an internal fingerprint
for cache matching, not a privacy control.

The cache envelope has:

| Field | Meaning |
| --- | --- |
| `schemaVersion` | Internal cache schema version |
| `configurationHash` | Internal hash of normalized desired configuration; not a secret or privacy control |
| `fetchedAt` | Local successful receipt timestamp |
| `validFrom` | Earliest point start |
| `validTo` | Latest point end |
| `hourly` | Bounded canonical hourly points |
| `daily` | Bounded canonical daily points |

The cache is rebuildable external state, not Registry metadata. Its concrete
storage mechanism must be capacity-tested before implementation. Registry may
hold only small metadata such as cache schema version and configuration hash.

### SAEF Decision AD-OM-004: Rolling slots are compatibility output only

**Decision:** Numbered fields such as `HourlyForecastTemperature1` are not the
canonical contract.

**Rationale:** Their meaning changes after every fetch, making archive history
and consumer time alignment ambiguous.

**Consequence:** A later compatibility adapter may expose a bounded number of
non-archived slots, but every slot must include its valid timestamp and must be
derived from the canonical series.

## 9. Time and Interval Semantics

Open-Meteo fields have different valid-time semantics. The parser must use a
field metadata table rather than treating every timestamp as an instantaneous
sample.

| Field class | Open-Meteo semantics | Canonical mapping |
| --- | --- | --- |
| Temperature, humidity, pressure, wind direction, soil values | Instant at timestamp | `validFrom == validTo == sourceTimestamp` for sample semantics |
| Aggregate fields in the `current` block | Aggregate over the response-declared current interval | `[current.time - current.interval, current.time)` |
| Hourly radiation | Mean over preceding hour | `[timestamp - 1 h, timestamp)` |
| Hourly precipitation/rain/showers/snowfall | Sum over preceding hour | `[timestamp - 1 h, timestamp)` |
| Hourly wind gust | Maximum over preceding hour | `[timestamp - 1 h, timestamp)` |
| Daily aggregates | Local calendar-day aggregate | `[local 00:00, next local 00:00)` |

PV energy for an hourly GTI point is based on the same preceding-hour
interval. Actual energy must be aggregated into that exact interval before
comparison.

No fixed `+1 hour` or `-1 hour` correction is permitted. A displayed curve may
choose interval-start, center or end labels, but that is presentation metadata
and must not change the underlying interval.

DST transitions are resolved through the configured IANA time zone. Offline
tests must include the 23-hour and 25-hour local days. Internally, Unix
timestamps or offset-qualified instants are preferred for uniqueness; local
labels alone are insufficient during the repeated autumn hour.

### SAEF Decision AD-OM-005: Forecast comparisons are interval-exact

**Decision:** Forecast and actual values are compared by matching UTC interval
boundaries, not by array position or hour label.

**Rationale:** The forum prototype demonstrates that display-time shifts can
appear installation-dependent. Exact interval matching separates model timing,
meter aggregation and presentation.

**Consequence:** Every persisted evaluation snapshot needs issue time, lead
time and exact valid interval.

## 10. Open-Meteo Request Profiles

The request builder produces deterministic query ordering and percent-encoding.
It never interpolates unvalidated user text directly into a URL.

### 10.1 Weather core profile

Endpoint:

```text
https://api.open-meteo.com/v1/dwd-icon
```

Required query shape:

```text
latitude=<LAT>
longitude=<LON>
timezone=<IANA_TIMEZONE>
timeformat=unixtime
temperature_unit=celsius
wind_speed_unit=kmh
precipitation_unit=mm
cell_selection=land
forecast_days=<1..10>
current=temperature_2m,relative_humidity_2m,dew_point_2m,
        apparent_temperature,precipitation,rain,showers,snowfall,
        weather_code,cloud_cover,pressure_msl,surface_pressure,
        wind_speed_10m,wind_direction_10m,wind_gusts_10m,is_day
hourly=temperature_2m,relative_humidity_2m,dew_point_2m,
       apparent_temperature,precipitation_probability,precipitation,
       rain,showers,snowfall,weather_code,pressure_msl,surface_pressure,
       cloud_cover,cloud_cover_low,cloud_cover_mid,cloud_cover_high,
       visibility,wind_speed_10m,wind_direction_10m,wind_gusts_10m,
       sunshine_duration,et0_fao_evapotranspiration,
       vapour_pressure_deficit
daily=weather_code,temperature_2m_max,temperature_2m_min,
      apparent_temperature_max,apparent_temperature_min,
      precipitation_sum,rain_sum,showers_sum,snowfall_sum,
      precipitation_probability_max,precipitation_hours,
      sunrise,sunset,sunshine_duration,wind_speed_10m_max,
      wind_gusts_10m_max,wind_direction_10m_dominant,
      shortwave_radiation_sum,et0_fao_evapotranspiration
```

Line breaks above are documentary only. The implementation uses one encoded
request.

### 10.2 Soil extension profile

When enabled, add these hourly fields to the same weather request:

```text
soil_temperature_0cm
soil_temperature_6cm
soil_temperature_18cm
soil_temperature_54cm
soil_moisture_0_to_1cm
soil_moisture_1_to_3cm
soil_moisture_3_to_9cm
soil_moisture_9_to_27cm
soil_moisture_27_to_81cm
```

Soil moisture remains volumetric water content in `m3/m3`. The module must not
convert it to plant-available water or irrigation demand without explicit soil
configuration and a separately designed domain model.

Further weather fields are introduced through named, versioned and
whitelisted request profiles. Arbitrary user-supplied field names are not
forwarded to the API. This keeps later additions possible without weakening
response validation or creating variables dynamically.

### 10.3 Solar orientation profile

One request is made for each unique `(tilt, azimuth)` pair used by a PV system:

```text
https://api.open-meteo.com/v1/dwd-icon
  ?latitude=<LOCATION_LAT>
  &longitude=<LOCATION_LON>
  &timezone=<IANA_TIMEZONE>
  &timeformat=unixtime
  &temperature_unit=celsius
  &cell_selection=land
  &forecast_days=<1..7>
  &tilt=<0..90>
  &azimuth=<-180..180>
  &hourly=temperature_2m,global_tilted_irradiance
```

Arrays with identical normalized orientation share one request result. Requests
are serialized and bounded; the implementation must not create one concurrent
request per array blindly.

Using `global_tilted_irradiance` avoids the incomplete prototype approximation
`direct_radiation * cos(tilt) + diffuse_radiation`, which does not model panel
azimuth and solar geometry correctly.

### 10.4 Response invariants

A response is usable only when:

- HTTP transport completed within the configured timeout;
- HTTP status is successful;
- JSON decoding succeeds without partial fallback;
- the response is not an Open-Meteo error envelope;
- latitude, longitude and time zone fields have plausible types;
- every requested series exists unless the profile explicitly marks it
  optional;
- every requested field has an expected compatible unit;
- `time` and every parallel value array have equal length;
- timestamps are strictly ordered after normalization;
- numeric values are finite or explicitly permitted `null` values;
- the returned range covers the minimum accepted forecast horizon; and
- GTI is non-negative when present.

Unknown extra response fields are ignored. Missing or invalid required fields
reject the candidate response atomically; public domain variables keep their
previous values. The parser applies the documented `utc_offset_seconds`
handling to daily Unix timestamps and proves DST-boundary behavior through
fixtures before the request profile is accepted for runtime use.

## 11. Weather Public Variable Contract

Public Idents use PascalCase. Display names may be localized later; Idents and
types are stable API.

### 11.1 Operational and freshness variables

| Ident | Type | Profile | Archive | Meaning |
| --- | --- | --- | --- | --- |
| `DataState` | integer | `OPENMETEO.DataState` | no | Unconfigured, fetching, current, stale, warning or error |
| `LastFetchAttempt` | integer | `~UnixTimestamp` | no | Last HTTP attempt |
| `LastSuccess` | integer | `~UnixTimestamp` | no | Last fully valid response |
| `ForecastValidFrom` | integer | `~UnixTimestamp` | no | Earliest cached forecast interval |
| `ForecastValidTo` | integer | `~UnixTimestamp` | no | Latest cached forecast interval |
| `ForecastAgeMinutes` | integer | none | no | Age since `LastSuccess` |

### 11.2 Curated current model variables

| Ident | Type | Profile | Archive default | Unit/meaning |
| --- | --- | --- | --- | --- |
| `Temperature` | float | `~Temperature` | user-owned/off | degC model value |
| `RelativeHumidity` | float | `~Humidity.F` or module percent | user-owned/off | percent |
| `DewPoint` | float | `~Temperature` | user-owned/off | degC |
| `ApparentTemperature` | float | `~Temperature` | user-owned/off | degC |
| `PressureMsl` | float | `OPENMETEO.Pressure` | user-owned/off | hPa at mean sea level |
| `SurfacePressure` | float | `OPENMETEO.Pressure` | user-owned/off | hPa at surface |
| `WindSpeed` | float | `OPENMETEO.WindSpeed` | user-owned/off | km/h |
| `WindDirection` | integer | `OPENMETEO.Direction` | user-owned/off | degrees |
| `WindGust` | float | `OPENMETEO.WindSpeed` | user-owned/off | maximum over declared current interval, km/h |
| `Precipitation` | float | `OPENMETEO.WaterDepth` | user-owned/off | sum over declared current interval, mm |
| `Rain` | float | `OPENMETEO.WaterDepth` | user-owned/off | sum over declared current interval, mm |
| `Showers` | float | `OPENMETEO.WaterDepth` | user-owned/off | sum over declared current interval, mm |
| `Snowfall` | float | `OPENMETEO.Snowfall` | user-owned/off | sum over declared current interval, cm |
| `WeatherCode` | integer | `OPENMETEO.WeatherCode` | user-owned/off | WMO code |
| `CloudCover` | integer | `~Intensity.100` | user-owned/off | percent |
| `IsDay` | boolean | default boolean | user-owned/off | model/astronomical flag |
| `CurrentValidAt` | integer | `~UnixTimestamp` | no | Source validity timestamp |

The module never enables, disables or rewrites Archive Control logging during
normal registration or update. Existing variable identity and user archive
configuration are preserved.

### 11.3 Curated today variables

| Ident | Type | Profile | Archive | Meaning |
| --- | --- | --- | --- | --- |
| `TodayWeatherCode` | integer | `OPENMETEO.WeatherCode` | no | Daily most severe WMO condition |
| `TodayTemperatureMin` | float | `~Temperature` | no | Daily minimum degC |
| `TodayTemperatureMax` | float | `~Temperature` | no | Daily maximum degC |
| `TodayPrecipitationProbabilityMax` | integer | `~Intensity.100` | no | Daily maximum percent |
| `TodayPrecipitationSum` | float | `OPENMETEO.WaterDepth` | no | Daily mm |
| `TodaySunshineDuration` | integer | `OPENMETEO.Duration` | no | Daily seconds or formatted duration |
| `TodayEt0` | float | `OPENMETEO.WaterDepth` | no | Reference evapotranspiration mm |
| `TodaySunrise` | integer | `~UnixTimestamp` | no | Local sunrise instant |
| `TodaySunset` | integer | `~UnixTimestamp` | no | Local sunset instant |

Tomorrow and later days remain canonical series data and are retrieved through
the bounded daily forecast method. Additional scalar day slots are a future
presentation decision, not part of the first core contract.

### 11.4 Optional soil variables

| Ident | Type | Profile | Archive default |
| --- | --- | --- | --- |
| `SoilTemperature0cm` | float | `~Temperature` | user-owned/off |
| `SoilTemperature6cm` | float | `~Temperature` | user-owned/off |
| `SoilTemperature18cm` | float | `~Temperature` | user-owned/off |
| `SoilTemperature54cm` | float | `~Temperature` | user-owned/off |
| `SoilMoisture0To1cm` | float | `OPENMETEO.SoilMoisture` | user-owned/off |
| `SoilMoisture1To3cm` | float | `OPENMETEO.SoilMoisture` | user-owned/off |
| `SoilMoisture3To9cm` | float | `OPENMETEO.SoilMoisture` | user-owned/off |
| `SoilMoisture9To27cm` | float | `OPENMETEO.SoilMoisture` | user-owned/off |
| `SoilMoisture27To81cm` | float | `OPENMETEO.SoilMoisture` | user-owned/off |

Names and documentation must say that these are model-grid values, not local
sensor measurements. Their current public value is selected deterministically
from the canonical hourly sample at or immediately before the current instant;
it is not invented from interpolation unless a later contract explicitly adds
that behavior.

## 12. Solar Public Variable Contract

| Ident | Type | Profile | Archive | Meaning |
| --- | --- | --- | --- | --- |
| `DataState` | integer | `OPENMETEO.DataState` | no | Solar forecast health |
| `LastFetchAttempt` | integer | `~UnixTimestamp` | no | Last GTI fetch attempt |
| `LastSuccess` | integer | `~UnixTimestamp` | no | Last complete multi-orientation forecast |
| `ForecastValidFrom` | integer | `~UnixTimestamp` | no | Earliest PV interval |
| `ForecastValidTo` | integer | `~UnixTimestamp` | no | Latest PV interval |
| `ForecastAgeMinutes` | integer | none | no | Age since last success |
| `CurrentPowerForecast` | float | `OPENMETEO.Power` | user-owned/off | Forecast AC power for current valid interval |
| `TodayEnergyForecast` | float | `OPENMETEO.Energy` | user-owned/off | Forecast local-day AC energy kWh |
| `TomorrowEnergyForecast` | float | `OPENMETEO.Energy` | user-owned/off | Next local-day AC energy kWh |
| `ConfigurationHash` | string | none | no | Diagnostic normalized PV configuration hash |

Per-array and later-day values are returned through bounded methods instead of
creating an unbounded variable tree.

The first implementation has no action variable and no `RequestAction()`
surface because the integration is read-only. A manual `UpdateData()` module
method is permitted and has no device side effect.

## 13. Custom Profiles

| Profile | Type | Purpose |
| --- | --- | --- |
| `OPENMETEO.DataState` | integer associations | Operational freshness state |
| `OPENMETEO.WeatherCode` | integer associations | WMO weather code presentation |
| `OPENMETEO.Pressure` | float | hPa |
| `OPENMETEO.WindSpeed` | float | km/h |
| `OPENMETEO.Direction` | integer | degrees |
| `OPENMETEO.WaterDepth` | float | mm for precipitation and evapotranspiration depth |
| `OPENMETEO.Snowfall` | float | cm |
| `OPENMETEO.SoilMoisture` | float | m3/m3 with suitable precision |
| `OPENMETEO.Duration` | integer | seconds or duration presentation |
| `OPENMETEO.Power` | float | W or kW, fixed contract before implementation |
| `OPENMETEO.Energy` | float | kWh |

`OPENMETEO.DataState` initial associations:

| Value | Association | Meaning |
| --- | --- | --- |
| `0` | Unconfigured | Required configuration is missing or invalid |
| `1` | Fetching | One bounded read is active |
| `2` | Current | Last-good data is within freshness policy |
| `3` | Stale | Last-good data is retained but too old |
| `4` | Warning | Data is usable with a non-fatal diagnostic condition |
| `5` | Error | No usable data exists or configuration/response is invalid |

Exact standard-profile reuse must be verified against supported IP-Symcon
versions before custom profiles are created.

## 14. Public Method Contract

The first runtime-facing methods should remain bounded:

### Weather instance

| Method | Result |
| --- | --- |
| `UpdateData()` | Executes one bounded fetch attempt; returns a structured success/failure result without throwing expected provider failures |
| `GetLocationDescriptor()` | Returns bounded location/time-zone/provider configuration required by linked local solar instances; the result is installation-sensitive and must not be logged or published |
| `GetCurrentJson()` | Returns the current curated model values and valid time |
| `GetHourlyForecastJson(from, to, fields)` | Returns a bounded subset within the cached range |
| `GetDailyForecastJson(fromDate, toDate, fields)` | Returns a bounded local-date subset |

### Solar instance

| Method | Result |
| --- | --- |
| `UpdateData()` | Fetches all unique orientations serially and atomically publishes only a complete valid result |
| `GetPowerForecastJson(from, to, breakdown)` | Returns bounded AC power intervals, optionally grouped by array/inverter |
| `GetDailyEnergyForecastJson(fromDate, toDate, breakdown)` | Returns bounded local-day energy totals |

Bounds are enforced even for local callers. Unknown fields and out-of-cache
ranges return a classified error instead of an unbounded raw cache dump.

## 15. PV Calculation Contract

For every array and hourly GTI interval:

```text
cellTemperatureC = airTemperatureC
    + (globalTiltedIrradianceWm2 / 800)
    * noctDeltaCAt800Wm2

temperatureFactor = 1
    + (temperatureCoefficientPctPerC / 100)
    * (cellTemperatureC - 25)

arrayDcKw = (globalTiltedIrradianceWm2 / 1000)
    * peakPowerKw
    * temperatureFactor
    * derateFactor
```

Rules:

- non-finite inputs reject the interval;
- negative GTI rejects the response before calculation;
- calculated negative array power is clamped to zero;
- arrays are summed by `InverterIdent`;
- inverter efficiency is applied once at inverter-group scope;
- group AC output is clipped to `AcLimitKw`;
- system output is the sum of clipped inverter-group outputs; and
- hourly energy is group/system average power multiplied by the exact interval
  duration in hours.

No local measured temperature is substituted into a future forecast point.
Local sensor data may be displayed separately or used in a later nowcast
contract, but mixing it into one forecast hour would make forecast evaluation
non-reproducible.

### SAEF Decision AD-OM-006: Calibration is a later evidence-backed layer

**Decision:** The first calculator uses explicit equipment and static derating
configuration only. Automatic calibration is disabled.

**Rationale:** A daily ratio conflates model cloud error, shading, curtailment,
snow, dirt, outages and hardware losses. Learning it directly can make the
forecast less stable and hide real faults.

**Consequence:** A later calibration design needs forecast snapshots, exact
actual intervals, exclusion rules, bounded factors and rollback to static
configuration.

## 16. Update, Retry and Recovery Contract

The request is read-only and safe to retry, but retries remain bounded.

Normal behavior:

1. validate configuration before network access;
2. acquire an instance-scoped execution lock;
3. set `LastFetchAttempt` and increment attempts;
4. execute one HTTP request for weather or one serialized request per unique
   solar orientation;
5. parse the complete candidate response offline;
6. atomically replace the cache and public values only after validation;
7. set `LastSuccess`, reset consecutive retry state and mark data current;
8. release the lock on every path.

Expected failure classification:

| Failure | Retry | Publication behavior |
| --- | --- | --- |
| Invalid local configuration | no | Keep matching old cache hidden; state `Unconfigured` or `Error` |
| HTTP 400 invalid request | no until configuration changes | Preserve last-good matching cache, record error |
| HTTP 429 | yes, respect usable `Retry-After` within bounds | Preserve last-good data, stale by age |
| HTTP 5xx | yes | Preserve last-good data |
| DNS/connect/timeout | yes | Preserve last-good data |
| Invalid JSON/schema/unit/series | yes on later schedule, not tight loop | Reject candidate atomically |
| Partial solar orientation success | yes for whole later attempt | Do not publish mixed-run PV result |

Recommended cross-execution retry schedule after normal failure:

```text
5 minutes -> 15 minutes -> 30 minutes -> normal polling interval
```

The exact values become named configuration/constants during implementation.
Retry state is persistent and reset only by a complete success or configuration
change. There is no unbounded same-execution sleep loop.

Staleness is based on `LastSuccess`, not on the last attempted request. A failed
attempt never makes old data appear fresh.

## 17. Diagnostics Contract

Module-native diagnostics should follow the same SAEF responsibilities even if
the exact helper functions are not reused inside a module implementation.

### Registry metadata

Small bounded metadata only:

- contract version;
- configuration hash and previous configuration hash;
- provider profile;
- cache schema version;
- current runtime phase; and
- migration marker when required.

### Statistics

Typed counters/timestamps:

- fetch attempts;
- successful fetches;
- transport failures;
- HTTP failures;
- response validation failures;
- retries scheduled;
- consecutive failures;
- last attempt;
- last success; and
- last duration milliseconds.

### Error ring buffer

A bounded list of concise sanitized failures:

- timestamp;
- component and phase;
- failure class;
- HTTP status when present;
- retry count;
- response reason truncated to a safe bound; and
- no coordinates, complete URL query, ObjectID, raw body or private PV data.

Optional raw diagnostics are disabled by default, latest-only, size-bounded
and non-archived. They are not part of the stable public contract.

## 18. Archive and Forecast-Evaluation Contract

The module does not automatically manage Archive Control logging. Current
variables are stable objects so users may enable logging without losing their
identity during compatible updates.

Rolling forecast evaluation requires explicit snapshots rather than archive
history of `TodayEnergyForecast` alone. A snapshot record contains:

| Field | Meaning |
| --- | --- |
| `issuedAt` | Time the forecast candidate was accepted |
| `validFrom` / `validTo` | Exact forecast interval |
| `leadTimeSeconds` | Difference from issue to interval start |
| `configurationHash` | PV/weather configuration used |
| `providerProfile` | Model family |
| `forecastValue` | Power or energy forecast |
| `actualValue` | Added only after the actual interval closes |
| `qualityFlags` | Missing actual, curtailment, outage, excluded day, etc. |

Snapshot retention and actual-variable linkage are not part of the first
module implementation. They require a separate bounded archive/evaluation
design and private consumer configuration.

## 19. Offline Core Boundary

The first implementation increment should create only pure-PHP domain code,
synthetic fixtures and tests.

Implemented offline structure:

```text
case-studies/open-meteo/
|-- distribution/
|   |-- OpenMeteoWeather/
|   |-- OpenMeteoSolarForecast/
|   `-- libs/OpenMeteo/
|       |-- FieldCatalog.php
|       |-- RequestBuilder.php
|       |-- ResponseParser.php
|       |-- ForecastPoint.php
|       |-- ForecastSeries.php
|       |-- IntervalAligner.php
|       |-- PvConfiguration.php
|       |-- SolarForecastCalculator.php
|       |-- ForecastStateReducer.php
|       `-- Profiles.php
|-- fixtures/
|   |-- weather-core-success.json
|   |-- weather-soil-success.json
|   |-- solar-south-success.json
|   |-- solar-east-success.json
|   |-- solar-west-success.json
|   `-- error-response.json
`-- tests/
    |-- request-builder.php
    |-- response-parser.php
    |-- interval-alignment.php
    |-- solar-calculator.php
    |-- state-reducer.php
    |-- module-scaffold.php
    `-- module-fileset.php
```

The pure core classes have no IP-Symcon function calls, timers, global variables
or filesystem assumptions. Module adapters and profiles are isolated in the
distribution boundary.

HTTP is represented by a transport interface or injected callable. Offline
tests supply response envelopes directly; they never call Open-Meteo.

### Required offline test matrix

Request building:

- deterministic field and query ordering;
- latitude/longitude/elevation validation;
- encoded IANA time zone;
- DWD forecast-day limits;
- soil profile composition;
- unique-orientation normalization; and
- no private value in exception messages beyond explicitly safe field names.

Response parsing:

- valid core and soil responses;
- Open-Meteo error envelope;
- malformed JSON;
- missing units;
- incompatible units;
- unequal array lengths;
- missing requested field;
- `null`, NaN-like and infinite-value rejection policy;
- duplicate and non-monotonic timestamps;
- unknown extra fields; and
- atomic rejection of partial candidates.

Time semantics:

- instantaneous samples;
- preceding-hour radiation and precipitation;
- exact UTC interval comparison;
- Europe/Berlin spring DST day with 23 hours;
- Europe/Berlin autumn DST day with 25 hours; and
- local daily aggregation without string-hour shifts.

PV calculation:

- south-only system;
- east/west arrays;
- two arrays sharing one inverter;
- multiple inverter groups;
- zero night GTI;
- temperature correction;
- static derating;
- inverter efficiency and shared clipping;
- invalid negative/non-finite inputs;
- interval-duration energy conversion; and
- order-independent array configuration.

Runtime state reducers, before module wiring:

- first success;
- temporary failure with last-good data;
- stale transition based on age;
- non-retryable configuration failure;
- bounded retry progression;
- partial solar-orientation failure; and
- configuration-hash change invalidating incompatible cache output.

## 20. Privacy, Licensing and Attribution

Public artifacts must not contain:

- exact private installation coordinates or elevation;
- personal ObjectIDs or consumer paths;
- local sensor, inverter or PV array names;
- production or archive identifiers;
- complete request URLs containing private coordinates; or
- copied OpenWeather/SolCast credentials.

Open-Meteo documents its free endpoint as non-commercial and rate-limited and
requires attribution for the underlying CC BY 4.0 weather data. The module
documentation and any visualization should include an appropriate Open-Meteo
and source-data attribution. Commercial use requires a separately reviewed
licence/endpoint decision.

Licensing and quota assumptions are drift-prone external facts and must be
rechecked before release.

## 21. Migration and Compatibility Gates

### Gate M0: Dependency inventory

Read-only inventory of:

- OpenWeather instances and stable variable identities;
- SolCast scripts/instances and outputs;
- scripts, links, visualizations, events and archives consuming them;
- time, unit and update assumptions in each consumer; and
- local weather/PV actual variables used for comparison.

This gate does not create temporary live objects or execute production
callers.

### Gate M1: Offline implementation

- implement the pure-PHP core;
- use synthetic/sanitized fixtures;
- pass focused tests and the complete repository gate; and
- introduce no live HTTP or Symcon runtime behavior.

### Gate M2: Inactive module scaffold

- create metadata and variable registration only;
- validate loader/metadata offline and in an explicitly authorized inactive
  environment;
- keep timers disabled and transport non-operational; and
- verify repeated `ApplyChanges()` preserves object identity.

### Gate M3: Parallel read-only weather pilot

- separately authorize HTTP traffic and live object changes;
- activate one location first while leaving OpenWeather unchanged;
- observe freshness, units, time semantics and failure behavior; and
- create no control action from weather values.

### Gate M4: Parallel solar shadow

- separately authorize PV configuration and shadow forecast activation;
- preserve SolCast as current consumer source;
- compare fixed issue/valid intervals over representative weather; and
- do not enable calibration or consumer switching.

### Gate M5: Consumer migration

- migrate one explicitly selected consumer at a time;
- preserve rollback to its former provider input;
- verify links, events, archives and visualization semantics; and
- keep provider deactivation separate.

### Gate M6: Provider retirement

OpenWeather and SolCast may be disabled or removed only after explicit
authorization, completed consumer inventory, passed observation criteria and
verified rollback/retention decisions. Disabling one provider does not
authorize removal of the other.

## 22. Acceptance Criteria for This Design

This design step is complete when:

1. location, weather and PV ownership are explicit;
2. private configuration has a defined boundary;
3. public variable Idents, roles and archive ownership are documented;
4. request profiles contain the required weather, soil and GTI fields;
5. response and unit validation are atomic;
6. radiation and actual energy share exact interval semantics;
7. arbitrary time shifts are rejected;
8. shared-inverter clipping is defined;
9. last-good/stale and bounded retry behavior are explicit;
10. diagnostics use bounded SAEF responsibilities;
11. the first implementation is pure PHP and network-free;
12. live activation and both provider retirements remain separate gates; and
13. no private installation detail appears in the public artifact.

## 23. Open Questions Before Runtime Implementation

These questions do not block the pure-PHP core but must be resolved before the
corresponding runtime surface is frozen:

1. Which supported IP-Symcon versions and standard profiles are the initial
   module target?
2. Should public power use W or kW? The pure domain model should use kW and the
   presentation contract must choose one stable unit.
3. What maximum cache size and storage mechanism remain safe across supported
   Symcon versions?
4. Should `current` model fields come from the Open-Meteo `current` block or be
   selected from the canonical hourly/15-minute series for one uniform timing
   contract?
5. Which weather fields are required by the actual OpenWeather consumers and
   therefore need compatibility output?
6. Does either initial PV site have multiple inverters, curtailment or battery
   behavior that requires additional evaluation flags?
7. What observation duration and error thresholds are required before SolCast
   replacement can be considered?
8. Should later uncertainty use DWD ensembles, empirical forecast errors or a
   combination?

## 24. Next SAEF Step

After review and explicit authorization, the next artifact should implement
the offline core and synthetic fixtures only:

```text
case-studies/open-meteo/02-offline-core-and-fixtures.md
```

That step must keep HTTP transport disabled, contain no IP-Symcon runtime
mutation and prove the request, parsing, interval and PV calculation contracts
before any module scaffold is created.
