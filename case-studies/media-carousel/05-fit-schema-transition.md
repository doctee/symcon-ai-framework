# MediaCarousel fit-option schema transition

Status: implementation candidate; installed adapter/profile and live activation
remain separate gates. No generic gateway, helper or restart boundary changes.

## Impact and reuse

The source owner is `Invoke-SaefMediaCarouselModuleAdapter.ps1`. It has no generated
copy; the channel installs a hash-pinned adapter and private policy. Its consumers
are the MediaCarousel transaction/package builder, adapter contract tests and the
target binding. Existing package-ownership migration and other module adapters
remain unchanged. The effective live owner must be inventoried before replacing
an installed binding; repository bytes do not establish installed ownership.

The existing adapter already supplies package snapshots, targeted reload,
configuration restoration, health checks and protected retention. Reuse those
paths. No new public helper or generic conversion engine is needed for one
specific default property. The module's eight client tests remain unaffected.

## Narrow admission

Without `configurationTransition` in the private policy, behavior stays byte-exact
and unchanged. The optional policy object follows
`media-carousel-fit-schema-transition.example.json`. It binds the source package,
candidate package, deployment ID and the exact expected UTF-8 configuration bytes
and SHA-256 for **every** instance. The packaged transaction must explicitly admit
`show-fit-toggle-default-false-v1`; a policy alone cannot broaden an old package.

The only permitted change is insertion of the previously absent scalar member
`"ShowFitToggle":false`. A bounded lexical check accepts compact flat Symcon
configuration objects, including escaped JSON list strings. It rejects duplicate
or case-aliased keys, nested values, whitespace rewrites, reordered existing keys,
changed values and all other additions/removals. Removing the one new member must
reproduce the original bytes exactly. Unsupported formatting fails closed; it is
never silently normalized. No locale-dependent serialization computes identity.

The adapter does not write the new property during activation: module registration
is expected to provide its default. Reviewed after-bytes must match the actual
reload output exactly. If that expectation is wrong, activation fails and invokes
the original rollback path. Enabling the option is a later instance-configuration
operation, outside this default-only transition.

## Phases and rollback

Preflight captures the original snapshot, verifies all transition bindings and
derives a separate expected candidate snapshot. Activation persists both snapshots
before directory switching. The immediate pre-mutation check still uses the
original snapshot. Post-reload health uses the candidate snapshot; object names,
parents, positions, visibility and statuses must remain identical, with ordinal
comparison. Neither policy nor before-snapshot is rewritten in place.

Rollback reloads the previous package, validates positive instance identity/type
and module ownership before each configuration restoration, restores original
configuration bytes and verifies the original snapshot. An unsuccessful restoration
remains `manual_recovery_required`, never a reported success. Failed candidate,
both snapshots and old package remain retained; no cleanup is enabled.

The transition is one-deployment-bound, not a reusable schema exemption. A later
policy reseal removes it and binds the new active package/configurations through
the existing protected installation gate.

## Qualification

The Windows PowerShell 5.1 CI job parses the exact production adapter and executes
its real schema functions against synthetic input in `en-US`, `de-DE`, `tr-TR`.
It covers default insertion at each position, legacy behavior, byte drift, invalid
values, duplicate/case-alias keys, changed package/deployment/instance/hash binding,
phase-specific snapshot comparison, and byte-exact idempotent rollback writes.
RPC is replaced by a test double; no credentials or live installation are used.
The transaction harness reuses the prior private MediaCarousel Windows
qualification fixture with synthetic loopback RPC, protected scratch ACLs and
DPAPI test credentials. It now invokes the existing hash-bound `SaefChildProcess`
helper, and exercises the complete unmodified adapter entry point: legacy
activation/reload failure, schema preflight/activation and schema drift rollback.
It verifies directory identities and retains both snapshots within each test
transaction. Scratch cleanup is verified. This does not prove a real Symcon
kernel's serialization, installed profile ACLs or physical client continuity.

Before live activation, require the exact reviewed source's Windows transaction
qualification, current target-binding inventory, protected backup, private
before/after plan, fresh read-only preflight and independent postflight. Any
target allowlist/profile installation is its own administrative gate; no service
restart or broad access change follows from this implementation.

## Isolated real-kernel schema qualification

`Test-SaefMediaCarouselSchema.ps1` is a separately authorized operator test, not
an adapter activation or a gateway verb. Its private hash-bound plan names the
installed channel/policy, reviewed candidate identity and an existing private
category. It imports the existing adapter's RPC, snapshot, package identity and
exact-byte transition validators; it never executes the adapter entry point.
Existing channel validators check all target bindings, including unrelated ones.

An inert, separately identified test library reproduces only property
registrations. It has no camera access, timers, visualization or runtime actions.
The test creates one instance, reproduces a legacy configuration, reloads only
the test library with the added default, and records actual kernel serialization
for every production configuration. PHP tests bind both fixture registrations
to current module source. Synthetic Windows tests qualify the operator entry
point; they do not stand in for real kernel results.

The existing PHP EnsureInstance helper was considered. This administrative path
uses fixed native RPC calls rather than arbitrary PHP execution, so its bounded,
test-specific creation rejects preexisting resources instead of adopting them.
Every object mutator rechecks a positive test ID, object type, module ownership
and absence of children. This is not a new public Ensure API. Test deletion is
authorized with test creation; retained production packages are never deleted.

The MediaCarousel adapter mutex excludes concurrent adapter transactions. Before
and after the test, all production configurations/presentation/status fields,
the active package and protected policies must match. Only after verified test
instance/library removal and production preservation is the exact transition
marked accepted. Partial creation, unknown children, altered test files or failed
cleanup remain explicit failures with a private recovery journal. Never rerun
blindly against leftover test resources. Interrupted runs require read-only
inspection of the journal and exact remaining identities before recovery.

The transition is evidence, not an installed policy or activation authorization.
Protected policy binding, inactive package staging/preflight, production reload,
instance option changes and retained-artifact cleanup remain distinct gates.
