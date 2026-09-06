# SymconTest Package Live Reload Rollback

**Status:** Candidate transferred; activation rolled back

**Date:** 2026-08-31

## Outcome

The repository-verified SymconTest lifecycle package was transferred to the
private Windows staging boundary and independently verified as the exact
29-file fileset
`a60d43f3432997797f48eb0d6ddc5426ec4265340aaf89aa3c403151991008eb`.
The transport archive matched its source size and SHA-256, every archive path
and entry type was checked before explicit extraction, and every extracted
payload matched `fileset.sources.json`.

The subsequent atomic module reload did not pass its acceptance contract. The
module transaction restored and reloaded the previous active package in the
same bounded MCP execution. The corrected candidate therefore did not remain
active.

## Rejected Acceptance Assumption

The failed condition was an exact substring assertion for the serialized
disabled `tileAccess` bootstrap object. The normalized object contains more
fields than `mode`, and `json_encode()` applies the `JSON_HEX_*` options before
embedding it in HTML. Searching for an exact minimal object was therefore not
a valid structural assertion.

A complete visualization HTML hash is not a valid reload invariant either.
The generated bootstrap contains runtime-dependent presentation data, so its
byte identity may change while the provider and security contract remains
unchanged.

## Verified Rollback Boundary

Read-only postflight proved:

- the previous 29-file package is active again;
- the earlier 28-file package remains an independent older rollback;
- the corrected 29-file candidate exists only in two inactive, byte-verified
  transaction/staging locations;
- the pilot is active and its configuration remains provider-free;
- all three OwnTracks source configurations and required logging states are
  unchanged;
- the two additive visualization links are unchanged;
- persistent WebHook Control still has no OwnTracks candidate entry;
- the visualization CSP remains `connect-src 'none'`; and
- no external tile authority appears in the active visualization.

No provider, tile authority, routing mode, archive, logging, existing
OwnTracks object, commit or publication was changed.

## Corrected Next-Gate Contract

A later, separately authorized reload may reuse the already verified inactive
candidate. Its postflight must:

1. prove the active fileset identity and unchanged live-object baselines;
2. locate the final `window.handleOwnTracksOpenLayersMessage(...)` invocation;
3. decode its JSON argument structurally;
4. require `action = bootstrap`, `basemap.mode = none`,
   `tileAccess.mode = none` and `tileAccess.enabled = false`;
5. require `connect-src 'none'` and reject an external tile authority;
6. verify the stable native hook through the uniform disabled `404` response;
   and
7. restore the current active package in the same transaction on any failure.

The inactive candidate and both rollback generations remain retained until a
successful live acceptance and a separate retention decision.
