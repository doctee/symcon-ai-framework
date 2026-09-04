# Fit Viewport Authorization Correction

**Status:** Repository implementation and synthetic desktop/iPad acceptance
complete; live activation remains closed

**Date:** 2026-09-01

## Physical Observation

After step 56, dynamic tiles appeared when zooming into the selected historical
path, while the initial `Fit all` view retained only the statically available
coverage. This separated provider reachability from initial viewport
authorization.

## Read-Only Diagnosis

A sanitized read-only live probe found successful provider and cache activity:
13 upstream requests succeeded and 12 tiles were retained. It found no
allowlist or budget rejection. No tile indices, viewport coordinates, source
identity or movement data were returned.

The renderer contained an ordering gap. A track result scheduled tile viewport
authorization after its initial fit, and pan or zoom scheduled it through
OpenLayers `moveend`. However, `fitAll()` itself did not schedule authorization.
The resize observer could therefore perform a later, final fit for the actual
host dimensions without authorizing that changed viewport. A following zoom
generated `moveend`, explaining why dynamic tiles then appeared.

## Correction

Every successful `fitAll()` now schedules the existing debounced viewport
request after the fit, displacement, label and occlusion calculations. This
reuses the existing 120-millisecond debounce, generation fingerprint,
selection envelope, ephemeral capability and server-side request budgets. It
does not introduce another tile API, expand the provider policy or reset a
selection budget.

A repository regression check requires the scheduling call to remain inside
the `fitAll()` boundary. The synthetic browser fixture additionally exposes
only aggregate viewport-generation counters through DOM test diagnostics.

## Verification

The complete OwnTracks test suite, deterministic OpenLayers bundle check,
36-file package build and validation, PHPCS and PHPStan passed. The regenerated
package identity is
`7d8b95ba09177c6d1c659ef2d09e3884df8339572f716c33b85fcbd1a8c6f09e`.

The internal browser used only synthetic positions and generated local tiles:

- at 1280 by 720, the initial fit produced viewport generation 1 and reached
  protected-tile state `ready`;
- changing to 1024 by 1366 caused the resize observer's fit to produce viewport
  generation 2 without a user zoom;
- after zoom produced generation 3, pressing `Fit all` independently produced
  generation 4; and
- all checks retained one tile layer, three visible synthetic points and zero
  fit/UI occlusions.

The local test server was stopped, the browser viewport override reset and the
364-KiB synthetic tile directory removed after verification.

## Remaining Gate

Activating the regenerated package requires a separate live gate with fresh
package/configuration/provider/WebHook/link preflight, exact rollback and an
independent postflight. Commit, publication, provider-policy changes, cache
purge, static-tile replacement and OwnTracks or visualization-object mutation
remain closed.
