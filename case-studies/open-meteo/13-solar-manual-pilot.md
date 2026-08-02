# Open-Meteo Solar Manual Pilot

## Outcome

The first controlled request for a storage-coupled solar configuration passed.
The installed module used `pv_harvest`, retained manual-only operation and
published one complete configuration-bound forecast after exactly one provider
request.

This report is intentionally sanitized. Coordinates, ObjectIDs, local names,
plant ratings, orientation details, raw responses and forecast values remain in
ignored private evidence.

## Gate Sequence

The live integration kept configuration and transport as separate gates:

1. a read-only preflight resolved exactly one configured solar instance and its
   weather reference;
2. the instance was active, its configuration hash matched, its cache and fetch
   markers were empty and its update timer was stopped;
3. one explicitly authorized manual `UpdateData()` call returned `success` with
   code `ok`; and
4. an independent read-only postflight inspected the published cache without
   issuing another request.

No retry, recurring timer, calibration, shading profile, archive mutation,
provider deactivation or consumer switch was attempted.

## Acceptance Evidence

The independent postflight proved:

- current module state and equal non-zero attempt/success timestamps;
- an unchanged configuration hash and one preserved weather reference;
- a stopped timer because automatic updates remained disabled;
- a bounded four-day power cache and bounded local-day energy cache;
- finite, non-negative values with `kW` power and `kWh` energy units;
- preceding-interval semantics for power and local-day semantics for energy;
- a forecast maximum below the configured PV-input boundary; and
- an unchanged protected root object.

The first read-only cache assertion expected `W`. The public contract and pure
domain model use `kW`, so that assertion was corrected and the existing cache
was read again. The configuration and provider request were not repeated.

## Regression Closure

The module scaffold regression now asserts the observed cache contract
directly:

- power points use `kW`, preceding-interval semantics and finite non-negative
  values;
- daily points use `kWh`, local-day semantics and finite non-negative values;
  and
- storage-coupled output remains bounded by the configured PV-input limit.

The regression uses synthetic responses only and contains no installation
data.

## Remaining Boundaries

The manual pilot does not authorize automatic polling. Recurring requests need
a separate live configuration gate and bounded observation. Calibration also
remains deferred: it requires immutable forecast snapshots and exact UTC
comparison intervals, with PV input separated from battery dispatch and house
feed.

Existing weather and solar providers remain in parallel until their consumers
have been inventoried, compared and migrated under separate rollback-capable
gates.
