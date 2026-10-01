# Profile Monitor — native presentation candidate

Fork based on elueckel/Profile-Monitor 1.6 and published as
`doctee/Profile-Monitor` version 1.7. See [provenance](PROVENANCE.md) and
[MIT license](LICENSE).

## Changes

- Keep existing profile rules, exclusions, instance/output identities and JSON contracts.
- Optional identity-based recognition of Zigbee2MQTT and Blink camera batteries.
- Blink raw states: 0 unknown, 1 critical, 2 low, 3 good. Default warning <= 2.
- Treat variables without a first update as unknown by default (switchable).
- Reject malformed presentations; integer percentage bounds must be integers.
- Explicit profile arguments during variable registration for Rust/Ninja.
- Missing notification destinations raise exceptions without echoing or sending to ID 0.
- Saved-rule preview returns diagnostics without writes or notifications.

## Compatibility and activation

Native rules need Symcon 8.1 or later. Their master switch and both presets
start disabled. Existing module/library GUIDs and BW functions are retained:
this candidate replaces the original installation and cannot coexist with it.
The live migration preserved the existing instance and output identities on
the current Symcon runtime. Older real Symcon versions remain uncertified; CLI
tests and the single live installation do not establish that compatibility.

Save desired presets while PresentationMonitoring is false, run the saved-rule
preview, and review additions, unknowns and the central ignore list before
enabling. Integer MIN/MAX options use JSON integers, not 0.0/100.0. The module
reports invalid foreign contracts; it does not rewrite foreign presentations.

AllCheckedVariables contains recognized IDs, including ignored and unknown
ones. Downstream battery scripts may enable archive logging for added IDs,
assign locations or send notifications. Review those consumers before rollout.
Keep the existing instance, variable IDs, links, events and archive history.
Back up exact code/configuration, disable new rules if necessary, and restore
through the verified existing deployment owner. A code rollback cannot undo
already sent notifications or downstream archive changes.

## Verification

The SAEF preparation workstream runs pure evaluator/runtime tests and full
original/candidate differential module tests for upstream 1.5 and 1.6. Those
checks include legacy output/notification parity, write-free preview, failure
before writes, repeated ApplyChanges, strict registration arguments and
output-free exported errors. The local manifest records the exact input and
output hashes. No private evidence is part of this fork.
