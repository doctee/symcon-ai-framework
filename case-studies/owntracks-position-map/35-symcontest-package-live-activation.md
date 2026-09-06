# SymconTest Package Live Activation

**Status:** Active; provider and tile access remain disabled

**Date:** 2026-08-31

## Outcome

The corrected SymconTest lifecycle package is active with the exact 29-file
fileset identity
`a60d43f3432997797f48eb0d6ddc5426ec4265340aaf89aa3c403151991008eb`.
The module reload retained the immediately previous 29-file package and the
older 28-file package as two independent byte-verified rollback generations.

The activation changed only the package generation and its volatile native
Strict hook lifecycle. It did not enable a provider, tile authority, routing,
capability issuance or external network access.

## Controlled Retry

The retry reused the already verified inactive Windows candidate; no second
transport was required. Before the reload it proved:

- the active and both older package identities;
- the candidate staging and earlier failed transaction copy;
- free, exact retry transaction paths;
- one healthy pilot with its provider-free configuration unchanged;
- unchanged source configurations and required logging states;
- unchanged additive visualization links; and
- unchanged persistent WebHook Control configuration without a candidate
  entry.

The candidate was copied into a new transaction directory, verified again,
and atomically exchanged with the active package. The previous active package
was retained before the targeted module reload. The same transaction contained
the complete rollback operation for every failed postcondition.

## Structural Runtime Postflight

The postflight did not compare dynamic visualization HTML byte-for-byte. It
located the final `window.handleOwnTracksOpenLayersMessage(...)` invocation,
decoded its JSON argument and proved:

- `action = bootstrap`;
- exactly three abstract source entries;
- `basemap.mode = none` and `basemap.enabled = false`;
- `tileAccess.mode = none` and `tileAccess.enabled = false`; and
- no private `tileAuthority` in the browser bootstrap.

The HTML retains `connect-src 'none'` and contains no external tile authority.
An independent second postflight repeated the package, object, configuration,
bootstrap and security checks after the activation transaction had completed.

## Disabled Native Hook Evidence

The native Strict hook registered during `Create()` is reachable but inert.
Three local requests were exercised independently:

1. `GET` without a capability;
2. `GET` with an invalid capability; and
3. `POST` with an invalid capability.

All three produced an indistinguishable nine-byte `404` response with
`text/plain`, `Cache-Control: no-store` and
`X-Content-Type-Options: nosniff`. The response body hashes were identical.
This proves the stable hook lifecycle without activating capability creation,
tile reads or provider behavior.

The persistent WebHook Control configuration remains byte-identical and has no
OwnTracks candidate entry, as expected for the native volatile Strict hook.

## Retention And Remaining Gate

The active package, two rollback generations, the earlier failed candidate
copy and the inactive candidate staging remain retained. Their cleanup is not
part of this gate.

Basemap/tile-access configuration, positive capability issuance, Connect tile
acceptance, routing, commit and publication still require separate gates.
