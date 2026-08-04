# 36 Client Subscription Coverage and Runtime Namespace Report

**Case study:** IP-Symcon MQTT Discovery Exporter  
**Gate:** Client-subscription validation and state-only light namespace correction  
**Outcome:** Live functional path passed; improved in-place migration candidate awaits fileset retention maintenance

## Finding

An additional state-only light was discovered and updated correctly by Home
Assistant, but commands published through Home Assistant did not reach its
IP-Symcon MQTT Client Device. Direct invocation of the owned command adapter
completed successfully, proving that command parsing, `RequestAction()`,
authoritative confirmation and the physical target path were intact.

The fault boundary was the runtime namespace. The entity used a retained legacy
command namespace while the site MQTT Client intentionally subscribed only to
the established SAEF site namespace. Adding one exact diagnostic subscription
proved insufficient because the broker path did not deliver that legacy
namespace. Existing working exporters on the same gateway all used the SAEF
namespace.

## Framework Correction

Client-transport reconciliation now validates that every desired command topic
is covered by at least one configured MQTT Client subscription before it
creates or changes command adapters and events.

The validator:

- reads but never mutates the shared gateway configuration;
- accepts exact filters, `+` single-level wildcards and a final `#`
  multi-level wildcard;
- rejects malformed wildcard placement and missing subscription contracts;
- fails before command resources are created when any command topic is
  uncovered; and
- runs only during reconciliation, not in the frequent state or command
  dispatch path.

This is a configuration preflight, not proof of broker ACL behavior. A real
ingress test remains required because broker-side authorization and routing are
outside the IP-Symcon configuration contract.

The repository candidate also permits runtime and command topic namespace
changes without treating unchanged capabilities as removed resources. Existing
command adapters and events are then updated in place. Discovery-topic changes,
transport changes and capability contraction retain their explicit cleanup
gates.

## Controlled Live Migration

The corrected subscription validator was activated through an immutable,
hash-bound fileset and a clean IP-Symcon restart. Runtime reflection, gateway
configuration and adapter status passed independent read-back.

The improved in-place migration candidate passed its complete offline fileset
gate, but inactive staging was rejected because the restricted deployment
channel had reached its configured managed-fileset retention count. No runtime
selection or active file changed during that rejected staging attempt.

The live entity was therefore migrated with the already active,
ownership-exact cleanup path:

1. capture exact owner, gateway, Registry and presentation rollback evidence;
2. remove only exporter-owned legacy command, event and publisher resources;
3. republish the unchanged discovery identity with SAEF runtime topics;
4. recreate owned resources with the same Idents and presentation defaults;
5. restore the shared gateway byte-exactly to its single SAEF wildcard
   subscription; and
6. verify active gateway and command-adapter status.

No physical device action was issued by migration. MQTT publication was
expected and bounded.

## Functional Result

The complete state-only path passed:

- a Home Assistant off command reached the new command adapter and produced
  authoritative off feedback;
- a manual physical on transition propagated through IP-Symcon and Home
  Assistant into Apple Home;
- an Apple Home off command reached the same path and switched the light off;
- repeated `OFF` delivery updated the command variable and triggered dispatch
  even though its string value did not change;
- final facade and retained MQTT state were both off; and
- gateway and command adapter remained active with no new failure.

Home Assistant briefly displayed off/on/off during the first command. The
timestamps showed the requested UI state first, the still-authoritative old
state during the bounded physical transition and the confirmed final off state
after feedback. This was convergence behavior, not a second physical on
command.

## Remaining Maintenance

The repository's in-place topic migration candidate should be activated after
local retention maintenance frees one managed-fileset slot. That maintenance
is separate from this functional migration and must not delete the active or
rollback-relevant filesets.

Exact ObjectIDs, private topics, source backups, hashes and diagnostic
timestamps remain only in private machine-readable evidence.
