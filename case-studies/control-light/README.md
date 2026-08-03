# ControlLight Modernization

**Status:** Twenty-six active v2 instances; twenty fully device-tested; three legacy contracts retained; one obsolete template retired

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
- `candidate/HueWallSwitchCore.php` and
  `candidate/HueWallSwitchRuntime.php` define the offline shared Hue input
  adapter for the dedicated CL-005/CL-012 migration cohort.
- `candidate/HueWallSwitchWrapper.php` documents its private-overlay contract
  without installation-specific IDs.
- `candidate/ManualOnPulseOffCore.php` and
  `candidate/ManualOnPulseOffRuntime.php` define the asymmetric adapter for a
  manually activated lamp whose remote off operation is one bounded supply
  pulse.
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
- `48-cl014-homematic-activation-and-functional-test.md` records the
  post-release state-only Homematic migration, corrected target-root
  classification, two-run non-commanding activation, bounded off-on-off device
  test and exact restoration of the initial state.
- `49-autooff-control-facade-alignment.md` records the four-consumer review,
  migration of two direct device references to tested local ControlLight
  facades, ownership-checked event cleanup and non-commanding postflight.
- `50-cl021-four-capability-activation.md` records the hash-locked,
  non-commanding four-capability activation, two-run idempotency proof,
  presentation and consumer preservation, and the still-separate device-test
  gate.
- `51-cl021-functional-test-and-color-finding.md` records the successful
  STATE, brightness and color-temperature checks, the authoritative color
  normalization timeout, bounded compensation and exact restoration.
- `52-cl021-color-contract-analysis.md` identifies the target module's lossy
  RGB-to-xy-plus-brightness projection as the incompatible color boundary and
  rejects a global weakening of authoritative confirmation.
- `53-cl021-color-capability-disable.md` records the instance-specific,
  non-commanding color disable, preserved target and presentation ownership,
  two-run idempotency and complete wrapper regression.
- `54-z2m-v6-readiness-and-migration-plan.md` records the read-only,
  installation-wide v6 dependency baseline, automatic extension-update
  boundary, bounded maintenance quiescence set and gated backup, rollout,
  rollback and regression plan.
- `55-z2m-v6-backup-and-maintenance-package.md` records the completed remote
  full backup, byte-exact restoration of the normal backup configuration,
  expected runtime-directory exclusions and the drift-sensitive event
  quiescence and restoration package.
- `56-z2m-v6-final-drift-preflight.md` records the drift-free post-backup
  baseline, unchanged v6 source candidate, repeated maintenance-package
  preflight and the fail-closed Store-release binding required before mutation.
- `57-hue-wall-integration-review.md` distinguishes intentional multi-consumer
  feedback events from inactive duplicate artifacts and defines CL-005,
  CL-012 and their shared Hue Wall Switch handler as one migration cohort.
- `58-hue-wall-offline-adapter-and-test-plan.md` implements the offline adapter,
  proves update-trigger, ownership, idempotency, feedback, failure and
  serialization contracts and defines the still-closed live activation matrix.
- `59-hue-wall-live-preflight-and-offline-package.md` records the fresh
  drift-free cohort preflight, exact private rollback package, new immutable
  fileset identity and deferred cleanup policy for two verifiably orphaned
  inactive events.
- `60-hue-wall-inactive-fileset-staging.md` records the hash-verified inactive
  staging, unchanged global runtime selection and live sources, and retention
  of private candidates in the local bounded activation overlay.
- `61-cl005-delta-preflight.md` explains the private-overlay activation path
  and records the drift-free staged fileset, 29-wrapper baseline, complete
  CL-005 presentation/dependency contract and expected command-free first
  cohort activation.
- `62-cl005-activation-and-idempotency.md` records the byte-exact first cohort
  wrapper activation, two command-free reconciliations, intentional STATE
  update-trigger transition and complete mixed-baseline regression.
- `63-cl012-activation-and-idempotency.md` records the drift-free second cohort
  wrapper activation, three-capability preservation, two command-free
  reconciliations and complete two-candidate mixed-baseline regression.
- `64-hue-wall-handler-activation.md` records the quiescent shared-handler
  activation, four in-place event transitions, command-free two-run
  idempotency and preservation of deferred legacy cleanup candidates.
- `65-hue-wall-concurrency-fix-and-physical-regression.md` records the
  source/target debounce correction, bounded target serialization, swapped
  switch-S assignment and successful normal, external-control and concurrent
  physical regression while retaining the separate offline/recovery gate.
- `66-hue-cohort-full-capability-functional-test.md` records the complete
  brightness and color-temperature matrices, device normalization, exact
  restoration and the operational decision that a manufactured live outage is
  neither applicable nor justified for the permanently powered targets.
- `118-hue-wall-observation-and-legacy-cleanup.md` closes the regular Hue Wall
  follow-up with zero runtime failures, corrects a proven parallel diagnostic
  counter loss, removes two inactive events and eight obsolete variables
  ownership-exactly, and records the separately deferred shared-helper
  activation after deployment-retention capacity was reached.
- `67-cl010-readiness-and-offline-package.md` records the read-only CL-010
  single-device preflight, verified private candidate/rollback package and
  closed activation gate, and corrects CL-008 to a separately gated Z2M group.
- `68-cl010-activation-and-idempotency.md` records the fail-closed verifier
  correction, exact CL-010 source activation, two command-free reconciliations
  and complete 29-wrapper regression before a separate device-test gate.
- `69-cl010-full-capability-functional-test.md` records the complete STATE and
  brightness device sequence, reported-brightness proof, bounded device
  normalization and exact restoration of CL-010's initial state.
- `70-cl008-member-confirmed-group-contract.md` defines the strict
  member-confirmed group authority, any-member-on facade state, reported group
  brightness, shared-deadline confirmation and partial-failure diagnostics
  and records the subsequently closed membership and reporting gates.
- `71-cl008-membership-and-dependency-preflight.md` records the authoritative
  two-member inventory, complete member-variable mapping and the newly exposed
  requirement to migrate Auto-Off atomically from two member controls to one
  CL-008 facade control.
- `72-cl008-offline-runtime-and-atomic-package.md` records the explicit
  member-confirmed runtime, shared-deadline confirmation, owned event topology,
  typed group failures and the verified atomic CL-008/Auto-Off candidate and
  exact rollback package. No live source or device was changed.
- `73-cl008-live-delta-preflight.md` records the command-free live drift
  comparison for both source baselines, member/action mappings, Auto-Off and
  foreign-event topology and the still-absent immutable candidate fileset.
- `74-cl008-inactive-fileset-staging.md` records restricted-channel transfer,
  server-side preflight, byte-exact live fileset readback and proof that no
  runtime, source, topology or light value selected the staged candidate.
- `75-cl008-post-staging-membership-requery.md` records the transaction-matched
  metadata response proving that group ID 1 still contains exactly the two
  package-bound IEEE members immediately after staging.
- `76-cl008-atomic-activation-and-idempotency.md` records the atomic CL-008 and
  Auto-Off source activation, two verifier-driven recoveries, corrected event
  assertions, two-run idempotency and the complete 29-wrapper postflight.
- `77-cl008-group-functional-test.md` records the complete two-member
  STATE/brightness matrix, reported brightness retention, bounded device
  normalization, one user-confirmed external voice command and exact
  restoration.
- `78-cl008-autooff-expiry-and-mirror-state-alignment.md` records the corrected
  Spiegel STATE control contract, two-run event reconciliation and successful
  real CL-008 Auto-Off timer expiry without changing the already-off Spiegel
  state or its retained brightness.
- `79-cl007-spiegel-atomic-migration-and-functional-test.md` records the atomic
  migration, complete capability matrix and real Auto-Off expiry for the
  Spiegel facade.
- `80-alexa-reported-brightness-consumer-alignment.md` records the non-commanding
  correction of two active reported-brightness voice-assistant consumers from
  brightness-only to separate facade power and brightness contracts.
- `81-echo-remote-alexa-functional-test.md` records their supervised Alexa text
  command matrices, authoritative feedback, exact restoration and the finding
  that remote-module acceptance alone does not prove downstream dispatch.
- `82-cl024-readiness-and-atomic-package.md` selects the available second
  brightness-only Alexa legacy consumer, records its reported-brightness
  initialization delta and closes all live gates around the verified private
  wrapper/Alexa rollback package.
- `83-cl024-atomic-activation.md` records the protected rollback, corrected
  waiting execution channel, command-free reported-brightness initialization,
  in-place Alexa alignment and complete 29-wrapper activation postflight.
- `84-cl024-direct-test-and-alexa-dispatch-finding.md` records the complete
  direct device test, exact restoration and two Alexa requests correctly
  blocked by the active alarm contract before device dispatch.
- `85-cl001-away-safe-activation.md` records Galerie's command-free activation,
  preserved inverse alarm and Alexa contracts, two-run idempotency and the
  deliberately deferred presence-bound device test.
- `86-cl002-external-trigger-readiness.md` records Eingang's two-channel
  Homematic on/off input contract, active-alarm behavior and verified inactive
  command-free migration package.
- `87-cl002-command-free-activation.md` records Eingang's command-free
  activation, preserved two-channel on/off inputs and alarm contract, two-run
  idempotency and complete 29-wrapper regression.
- `88-cl015-group-membership-and-offline-contract.md` records Kuschelsofa's
  authoritative three-member group inventory, permanent-power assumption,
  member-confirmed STATE/brightness/color-temperature contract, preserved
  alarm-aware inputs and verified inactive candidate.
- `89-cl015-inactive-fileset-staging.md` records the restricted-channel
  transfer, server-side and independent hash verification, unchanged wrapper
  and light values, and the successful post-interruption status check.
- `90-cl015-command-free-activation.md` records the protected stopped attempt,
  corrected direct source activation, stable three-member event topology,
  preserved Alexa facade consumer and complete 18-v2/11-legacy postflight.
- `91-cl011-group-readiness-and-shutdown-dependency-finding.md` records
  Grillbereich's authoritative three-member inventory, missing non-STATE
  reporting evidence, intentional per-member random-color owner, shutdown
  consumer defects and the newly exposed CL-015 member-action bypass.
- `92-cl015-shutdown-consumer-handoff.md` records the non-executing hand-off
  from three member-off actions to one CL-015 facade action and the subsequent
  discovery of a second member-bypassing welcome-lighting consumer.
- `93-cl015-welcome-consumer-handoff-and-ownership-closure.md` records the
  welcome automation's STATE/DIMMER facade migration, protected stopped
  pre-write attempt and installation-wide proof that no foreign consumer
  retains a Kuschelsofa member reference.
- `94-cl013-cl028-munich-complete-migration.md` records the complete Wannenbad
  and Wolke migrations, bounded per-instance temperature normalization,
  direct and Alexa tests, exact restoration, Wolke's atomic Alexa hand-off and
  the current runtime-mirror reconciliation.
- `95-cl009-regalspot-complete-migration.md` records the complete Regalspot
  migration with its 0–255 brightness scale, facade-owned Auto-Off hand-off,
  capability-correct Alexa row, direct and voice tests, and preserved scene
  and device-warning consumers.
- `96-cl020-home-assistant-entity-action-contract-blocker.md` records the
  fail-closed Stehlampe Max attempt, contradictory target-module action result,
  exact device and wrapper rollback, module-wide scope and separate repair
  gate.
- `97-home-assistant-entity-fix-and-cl020-color-finding.md` records the targeted
  no-restart module repair, thirteen-instance regression, truthful STATE
  contract, successful CL-020 STATE/brightness/Kelvin matrix and the remaining
  lossy-color blocker with preserved Alexa and scene consumers.
- `98-color-chromaticity-conversion-analysis.md` separates the shared
  chromaticity/intensity semantics from the distinct Matter/HA HS-normalization
  and Z2M xy-plus-brightness boundaries and adds a reproducible offline matrix
  without changing the production runtime.
- `99-hs-native-feedback-matcher.md` implements the bounded native-HS
  confirmation rule, retains exact RGB matching for all other formats and
  records the still-closed CL-020-only live activation gate.
- `100-cl020-hs-native-preflight-and-package.md` records the fresh drift-free
  read-only CL-020 gate, preserved consumers and module repair, hash-bound local
  candidate/rollback package and still-closed inactive-fileset staging gate.
- `101-cl020-hs-native-inactive-fileset-staging.md` records the corrected
  equal-length directory token, restricted-channel staging, complete
  independent file readback and proof that runtime selection, wrapper,
  consumers, topology and light values remained unchanged.
- `102-cl020-hs-native-activation.md` records the drift-free wrapper
  activation, two zero-command reconciliations, preserved event and consumer
  contracts, unchanged runtime mirror and the 22-v2/seven-legacy baseline
  before the separate device-test gate.
- `103-cl020-hs-native-functional-test-and-rollback.md` records the successful
  direct and Alexa on-state capability matrix, the independent
  brightness-preservation proof, the off-state color normalization timeout and
  the fail-closed return to the exact legacy wrapper.
- `104-off-state-color-transition-candidate.md` defines and implements the
  inactive one-command `target-turns-on` contract with a shared COLOR/STATE
  confirmation deadline and transition-only HS tolerances.
- `105-cl020-off-state-color-preflight-and-package.md` records the fresh
  drift-free read-only live gate, exact legacy and consumer baseline, and the
  new hash-bound package while keeping fileset staging closed.
- `106-cl020-off-state-color-inactive-fileset-staging.md` records the
  fail-closed local token correction, restricted-channel staging, independent
  nineteen-file readback and unchanged runtime, consumers and light values.
- `107-cl020-off-state-color-activation.md` records the fresh drift gate,
  byte-exact wrapper activation, two zero-command reconciliations and complete
  22-v2/seven-legacy structural postflight before the separate device test.
- `108-cl020-off-state-color-functional-test.md` records the successful direct
  and Alexa off-state COLOR-to-STATE transition, independent brightness
  preservation, exact initial-state restoration and clean diagnostic
  postflight.
- `109-cl006-dummy-retirement.md` records the reference-free retirement of the
  obsolete copy template and the resulting 28-instance inventory.
- `110-cl011-random-lighting-retirement-and-activation.md` records removal of
  the dedicated random-lighting owner and command-free activation of the
  three-member Grillbereich group with color deliberately disabled.
- `111-home-assistant-entity-upstream-handoff.md` records the minimal upstream
  property-contract repair, executable regression and prepared patch handoff.
- `112-z2m-v6-refresh-and-color-gate.md` records the refreshed unchanged V6
  candidate and installed Store-Beta boundary, keeping all update and color
  activation gates closed.
- `113-cl011-shutdown-consumer-handoff.md` records the explicitly advanced,
  non-executing migration of both Grillbereich shutdown consumers from member
  actions to the CL-011 facade and the remaining presence-bound functional
  gate.
- `114-cl030-manual-on-pulse-off-preflight.md` defines and verifies the
  asymmetric manual-on, bounded-pulse-off contract while keeping every live
  mutation gate closed.
- `115-cl030-command-free-activation-and-consumer-handoff.md` records the
  rollback-safe, zero-command activation, exact consumer handoff and remaining
  real-pulse test gate.
- `116-cl030-functional-test-and-race-hardening.md` records the direct pulse
  test, corrected site-specific alarm binding, device-side power-delta
  feedback, bounded race-window hardening and successful immediate Alexa-off
  regression.
- `117-cl030-legacy-ha-exporter-retirement.md` records the byte-exact rollback
  capture, fail-closed retirement of the former exporter owner, preserved Home
  Assistant identity and deliberately deferred deletion of all legacy objects.
- `119-cl016-lowboard-command-free-migration.md` records the atomic Lowboard
  wrapper and consumer handoff, complete ten-scene/eight-target migration,
  state-only off scene and independent zero-command postflight.
- `120-cl016-functional-test-and-color-finding.md` records the direct STATE,
  normalized-brightness and Kelvin passes, the reproducible Z2M
  color-plus-brightness timeout, exact final restoration and the recommended
  command-free color-disable gate.
- `121-cl016-color-capability-disable.md` records the rollback-safe no-color
  activation, exact Alexa color-binding removal, two command-free reconcile
  runs and independent preservation checks.
- `122-cl003-kuerbis-hard-power-readiness.md` records the physical mains-switch
  contract, fresh reachability and dependency inventory, and the verified
  offline package with separate activation and hard-cycle gates.
- `123-cl003-kuerbis-command-free-activation.md` records the zero-command v2
  activation, availability-guarded shutdown handoff, two-run reconciliation
  and remaining capability and physical-switch tests.
- `124-cl003-state-brightness-functional-test.md` records the corrected
  hard-power preflight, authoritative STATE and brightness passes, target
  normalization and exact tested-domain restoration.
- `125-cl003-color-test-and-temperature-stop.md` records the successful forward
  color request, reproduced Z2M color/brightness restoration timeout, safe
  brightness restoration and fail-closed stop before the Kelvin test.
- `126-cl003-color-capability-disable.md` records the command-free no-color
  activation, two-run idempotency, preserved native target and observer, and
  unchanged device-command counter.
- `127-cl003-kelvin-functional-test.md` records the isolated exact 4000 K
  authoritative-feedback pass, preserved STATE/brightness and unchanged error
  and timeout counters.
- `128-cl003-hard-cycle-and-kelvin-normalization-finding.md` records the passed
  physical reconnection and immediate-command contract, stale availability
  evidence, exact final restoration and the open mired-aware matcher finding.
- `129-mired-aware-kelvin-feedback-matcher.md` records the shared offline
  Z2M-only integer-Mired comparison contract, complete 2000–6500 K matrix and
  unchanged Matter behavior.
- `../mqtt-discovery-exporter/36-client-subscription-coverage-and-runtime-namespace-report.md`
  records the later CL-030 MQTT namespace correction and complete Home
  Assistant/Apple Home functional closure.
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

The sanitized fixture marks the twenty-five currently active v2 instances as
`reported`. The remaining four legacy instances stay `pending`. The historical
v0.2 rollout closed at seven v2 and 22 explicit retains; the later state-only
Homematic migration advances the current installation baseline without
rewriting that release decision. Each future migration must apply the default
or justify an exception after its consumers have been checked. A pending value
is rejected by the normalized runtime configuration and therefore cannot reach
live execution accidentally.

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

## Voice-Assistant Consumer Gate

Voice-assistant integrations are downstream consumers and participate in every
ControlLight migration preflight. For `reported` brightness semantics they
must bind power to the local `STATE` facade and brightness to the local
`DIMMER` facade. A brightness-only device contract cannot infer power because
retained non-zero brightness while off is valid.

The current targeted live audit aligned CL-008 and CL-025 with that contract;
CL-007 was already aligned. CL-024 and CL-028 subsequently migrated atomically
with their wrappers, while CL-013 retained its already-correct expert-light
mapping. No brightness-only ControlLight Alexa migration remains pending. The
sanitized fixture keeps the aligned set explicit. Alexa device identities and
user-facing names are presentation state and must be preserved during an
in-place contract migration.

CL-008, CL-013, CL-025 and CL-028 subsequently passed Alexa power and
brightness commands through the installed Echo remote text-command path.
CL-013 also proved delivery of an Alexa color-temperature intent, while
retaining strict failure for the assistant's out-of-device-range cold-white
request. The test requires
authoritative feedback and command-counter evidence because a successful
remote-module return does not itself prove that Alexa dispatched a smart-home
directive. Microphone and speech-recognition behavior remain outside this
integration gate.

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

Twelve instances are active on v2 with `reported` brightness semantics: the
hallway pilot, the Wave 2 candidate, both Wave 3 members and CL-004 as the first
Wave 4 member plus CL-017 as its second member, followed by the Auto-Off-dependent
CL-026 instance, the later state-only Homematic `CL-014` migration and the
three-capability `CL-021` contract, `CL-005` and `CL-012` as the activated
facades of the dedicated Hue Wall cohort, and the separately activated
STATE/brightness-only `CL-010` contract. The Hue cohort and CL-010 select a separately staged
adapter-capable immutable fileset; their shared Hue handler selects the
concurrency-corrected successor privately. Its normal, external-control and
parallel physical regression and complete enabled-capability matrices passed.
Both targets are permanently powered, so a manufactured live outage is not an
applicable remaining gate.
The first four and both Wave 4 members passed bounded real-device testing for
every enabled capability. CL-026 passed its two-run non-commanding activation
and Auto-Off regression and subsequently completed its full
STATE/DIMMER/color-temperature device sequence plus a real Auto-Off timer
shutdown. CL-014 completed its state-only off-on-off sequence with exact
restoration. These eight retain authoritative feedback and complete
enabled-capability device evidence.
CL-021 passed non-commanding activation, full structural regression and the
STATE, brightness and color-temperature device paths. Its color path exposed
an incompatible target contract: the target module projects RGB through
CIE-xy plus derived brightness and cannot provide exact integer color
round-trip semantics. Compensation restored all four initial values exactly.
Its color capability is now explicitly disabled: the existing local color
variable is hidden and non-actionable, its feedback event is inactive, and
the native target variable plus user-owned presentation link are preserved.
Both Hue-cohort facades and their shared handler passed two-run command-free
activation. The handler reused all four active events and now routes physical
input through the local ControlLight STATE facades. Its corrected per-source/
target debounce permits simultaneous different-target operation, while bounded
target serialization preserves rapid same-target commands. The normal physical
matrix, external-control interaction, switch-S assignment and concurrency
checks passed without a failure, timeout or dropped valid action. Offline/
recovery behavior remains covered by executable failure regressions rather than
a disruptive shared-infrastructure simulation. Both facades subsequently
passed their complete enabled-capability matrices with exact restoration and
now advance the fully-device-tested count to ten. CL-010 subsequently passed
its complete STATE and brightness sequence, directly verified retained
reported brightness while off and restored its initial on/100% state exactly.
This advances the fully-device-tested count to eleven. CL-008 then completed
its member-confirmed group and atomic Auto-Off activation, followed by its full
two-member STATE/brightness matrix and exact restoration. Its real Auto-Off
expiry then passed after aligning the already-off Spiegel consumer with STATE
semantics while retaining brightness as an activity signal. This advances the
fully-device-tested count to twelve with no remaining CL-008 functional gate.
CL-007 then migrated its Spiegel target and Auto-Off dependency atomically,
passed STATE, brightness, color-temperature and real Auto-Off tests, and
advanced the current inventory to fourteen active wrappers—thirteen fully
device-tested—and 15 legacy wrappers. All 29 source identities continue to
participate in regression checks.

CL-024 subsequently activated with an atomic Alexa consumer migration and
command-free reported-brightness initialization. Its complete direct STATE and
brightness sequence passed with exact restoration, advancing the current
inventory to fifteen active wrappers—fourteen fully device-tested—and 14 legacy
wrappers. Two Echo Remote text commands reached ControlLight and were correctly
blocked by the active alarm contract before device dispatch. The direct
`Action` path remains deliberately permitted under that condition.

CL-001 Galerie then activated while the installation owner was away. The
inverse alarm contract remained active, both reconciliation runs completed
without a command or value change, and the existing Alexa expert-light mapping
remained unchanged. The current inventory is sixteen active wrappers—fourteen
fully device-tested—and 13 legacy wrappers. Galerie's real capability matrix is
deferred until the owner is present because the explicit `Action` test path
deliberately bypasses the alarm gate.

CL-002 Eingang then activated through two command-free reconciliation runs.
Both Homematic short-press event identities and their alarm-aware on/off
mappings remained unchanged. The current inventory is seventeen active
wrappers—fourteen fully device-tested—and 12 legacy wrappers. Its physical
wall-control and facade tests remain presence-bound.

CL-015 Kuschelsofa then activated as a member-confirmed three-light group and
closed both direct-member consumer bypasses, advancing the structural baseline
to eighteen v2 wrappers. CL-013 Wannenbad and CL-028 Wolke subsequently
completed their full direct capability matrices and Alexa consumer checks.
Wolke's brightness-only Alexa row migrated in place to STATE plus brightness,
and Wannenbad established the bounded temperature-normalization contract. The
subsequent CL-009 Regalspot migration completed its direct STATE, 0–255
brightness and Kelvin matrix, moved Auto-Off and Alexa onto the facade
contract, and preserved both the room scene and device-warning observer. The
baseline before the HS-native correction was twenty-one active
wrappers—seventeen fully device-tested—and eight retained legacy wrappers. A
subsequent CL-020 Stehlampe Max
candidate passed command-free activation but was restored to legacy after its
Home Assistant Entity target returned action failure despite asynchronously
dispatching the command. The shared action contract was subsequently repaired
through a targeted module reload, but CL-020 was again restored to legacy after
its color projection failed authoritative equality. Its existing Alexa and
scene color consumers remained preserved. The later native-HS matcher and
rollback-backed activation advanced CL-020 command-free to v2. Its direct and
Alexa on-state matrix subsequently passed, including brightness independence
during color changes. An off-state color request nevertheless powered the
target on and normalized native HS feedback outside the deliberately narrow
confirmation tolerance. The wrapper was therefore restored byte-exactly to
legacy. After the state-aware one-command transition was implemented,
regression-tested, packaged and staged, a new command-free activation passed
twice. The separately approved direct and Alexa off-state color transitions
then passed with exact brightness preservation, authoritative STATE=true
feedback, zero errors or timeouts and exact initial-state restoration. The
current baseline is consequently twenty-two v2 wrappers, eighteen fully
device-tested wrappers and seven legacy wrappers. The scene consumer remains
structurally verified but unexecuted because it controls additional devices.

The obsolete CL-006 copy template was subsequently retired after a
reference-free preflight and independent absence check. Grillbereich's
dedicated random-lighting owner was also retired, after first removing its
owned presentation links and eliminating only the corresponding shutdown
delay. CL-011 then activated command-free as a member-confirmed three-light
group with STATE, reported brightness and color temperature. Its color
capability remains disabled pending the Zigbee2MQTT V6 module contract. No
device action was performed while presence was unconfirmed. By explicit owner
decision, both shutdown consumers were subsequently aligned command-free with
the CL-011 STATE facade; their real shutdown effect remains presence-bound.
The current structural baseline after the later CL-003 Kürbis activation is
therefore 26 v2 wrappers, 20 fully device-tested wrappers and three retained legacy wrappers across
29 tracked ControlLight instances. CL-030 passed its direct off-pulse and
manual-on/immediate-Alexa-off sequences with one physical pulse per effective
shutdown, restored supply and authoritative power feedback.

The eight pre-existing Z2M v2 wrappers select the availability-aware post-v0.2
runtime from one immutable staged fileset. Activation of that fileset for the
seven pre-existing Z2M wrappers preserved System.Locals and every then-legacy
wrapper, updated the non-executable runtime mirror byte-exactly and passed one
non-commanding reconciliation per wrapper with zero command, error, timeout or
local-value deltas. Five targets reported available and two reported
unavailable during that readback; availability did not gate command-free
activation or reconciliation. CL-021 later selected the unchanged fileset and
reported available during its own two-run activation. The Homematic state-only
wrapper also selects this runtime, while its preset deliberately has no
implicit availability variable.

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
3. voice-assistant consumers of a `reported` facade must use separate local
   `STATE` power and `DIMMER` brightness controllers; brightness-only legacy
   mappings must be migrated or explicitly deferred with their wrapper;
4. protocol-specific target-root and target-Ident differences must be resolved
   explicitly without broadening preset assumptions for unrelated devices;
5. all offline checks must pass from a clean candidate build;
6. a hash-locked backup and rollback package must be reviewed;
7. the user must approve the specific live migration wave.

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
composer test:control-light-color-analysis
composer test:control-light-runtime
composer test:manual-on-pulse-off
composer test:control-light-group-runtime
composer test:control-light-topology
composer test:control-light-runtime-mirror
composer test:hue-wall-core
composer test:hue-wall-runtime
composer test:hue-wall-topology
```

The tests cover all sanitized installed contracts, both brightness modes,
Matter scaling, conversions, bounded tolerances, immediate/delayed/missing
feedback, semaphore rejection and release, and the absence of optimistic local
state after a timeout. The color-analysis matrix additionally distinguishes
native HS normalization from the incompatible Z2M xy-plus-brightness
projection, including circular hue and achromatic edge behavior. The topology
test proves idempotency, presentation preservation, explicit event binding and
non-interference with foreign siblings.
The group-runtime suite additionally proves one command for multiple members,
one shared deadline, freshness-sensitive equality, bounded brightness
tolerance, passive any-member-on projection and distinct partial, stale,
offline, endpoint and projection failures without optimistic facade writes.
The runtime-mirror test additionally proves deterministic reference generation,
byte-exact payload embedding, no-op reconciliation, presentation preservation,
ownership rejection and exact rollback.
The Hue Wall tests separately prove repeated identical physical action updates,
bounded burst suppression, serialized facade toggles, non-optimistic failure
handling and in-place event reconciliation without touching unidentified or
foreign events.
The manual-on/pulse-off suite additionally proves the asymmetric remote
contract, command-free manual-on rejection, idempotent stable-off handling and
the bounded manual-on/voice-off race with exactly one restored supply pulse.
