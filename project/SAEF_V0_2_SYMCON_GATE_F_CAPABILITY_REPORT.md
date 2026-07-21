# SAEF v0.2 Symcon Gate F Capability Report

**Gate:** Brightness, color temperature and Home Assistant MQTT contract
**Result:** PASS with bounded rapid-command caveat
**Date:** 2026-07-21
**Final live state:** Power restored off; test changes remain bounded

## Scope

This Gate F sequence followed the successful external-producer power test. It
verified brightness, color temperature and the retained MQTT contract consumed
by Home Assistant.

Object IDs, private topics, broker endpoints, credentials, device names and
payload contents remain outside this repository.

## Initial Brightness Finding

An independent MQTT client published one non-retained brightness command. The
device reported a value one percentage point below the request. Strict equality
therefore classified valid quantized feedback as a confirmation timeout.

Compensation restored the original brightness and power state. The retained
Home Assistant discovery and runtime messages remained valid and consistent
with the observed Symcon state.

## Runtime Correction

The corrected exporter keeps confirmation bounded while using capability-aware
feedback predicates:

- boolean power and packed RGB remain exact;
- brightness accepts a difference of one percentage point;
- color temperature accepts a difference of ten Kelvin;
- a false `RequestAction()` result is accepted only when authoritative device
  feedback independently confirms the requested state;
- absence of matching feedback still produces `action_failed` or
  `confirmation_timeout`; and
- state events produced during an active command dispatch are coalesced because
  the owning command publishes the authoritative state after confirmation.

The implementation composes the existing `SAEF_WaitForVariable()` predicate
and lookback support. It introduces no helper and no public API. Fourteen
dispatch tests cover accepted and rejected tolerances, false action results,
bounded timeouts and state-event lock contention.

Every corrected runtime was generated as a new immutable fileset, staged
inactive, verified by file hash and activated atomically with an external
restart and byte-exact rollback source. No published release artifact was
modified in place.

## Live Verification

The brightness retest confirmed the one-point feedback difference as a
successful command without advancing failure diagnostics. Immediate
compensation restored the original observed brightness and power state.

The supervised installation required a longer per-entity confirmation window
for its color-temperature path. This was configured in the private owner
script through the existing confirmation contract, followed by preparation and
publication reconciliation. Public defaults and APIs were not changed for an
installation-specific latency.

Home Assistant then produced several real color-temperature commands. Multiple
commands were confirmed end to end, and the final deliberately separated power
command restored the off state. Rapid repeated symbol selection also produced
bounded lock and confirmation-timeout diagnostics because intermediate targets
were superseded before confirmation. No unbounded execution or continuing
publication loop was observed.

## Home Assistant MQTT Contract

An independent read-only broker check received the expected retained discovery
document and runtime state channels. Discovery command and state bindings
matched the managed Registry contract, and retained runtime values matched the
observed Symcon state.

This proves the broker-level Home Assistant contract. It does not claim a
particular dashboard layout or that arbitrary rapid UI command bursts are
lossless.

## Gate Decision

Power, brightness, color temperature and the retained Home Assistant MQTT
contract are **PASS** for deliberately issued, bounded commands. The original
strict-confirmation defect is corrected in the repository and in the active
Symcon fileset.

Rapid color-temperature command bursts remain an explicit operational caveat:
superseded intermediate commands may produce bounded diagnostics. Future
hardening may model latest-command-wins coalescing, but it must be designed and
tested separately rather than weakening confirmation or removing serialization.

## Related Artifacts

- `project/SAEF_V0_2_SYMCON_ROLLOUT_PLAN.md`
- `project/SAEF_V0_2_SYMCON_GATE_F_COMMAND_REPORT.md`
- `case-studies/mqtt-discovery-exporter/11-command-state-dispatch-report.md`
- `case-studies/mqtt-discovery-exporter/14-supervised-integration-and-rollback-plan.md`
- `case-studies/mqtt-discovery-exporter/candidate/MqttDiscoveryExporterRuntime.php`
