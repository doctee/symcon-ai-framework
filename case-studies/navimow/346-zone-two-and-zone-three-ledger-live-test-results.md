# 346 Zone Two And Zone Three Ledger Live Test Results

**Case study:** Navimow native IP-Symcon module

**Status:** Zone 3 passed; Zone 2 rain-aborted with separate correlation

**Date:** 2026-08-26

## 1. Objective

Complete the natural zone-correlation evidence from step 343 without mower
commands and determine whether retained task and position evidence separates
the operator-confirmed app zones.

Both runs used the official schedule. REST remained authoritative and MQTT was
strictly receive-only.

## 2. Zone 2 Result

The scheduled Zone 2 task created a second privacy-safe partition correlation
and briefly changed the REST-authoritative state from `Docked` to `Running`.
The official app then reported a return to the station because of rain or
forecast rain.

The run retained movement evidence but no task progress, subtotal area or
weekly-area increment. Zone 2 therefore has a distinct private correlation
candidate, but not yet a productive mowing-pass proof.

### Cleanup deviation

The intended Codex heartbeat could not be created, and authorization for a
temporary Symcon cleanup event was not received before the conversation
stopped. Consequently, the receive-only transport remained active beyond the
short evidence window. The next-day preflight detected the open session and
stored Core credentials before any Zone 3 activation.

The already mandatory cleanup was then executed exactly once. Immediate and
delayed read-only postflights proved:

- both features disabled;
- MQTT and WebSocket inactive with status `104`;
- Authorization and MQTT credentials absent;
- REST operational;
- retained Zone 1 and Zone 2 ledger evidence available.

This is a process failure even though no mower command, OAuth action or restart
occurred and no private value entered public evidence.

## 3. Zone 3 Result

After the independent disabled and credential-free preflight, exactly one new
receive-only activation created Session 12. The official schedule then created
a third partition correlation, distinct from both earlier candidates.

The natural run provided complete early-closure evidence:

- REST-authoritative `Running`;
- a stable private boundary and partition correlation;
- pass-local progress increasing from 40.00 to 42.06 percent;
- subtotal and weekly-area candidates increasing in the same pass;
- 219 position samples with 218 coordinate changes;
- no MQTT transport incident during the evidence window.

The bounded cleanup was invoked immediately after the sufficient increment.
The first synchronous read still showed the known asynchronous Core shutdown,
so no retry was issued. Immediate and delayed read-only postflights then proved
complete credential-free cleanup, REST continuity and retained Pass 3 evidence.

## 4. Cross-Zone Decision

Zone 1 and Zone 3 now provide two productive, operator-confirmed and mutually
distinct correlations with progress and area evidence. This satisfies the
minimum correlation objective from step 343.

The Zone 2 candidate is also distinct, but remains lower-confidence until a
natural weather-permitted run supplies progress or area evidence.

No correlation hash, coordinate, device identifier, private topic or
installation ObjectID is published in this report.

## 5. Architecture Consequences

The evidence supports offline work on bounded path segmentation and per-pass
zone aggregation. It does not yet establish coordinate scale, zone polygons or
a geometric coverage denominator.

Dock, departure, rain return and productive mowing must be separate path
segments. Position samples must never be assigned to a zone solely because the
latest task correlation exists; assignment requires bounded temporal overlap
with a productive state and task evidence.

Before another short live activation, the cleanup dependency identified here
must be hardened according to step 347.
