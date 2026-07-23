# ControlLight Modernization

**Status:** Seven active v2 instances fully device-tested; generated runtime mirror visible

This case study modernizes the installation's shared ControlLight automation
against current SAEF standards. The successfully tested hallway-light pilot is
the behavioral starting point for authoritative feedback, not proof that the
same brightness semantics are safe for every existing consumer.

## Candidate Files

- `candidate/ControlLightCore.php` contains side-effect-free configuration,
  scaling, color conversion and feedback comparison.
- `candidate/ControlLightRuntime.php` composes SAEF helpers for owned resources,
  diagnostics, bounded feedback confirmation and per-wrapper serialization.
- `candidate/ControlLightWrapper.php` documents the intended wrapper contract.
- `candidate/ControlLightRuntimeMirror.php` provides the ControlLight-local,
  deterministic mirror generator and rollback-safe provisioner. It is not a
  public SAEF helper.
- `02-pilot-migration-readiness-report.md` records the non-activating pilot
  package and its live approval gates.
- `03-inactive-pilot-fileset-staging-report.md` records the hash-verified,
  inactive placement on the connected installation.
- `04-pilot-pre-activation-drift-report.md` records the complete read-only
  drift gate immediately before the separately approved wrapper transition.
- `05-pilot-activation-attempt-and-rollback-report.md` records the verifier
  false negative, completed rollback and corrected retry boundary.
- `06-pilot-activation-and-regression-report.md` records the corrected pilot
  activation, two-run idempotency proof and all-instance source regression.
- `07-autooff-state-contract-migration-report.md` records the narrowly scoped
  migration of the distinct `CL-026` legacy instance's Auto-Off consumer from
  DIMMER-as-switch to STATE, while retaining DIMMER as an activity trigger. It
  also contains the correction of the original, misleading pilot attribution.
- `08-remaining-instance-readiness-and-wave-2-plan.md` records the read-only
  assessment of all remaining instances, their risk groups and the selected
  second migration candidate.
- `09-wave-2-package-and-preflight-report.md` records the private package
  construction, exact rollback proof and non-activating preflight for the
  selected second candidate.
- `10-wave-2-activation-and-regression-report.md` records the activated
  second instance, verifier false-negative rollback, corrected retry,
  idempotency and complete source regression.
- `11-wave-2-functional-test-report.md` records the stopped functional test,
  confirmed late STATE feedback, successful compensation and the timeout
  adjustment gate.
- `12-wave-2-timeout-experiment-and-wait-race-analysis.md` records the failed
  five-second hypothesis, safe configuration rollback and the identified
  second-resolution race in the variable-wait helper.
- `13-wait-helper-fix-and-offline-verification.md` records the shared-helper
  correction, polling-cost contract, regenerated filesets and complete offline
  verification.
- `14-wait-helper-fileset-staging-and-preflight.md` records the atomic inactive
  staging, independent hash readback and current CL-023 drift gate.
- `15-wait-helper-selection-and-regression.md` records the scoped CL-023
  selection, two-run idempotency proof and all-instance regression.
- `16-wait-helper-live-test-and-load-order-conflict.md` records the stopped
  device test, safe rollback and authoritative discovery that the active MQTT
  fileset preloads the old shared helper.
- `17-shared-wait-helper-runtime-unblock-report.md` records the clean-process
  owner-fileset activation, reflected corrected helper and complete 29-wrapper
  source regression before a separate functional retest.
- `18-corrected-helper-functional-retest-report.md` records the successful
  same-second STATE confirmation, stopped DIMMER test, safe compensation and
  the unresolved target brightness/STATE coupling.
- `19-isolated-z2m-command-trace-report.md` records the isolated brightness-only
  Z2M payload, stale-then-current STATE feedback, exact command deltas and the
  completed CL-023 functional sequence.
- `20-wave-3-readiness-and-cohort-plan.md` records the refreshed dependency-free
  inventory, exclusions and the sequential CL-025/CL-027 package gate.
- `21-wave-3-offline-package-report.md` records the private hash-locked package,
  closed mutation gate, direct Symcon transaction plan and offline regression
  result.
- `22-wave-3-live-preflight-report.md` records the fresh read-only live gate,
  effective helper/fileset identities, complete wrapper regression and both
  candidates' activation readiness.
- `23-wave-3-cl025-activation-report.md` records the first sequential member's
  two-run non-commanding activation, presentation preservation, diagnostics and
  complete wrapper regression result.
- `24-wave-3-cl027-delta-preflight-report.md` records the fresh mixed-baseline
  gate, first-member runtime health and STATE-only second-member readiness.
- `25-wave-3-cl027-activation-and-closure-report.md` records the STATE-only
  activation, full mixed-baseline postflight and completed sequential Wave 3.
- `26-wave-3-functional-test-and-capability-correction.md` records the complete
  CL-025 device sequence and corrects CL-027's STATE-only classification from a
  device limitation to an explicitly disabled wrapper capability.
- `27-cl027-dimmer-capability-activation.md` records the minimal wrapper
  correction, idempotent DIMMER/event creation, reported synchronization and
  complete source regression without a device command.
- `28-cl027-dimmer-functional-test.md` records the corrected instance's complete
  STATE/DIMMER device sequence, authoritative feedback and final regression.
- `29-hallway-pilot-full-functional-test.md` records the pilot's complete
  STATE/DIMMER/color-temperature sequence, historical-diagnostic baseline and
  exact restoration of its initial live state.
- `30-runtime-mirror-reference-search-pilot.md` records the visible managed
  runtime mirror, explicit private reference index and successful native
  Symcon console-reference queries without changing the action path.
- `31-managed-runtime-mirror-generator.md` records the general SAEF decision,
  idempotent local implementation and offline regression result.
- `32-managed-runtime-mirror-live-activation.md` records the content-only live
  transition from the manual pilot to the deterministic generated mirror,
  direct readback and successful console-reference acceptance.
- `33-current-fileset-and-mirror-activation.md` records the hash-addressed
  current-fileset selection for all four active wrappers and coupled mirror
  reconciliation.
- `34-current-fileset-regular-observation.md` closes the activation with passive
  successful executions of all four wrappers and zero command/error/timeout
  deltas.
- `35-remaining-inventory-and-wave-4-plan.md` reconciles the four-v2/25-legacy
  baseline, records the fresh read-only remaining inventory and selects the
  operationally reachable color-temperature pair for a sequential Wave 4
  package.
- `36-wave-4-offline-package-report.md` records the corrected CL-004/CL-017
  cohort, byte-exact private rollback package, closed mutation gates and full
  offline verification.
- `37-wave-4-live-preflight-report.md` records exact fileset/helper/wrapper
  identities, both candidates' feedback and consumer contracts, and readiness
  of CL-004 for a separately approved first-member activation.
- `38-wave-4-cl004-activation-report.md` records the rollback-safe verifier
  corrections, synchronous two-run activation and complete CL-004 postflight.
- `39-wave-4-cl017-delta-preflight-report.md` confirms CL-004 health, exact
  CL-017 drift freedom and the complete three-link presentation baseline before
  a separately approved second-member activation.
- `40-wave-4-cl017-activation-and-closure-report.md` records the synchronous
  two-run CL-017 activation, exact three-link preservation and completed Wave 4.
- `41-wave-4-functional-test-report.md` records both complete STATE/DIMMER/
  color-temperature sequences, bounded device normalization and exact restoration
  of both initial states.
- `42-autooff-modernization-and-cl026-contract-verification.md` records the
  completed SAEF modernization of all four Auto-Off scripts and the full live
  STATE/DIMMER/timer contract test against the still-legacy `CL-026` instance.
- `43-cl026-offline-package-and-live-preflight.md` records the hash-locked
  candidate and rollback package, fresh read-only dependency/preflight result
  and expected non-commanding DIMMER and color-temperature synchronization.
- `44-cl026-activation-and-regression-report.md` records the two-run v2
  activation, reported synchronization, Auto-Off reaction, corrected event
  verifier and complete seven-v2/22-legacy regression result.
- `45-cl026-functional-and-autooff-test-report.md` records the complete
  STATE/DIMMER/color-temperature sequence, real Auto-Off timer shutdown,
  exact baseline restoration and final all-wrapper regression.
- `46-v0.2-scope-closure.md` closes the rollout with seven fully tested v2
  wrappers and an explicit retain decision for the 22 heterogeneous legacy
  wrappers.
- `47-availability-aware-feedback-classification.md` adds the post-v0.2
  availability contract: command dispatch is never gated by a possibly stale
  indicator, while an unconfirmed command can be classified as
  `device_offline` for diagnostics and caller-specific recovery.
- `tests/control-light/fixtures/installed-contracts.json` is a sanitized
  29-instance regression inventory.
- `private/control-light/migration-manifest.local.json` contains the private
  live mapping and must never be committed.

## Ownership Contract

ControlLight owns:

- local variables identified by `STATE`, `DIMMER`, `COLOR_TEMPERATURE` and
  `COLOR` when the corresponding capability is enabled;
- target and external events below the owning wrapper script;
- its Registry, Statistics and ErrorRingBuffer variables below that script;
- the hidden `LINK_TARGET_PARENT` link object, but not the target selected by
  the user after initial creation.

ControlLight does not own arbitrary sibling objects below the wrapper's parent.
The candidate therefore does not rename the parent and does not hide unrelated
children.

Names, positions and icons of existing visible variables and events are treated
as user presentation. Reconciliation passes `false` for the SAEF presentation
update policy. Functional properties such as type, profile, action, Ident,
parent, trigger and event action remain managed.

## Brightness Semantics Gate

The agreed default for future migrations is `reported`. Every instance still
records exactly one mode before live preflight:

- `reported`: `DIMMER` mirrors retained target brightness even when `STATE` is
  false.
- `effective`: `DIMMER` becomes zero while `STATE` is false without changing
  retained target brightness.

The sanitized fixture marks the seven active v2 instances as `reported`. The
remaining 22 legacy instances stay `pending` and are explicitly retained for
the v0.2 rollout. Each future migration must apply the default or justify an
exception after its consumers have been checked. A pending value is rejected
by the normalized runtime configuration and therefore cannot reach live
execution accidentally.

## Downstream Auto-Off Contract

An Auto-Off consumer must define its dependency by variable role, not by the
upstream ControlLight implementation version:

- `controlID` is the authoritative on/off contract and the only timer-expiry
  action target;
- the control variable is changed through `RequestAction()` and the same
  variable is observed for bounded shutdown confirmation;
- optional `activityIDs`, including DIMMER, may extend the timer only while the
  authoritative control variable is active;
- activity variables are not on/off truth and retained non-zero brightness
  while STATE is false must not arm the timer;
- the consumer must not require an upstream ControlLight Registry, Statistics,
  ErrorRingBuffer or `authoritativeFeedback` configuration in order to operate;
- the upstream wrapper's own migration remains a separate transaction with its
  own preflight, rollback and regression gates.

This contract allowed the modernized Auto-Off automation to operate safely
against the former legacy `CL-026` wrapper and remains valid after its v2
migration.

## Availability Contract

Availability is optional protocol metadata and is not a ControlLight
capability. The Z2M preset resolves the boolean `device_status` target when it
exists; other presets remain unconfigured unless an installation supplies an
explicit target Ident, type and available value.

ControlLight deliberately does not read availability before dispatch. A lamp
that has just regained physical power can still carry a stale offline marker
when a voice, visualization or wall-control command arrives. The permitted
command is sent once and evaluated through the normal bounded authoritative
feedback contract. Successful feedback wins regardless of the prior
availability marker.

Only after confirmation times out does ControlLight read the latest optional
availability value. A still-unavailable target raises a classified
`device_offline` `ControlLightCommandException`; an available or unconfigured
target remains `feedback_timeout`. The bounded error history records failure
class and capability without duplicating device snapshots or creating a new
diagnostics store. Automatic consumers may apply their own bounded recovery
policy to the typed failure, but availability does not change interactive
dispatch semantics.

The outer ControlLight runtime boundary converts a classified command failure
to the structured `command_failed` result after recording diagnostics. It does
not rethrow the expected operational exception through the Symcon action-script
boundary, because that would create an additional uncaught ScriptEngine fatal.
Unexpected configuration and programming failures continue to be logged and
re-thrown.

## Current Live State and Blockers

Seven instances are active on v2 with `reported` brightness semantics: the
hallway pilot, the Wave 2 candidate, both Wave 3 members and CL-004 as the first
Wave 4 member plus CL-017 as its second member, followed by the Auto-Off-dependent
CL-026 instance. They select the same current
hash-addressed fileset as the visible generated runtime mirror. The first four
and both Wave 4 members passed bounded real-device testing for every enabled
capability. CL-026 passed its two-run non-commanding activation and Auto-Off
regression and subsequently completed its full STATE/DIMMER/color-temperature
device sequence plus a real Auto-Off timer shutdown. All seven retain
authoritative feedback and complete enabled-capability device evidence. The other 22 wrappers remain
legacy, and all 29 source identities continue to participate in regression
checks.

All seven v2 wrappers now select the availability-aware post-v0.2 runtime from
one immutable staged fileset. Its activation preserved System.Locals and every
legacy wrapper, updated the non-executable runtime mirror byte-exactly and
passed one non-commanding reconciliation per wrapper with zero command, error,
timeout or local-value deltas. Five targets reported available and two reported
unavailable during the final readback; availability did not gate command-free
activation or reconciliation.

The global load owner supplies the corrected shared wait helper, verified by
Reflection after a clean-process restart. The corrected helper confirmed the
formerly failing same-second STATE feedback without timeout. The isolated
target-module trace classified the preceding anomalous STATE transition as
concurrent multi-controller activity, not fixed target coupling.

Before broader migration waves:

1. every selected instance must record `reported`, or justify `effective` as a
   compatibility exception after checking its consumers;
2. Auto-Off and comparable downstream consumers must use `STATE` for on/off
   semantics; the `CL-026` consumer is now fully modernized and live-tested
   against the explicit version-independent contract above;
3. the Homematic target action mismatch must be resolved or explicitly accepted
   as an inert instance;
4. all offline checks must pass from a clean candidate build;
5. a hash-locked backup and rollback package must be reviewed;
6. the user must approve the specific live migration wave.

The `CL-026` package, preflight and two-run source activation are complete. Its
reported initialization synchronized local DIMMER 0 to retained brightness 28
and local color temperature 2600 K to target 2604 K while STATE remained false
and no device command was issued. Its later bounded real-device capability test
also passed, including a real Auto-Off timer expiry and exact restoration of
STATE, retained brightness, color temperature, timer metadata and internal
Auto-Off state.

The pilot package carries `SAEF_EnsureLink()` in its versioned dependency
closure and loads the ControlLight runtime directly. It therefore needs no
global bootstrap replacement and no service restart.

## Offline Verification

Run:

```console
composer test:control-light-core
composer test:control-light-runtime
composer test:control-light-topology
composer test:control-light-runtime-mirror
```

The tests cover all sanitized installed contracts, both brightness modes,
Matter scaling, conversions, bounded tolerances, immediate/delayed/missing
feedback, semaphore rejection and release, and the absence of optimistic local
state after a timeout. The topology test proves idempotency, presentation
preservation, explicit event binding and non-interference with foreign siblings.
The runtime-mirror test additionally proves deterministic reference generation,
byte-exact payload embedding, no-op reconciliation, presentation preservation,
ownership rejection and exact rollback.
