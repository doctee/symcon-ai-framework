# Qualified direct dim start and runtime consolidation

Positive brightness means on at the requested brightness. The default runtime
confirms STATE on before requesting brightness. The explicit opt-in
`brightnessOffStateTransition: ['mode' => 'target-turns-on']` instead sends
one brightness request for a qualified single target whose native brightness
action already powers it on. Both STATE and brightness must confirm within
the existing shared deadline. There is no fallback on command after failure.

The existing RequestAction path, semaphore, scaling, alarm gate, diagnostics
and wait helper remain authoritative. A retained matching brightness while
off must not suppress the command. A repeated matching request while on stays
idempotent. Zero brightness retains its existing STATE-off behavior.

Only a bidirectional single STATE/brightness target may opt in. Manual-on,
off-only and member-confirmed group configurations cannot enable this mode.
An omitted option preserves the previous normalized configuration and hash.
Native and production-facade high-to-off-to-low tests passed for one optical
pilot. Telemetry alone is not proof that no brief flash occurred.

## Consolidation without behavior changes

A common immutable runtime does not imply a common device strategy. Preserve
each caller's configuration, including color power-on, alarm polarity, group
membership and manual-on behavior. Do not enable direct dimming for additional
devices merely because they share a module or transport.

Before migration, inventory every selected runtime and caller, compare enabled
capability conversions, inspect normalized configuration changes and run the
complete regression suite. New disabled/default metadata can legitimately
change an older configuration hash without changing an enabled capability.
Verify each expected new hash during command-free reconciliation.

Use a hash-pinned, reversible wrapper-path transaction. Preserve object IDs,
user presentation, variable actions and event bindings; independently read back
sources and verify reconciliation twice. Do not change global bootstrap,
restart services or retire still-referenced immutable packages. Exclude callers
whose separate device qualification is incomplete.

The active runtime mirror belongs in a central technical runtime location.
An explicitly approved domain-level category is also suitable; record the
new parent in the private ownership input rather than letting reconciliation
recreate a mirror at its former location.
Keep its private reference index aligned with its actual consumers. Historical
mirrors and filesets remain rollback evidence until a separate retention gate;
moving a mirror requires an explicit ownership migration, not ordinary Ensure
reconciliation. Preserve excluded callers and their mirrors.

## Existing finding outside this consolidation

An offline comparison under PHP 8.5 found that exact RGB white in the HS encoder
can raise DivisionByZeroError in both the prior and consolidated source. This
is a shared pre-existing failure, not a successful conversion or a new
regression. Its correction and live-version qualification are a separate task.
