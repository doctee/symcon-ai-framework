# Gate 90-M — independent live health postflight

**Status:** A separately authorized, bounded Symcon-MCP postflight independently
verified the activated OwnTracks package, retained rollback package,
configuration and format-2 runtime health without mutation, 2026-09-06.

## Scope

The postflight used `symcon_run_script_text_ex` once with a reviewed private PHP
probe. The probe read the installed single-target channel binding, pinned
adapter and policy hashes, activation status, active adapter record and retained
transaction. It independently computed identities for the active, rollback and
still-staged package trees and inspected the current OwnTracks instance,
library, Module Control and runtime-state contracts.

The probe briefly acquired and released the five existing runtime locks in the
adapter's canonical order before reading request-budget and miss-state health.
Locking changed no file content. The probe had no object mutation, action,
reload, service, HTTP, provider, publication or cleanup call site. Its bounded
output contained only sanitized booleans and counts.

## Result

The MCP call passed all three required result channels independently:

- `transportError` was empty;
- `executionError` was empty; and
- `truncated` was `false`.

The sanitized payload reported `outcome: passed`. It verified the installed
channel and adapter activation records, the active transaction and both the
retained rollback and staged packages. The active and rollback trees each
contained the expected 37 files and matched the reviewed package identity.

Exactly one OwnTracks instance remained healthy with status `102`, unchanged
configuration and no unapplied changes. Kernel runlevel and Module Control were
ready. The authoritative miss state remained format 2. All five runtime locks
were acquired, with zero active request leases and zero active miss-state
reservations.

The private exact probe and sanitized result are retained with SHA-256 checksums
outside tracked public files. No private path, account, host identity, ObjectID,
configuration, credential, coordinate, tracker identifier or movement history
is recorded here.

## Decision boundary

Gate 90-M closes the independent server-side activation and rollback-health
postflight for this deployment. It does not exercise the HTML-SDK map, make a
tile request or prove physical Safari/iPad behavior.

The remaining sequence stays separately gated:

1. explicitly disclose and authorize any synthetic browser test that may send
   position-derived XYZ tile indices to the configured provider;
2. perform physical Safari/iPad acceptance separately;
3. keep the active transaction and previous package as rollback evidence; and
4. keep publication and retention cleanup closed until their own review.

Gate 90-M authorizes none of those later actions.
