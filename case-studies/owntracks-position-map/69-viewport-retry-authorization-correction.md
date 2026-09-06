# Viewport Retry Authorization Correction

**Status:** Repository and synthetic browser verification complete; exact
package live-activated; physical browser acceptance pending

**Date:** 2026-09-02

## Evidence

The protected tile renderer previously scheduled its single failed-tile retry
after 65 seconds while the server retained an accepted viewport generation for
only 60 seconds. The retry refreshed the existing OpenLayers source and reused
that expired generation. The gateway then remained fail-closed for provider
fallback and could return only exact pre-provisioned static tiles. At the edge
of the private static authority this appeared as abrupt rectangular strips and
blank areas. A pan or zoom often repaired the display because it requested a
new viewport generation.

The static authority does not publish or apply a coverage extent. It resolves
each requested XYZ PNG independently. The visible boundary was therefore a
fallback symptom, not an oversized fit extent.

## Correction

The renderer now performs at most one recovery per normal viewport:

1. wait three seconds after the first non-abort tile failure;
2. request a fresh authorization for the unchanged visible viewport;
3. preserve the one-retry budget while the new generation is issued; and
4. let the accepted viewport rebuild the OpenLayers tile source.

It no longer calls `source.refresh()` with an already accepted generation. A
cross-component test reads both the renderer retry delay and the server grace
constant and rejects a retry at or beyond the authorization window. A browser
fixture can reject one complete synthetic viewport generation so recovery can
be verified without provider traffic or private movement data.

## Verification

The synthetic protected-gateway browser rejected every request carrying the
first viewport generation. After the bounded delay the renderer requested one
new generation without user input; all 20 visible synthetic tiles then loaded,
with 20 expected first-generation failures and no blank tile remaining. The
fixture used no provider and no private coordinate or movement data.

The complete OwnTracks suite, deterministic 36-file package check, PHPStan,
PHPCS and the full SAEF check pass. The package identity is
`44ded6323a8030af194af22df66f0e94cf74bfdd59b9aa1ee006b0221850d402`.

No public helper or general map abstraction is introduced. Static tiles remain
authoritative, Connect capability protection and provider budgets remain
unchanged, and the correction does not alter OwnTracks objects, archives,
logging, configuration or the legacy map.

The exact package was subsequently activated under the separately recorded
live gate. The in-app browser did not open the live map because its security
review required an additional action-time confirmation for transmitting
position-derived tile indices to the configured provider. That refusal
occurred before navigation and is not a failed renderer result.

## Rollback Boundary

Repository rollback restores the preceding renderer source, generated bundle,
deterministic distribution and fileset identity. Live rollback restores the
immediately preceding complete package and its byte-exact configuration. No
cache purge is part of either rollback.
