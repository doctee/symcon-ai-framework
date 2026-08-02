# Open-Meteo Weather Runtime

## Outcome

The weather module has a productive, read-only runtime adapter. A valid
configuration activates a stateless IP-Symcon timer only when
`EnableAutomaticUpdates` is enabled; `ApplyChanges()` itself performs no HTTP
request. The later solar runtime is documented separately in
`12-solar-runtime.md`.

This increment is implemented and verified offline only. It does not publish a
new public-module revision, update an installed library, configure private
coordinates, activate a live instance or change OpenWeather/SolCast consumers.

## Update Contract

`UpdateData()` performs exactly one bounded attempt:

1. validate location and runtime policy before transport;
2. acquire an instance-scoped semaphore;
3. record the attempt and mark the instance as fetching;
4. execute one `Sys_GetURLContentEx()` call with TLS verification and the
   configured timeout;
5. parse and project the complete response in memory;
6. replace the bounded forecast cache and curated public values only after all
   requested fields, units and intervals are valid; and
7. publish freshness metadata and release the semaphore on every path.

Expected provider and schema failures return a small JSON result instead of
throwing through the public module API. Logs contain only stable failure codes;
request URLs, coordinates and response bodies are not logged.

## Last-Good, Stale and Retry Behavior

A failed candidate never clears or partially overwrites the last valid domain
values. Freshness is calculated from `LastSuccess`, never from the most recent
attempt. Last-good data first becomes `Warning` and later `Stale` when its age
exceeds `StaleAfterMinutes`.

Retry attempts are scheduled across executions at 5, 15 and 30 minutes. After
that bounded sequence the module returns to the configured normal polling
interval. With automatic updates disabled, both normal polling and retry timers
remain at zero while an explicitly invoked `UpdateData()` is still allowed.
There is no sleep loop or concurrent request fan-out.

## Forecast Access

The module stores the curated current, hourly and daily series as a bounded
cache tied to the request-configuration hash. It exposes:

- `GetCurrentJson()`;
- `GetHourlyForecastJson(from, to, fieldsJson)`; and
- `GetDailyForecastJson(from, to, fieldsJson)`.

Forecast queries accept at most ten days and 32 explicit known fields. Unknown
fields, invalid ranges, incompatible configuration hashes and empty caches fail
with classified JSON results. `GetLocationDescriptor()` provides the linked
solar module's later location contract, but its result is installation-sensitive
and must not be logged or published.

## Offline Proof

The module harness injects synthetic transport responses and verifies:

- active/inactive timer reconciliation without an ApplyChanges fetch;
- manual-only operation with normal and retry timers disabled;
- a complete successful current/daily forecast publication;
- bounded hourly cache queries;
- transport failure classification;
- last-good value retention;
- warning-to-stale transition; and
- the first two persistent retry intervals.

The deterministic module fileset includes the pure
`WeatherForecastProjector`. Network traffic and IP-Symcon live mutations remain
separate authorization gates.
