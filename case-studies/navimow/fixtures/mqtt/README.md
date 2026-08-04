# Navimow MQTT Fixture Set

**Status:** Docked and active evidence plus synthetic native envelopes promoted
**Capture date:** 2026-07-27
**Source:** Private Smart Home MQTT/WSS captures from SAEF steps 86 and 89

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
| `mqtt-credential-success.json` | Synthetic successful credential response with an example WSS host, relative path and non-secret placeholders. |
| `mqtt-credential-business-error.json` | Synthetic business failure used to prove response-description redaction. |
| `location-pose-partial.json` | Preserve a location array containing pose, time, type and numeric vehicle-state fields. |
| `location-type-3-partial.json` | Preserve a smaller location array containing only time and type. |
| `state-running.json` | Preserve the direct state and battery object observed while Running. |
| `state-docking.json` | Preserve the direct state and battery object observed at the Docking transition. |
| `state-docked.json` | Preserve the direct state and battery object observed at the final Docked transition. |
| `location-running.json` | Preserve numeric location state 4 aligned with Running. |
| `location-docking.json` | Preserve numeric location state 5 aligned with Docking. |
| `location-docked.json` | Preserve numeric location state 2 aligned with final Docked. |
| `location-type-4-no-time.json` | Preserve the observed task-delay message without a source timestamp. |
| `symcon-envelope-location.json` | Synthetic native Symcon receive envelope carrying a location payload string. |
| `symcon-envelope-state.json` | Synthetic native Symcon receive envelope carrying a direct-state payload string. |
| `symcon-envelope-retained.json` | Synthetic retained-envelope classification case; not evidence that Navimow sends retained state. |
| `symcon-envelope-invalid-data-id.json` | Synthetic negative case for exact native receive-interface validation. |
| `bounded-diagnostics-shadow-active.json` | Synthetic exact version-2 contract for the privacy-safe `ShadowActive` diagnostic projection, including one identity-free semantic hint. |
| `episode-accounting-reconciled.json` | Sanitized aggregate regression evidence separating 12 disconnect observations from 8 distinct transport episodes and 4 duplicates. |
| `core-resume-transient-core-readiness.json` | Synthetic runtime contract for an active Core whose configuration is temporarily unreadable before `KR_READY`. |
| `core-resume-bounded-health-observation.json` | Synthetic absolute `+15/+30/+60/+90/+120/+180 s` contract for delayed native Core readiness and one final bounded recovery. |
| `core-resume-post-ready-unhealthy-live.json` | Sanitized payload-free signature of the step-181 restart: correct 15-second barrier, receive-counter advancement with unresolved timing, unhealthy Core classification and completed cleanup. |
| `transport-subscription-schema-live-v3.json` | Sanitized payload-free live aggregate proving canonical native `QoS`, delivery to both compatible children and complete cleanup. |

## Interpretation Boundary

The docked and active location payloads prove that messages on one topic are
partial and do not share a fixed complete field set.

The active comparison additionally proves:

- `state` is a direct object with device ID, state string, battery and
  timestamp;
- numeric location states 4, 5 and 2 aligned with Running, Docking and final
  Docked in one observed transition;
- `type: 4` can arrive without `time` and must not update a timestamp-ordered
  accumulator.

The observed numeric values do not yet prove:

- what docked observation `vehicleState: 1` means relative to final Docked
  value 2;
- whether `type: 1` means pose, update or another vendor event class;
- whether `type: 3` is a heartbeat, terminator or another partial update;
- whether missing fields should be retained from a previous message;
- whether `state`, `event` or `attributes` messages are sent while docked.

The direct state strings are stronger state evidence than the numeric location
codes. Numeric mappings remain candidate reconciliation evidence until
repeated across another transition.

The V3 transport aggregate additionally proves that a normal corrected
connection:

- rewrote all four retained subscription entries to exact native `QoS`;
- delivered one observed location message to both the productive Receiver and
  the known-good sibling probe;
- completed receive-only cleanup without retaining credentials or the probe.

It contains no topic or payload and does not change REST state authority.

The step-181 Core-resume aggregate proves a different lifecycle boundary:

- the durable pre-ready barrier can complete with the exact 15-second
  post-ready delay;
- receive-only counters advanced between the final active baseline and the
  first reconciled projection, but the message timing relative to the restart
  is unresolved;
- the Core can nevertheless be unhealthy at the decisive reconciliation
  projection;
- the Account then follows its existing bounded
  `unhealthy-with-credentials` recovery path;
- mandatory cleanup restores the disabled credential-free state.

This fixture records the observed signature. It does not establish why the
native Core became unhealthy before classification.

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

The four `symcon-envelope-*.json` files are derived contracts rather than
captures. Their metadata identifies them as synthetic, references the native
envelope evidence and makes no claim about an observed `PacketType` value.
