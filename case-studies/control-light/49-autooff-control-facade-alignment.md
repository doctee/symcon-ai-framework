# Auto-Off Control-Facade Alignment

Date: 2026-07-24

## Scope

Four live Auto-Off consumers were reviewed against the current ControlLight
downstream contract. The control role must use the authoritative local
ControlLight `STATE` variable once that wrapper has completed its v2 migration.
Local `DIMMER` feedback may additionally extend activity, but it is not an
on/off command target.

## Result

Two consumers required a live configuration change:

- the consumer of `CL-025` now uses local `STATE` as `controlID` and local
  `STATE` plus `DIMMER` as `activityIDs`;
- the consumer of state-only `CL-014` now uses its local `STATE` for both
  control and activity.

The `CL-026` consumer already followed this contract and remained byte
unchanged. The fourth consumer also remained byte unchanged because none of its
ControlLight-related targets has an eligible migrated v2 facade yet. Moving
that consumer to a legacy wrapper would have exchanged a known direct contract
for an unverified intermediate contract.

## Activation and verification

Both changed sources were backed up byte-exactly in the private evidence
package and checked for PHP syntax before activation. Each source was then
installed separately and reconciled once in the non-commanding `Execute`
configuration path.

Postflight confirmed:

- both scripts are syntactically valid and not broken;
- all expected managed events are active and have an explicit Run-Automation
  action binding;
- obsolete direct-device events were deleted by the ownership-checked cleanup;
- the two unchanged consumers retained their exact source hashes;
- all four consumers expose their expected active event set.

No light command or real-device state change was used for this consumer-only
migration. The affected ControlLight facades had already passed their own
bounded functional tests.

Private ObjectIDs, source backups, hashes and event evidence remain in
`private/control-light/autooff-facades-20260724/`.
