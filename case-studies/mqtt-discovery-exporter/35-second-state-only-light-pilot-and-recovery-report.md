# Second State-Only Light Pilot and Recovery Report

**Gate:** Supervised multi-entity client-transport extension
**Result:** PASS — EXTERNAL TRANSIENT FAILURE OBSERVED AND RECOVERED
**Date:** 2026-07-23
**Live impact:** One additional retained MQTT light; final physical state restored

## Scope

The active cleanup-disabled MQTT Discovery pilot was extended from one
tunable-white light to a second state-only light. The second entity uses a
ControlLight-owned Boolean state/action variable rather than addressing the
underlying device integration directly.

Installation-specific names, ObjectIDs, MQTT topics and source backups remain
in the private overlay.

## Preparation and Publication

The active exporter source was hash-verified against a private rollback copy.
The candidate preserved the first entity's exact discovery and runtime hashes
and added one distinct device and entity identity.

Two non-publishing preparation runs proved:

- stable owned object identities;
- one update-triggered MQTT command event;
- one change-triggered authoritative state event;
- explicit IP-Symcon Run Automation action bindings;
- unchanged command, publication and failure counters; and
- no physical device action.

The separately authorized cleanup-disabled publication produced exactly the
three messages required by an on/off-only MQTT light: discovery, power state
and color-mode state. The Registry then contained two managed entities and
eight publishers. No cleanup tombstone or device command occurred.

Home Assistant discovered the new MQTT device and entity while the retained
legacy entity remained present as an accepted pilot duplicate.

## Supervised Command Observation

The first two Home Assistant interactions did not change the device. Both
reached the exporter with valid power payloads, but the underlying target
action returned `false`. The exporter retained the failure as
`action_failed`, waited the configured bounded interval for authoritative
feedback and did not report a successful command.

Independent log evidence placed both attempts inside a temporary device-
integration outage:

1. the site-local fieldbus socket lost its keepalive;
2. both target actions were rejected during the outage;
3. the socket reconnected automatically; and
4. a later on/off sequence through the new MQTT entity completed successfully.

The two successful actions produced exactly two exporter commands and two
ControlLight commands. Feedback and retained-state publication settled
normally. The final local state, target state and Home Assistant command state
all returned to the initial off value without a compensation command.

## Engineering Classification

The confusing Home Assistant presentation during the first attempts was stale
state during an unavailable target transport, not evidence of malformed MQTT
payloads or optimistic publication.

The observation confirms the intended failure boundary:

- Home Assistant commands remained valid;
- the exporter did not convert a rejected target action into success;
- ControlLight did not increment its command counter for rejected actions;
- no confirmation timeout was misclassified as a successful command; and
- normal operation resumed without source, object or configuration changes
  after the external transport recovered.

The exporter deliberately does not retry actuator commands automatically.
Automatic retry could duplicate a command after uncertain fieldbus recovery.
Interactive retry after transport recovery remains the safer contract.

## Evidence Closure

Exact source hashes, private object identities, timestamps, counters and final
values are retained in the private machine-readable preparation, publication
and functional-test artifacts. This report records only the reusable
engineering result.

## Gate Decision

The two-entity MQTT Discovery pilot is operational. The second state-only light
passed discovery, failure observability, transport recovery, bidirectional
command handling and exact initial-state restoration. No exporter or
ControlLight implementation correction is required from this observation.
