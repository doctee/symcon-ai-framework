# CWWW target integration and acceptance

## Accepted scope (2026-10-28)

The opt-in adapter and Kelvin-range contract were adopted in a bounded cohort:
two switch-only targets and two CWWW targets. Supervised switching, positive
dimming from OFF, Kelvin endpoints, authoritative feedback and Alexa commands
passed. Existing facade identities were preserved. The pre-existing common
runtime cohort was not migrated by this adoption.

Fixed voice-assistant white presets may exceed a lamp's physical Kelvin range.
The private entry boundary maps valid integer user requests to the configured
endpoints before dispatch and confirmation. The core remains strict and the
device-feedback path remains unchanged; this is not blanket clamping of malformed
input. Complete-entry offline tests cover the boundary and alarm routing.

Scene adoption used temporary actionless targets while the operator adjusted
values. A displayed zero was not proof of a stored cell: the scene form could
fall back to the linked variable's current value. Release therefore required
explicitly saved values and an independent before/after comparison of all scene
cells, with only the intended target IDs normalized. Both mappings were released;
temporary targets remain retained, not implicitly eligible for deletion.

The deployed source closure and wrapper hashes were checked independently.
The existing diagnostic mirror still represents the preceding runtime cohort;
mirror adoption is a separate live gate, not part of repository publication.
Forced reconnect/duplicate-delivery fault injection remains unclaimed. Offline
failure/interleaving tests do not substitute for those production observations.

The preparation record below is historical. Its installation gates were resolved
only for the accepted cohort, not as permission to adopt additional consumers.

## Original preparation record

This local candidate adds opt-in Kelvin control to Core/Runtime. It does not
change a managed fileset, live wrapper, firmware, MQTT subscriptions or a device.
It is not deployment-ready.

Two switch-only targets can use existing explicit target Idents and the existing
ControlLight confirmation path. Two CWWW targets need a small transport adapter,
not a separate ControlLight implementation. All receive the normal alarm and
reported-brightness contract. Existing user-facing identity should be retained.

`CwwwLightAdapter` is a pure protocol mapping candidate, not a generic SAEF helper.
No suitable CWWW mapping was found in the existing helper/ControlLight library.
Existing ControlLight continues to own object reconciliation, RequestAction,
confirmation, diagnostics and command serialization. No new shared helper API.

The ESPHome MQTT JSON contract uses integer brightness and C/W channels 0..255.
See [official parser](https://github.com/esphome/esphome/blob/dev/esphome/components/light/light_json_schema.cpp)
and [CWWW documentation](https://esphome.io/components/light/cwww/).
Source reviewed 2026-10-27; current upstream is not proof of installed firmware.
Commands are minimal: ON/OFF retains mix and brightness; positive brightness
combines ON and the value in one packet; temperature changes only C/W mix.

Kelvin is a configured linear channel-ratio approximation, consistently inverted
on receive. It is not a photometric or physical spectral calibration. Validate
the configured endpoints against the installed device contract before adoption.
The initial 2650..4800 endpoint fixture has <=5 K byte-roundtrip error. Do not
silently clamp out-of-range requests and then confirm the original request.

## Original integration gates

- Use a separate MQTT Server Device with String payload on the existing state
  topic, leaving the old JSON instance unchanged. The official MQTT Server
  documentation describes a String payload variable; the installed form must
  independently confirm the property mapping before creation. No new broker.
  See [MQTT Server](https://www.symcon.de/de/service/dokumentation/modulreferenz/geraete/mqtt/mqtt-server/).
- `feedbackJson()` now validates one bounded payload (8192 bytes, depth8) before
  producing any updates. C/W can never be joined across messages. Initial
  adoption uses `initialFeedback()` and requires all three capabilities from
  one packet; this does not prove freshness or acknowledge any command.
- Qualify duplicate-topic delivery and raw-variable identity read-only after
  separately authorized creation. Native support for String is confirmed, but
  delivery to the additional instance has not been exercised in production.
- `CwwwLightTargetRuntime` implements target action/feedback wiring and readiness
  offline. Qualify its exact installed transports and ControlLight wrapper gates.
  No optimistic SetValue on dispatch. Missing/invalid fields preserve previous
  observations and cannot confirm a new command. No fabricated startup defaults.
- Bind positive, typed, distinct IDs privately. Use existing Ensure helpers;
  preserve user names/positions, archive and links. Do not generate ID 0.
- Preserve facade IDs but explicitly migrate dimmer profiles/values from native
  0..255 to percent, from independent observed brightness, not old facade state.
- Retire only the exact legacy action/feedback bindings after snapshot and drift
  checks. Do not leave two writers for one facade or reuse facade as target.
- Test full candidate lifecycle twice, alarm, offline failure, delayed feedback,
  concurrency and rollback in the existing isolated fake before deployment.
- Keep all existing managed contracts and the live common runtime unchanged.
  Packaging, production mutation, optical tests and publication require approval.

Tests cover mapping, target lifecycle and confirmation interleaving with real
common runtime and helpers under an offline kernel fake. Private source-entry
tests additionally cover complete facade entry/alarm routing. They do not prove
installed MQTT delivery or the coupled live migration.

## Opt-in Kelvin control

`colorTemperatureControl` contains exactly `minimumKelvin`, `maximumKelvin` and
`profile`. Bounds are ordered integers within1000..12000; input must be Kelvin
and temperature enabled. A custom profile name is mandatory; system profiles
are rejected. Example:2650..4800. Commands outside bounds fail before dispatch,
never silently clamp. Feedback conversion remains unchanged.

Without this option, normalization and profiles remain unchanged. With it,
reconciliation validates the pre-existing integer profile's bounds, step1,
empty associations/prefix and suffix ` K`, then uses the existing EnsureVariable
helper to assign it. Missing or drifted profiles fail closed, without repair.
No new helper: explicit later installation should reuse EnsureProfile only
after collision/ownership checks and backup. Both equal-range lamps may share
one owned profile. Never change the system-wide ~TWColor profile.

A one-time presentation override was rejected: the current runtime would reset
it on subsequent reconciliation. An opt-in contract keeps ownership explicit
and does not alter existing consumers. A newly built and separately approved
managed package is required before any wrapper can use this extension; existing
live immutable filesets must not be patched. Profile provisioning and rollback
belong in the coupled migration gate, not ordinary action dispatch.

## Implemented target runtime

- Explicit `reconcile()` consumes three previously validated external variables:
  raw String input, separate native-action String command output, Boolean online.
  It creates four owned targets and two explicitly bound, inactive events using
  existing Ensure helpers. It rejects active receiver events and ownership drift.
  Reconciliation never runs implicitly on commands or receive events.
- `handle()` accepts only validated Action/Variable contexts. Receive reads the
  latest raw value under a per-target lock and publishes readiness last. An
  online transition invalidates readiness until the next complete raw update.
- `dispatch()` uses RequestAction on command output; it never writes target values.
  Lock is held only for bounded transport dispatch, not confirmation waiting.
- `assertReady()` verifies online, readiness and that all targets match the latest
  complete raw packet. Call before AND after the common runtime in the future
  facade wrapper. The common runtime's existing already-confirmed path does not
  itself consult this adapter's ready flag. Do not activate a wrapper without
  this additional gate. The combined dispatch test now exercises real common
  runtime confirmation, delayed receive, no-feedback timeout and partial packets.
  This does not yet test the full facade wrapper entry, alarm routing or installed
  MQTT delivery. Availability gating here is stricter than common ControlLight's
  diagnostic-only availability; it protects adapter initialization/consistency.
- A failed write leaves readiness false; the next complete receive can recover.
  This is not transaction rollback of existing facade bindings or measurements.

Send and receive must use distinct variables/transport instances; some transports
update the sending variable optimistically. The receive-only String endpoint
therefore cannot double as command output. Prefer a separately pinned native
MQTT command endpoint, without changing existing topics or the old JSON instance.
Creating these transports remains outside this local candidate's authority.

The global SetValue static-analysis stub was corrected from void to bool using
the current official kernel contract (verified2026-10-27). This enables explicit
failed-write handling. IPSModule::SetValue and live kernel/module code are unchanged.

## Receiver and migration ordering

The raw receiver is distinct from command output. An optimistic write by a
command transport must never be consumed as device feedback. Trigger receive on
update, not only change. Serialize receive runs using a separate per-target lock
from ControlLight's command-confirmation lock; read the latest complete raw value
inside that lock so a delayed event cannot restore an older queued payload.
Parse and validate the complete update before touching target variables. Do not
hold the receive lock while waiting for ControlLight confirmation.

Keep the adapter inactive until a complete initial snapshot and the availability
contract are qualified. A retained message is not evidence of current online
status. Do not let newly created zero/false defaults appear as measured values.
Writing several target variables is not an atomic Symcon transaction: lifecycle
tests must still qualify confirmation/interleaving and failure recovery.

For adoption, snapshot source/configuration/event bindings and facade metadata;
stop only the declared legacy writers, then transfer facade ownership. Convert
brightness from independently received native values, including while OFF,
rather than reinterpreting the former facade number as percent. Preserve IDs,
names, positions, visibility and Alexa endpoints. Archive history is not rescaled
implicitly. Quiescence and exact rollback remain required before live adoption.
