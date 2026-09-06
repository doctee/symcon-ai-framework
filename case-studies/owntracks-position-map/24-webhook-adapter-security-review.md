# WebHook Adapter Security Review

**Status:** Repository implementation and negative matrix complete; no WebHook
registered or activated

**Date:** 2026-08-31

## Outcome

The case-study-local `OwnTracksTileWebhookAdapter` now composes capability
issuance, strict HTTP request translation, the protected XYZ gateway, optional
tile cache and a persistent request budget. It emits a response value only; it
does not call `RegisterHook()`, write HTTP headers, choose a provider or modify a
live module instance.

The default runtime remains provider-free and WebHook-free. Consequently this
repository gate adds no reachable endpoint and grants no live authority.

## Security Finding Closed Before Integration

The gateway's original rate and concurrency counters lived only in an array
owned by one caller. Recreating an adapter for every PHP WebHook execution could
therefore reset those counters. The new `OwnTracksTileRequestBudget` closes that
gap with a small private file-backed state transaction:

- each capability identifier and request lease is SHA-256 hashed before storage;
- an exclusive lock protects every admission and release update;
- the state is limited to 64 clients, 1,200 requests per minute per capability,
  16 active leases per capability and 64 KiB total;
- leases expire after 30 seconds so an interrupted PHP execution cannot block a
  client indefinitely;
- capability issuance is globally limited to four per minute with at most two
  concurrent issuances, so minting fresh tokens cannot reset the tile budget
  without bound;
- malformed, oversized or linked state fails closed instead of resetting a
  security counter; and
- atomic private-file replacement avoids partially written accepted state.

The future internal tile authority must complete a request within the 30-second
lease boundary. No external provider fetch is authorized behind this gateway.

## Request Boundary

The adapter accepts an explicit list of raw header name/value pairs so duplicate
capability headers survive transport normalization and can be rejected. Before
the gateway it enforces:

- at most 64 headers and 16 KiB of aggregate header material;
- RFC-token-shaped bounded header names and control-character-free values;
- exactly one syntactically bounded capability header;
- an exact bounded request URI, no query string and no prefix extension;
- only `GET` or `HEAD`; and
- zero declared and observed request-body bytes.

Missing, duplicate, case-variant duplicate, comma-merged, malformed, expired or
wrong-audience credentials receive the same generic non-cacheable response as an
invalid path. No CORS authority, credential, exception, local path, provider or
installation detail is returned. Internal adapter/state failure returns only a
generic non-cacheable 503.

Capability issuance accepts exactly a positive request generation and a bounded
client-session key. It is impossible for the browser to select the audience,
TTL, header name or signing secret. Disabled policy refuses issuance.

One capability is intentionally reusable for multiple XYZ requests during its
short lifetime; single-use replay prevention would make normal parallel tile
loading impossible. Replay exposure is instead bounded by the 60-to-900-second
expiry, audience binding, global issuance budget, per-capability request budget
and concurrent leases.

## Secret And Privacy Boundary

The signing secret remains an injected server-side dependency. The adapter does
not persist or log it. A capability is returned only in the HTML-SDK message,
held in browser memory and sent in the dedicated request header. The persistent
budget contains neither token, client session, audience, XYZ path, coordinate,
tracker identity nor provider identity.

XYZ paths inherently disclose a requested geographic tile index to the private
WebHook/Connect access-log boundary. Such raw paths must not be copied into
public diagnostics or SAEF artifacts.

## Negative Matrix

The executable repository matrix proves:

- default-disabled and malformed capability issuance fail closed;
- capability-issuance limits survive reconstruction and prevent token-churn
  bypass;
- valid authorization yields one private PNG response with no credential or
  CORS leakage;
- missing, tampered, duplicate, comma-merged and malformed headers fail before
  tile access;
- unsupported methods, query strings, bodies and invalid header syntax fail;
- rate limits survive reconstruction of the PHP adapter object;
- concurrent leases are enforced across adapter objects and abandoned leases
  expire within the bounded recovery interval;
- persisted state contains only hashes and counters; and
- corrupt state produces a generic 503 without resetting the limit or leaking a
  private path.

All OwnTracks tests, deterministic 28-file packaging, PHPStan and PHPCS pass.
The repository package identity is:

```text
692fb17b32a256ac51edef50b4e4ee72584c925b88a2e5f448cfcd99ed4d5a4c
```

This hash identifies repository output only. It has not been activated or
published.

## Follow-up Gate

The separately gated synthetic Connect acceptance is now recorded in
`25-synthetic-connect-webhook-acceptance.md`. It verified forwarding and exposed
the transport-specific query/header canonicalization that the repository
contract now treats explicitly.

The default-disabled `ProcessHookData()` and owned `RegisterHook()` lifecycle
are integrated and privately loaded as recorded in
`26-default-disabled-webhook-runtime-integration.md`. Provider/tile-authority
selection and activation remain independent gates.
