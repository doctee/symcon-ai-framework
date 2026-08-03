# CL-008 post-staging membership requery

## Outcome

The post-staging authoritative Zigbee2MQTT extension query passed. Group ID 1
still contains exactly the two IEEE members bound into the private CL-008
candidate.

The response carried the unique request transaction and arrived after 91 ms.
The probe published exactly one metadata request and no device or group
command.

## Closed gate

This query closes the external-membership drift gate left open by the Symcon
group instance. Symcon itself exposes group ID and topic but not the live
member list; the extension response is therefore the authoritative membership
source.

Both returned IEEE identities map unchanged to the two active device instances,
their actionable STATE and brightness feedback, availability variables and
freshness evidence already verified in the live delta preflight.

## Activation boundary

The staged fileset remains inactive. CL-008 and Auto-Off still contain their
exact rollback sources, and no reconciliation has run.

The package is now activation-ready, but activation remains closed until
explicit approval. That later gate must update both script sources as one
rollback unit, reconcile CL-008 and Auto-Off without a device command, prove
two-run idempotency and run the sanitized 29-wrapper structural regression.
Real-device and Auto-Off timer testing remain a subsequent separate gate.
