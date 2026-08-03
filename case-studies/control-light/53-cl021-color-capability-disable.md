# CL-021 Color Capability Disable

**Gate:** Instance-specific safety change after target color-contract finding
**Result:** PASS
**Date:** 2026-07-25
**Live impact:** Color facade disabled; no device command

## Scope

The approved change disabled only the color capability of the selected
ControlLight v2 contract. STATE, brightness and color temperature remained
enabled. The Zigbee2MQTT target instance and its native color variable were not
changed.

The wrapper retained its Z2M preset but explicitly overrides the color target
Ident with an empty value. This is the normal ControlLight capability-disable
contract and does not weaken authoritative feedback for any enabled
capability.

## Activation

A fresh preflight verified the exact active wrapper source, operational target,
availability, facade actions, feedback events and equal local and target
values.

The hash-locked wrapper source was then replaced and reconciled twice. Both
runs succeeded without a device command. The runtime:

- hid the existing script-owned local color variable;
- removed its wrapper action;
- deactivated its script-owned color feedback event;
- preserved the variable identity, name, position, profile and retained value;
- preserved the native target color variable; and
- left STATE, brightness and color-temperature facade actions and feedback
  events active.

An existing user-owned presentation link to the color facade was deliberately
preserved under the ownership contract. Its target facade no longer has an
action, so it cannot dispatch a color command. Presentation cleanup may be
handled separately if desired.

## Verification

The postflight verified unchanged local and target values, unchanged target
availability and an unchanged cumulative device-command counter. The existing
historical color timeout remains visible in diagnostics instead of being
cleared.

All 29 ControlLight wrapper scripts remained present and non-empty. Existing
names, positions, event identities, target links, presentation links and the
three enabled capability contracts were preserved. Repeated reconciliation
created no duplicate object.

## Decision

The selected contract is now safely operated with STATE, brightness and color
temperature only. Color stays disabled until the Zigbee2MQTT target module
provides a tested stable color action/feedback contract.

Updating to the separate Zigbee2MQTT module v6 testing line remains a distinct
migration gate. It requires its own installation-wide inventory, backup,
rollback and regression plan before any module update.
