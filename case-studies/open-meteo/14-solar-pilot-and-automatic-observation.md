# Solar Pilot and Automatic Observation

## Outcome

The storage-aware solar runtime passed both its first controlled manual request
and the later separately authorized automatic-update observation. The installed
module used `pv_harvest`, retained its weather dependency and published complete
configuration-bound forecasts after manual and scheduled provider requests.

This report is intentionally sanitized. Coordinates, ObjectIDs, local names,
plant ratings, orientation details, raw responses and forecast values remain in
ignored private evidence.

## Manual Gate

The live integration initially kept configuration and transport separate:

1. a read-only preflight resolved exactly one configured solar instance and its
   weather reference;
2. the instance was active, its configuration hash matched, its cache and fetch
   markers were empty and its update timer was stopped;
3. one explicitly authorized manual `UpdateData()` call returned `success` with
   code `ok`; and
4. an independent read-only postflight inspected the published cache without
   issuing another request.

No retry, calibration, shading profile, archive mutation, provider deactivation
or consumer switch was attempted.

## Acceptance Evidence

The independent postflight proved:

- current module state and equal non-zero attempt/success timestamps;
- an unchanged configuration hash and one preserved weather reference;
- a bounded four-day power cache and bounded local-day energy cache;
- finite, non-negative values with `kW` power and `kWh` energy units;
- preceding-interval semantics for power and local-day semantics for energy;
- a forecast maximum below the configured PV-input boundary; and
- an unchanged protected root object.

The first read-only cache assertion expected `W`. The public contract and pure
domain model use `kW`, so that assertion was corrected and the existing cache
was read again. The configuration and provider request were not repeated.

## Automatic-Update Observation

Automatic updates were enabled later under a separate gate with a bounded
hourly interval. A scheduled cycle advanced both attempt and success markers,
left the instance active with a current data state and produced another readable
bounded cache. Both weather instances remained active during the same
observation. No manual fallback request, retry timer, service restart or module
reload was needed.

The observation proves scheduling and successful runtime reconciliation; it is
not yet calibration evidence. Forecast loss parameters remain provisional until
immutable forecast snapshots can be compared with PV input, battery dispatch
and house-feed measurements over exact UTC intervals.

## Regression Closure

The module scaffold regression asserts the observed cache contract directly:

- power points use `kW`, preceding-interval semantics and finite non-negative
  values;
- daily points use `kWh`, local-day semantics and finite non-negative values;
  and
- storage-coupled output remains bounded by the configured PV-input limit.

The regression uses synthetic responses only and contains no installation data.

## Remaining Boundaries

Existing weather and solar providers remain in parallel until their consumers
have been inventoried, compared and migrated under separate rollback-capable
gates. Calibration, shading and consumer migration remain separate work.
