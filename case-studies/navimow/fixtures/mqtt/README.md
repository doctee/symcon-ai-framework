# Navimow MQTT Fixture Set

**Status:** Initial docked receive-only evidence promoted
**Capture date:** 2026-07-27
**Source:** Private Smart Home MQTT/WSS capture from SAEF step 86

## Scope

These fixtures preserve the smallest reviewed shapes from one bounded
three-minute receive-only capture.

The mower remained docked. The client:

- connected through TLS-verified WSS;
- received acknowledgements for four exact QoS-0 subscriptions;
- published no MQTT message;
- sent no mower command;
- received two messages, both on the `location` channel.

## Fixtures

| File | Purpose |
| --- | --- |
| `credential-shape.json` | Preserve the successful MQTT credential response envelope and field names after redaction. |
| `location-pose-partial.json` | Preserve a location array containing pose, time, type and numeric vehicle-state fields. |
| `location-type-3-partial.json` | Preserve a smaller location array containing only time and type. |

## Interpretation Boundary

The two location payloads prove that messages on one topic are partial and do
not share a fixed complete field set.

The observed numeric values do not yet prove:

- what `vehicleState: 1` means across operating states;
- whether `type: 1` means pose, update or another vendor event class;
- whether `type: 3` is a heartbeat, terminator or another partial update;
- whether missing fields should be retained from a previous message;
- whether `state`, `event` or `attributes` messages are sent while docked.

An offline parser must therefore:

- accept arrays of partial objects;
- validate each present field independently;
- distinguish absent from explicit null;
- never clear a known value merely because a later partial object omits it;
- retain REST as the authoritative state source until an active comparison
  capture defines numeric state semantics.

## Sanitization

The public fixtures use:

- `DEVICE_001` for the topic device segment;
- synthetic timestamps that preserve millisecond integer type;
- synthetic coordinates that preserve number type;
- redacted MQTT endpoint and credential placeholders.

No real account, device, credential, endpoint, coordinate or local path is
present.
