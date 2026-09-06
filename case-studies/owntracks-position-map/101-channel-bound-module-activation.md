# Gate 90-L — channel-bound module activation

**Status:** The exact preflighted OwnTracks Position Map candidate was activated
through the installed SAEF deployment channel version 8. The adapter completed
its package, state, reload and health contract without rollback, 2026-09-06.

## Scope

The existing deployment client addressed only deployment
`saef-owntracks-position-map-20260905-01`. Immediately before activation, a
sanitized channel status confirmed the successful Gate 90-K preflight for the
same standalone-module deployment. Exactly one `activate` operation followed,
then one independent sanitized status readback.

The staged archive remained the Gate 90-E package with SHA-256
`995b3d31c9f2382df737cc71f9d60ac029cfc333f69da884172e2c46d311cca7`
and package identity
`65b81cf76741f31f08688c32596450ca7bfe4435613a4e4e353eff16553179fa`.
That identity was intentionally equal to the previously active 37-file package;
this gate validates the new SAEF deployment path without introducing a new
OwnTracks code revision.

## Result

The activation request returned process and channel exit code `0`,
`success: true` and `outcome: activated`. The independent status readback then
confirmed `phase: activation`, `outcome: activated` and
`deploymentExitCode: 0` for the same deployment.

Before switching the package, the target-specific adapter repeated its
ownership and quiescence checks, captured fresh instance configuration and
format-2 runtime-state snapshots, copied the candidate into an adapter-owned
transaction and retained the previous package as rollback state. It performed
the same-volume package switch and exactly one targeted `MC_ReloadModule`, then
required the expected library/module identity, exactly one configured instance,
unchanged configuration, accepted state schema, zero active leases and ready
Symcon runlevel. It finally recorded the active transaction only after those
checks passed.

The successful adapter outcome means no automatic rollback or manual recovery
was required. The activation did not restart the Symcon service or OpenSSH,
change the target allowlist or deployment-channel policy, contact a tile
provider, publish or remove retained package, state or transaction evidence.
No private path, account, host identity, ObjectID, credential, coordinate,
tracker identifier or movement history is recorded here.

## Decision boundary

Gate 90-L proves the hash-bound server-side module activation and the adapter's
immediate internal health contract. It does not replace an independent runtime
postflight or physical Safari/iPad map acceptance and does not authorize
provider contact, publication or retention cleanup.

The remaining sequence stays separately gated:

1. run an independent read-only live health postflight against the activated
   package and retained rollback record;
2. separately authorize any browser test that may contact the configured tile
   provider, with its privacy disclosure;
3. obtain physical Safari/iPad acceptance; and
4. keep publication and retention cleanup closed until their own review.

Gate 90-L authorizes none of those later actions.
