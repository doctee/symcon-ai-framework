# Drain-Aware Tile Retry Correction

**Status:** Repository correction and synthetic browser verification complete

**Date:** 2026-09-02

## Symptom

Historical `Fit all` views could stop with changing vertical or horizontal map
strips. Repeating the same selection or zooming sometimes loaded additional
tiles, but coverage remained nondeterministic.

## Read-Only Evidence

Sanitized runtime counters showed a healthy instance, an enabled protected
gateway, retained provider-cache entries and no exhausted per-selection byte or
request budget. A small number of spatially rejected requests remained. The
renderer scheduled a complete viewport retry three seconds after the first
failed tile, even while other protected tile requests were still active or
queued.

When that retry viewport was accepted, the renderer recreated the protected
source. Source recreation cancelled all unfinished work. Already available
static or cached tiles survived visually, while slower dynamic requests were
discarded. Their timing determined which strips remained visible.

## Correction

The bounded one-generation retry is retained, but it is now drain-aware:

- a detected failure still schedules the existing recovery generation;
- the timer first checks both the active request set and the pending queue;
- while either contains work, recovery is deferred without consuming the
  single retry allowance; and
- only an idle queue may request and activate the replacement viewport.

Selection ownership, capability headers, viewport generation binding,
allowlists, provider policy, concurrency limits, request and byte budgets,
cache retention and static-tile precedence remain unchanged.

## Synthetic Browser Proof

A provider-free loopback fixture delayed every tile and rejected the entire
first viewport generation. The corrected renderer allowed all 20 rejected
requests to drain, deferred recovery three times, then loaded all 20 tiles from
the second generation. The final state had 20 successes, one bounded retry, no
console warning or error and no retained object URL.

The fixture records only aggregate counters. It contains no ObjectID, tracker
identifier, coordinate, private origin, tile index or movement history.
