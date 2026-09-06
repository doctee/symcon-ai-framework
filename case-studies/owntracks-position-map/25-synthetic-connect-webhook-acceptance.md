# Synthetic Connect WebHook Acceptance

**Status:** Bounded live spike complete; all temporary objects and routes
removed

**Date:** 2026-08-31

## Outcome

A separately authorized Symcon MCP spike proved that a custom capability header
reaches a synthetic WebHook unchanged through both the local WebServer path and
the HTTPS Connect path. Missing and wrong values were rejected with the same
generic non-cacheable response. No credential was reflected in a response.

The spike contacted no provider and exposed no geographic or OwnTracks data. It
used synthetic response markers only.

## Preflight And Ownership

The read-only preflight found exactly one active WebHook Control and one active
Connect Control. The WebHook inventory contained 20 existing registrations.
After normalizing stored hook names to their effective `/hook/...` routes, no
existing route overlapped either random synthetic candidate.

Each temporary script was created below the already owned pilot instance only
after a positive ID, object type, parent and existence check. Exactly one
temporary hook pair was present at a time.

Symcon 9.1 stores the registered hook name without the effective `/hook/`
prefix. Registration ownership and overlap checks must therefore normalize the
stored and effective forms before comparison. Comparing the HTTP path directly
with the stored property is not a valid ownership check.

## Acceptance Matrix

The following behavior was identical locally and through Connect:

| Request | Result |
| --- | --- |
| valid custom capability header | accepted synthetic response |
| missing header | generic 404 |
| wrong header | generic 404 |
| unsupported method | generic 404 |
| misleading longer-prefix path | generic 404 |
| response credential reflection | absent |
| redirect | absent |
| cache policy | `no-store` |
| content sniffing protection | enabled |

Every MCP result had empty transport and execution errors and
`truncated=false`.

## Transport Findings And Corrections

The negative matrix found two transport-specific behaviors:

1. `REQUEST_URI` contains the effective path but omits the query component.
   `QUERY_STRING` and the parsed query array retain it. The adapter now rejects
   a non-empty `QUERY_STRING`, so an exact-path check cannot accidentally accept
   a query-bearing request.
2. Symcon exposes no `getallheaders()` function and collapses repeated custom
   header names before PHP execution. In both local and Connect tests the last
   supplied value became the single effective server value.

The second behavior is acceptable for this bearer-capability boundary only
because the hook handler is the sole authorization authority for that header.
No Connect component, reverse proxy or other upstream layer may authorize a
different raw value. `OwnTracksTileAccessPolicy` now requires explicit verified
header canonicalization in addition to verified Connect forwarding. If that
invariant changes, activation fails closed.

The SAEF standard and EK-008 now distinguish transports that expose duplicate
headers from transports that canonicalize them before the handler. They still
forbid ambiguous multi-authority interpretation.

## Cleanup Evidence

After each probe, cleanup:

1. read the complete current hook inventory;
2. required exactly one matching stored path and target pair;
3. removed only that pair;
4. applied and read back WebHook Control;
5. deleted only the matching temporary script; and
6. verified no route, script ID or matching child name remained.

The final WebHook count returned to 20, both temporary scripts were absent, no
candidate name remained below the owned parent and WebHook Control remained
active. Existing registrations were not rewritten or removed.

## Remaining Gates

The spike proves transport behavior, not a geographic basemap. The reviewed
adapter is now integrated with the private pilot while retaining mode `none`
by default and preserving the verified stored/effective hook normalization;
see `26-default-disabled-webhook-runtime-integration.md`.

Internal tile-authority selection, provider operation, basemap activation,
routing, publication and commit remain separate gates.
