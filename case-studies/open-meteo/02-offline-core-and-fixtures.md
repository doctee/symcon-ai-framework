# Open-Meteo Offline Core and Fixtures

## Outcome

Gate M1 now has an executable, network-free core. It validates deterministic
Open-Meteo requests, parses synthetic response envelopes into field-oriented
forecast series, preserves explicit interval semantics, calculates photovoltaic
power behind shared inverter limits and reduces last-good runtime state.

This increment intentionally creates no IP-Symcon instance, object, variable,
profile or timer. It performs no HTTP request and contains no production
coordinates, ObjectIDs, equipment names or provider credentials.

## Implemented Components

| Component | Responsibility |
| --- | --- |
| `FieldCatalog` | Whitelisted weather, soil and solar fields, expected units and field-specific semantics |
| `RequestBuilder` | Deterministic DWD ICON weather and orientation-specific solar URLs after configuration validation |
| `ResponseParser` | Atomic JSON, metadata, unit, length, timestamp and finite-value validation |
| `ForecastPoint` | One numeric value with source timestamp and exact validity boundaries |
| `ForecastSeries` | Strictly ordered points with one stable field and unit contract |
| `ParsedForecast` | Separate current, hourly and daily field series |
| `IntervalAligner` | Exact interval matching, containing-interval lookup and DST-aware local-day boundaries |
| `PvConfiguration` | Normalized arrays, inverter groups and deduplicated orientation keys |
| `SolarForecastCalculator` | Temperature correction, static derating, group efficiency, shared clipping and duration-based energy |
| `ForecastStateReducer` | Last-good retention, bounded retries, stale evaluation and configuration-hash invalidation |

No general-purpose SAEF helper was added. These classes express the
Open-Meteo domain contract and do not duplicate object, variable, event or
diagnostics infrastructure helpers.

## Canonical Data Refinement

Implementation exposed one ambiguity in the initial design: a single point
cannot safely hold several fields when those fields use different time
semantics. For example, hourly temperature is instantaneous while hourly
precipitation and irradiance cover the preceding hour.

The executable contract is therefore field-oriented:

```text
ForecastSeries(field, unit)
`-- ForecastPoint(
      field,
      unit,
      semantics,
      sourceTimestamp,
      validFrom,
      validTo,
      value
    )
```

This makes unit validation, alignment and aggregation explicit and prevents a
display-time shift from changing the underlying validity interval.

## Request Boundary

`RequestBuilder` currently emits URLs only; it does not execute them. It uses:

- the DWD ICON endpoint;
- validated latitude, longitude, optional elevation and IANA time zone;
- bounded weather or solar forecast days;
- fixed unit parameters and `timeformat=unixtime`;
- a fixed weather field catalogue with optional soil fields; and
- one solar request descriptor for each unique normalized tilt/azimuth pair.

Unknown user-provided field names are never forwarded. Exception messages
identify invalid configuration fields without echoing configuration values or
complete private request URLs.

## Parser and Interval Boundary

A response candidate is rejected before publication if JSON or provider
metadata is invalid, a requested field or unit is absent, parallel arrays
differ in length, timestamps are not strictly increasing, or a required value
is null, non-numeric or non-finite.

Individual null gaps are permitted only for hourly visibility and are omitted
from that canonical field series; an entirely unavailable visibility series is
still rejected.

The parser ignores unknown extra response fields. This permits compatible
provider additions without weakening the required-field contract.

The current offline policy is:

| Field class | Canonical validity |
| --- | --- |
| Instantaneous current/hourly samples | `validFrom == validTo == sourceTimestamp` |
| Current aggregates | Response-declared preceding interval |
| Hourly precipitation, gust, radiation and similar aggregates | Exact preceding hour |
| Daily values | IANA-time-zone local calendar day |

Local-day creation uses time-zone rules and has explicit tests for the 23-hour
spring day and 25-hour autumn day in `Europe/Berlin`.

## Photovoltaic Boundary

The calculator accepts a complete GTI series for every unique orientation and
one matching forecast-air-temperature series. It rejects partial orientation
sets and non-aligned timestamps or intervals.

For each interval it applies the equipment contract from the design:

1. derive cell temperature from air temperature, GTI and the configured NOCT
   delta;
2. apply the module temperature coefficient and static derating;
3. sum all DC arrays assigned to the same inverter;
4. apply inverter efficiency once to the group;
5. clip the group at its AC limit; and
6. sum the clipped groups into system AC power.

Energy is calculated from power and the exact interval duration. Array input
order is normalized and does not change the result.

## Runtime-State Boundary

The pure reducer proves the state rules before any module wiring exists:

- a complete success atomically establishes last-good data;
- a retryable failure retains compatible last-good data;
- retry counts cannot exceed the configured maximum;
- age can transition retained data to stale;
- a non-retryable configuration failure does not schedule further retries;
- a partial solar-orientation result is represented as a whole-attempt failure;
  and
- a changed configuration hash invalidates incompatible cached output.

The reducer stores no state itself. A future module adapter must compose it
with the existing SAEF Registry, Statistics, ErrorRingBuffer and
ConfigurationHash building blocks instead of introducing another diagnostics
storage abstraction.

## Synthetic Fixtures

The fixture set covers:

- weather current, hourly and daily values;
- model-grid soil temperature and volumetric soil moisture;
- south, east and west GTI series; and
- a provider error envelope.

Coordinates and equipment identifiers are deliberate synthetic placeholders.
The fixtures are not captured production responses.

## Verification

Run:

```console
case-studies/open-meteo/tools/check-offline.sh
```

The focused gate performs PHP syntax checks, executes all standalone tests,
runs PHPStan level 8 and applies the repository PHPCS rules to the complete
case-study implementation. `make check` invokes this focused gate before the
full repository gate.

The current test matrix covers:

- deterministic request composition and validation;
- soil and solar profile selection;
- provider errors, malformed JSON, missing/incompatible units, length mismatch,
  null values and duplicate timestamps;
- instantaneous, current aggregate, preceding-hour and local-day semantics;
- exact interval matching and both Berlin DST boundary days;
- night output, temperature correction, derating, inverter efficiency and
  shared clipping;
- single- and multiple-orientation systems, duration-based energy and
  order-independent array configuration; and
- success, last-good failure, stale, bounded retry, partial-orientation and
  configuration-change state transitions.

## Deferred Work and Next Gate

The following remain outside this increment:

- HTTP transport and provider traffic;
- cache serialization and capacity testing;
- IP-Symcon module metadata, instances, variables, profiles, methods and timers;
- OpenWeather or SolCast dependency inventory;
- shadow comparison with live forecasts or actual production data; and
- migration, relinking or provider deactivation.

The offline portion of M2 is continued in `03-inactive-module-scaffold.md` with
an inactive `OpenMeteoWeather` scaffold and an inactive
`OpenMeteoSolarForecast` scaffold. Timers and HTTP remain disabled until
separately authorized.
