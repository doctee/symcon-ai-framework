# SymconTest Strict Hook Lifecycle Correction

**Status:** Repository implementation; no live activation

**Date:** 2026-08-31

## Outcome

The OwnTracks pilot now follows the native `IPSModuleStrict` lifecycle shown by
the current SymconTest `HookServeSimpleStrict` example. The stable owned hook
address is registered once during `Create()`. `ApplyChanges()` validates
configuration and domain references but no longer registers, unregisters or
mirrors the volatile route in a persistent attribute.

No live package, instance, WebHook Control configuration, provider, tile,
archive, logging or visualization object is changed by this repository gate.

## Evidence And Correction

Two controlled activation attempts produced the exact intended provider
configuration and preserved every non-provider property and persistent WebHook
configuration, but the module did not reach active status. The last operation
before `SetStatus(IS_ACTIVE)` was the configuration-dependent native hook
synchronization.

The current SymconTest `HookServeSimpleStrict` implementation instead:

- registers the address in `Create()`;
- does not repeat registration in `ApplyChanges()`;
- does not persist a second representation of the volatile hook state; and
- leaves request behavior to `ProcessHookData()`.

The earlier SAEF candidate violated this lifecycle by persisting
`RegisteredTileHook` and attempting to reconcile it from `ApplyChanges()`.
That state survives a restart while the native hook registration is volatile.
It also makes repeated `ApplyChanges()` contend with a route that may already
be owned by the same instance.

## Security Boundary

The stable registration does not activate tile access. With
`tileAccess.mode = none`, the handler returns a generic `404 Not found` with
`no-store` and `nosniff` before capability secrets, request-budget state, tile
authority construction or file access. Capability issuance remains disabled,
and the visualization keeps `connect-src 'none'`.

When tile access is enabled, every request still passes the existing exact-
path, method, header, capability, expiry, rate, concurrency and tile-authority
checks. Registration lifecycle and request authorization remain separate
responsibilities.

## Verification Contract

Repository tests must prove:

1. exactly one registration during `Create()`;
2. no registration or unregistration during repeated `ApplyChanges()`;
3. a generic disabled response without secret creation;
4. invalid configuration cannot remove or expose the stable handler;
5. source and packaged runtimes remain identical; and
6. all existing capability, gateway, cache and browser security tests remain
   green.

The next live gate requires a newly built and byte-verified module package,
retention of the current package for rollback, a controlled reload and a fresh
provider-free postflight before any basemap activation is reconsidered.

## SAEF Reference Decision

SAEF now treats official IP-Symcon documentation as the API contract and the
[SymconTest repository](https://github.com/symcon/SymconTest) as the preferred
executable reference for current SDK and module-lifecycle usage. Examples do
not replace SAEF security, ownership, observability or rollback requirements.
Compatibility-sensitive decisions should record the inspected branch or
commit; this correction inspected `master` at commit
`760bf04fba8d05228e43339f8319207c8ca62714`.

## References

- [SymconTest](https://github.com/symcon/SymconTest)
- [SymconTest `HookServeSimpleStrict`](https://github.com/symcon/SymconTest/blob/master/HookServeSimpleStrict/module.php)
- [IP-Symcon `RegisterHook`](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/registerhook/)
- `knowledge/EK-008-connect-reachable-webhook-security.md`
- `standards/SYMCON_STANDARDS.md`
