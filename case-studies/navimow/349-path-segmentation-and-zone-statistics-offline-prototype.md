# 349 Path Segmentation And Zone Statistics Offline Prototype

**Case study:** Navimow native IP-Symcon module

**Status:** Offline prototype verified; runtime integration and publication closed

**Date:** 2026-08-26

## 1. Scope

This step implements the offline-only prototype defined in step 344. It uses
synthetic local coordinates and privacy-safe correlation hashes. It is not
loaded by the Navimow module and changes no Symcon object, variable, archive,
transport or command.

## 2. Reuse Decision

The prototype consumes the existing projections of:

- `MqttPositionDiagnostic` for bounded local pose samples;
- `MqttTaskObservationLedger` for correlated pass windows, transitions,
  progress candidates and area candidates.

It does not extend the public SAEF helper library. Both reducers remain
Navimow-specific candidates until runtime use and repeated reuse justify a
stable API.

## 3. Path Segmentation Contract

`MqttPathSegmenter` joins position samples to task passes by bounded timestamp
distance. It never joins streams by array index.

A new segment begins on:

- source-time or receive-order regression;
- a configured maximum time gap;
- transport-session change;
- area-correlation change;
- vehicle-state change;
- coordinate discontinuity.

The result retains a separate latest point, applies time-and-distance
downsampling and enforces input, segment, point and serialized-byte limits.
Coordinates remain explicitly `uncalibrated-local`; no geographic position,
scale, rotation or geometric coverage is inferred.

## 4. Zone Statistics Contract

`ZoneStatisticsReducer` groups retained pass summaries by hashed partition or
boundary correlation and derives:

- pass-local progress candidate;
- non-negative observed subtotal-area delta per pass;
- completion evidence;
- interruption and resume counts;
- first and last observation time;
- aggregate observed area and evidence confidence.

`latestObservedAreaPercent` is emitted only when a positive configured zone
area exists. It means latest-pass area divided by configured zone area. It is
not geometric coverage. Weekly area remains outside zone allocation.

User-facing zone names and real area sizes remain installation-private
configuration.

## 5. Synthetic Evidence

The fixture models three anonymous areas:

- one completed productive pass;
- one partial rain-delayed pass without an area denominator;
- one productive pass spanning a transport-session change.

Offline checks prove:

- three distinct time-window correlations;
- deterministic downsampling and all required break classes;
- current-position retention;
- completion, interruption and resume accounting;
- denominator-gated percentages;
- rejection of invalid denominators;
- absence of device identities, topics and private zone labels.

## 6. Remaining Gates

Before runtime integration:

1. calibrate coordinate unit, origin, rotation and cross-session stability;
2. obtain a productive coordinate-rich observation for the remaining zone;
3. define installation-private mappings and configured zone areas;
4. decide retention and archive ownership for map history;
5. review the candidate API and failure behavior;
6. publish and install disabled through separate gates.

No map UI, public statistics variable or live pilot is authorized by this
step.
