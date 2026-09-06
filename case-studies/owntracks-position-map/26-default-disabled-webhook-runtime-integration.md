# Default-Disabled WebHook Runtime Integration

**Status:** Repository implementation and controlled private package reload
complete; WebHook and provider activation remain closed

**Date:** 2026-08-31

> **Lifecycle correction:** The later repository step documented in
> `33-symcontest-strict-hook-lifecycle.md` replaces configuration-dependent
> registration from `ApplyChanges()` with the native Strict pattern from
> SymconTest: one stable registration in `Create()` and a fail-closed disabled
> handler. This document remains the historical record of the earlier gate.

## Outcome

The reviewed tile WebHook adapter is now composed by the private pilot runtime.
The effective default remains `mode: none`. In that state the module:

- does not call `RegisterHook()` or `UnregisterHook()`;
- does not generate a capability secret;
- rejects `RequestTileCapability` without creating request-budget state;
- keeps `connect-src 'none'` and `img-src data:` in the HTML tile; and
- keeps basemap and routing authority disabled.

No provider, tile authority or public endpoint is introduced by this gate.
The existing `ProviderConfiguration` is reused as the private envelope: an
absent `tileAccess` member normalizes to `mode: none`. This avoids a new public
configuration property and preserves the existing stored configuration during
the package-only update.

## Runtime Boundary

The runtime now contains the complete but gated transport bridge:

1. `ApplyChanges()` validates tile access and synchronizes the owned hook only
   when the policy is explicitly enabled.
2. `RequestTileCapability` delegates to the bounded capability adapter only
   after the enabled policy has been established. The persistent secret is
   generated lazily, never during `Create()` or the default `ApplyChanges()`.
3. `ProcessHookData()` passes the verified effective custom header,
   `REQUEST_URI`, `QUERY_STRING`, method and body presence to the reviewed
   adapter.
4. The stored hook address `owntracks-position-map` is intentionally distinct
   from its effective HTTP prefix `/hook/owntracks-position-map`.
5. Any invalid configuration fails closed and removes a previously registered
   runtime hook.

At this gate the tile reader deliberately returned no tile. The subsequently
authorized repository gate in
`27-private-xyz-directory-tile-authority.md` replaces that placeholder with a
strict local reader while preserving the same disabled live configuration.

The lifecycle uses the native volatile `IPSModuleStrict` hook functions. Symcon
documents that `RegisterHook()` connects a hook to `ProcessHookData()` and that
`UnregisterHook()` is the runtime-change counterpart. The private activation
gate must still prove exclusive ownership of the singleton hook address before
enabling it.

## Verification

The repository tests prove both the source runtime and deterministic packaged
runtime. They specifically assert:

- `tileAccess.mode` is `none` in bootstrap data;
- no hook lifecycle call occurs in the default state;
- the secret attribute remains empty after apply;
- a disabled capability request returns only `tileCapabilityError` and still
  leaves the secret empty;
- the packaged module includes the hook lifecycle, handler and adapter calls;
  and
- the package remains free of archive/logging mutation APIs.

The complete OwnTracks target, PHPCS, PHPStan and deterministic fileset checks
pass.

## Controlled Private Reload

The separately authorized live gate activated the exact 28-file package with
identity:

```text
2e2d6aca3b40ed3cb54daa05c4153518605e2490ee1c997b1b44d4e31d3089b2
```

The immediately preceding 22-file package remains byte-exact as the rollback
unit:

```text
12a47174f29c6115335be2a642d06e5fcdf25e18bd5dc3456b91f77436f8d2c5
```

Two archive-shape errors were rejected before any package exchange: macOS
AppleDouble metadata and an explicit TAR root entry were not accepted by the
Windows staging extractor. Both transfer and staging artifacts were removed.
A first successful exchange then intentionally exercised rollback because the
same PHP execution context could not yet see the freshly generated module
wrapper used by an over-specific diagnostic. The old package was restored and
reloaded. The retained byte-exact candidate was then activated again and the
HTML-SDK read-back was correctly performed through the platform's
`IPS_GetVisualizationTile()` function in a fresh execution.

Every MCP result used for the completed exchange and postflight had an empty
transport error, an empty PHP execution error and `truncated=false`.

## Live Postflight

The final read-only postflight proved:

- the active and rollback packages match every payload hash and expected
  inventory;
- the pilot remains active and its stored configuration is byte-identical;
- all three source configurations and all position, accuracy and motion
  logging states are unchanged;
- the pilot links are unchanged;
- the WebHook Control retains its complete preceding configuration and no
  candidate hook exists;
- basemap, routing and tile access all remain `none`;
- the delivered HTML-SDK bootstrap contains `tileAccess.mode = none` and the
  restrictive `connect-src 'none'` policy;
- neither tile-cache nor request-budget runtime state exists; and
- no transfer, staging or failed-candidate residue remains.

The secret-generation function is reachable only after an enabled tile policy.
The live policy remained disabled throughout; no capability request or hook
request was executed. Together with the empty cache/request-budget state and
the repository regression test for the empty secret attribute, this closes the
default-disabled secret boundary without exposing internal persistence.

## Remaining Gates

The internal tile authority is now selected and implemented repository-side.
Separate authorization remains required for licensed tile provisioning, hook
activation, basemap activation, provider operation, routing, commit or
publication.

## References

- [Symcon RegisterHook documentation](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/registerhook/)
- [Symcon UnregisterHook documentation](https://www.symcon.de/en/service/dokumentation/developer-area/sdk-tools/sdk-php/module/unregisterhook/)
- [Symcon ProcessHookData documentation](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/processhookdata/)
