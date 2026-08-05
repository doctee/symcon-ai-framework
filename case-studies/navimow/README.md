# Navimow Native IP-Symcon Module Case Study

**Status:** Private pilot `pilot-0.1.0.4`; receive-only MQTT shadow lifecycle
published; the first native-diagnostic 72-hour pilot stopped after two
recovered transport episodes and completed immediate plus delayed
credential-free cleanup
**Scope:** Native IP-Symcon module for Segway Navimow robotic mowers
**Implementation state:** The canonical case-study distribution implements
OAuth, discovery, adaptive read-only status polling and bounded Dock, Pause and
Resume commands. The optional receive-only MQTT Receiver, symmetric Account
pairing, private shadow ingestion and targeted REST confirmation are published
and installed on standalone `main`, but remain disabled by default and
credential-free after testing. REST remains the only source that updates
public Device variables.

## Purpose

This case study documents the first SAEF-guided development of a native
IP-Symcon module that integrates Segway Navimow robotic mowers.

It records analysis, architecture decisions, fixtures, implementation gates,
test evidence, risks and follow-up questions. It is not a reusable module
template or a general SAEF reference implementation.

## Case Study Files

| File | Purpose |
| --- | --- |
| `01-requirements.md` | Initial requirements and source analysis based on `TA2k/ioBroker.navimow`. |
| `02-module-design.md` | Pre-implementation module structure, ownership, state and diagnostics design. |
| `03-variable-and-action-contract.md` | MVP public variable, profile, action, archive and payload mapping contract. |
| `04-implementation-plan.md` | Ordered work packages, readiness gates and verification plan before PHP module code. |
| `05-fixture-plan.md` | Fixture collection, sanitization, review and completeness plan. |
| `06-structure-discovery-plan.md` | Static payload structure discovery and real API capture timing. |
| `07-private-capture-checklist.md` | Manual private API capture checklist and safe return format. |
| `08-fixture-validation-report.md` | First sanitized REST fixture validation and contract impact. |
| `09-rest-mvp-readiness-review.md` | Go/No-Go review for REST MVP implementation planning. |
| `10-module-scaffold-plan.md` | Concrete REST MVP module scaffold, communication and test plan. |
| `11-implementation-start-decision.md` | Conditional Go decision for the first REST MVP module scaffold. |
| `12-rest-mvp-scaffold.md` | First REST MVP module scaffold and fixture-based mapper verification. |
| `13-metadata-and-loader-validation.md` | Static module metadata review and direct Symcon loader smoke-test scope. |
| `14-symcon-loader-test-report.md` | Loader smoke-test report with local preflight and pending direct Symcon checks. |
| `15-loader-fix-report.md` | Direct loader finding, dedicated distribution decision and retest gate. |
| `16-auth-and-readonly-rest-plan.md` | OAuth, token refresh, discovery and read-only REST implementation plan. |
| `17-rest-client-and-auth-implementation.md` | Tested REST transport and account authentication implementation boundary. |
| `18-auth-symcon-test-report.md` | Official schema, direct Symcon OAuth, persistence and token-refresh test report. |
| `19-discovery-and-readonly-status-implementation.md` | Account discovery, dynamic configurator and read-only device status implementation. |
| `20-discovery-and-status-symcon-test-report.md` | Live discovery, configurator, device status and polling verification report. |
| `21-command-implementation-readiness.md` | Conditional Go decision and safety gates for a Dock-first command implementation. |
| `22-dock-command-implementation.md` | Dock-only command transport, diagnostics, verification and local test evidence. |
| `23-dock-command-symcon-test-report.md` | Supervised live Dock command and delayed status-verification report. |
| `24-command-transition-evidence-plan.md` | Safety, capture and fixture plan for one real Running-to-Docked transition. |
| `25-command-transition-capture-procedure.md` | Executable private terminal procedure for one supervised Dock transition capture. |
| `26-command-transition-capture-report.md` | Successful Dock transition, sanitized SUCCESS fixture and verification-timing finding. |
| `27-dock-transition-verification-design.md` | Long-running read-only Dock verification state-machine design. |
| `28-dock-transition-verification-implementation.md` | Timer-driven long-running Dock verification implementation and local checks. |
| `29-dock-transition-verification-symcon-test-report.md` | Published update and already-docked Symcon retest after long-running verification change. |
| `30-dock-transition-verification-live-test.md` | Supervised Running-to-Docked Symcon live transition with final Verified result. |
| `31-rest-mvp-stabilization-and-release-check.md` | REST MVP release boundary, blockers and private-pilot readiness decision. |
| `32-private-pilot-release-preparation.md` | Private-pilot README, release hygiene and version/tag policy preparation. |
| `33-release-metadata-and-tag-plan.md` | Pilot version metadata, build/date and tag naming decision. |
| `34-pilot-readme-publication-and-tag.md` | Private-pilot README module commit and annotated pilot tag preparation. |
| `35-pilot-publication-verification.md` | Remote branch, annotated pilot tag, published content and metadata verification. |
| `36-case-study-mainline-consolidation.md` | Consolidated private-pilot evidence, implementation, fixtures and release documentation checkpoint. |
| `37-private-pilot-observation-plan.md` | Bounded deterministic and supervised pilot plan for timeout, restart, cloud, token and duplicate-command evidence. |
| `38-pilot-observation-harness-design.md` | Non-actuating CLI harness, fake time, scripted transport and restart-reconstruction design. |
| `39-pilot-observation-harness-implementation.md` | Deterministic harness implementation with timeout, read-cadence and token-recovery findings. |
| `40-pilot-recovery-hardening-design.md` | Deadline, WaitingRead cadence and bounded token-refresh recovery design. |
| `41-pilot-recovery-hardening-implementation.md` | Implemented recovery hardening with 16 green deterministic harness cases. |
| `42-pilot-recovery-hardening-publication.md` | Published and remotely verified recovery hardening commit. |
| `43-pilot-recovery-hardening-symcon-smoke-test-report.md` | Direct read-only Symcon update, auth, status and command-invariance verification. |
| `44-pilot-restart-observation-live-test.md` | Supervised transition attempt safely completed before restart; normal path passed, restart evidence pending. |
| `45-pilot-restart-observation-live-retest.md` | Successful supervised restart during Docking with automatic final verification. |
| `46-private-pilot-observation-status-review.md` | Consolidated `OBS-01` through `OBS-05` evidence and remaining release gate. |
| `47-passive-token-refresh-observation.md` | Passive scheduled token refresh and continued polling verification. |
| `48-private-pilot-release-review-and-tag-decision.md` | Conditional GO for a documented recovery-hardened second pilot tag. |
| `49-pilot-readme-refresh-and-second-tag-publication.md` | Published recovery-hardened README and immutable `pilot-0.1.0.2` tag. |
| `50-second-pilot-case-study-consolidation.md` | Consolidated hardening, observation evidence, harness and second pilot publication checkpoint. |
| `51-post-pilot-roadmap-decision.md` | Prioritized public OAuth feasibility before Store, MQTT/WSS or command expansion. |
| `52-public-oauth-and-release-feasibility-analysis.md` | Classified public OAuth as vendor-blocked while preserving controlled private-pilot use. |
| `53-navimow-oauth-vendor-clarification-plan.md` | Prepared an evidence-safe vendor inquiry, contact order and response classification without sending it. |
| `54-navimow-oauth-vendor-inquiry-execution.md` | Published and verified the credential-free vendor clarification as NavimowHA issue 82. |
| `55-command-integration-sequence-and-safety-plan.md` | Sequenced Pause, Resume, conditional Stop and Start through independent safety gates. |
| `56-pause-command-evidence-and-readiness-plan.md` | Defined a two-read Running gate and conditional one-shot supervised Pause capture. |
| `57-pause-command-private-capture-procedure.md` | Implemented and statically validated the private one-shot Pause capture procedure. |
| `58-pause-command-private-capture-report.md` | Verified one private Running-to-Paused transition and retained the productive implementation gate. |
| `59-pause-command-fixture-validation-and-implementation-readiness.md` | Promoted minimal Pause fixtures and issued a conditional implementation GO. |
| `60-pause-command-implementation.md` | Implemented bounded Pause with a fresh Running gate and restart-safe read-only verification. |
| `61-pause-command-publication-and-symcon-test-plan.md` | Gated publication, variable/archive compatibility checks and one supervised Symcon Pause test. |
| `62-pause-command-publication.md` | Published and remotely verified the bounded Pause implementation without creating a tag. |
| `63-pause-command-symcon-test-report.md` | Verified published Pause, variable/archive stability, physical stop and official-app cleanup in Symcon. |
| `64-pause-integration-review-and-resume-readiness.md` | Closed Pause evidence, deferred a new tag and conditionally opened Resume evidence planning. |
| `65-resume-command-evidence-and-readiness-plan.md` | Revalidated Resume support and conditionally approved an isolated one-shot capture procedure. |
| `66-resume-command-private-capture-procedure.md` | Implemented and statically validated the isolated one-shot Resume capture tool. |
| `67-resume-command-private-capture-report.md` | Verified one private Paused-to-Running Resume transition and official-app cleanup. |
| `68-resume-command-fixture-validation-and-implementation-readiness.md` | Promoted minimal Resume evidence and issued a conditional implementation GO. |
| `69-resume-command-implementation.md` | Implemented bounded Resume with fresh Paused eligibility and restart-safe Running verification. |
| `70-resume-command-publication-and-symcon-test-plan.md` | Gated pre-update identity/archive baseline, publication and one supervised Symcon Resume test. |
| `71-resume-preupdate-baseline-and-publication.md` | Captured the repeatable private compatibility baseline and published bounded Resume without a tag or Symcon update. |
| `72-resume-command-symcon-test-report.md` | Verified update compatibility, archive continuity and one supervised Paused-to-Running Resume transition in Symcon. |
| `73-resume-integration-review-and-stop-readiness.md` | Closed Pause/Resume private-pilot evidence, approved a new tag checkpoint and kept Stop behind non-actuating support research. |
| `74-command-expanded-pilot-tag-publication.md` | Published documentation-complete command guidance and immutable `pilot-0.1.0.3` without runtime changes. |
| `75-stop-support-and-semantics-analysis.md` | Confirmed the official Stop request mapping while blocking capture and implementation on unresolved task semantics. |
| `76-stop-vendor-and-upstream-clarification-plan.md` | Prepared a privacy-safe official SDK inquiry and response gates without external contact or mower actuation. |
| `77-stop-vendor-and-upstream-inquiry-execution.md` | Published and independently verified official SDK issue 22 while preserving every Stop safety gate. |
| `78-stop-vendor-response-and-gate-decision.md` | Classified same-day silence as a pending waiting window rather than support, rejection or mature no-response evidence. |
| `79-adaptive-polling-and-power-hint-implementation.md` | Separates a private station-power wake hint from API-owned state, implements bounded active-state polling, records the passive natural-transition result and publishes immutable `pilot-0.1.0.4`. |
| `80-stop-vendor-inquiry-follow-up.md` | Revalidates unchanged official evidence, publishes and verifies the single permitted Stop follow-up, and keeps capture and implementation blocked through the second waiting window. |
| `81-oauth-vendor-response-and-release-gate-review.md` | Classifies the official evaluation acknowledgement as Class F and preserves the private pilot while public OAuth, Store setup and broad release remain blocked. |
| `82-start-command-support-and-semantics-analysis.md` | Confirms official generic Start support, excludes unavailable zone targeting and defines the evidence gates before any capture or implementation. |
| `83-stop-second-window-monitoring-checkpoint.md` | Confirms no Stop response or source change during the second waiting window and prohibits further contact or actuation before the next gate. |
| `84-navimow-pro-community-source-review.md` | Classifies `ilguala/navimow_pro`, records private zone/start evidence and rejects private-protocol adoption. |
| `85-mqtt-wss-track-reprioritization-and-evidence-plan.md` | Promotes receive-only MQTT/WSS evidence work, defines exact topics, privacy, reconciliation and staged Symcon gates. |
| `86-mqtt-wss-private-capture-procedure.md` | Implements the bounded private receive-only WSS/MQTT capture procedure with exact-topic and no-publish enforcement. |
| `87-mqtt-wss-private-capture-report.md` | Closes the successful docked WSS transport gate, promotes partial location fixtures and keeps MQTT state authority blocked. |
| `88-mqtt-partial-payload-parser-design-and-implementation.md` | Implements an offline exact-topic parser and timestamp-aware accumulator for fixture-backed partial location messages. |
| `89-mqtt-active-rest-comparison-capture-procedure.md` | Prepares a bounded receive-only active MQTT run with read-only REST comparison and fixed physical-phase markers. |
| `90-mqtt-active-rest-comparison-capture-report.md` | Closes two active comparison sessions, proves direct MQTT state/battery and rapid location transitions, and retains REST authority. |
| `91-mqtt-symcon-transport-topology-spike.md` | Selects the native WebSocket-to-MQTT chain conditionally from read-only Symcon capability evidence and defines its live gate. |
| `92-native-mqtt-wss-symcon-live-spike-plan.md` | Plans a disposable receive-only native WSS/MQTT live probe with private credential entry, bounded evidence and verified rollback. |
| `93-native-mqtt-wss-symcon-spike-harness-implementation.md` | Implements, validates and publishes the isolated receive-only probe branch while keeping the live Symcon gate closed. |
| `94-native-mqtt-wss-symcon-live-spike-report.md` | Proves native WSS/MQTT custom-child delivery, closes the envelope contract and verifies complete rollback with one private deadline finding. |
| `95-native-mqtt-shadow-integration-design.md` | Designs the optional receiver, explicit transport ownership, bounded recovery and REST-authoritative shadow reconciliation. |
| `96-native-mqtt-shadow-implementation-plan.md` | Orders drift closure, parser promotion, optional Receiver integration, lifecycle gates and staged shadow publication. |
| `97-distribution-main-drift-consolidation.md` | Closes the isolated three-file publication, byte equality and read-only post-update Symcon compatibility gate. |
| `98-native-mqtt-envelope-and-parser-implementation.md` | Implements synthetic native envelopes, strict envelope parsing and persistence-safe semantic reduction as an offline-only increment. |
| `99-native-mqtt-receiver-scaffold.md` | Adds the officially identified, drop-only native MQTT Receiver scaffold with bounded metadata and no Account handoff. |
| `100-native-mqtt-account-pairing-and-ingestion.md` | Adds disabled-by-default symmetric Account pairing, chain drift checks and bounded private MQTT ingestion without REST execution. |
| `101-native-mqtt-targeted-rest-reconciliation.md` | Coalesces MQTT hints into bounded targeted reads while preserving REST as the only public state authority. |
| `102-native-mqtt-credential-endpoint-implementation.md` | Adds read-only MQTT credential retrieval, strict WSS composition and exhaustive secret-redaction tests without persistence. |
| `103-native-mqtt-owned-transport-lifecycle-design.md` | Designs explicit adoption, redacted ownership, single-attempt connection, rollback and staged Symcon lifecycle gates. |
| `104-native-mqtt-explicit-adoption-and-lifecycle-implementation.md` | Implements offline-only explicit adoption, redacted ownership, one-attempt native connection and ownership-checked credential cleanup. |
| `105-native-mqtt-lifecycle-publication-and-symcon-test-plan.md` | Gates the 17-file standalone publication, compatibility baseline, explicit adoption, one connect attempt, cleanup and restart evidence. |
| `106-native-mqtt-lifecycle-publication.md` | Publishes and remotely verifies the exact 17-file native MQTT lifecycle increment without a tag or live Symcon mutation. |
| `107-native-mqtt-preupdate-baseline-and-symcon-update.md` | Proves pre/post-update instance, variable and archive continuity plus Receiver availability without creating or connecting MQTT topology. |
| `108-native-mqtt-inactive-topology-preparation.md` | Prepares and independently verifies one dedicated inactive, credential-empty native MQTT candidate without adoption or broker communication. |
| `109-native-mqtt-explicit-adoption.md` | Adopts the dedicated inactive chain once, proves idempotency and core immutability, and stops before credentials or broker activation. |
| `110-native-mqtt-supervised-connect-and-receive.md` | Proves one-shot native transport and cleanup, records unavailable receive diagnostics and a deadline deviation, then disables the experiment. |
| `111-native-mqtt-bounded-diagnostics-design-and-implementation.md` | Implements a fixed read-only MQTT lifecycle and counter projection with strict privacy, malformed-state and `ShadowActive` regressions. |
| `112-native-mqtt-diagnostics-publication-and-retest-plan.md` | Plans the exact three-file diagnostics publication, compatibility update and absolute-deadline one-shot receive retest with mandatory cleanup. |
| `113-native-mqtt-diagnostics-publication.md` | Publishes and remotely verifies the exact three-file bounded MQTT diagnostics increment while keeping every Symcon and live MQTT gate closed. |
| `114-native-mqtt-diagnostics-symcon-retest-report.md` | Verifies the published diagnostic wrapper read-only in Symcon, preserves all instance and archive contracts and keeps MQTT disabled. |
| `115-native-mqtt-diagnostics-one-shot-retest-report.md` | Records healthy one-shot native transport without accepted Receiver evidence and proves deadline-conformant automatic cleanup. |
| `116-native-mqtt-receive-gap-analysis-and-next-evidence-plan.md` | Locates the unresolved gap before Account diagnostics and selects bounded Receiver ingress counters before any further live connection. |
| `117-native-mqtt-receiver-bounded-diagnostics-design-and-implementation.md` | Implements fixed, privacy-bounded Receiver ingress and handoff diagnostics offline without changing REST authority or public mower variables. |
| `118-native-mqtt-receiver-diagnostics-publication-and-live-test-plan.md` | Gates the exact one-file Receiver publication, compatibility update and one supervised receive-only live test with automatic cleanup. |
| `119-native-mqtt-receiver-diagnostics-publication.md` | Publishes and remotely verifies the exact one-file Receiver diagnostics increment without updating Symcon or creating a tag. |
| `120-native-mqtt-receiver-diagnostics-symcon-update-report.md` | Verifies the one-time Module Control update, archive continuity and both bounded diagnostic wrappers while MQTT remains disabled. |
| `121-native-mqtt-receiver-diagnostics-live-test-report.md` | Records healthy native Core transport with zero Receiver ingress during supervised mowing and proves deadline-safe cleanup. |
| `122-native-mqtt-zero-ingress-root-cause-and-client-id-experiment-plan.md` | Compares the successful disposable and retained native transports, isolates the stable client ID and gates a reversible one-shot experiment. |
| `123-native-mqtt-fresh-client-id-experiment-harness.md` | Implements and offline-validates a private one-shot fresh-client-ID patch, live harness and deterministic restoration contract. |
| `124-native-mqtt-fresh-client-id-publication-and-live-test-plan.md` | Gates a temporary one-file branch, supervised fresh-client-ID experiment, mandatory return to main and verified branch deletion. |
| `125-native-mqtt-fresh-client-id-experiment-publication.md` | Publishes and remotely verifies the one-file temporary experiment branch while proving main unchanged and keeping Symcon closed. |
| `126-native-mqtt-fresh-client-id-symcon-update-report.md` | Installs the temporary branch through one supported Module Control mutation and proves read-only compatibility with MQTT still disabled. |
| `127-native-mqtt-fresh-client-id-live-test-and-restoration.md` | Executes the bounded Fresh-Client-ID experiment once, records zero Receiver ingress and restores runtime plus Module Control to verified main. |
| `128-native-mqtt-zero-ingress-cross-probe-plan.md` | Closes Client ID as the next variable and selects a known-good sibling probe on the retained MQTT Client as the smallest discriminating test. |
| `129-native-mqtt-sibling-cross-probe-harness.md` | Implements and validates the private inactive staging, one-shot sibling observation and guaranteed cleanup harness without touching Symcon. |
| `130-native-mqtt-sibling-cross-probe-publication-and-live-test-plan.md` | Freezes the five-file temporary probe branch and separates publication, inactive staging, one-shot comparison and mandatory restoration into explicit gates. |
| `131-native-mqtt-sibling-cross-probe-publication.md` | Publishes and remotely verifies the exact five-file sibling probe branch while proving productive main unchanged and leaving Symcon untouched. |
| `132-native-mqtt-sibling-cross-probe-symcon-staging-report.md` | Installs the temporary branch, proves productive compatibility and stages exactly one unarmed sibling probe while keeping MQTT disabled. |
| `133-native-mqtt-sibling-cross-probe-live-test-and-restoration.md` | Records the safely cleaned but inconclusive one-shot run, identifies the frozen harness return-contract mismatch and verifies complete restoration to main. |
| `134-native-mqtt-sibling-cross-probe-harness-correction-and-readiness.md` | Preserves the executed V1 evidence, adds a contract-coupled V2 harness and opens only the next planning gate. |
| `135-native-mqtt-sibling-cross-probe-v2-publication-and-live-retest-plan.md` | Defines separate publication, inactive staging and one-shot V2 gates with mandatory cleanup and restoration to main. |
| `136-native-mqtt-sibling-cross-probe-v2-publication.md` | Publishes and remotely verifies the exact temporary five-file V2 probe branch while leaving Symcon and productive main untouched. |
| `137-native-mqtt-sibling-cross-probe-v2-symcon-staging-report.md` | Installs the V2 branch, proves productive compatibility and stages exactly one unarmed sibling probe while MQTT remains disabled. |
| `138-native-mqtt-sibling-cross-probe-v2-live-test-and-restoration.md` | Proves healthy retained Core status with zero ingress to both compatible children, then restores runtime, main and Git completely. |
| `139-native-mqtt-retained-core-subscription-gap-analysis.md` | Proves the retained `QualityOfService` versus native `QoS` subscription-schema mismatch and defines a migration-safe correction while preserving envelope semantics. |
| `140-native-mqtt-subscription-schema-correction-implementation.md` | Implements canonical native `QoS`, strict legacy normalization and compact V3 live evidence entirely offline. |
| `141-native-mqtt-subscription-schema-correction-publication-and-live-test-plan.md` | Separates productive schema publication, temporary V3 probe publication, inactive Symcon staging and one corrected receive-only live test into explicit gates. |
| `142-native-mqtt-subscription-schema-correction-publication.md` | Publishes and remotely verifies the exact one-file native `QoS` correction while leaving Symcon and all live MQTT gates closed. |
| `143-native-mqtt-sibling-probe-v3-publication.md` | Publishes and remotely verifies the exact temporary five-file V3 receive probe while proving corrected productive `main` unchanged. |
| `144-native-mqtt-subscription-schema-correction-symcon-staging.md` | Installs the V3 branch once, preserves all productive and archive contracts, and stages exactly one credential-empty inactive sibling probe. |
| `145-native-mqtt-subscription-schema-correction-live-test-and-restoration.md` | Proves canonical native `QoS` and delivery to both MQTT children, then restores runtime, corrected `main`, contracts and Git completely. |
| `146-native-mqtt-corrected-ingress-review-and-passive-pilot-plan.md` | Accepts corrected ingress, identifies restart/token/reconnect gaps and gates recovery hardening before an event-based receive-only pilot. |
| `147-native-mqtt-passive-pilot-recovery-hardening-implementation.md` | Implements and offline-verifies delayed restart, token rotation, finite reconnect and stable-health reset without changing REST authority. |
| `148-native-mqtt-passive-pilot-publication-and-symcon-test-plan.md` | Freezes the one-file recovery publication and separates disabled update, inactive staging, passive activation, restart, token and degraded-connectivity gates. |
| `149-native-mqtt-passive-pilot-recovery-publication.md` | Publishes and remotely verifies the exact one-file recovery hardening while leaving every Symcon and live MQTT gate closed. |
| `150-native-mqtt-passive-pilot-recovery-symcon-update.md` | Installs the published recovery once, preserves all variable and archive contracts and verifies the new diagnostics while MQTT remains disabled. |
| `151-native-mqtt-passive-pilot-inactive-staging.md` | Verifies the retained canonical Account/Receiver/MQTT/WebSocket chain read-only and closes a credential-free pre-pilot baseline. |
| `152-native-mqtt-passive-pilot-activation.md` | Activates the receive-only pilot once, proves delayed healthy ingress and REST reconciliation, and preserves all variable and archive contracts. |
| `153-native-mqtt-passive-pilot-restart-observation.md` | Fails the restart gate after native Core resumes persisted credentials without Account reconstruction, then disables and cleans the pilot safely. |
| `154-native-mqtt-service-restart-recovery-redesign.md` | Redesigns service-restart recovery around explicit kernel-start reconciliation while retaining REST authority and bounded MQTT ownership. |
| `155-native-mqtt-kernel-start-reconciliation-implementation.md` | Implements and offline-verifies the kernel-start reconciliation state machine without live Symcon mutation. |
| `156-native-mqtt-kernel-start-reconciliation-publication-and-live-test-plan.md` | Separates publication, disabled update, inactive staging, restart observation, activation and cleanup into explicit gates. |
| `157-native-mqtt-kernel-start-reconciliation-publication.md` | Publishes and remotely verifies the kernel-start reconciliation while leaving all Symcon and MQTT gates closed. |
| `158-native-mqtt-kernel-start-reconciliation-symcon-update.md` | Installs the reconciliation once and proves disabled, credential-free compatibility plus archive continuity. |
| `159-native-mqtt-kernel-start-reconciliation-inactive-staging.md` | Verifies the retained inactive topology and closes the credential-free staging baseline. |
| `160-native-mqtt-kernel-start-reconciliation-disabled-restart.md` | Proves the disabled kernel hook across a supervised restart without activating MQTT. |
| `161-native-mqtt-kernel-start-reconciliation-credential-persistence-acceptance.md` | Records the explicit, bounded acceptance required for a Core credential-resume restart test. |
| `162-native-mqtt-kernel-start-reconciliation-temporary-activation.md` | Temporarily activates the receive-only transport and verifies healthy ingress before restart. |
| `163-native-mqtt-kernel-start-reconciliation-core-resume-restart.md` | Captures the Core-resume ordering failure during the authorized restart and restores the disabled credential-free state. |
| `164-native-mqtt-core-resume-ordering-failure-analysis.md` | Locates the startup-ordering defect and defines the smallest correction and regression boundary. |
| `165-native-mqtt-core-resume-ordering-correction-implementation.md` | Implements the post-ready barrier, connection-trigger diagnostics and offline ordering regressions. |
| `166-native-mqtt-core-resume-ordering-correction-publication-and-live-test-plan.md` | Freezes the correction and defines gated publication, installation, staging, activation, restart and cleanup. |
| `167-native-mqtt-core-resume-ordering-correction-publication.md` | Publishes and remotely verifies the exact one-file ordering correction on standalone main. |
| `168-native-mqtt-core-resume-ordering-correction-symcon-update.md` | Installs the ordering correction once and verifies commit, contracts and neutral diagnostics with MQTT disabled. |
| `169-native-mqtt-core-resume-ordering-correction-inactive-staging.md` | Verifies the retained native chain, canonical subscriptions and stopped lifecycle read-only before renewed persistence acceptance. |
| `170-native-mqtt-core-resume-ordering-correction-credential-persistence-acceptance.md` | Records renewed contextual acceptance for one receive-only activation/restart window with mandatory cleanup, without mutating Symcon. |
| `171-native-mqtt-core-resume-ordering-correction-temporary-activation.md` | Activates the receive-only transport once, proves the initial healthy Core path and freezes the corrected pre-restart baseline. |
| `172-native-mqtt-core-resume-ordering-correction-live-restart-and-cleanup.md` | Records the failed corrected Core-resume adoption, proves unchanged healthy Core state and closes mandatory credential-free cleanup. |
| `173-native-mqtt-core-resume-post-ready-barrier-failure-analysis.md` | Establishes the pre-ready semantic-validation false-negative, reconstructs the premature cleanup path and defines a transient-readiness regression. |
| `174-native-mqtt-core-resume-transient-readiness-correction.md` | Reproduces the live failure offline, implements a durable epoch barrier and passes transient, negative, idempotency and complete Navimow validation. |
| `175-native-mqtt-transient-readiness-correction-publication-and-live-test-plan.md` | Freezes the one-file correction and separates publication, disabled installation, inactive staging, renewed persistence acceptance, one active restart and mandatory cleanup into explicit gates. |
| `176-native-mqtt-transient-readiness-correction-publication.md` | Publishes and remotely verifies the exact one-file durable-barrier correction while leaving all Symcon and live gates closed. |
| `177-native-mqtt-transient-readiness-correction-symcon-update.md` | Installs the correction once and proves disabled, credential-free compatibility plus stable variables, archive logging and MQTT lifecycle counters. |
| `178-native-mqtt-transient-readiness-correction-inactive-staging.md` | Verifies the retained native chain, canonical subscriptions and credential-free inactivity across more than one lifecycle period without mutation. |
| `179-native-mqtt-transient-readiness-correction-credential-persistence-acceptance.md` | Records renewed verbatim acceptance for one receive-only activation/restart sequence with mandatory cleanup, without changing Symcon. |
| `180-native-mqtt-transient-readiness-correction-temporary-activation.md` | Activates the receive-only transport once, proves healthy natural ingress and freezes two equal restart baselines with mandatory cleanup armed. |
| `181-native-mqtt-transient-readiness-correction-live-restart-and-cleanup.md` | Proves the durable 15-second barrier, records post-ready native Core unhealthiness and receive-counter advancement with unresolved timing, then restores and verifies the disabled credential-free state. |
| `182-native-mqtt-post-ready-core-health-failure-analysis.md` | Corrects the receive-timing interpretation, narrows the post-ready failure without overclaiming root cause and requires a bounded Core-health observation design. |
| `183-native-mqtt-core-resume-health-observation-design.md` | Freezes a bounded `+15/+30/+60/+90 s` Core-resume observation state machine, immediate safety gates, pre-cleanup diagnostics and the complete offline regression matrix. |
| `184-native-mqtt-core-resume-health-observation-implementation.md` | Reproduces the one-shot failure red, implements bounded absolute Core-health observations with pre-cleanup diagnostics and passes the complete Navimow offline gate. |
| `185-native-mqtt-core-resume-health-observation-publication-and-live-test-plan.md` | Freezes one-file publication and a restart test that separates multi-minute kernel startup from the post-ready 90-second Core observation and accounts for autonomous bounded recovery. |
| `186-native-mqtt-core-resume-health-observation-publication.md` | Publishes and remotely verifies the exact one-file bounded Core-resume observation correction while leaving every Symcon and live gate closed. |
| `187-native-mqtt-core-resume-health-observation-symcon-update.md` | Installs the correction once and proves disabled, credential-free compatibility with stable variables, archive logging and MQTT lifecycle diagnostics. |
| `188-native-mqtt-core-resume-health-observation-inactive-staging.md` | Verifies the retained native chain, canonical subscriptions and credential-free inactivity across more than one lifecycle interval without mutation. |
| `189-native-mqtt-core-resume-health-observation-credential-persistence-acceptance.md` | Records renewed contextual acceptance for one receive-only activation/restart sequence, bounded autonomous recovery and mandatory cleanup without changing Symcon. |
| `190-native-mqtt-core-resume-health-observation-temporary-activation-stop.md` | Records a healthy receive-only activation stopped by the frozen token-horizon contract, followed by complete mandatory cleanup without restart. |
| `191-native-mqtt-core-resume-health-observation-token-horizon-retry-plan.md` | Freezes passive refresh evidence, separate 2400/1800-second token gates and renewed authorization boundaries for one clean restart retry. |
| `192-native-mqtt-core-resume-health-observation-passive-token-refresh.md` | Proves passive scheduled expiry movement to a 3000-second horizon with continuous REST authentication and disabled credential-free MQTT. |
| `193-native-mqtt-core-resume-health-observation-retry-inactive-staging.md` | Revalidates the inactive retained chain, stopped lifecycle and complete compatibility contracts across more than one lifecycle interval before retry acceptance. |
| `194-native-mqtt-core-resume-health-observation-retry-persistence-acceptance.md` | Records renewed contextual acceptance for one threshold-gated receive-only activation/restart retry and mandatory cleanup without changing Symcon. |
| `195-native-mqtt-core-resume-health-observation-retry-token-readiness-check.md` | Blocks retry activation on a fresh sub-2400-second horizon and defines a coordinated read-only refresh and restaging window. |
| `196-native-mqtt-core-resume-health-observation-coordinated-readiness.md` | Proves passive refresh and immediate read-only restaging in one bounded window while preserving the separate activation gate. |
| `197-native-mqtt-core-resume-health-observation-retry-activation-and-restart-arm.md` | Activates the receive-only transport once, freezes two stable active baselines and passes the separate 1800-second restart-arm threshold. |
| `198-native-mqtt-core-resume-health-observation-retry-live-restart-and-cleanup.md` | Proves healthy retained-Core adoption at the exact `+90 s` boundary with zero Account reconnects, then closes immediate and delayed credential-free cleanup. |
| `199-native-mqtt-core-resume-health-observation-deadline-and-diagnostics-review.md` | Extends the designed retained-Core observation horizon to six absolute points through `+180 s`, preserves immediate healthy adoption and freezes the bounded diagnostics and regression contract. |
| `200-native-mqtt-core-resume-health-observation-deadline-hardening-implementation.md` | Implements the six-point retained-Core observation horizon through `+180 s`, proves immediate adoption at every offset and passes the complete offline gate with a two-constant productive delta. |
| `201-native-mqtt-core-resume-health-observation-deadline-hardening-publication-and-live-test-plan.md` | Freezes the one-file deadline-hardening candidate and separates publication, official validation, disabled update, token-gated activation, one restart and mandatory cleanup into explicit gates. |
| `202-native-mqtt-core-resume-health-observation-deadline-hardening-publication.md` | Publishes and remotely verifies the exact one-file six-point deadline hardening while leaving validator, Symcon and all live MQTT gates closed. |
| `203-native-mqtt-core-resume-health-observation-deadline-hardening-validator-blocker.md` | Records the reproducible official Validator `$` runtime failure for all five metadata inputs, an explicitly non-substitutive exact-schema diagnostic pass and the resulting blocked Gate B. |
| `204-native-mqtt-core-resume-health-observation-deadline-hardening-validator-fallback-decision.md` | Restores the established exact-official-schema fallback, closes Gate B for the exact published commit and keeps the separately authorized disabled Symcon update closed. |
| `205-native-mqtt-core-resume-health-observation-deadline-hardening-symcon-update.md` | Installs the exact deadline-hardening commit through one supported update and proves twice that contracts, REST continuity and the disabled credential-free MQTT lifecycle remain unchanged. |
| `206-native-mqtt-core-resume-health-observation-deadline-hardening-inactive-staging.md` | Verifies the retained native chain, exact subscriptions and complete credential-free disabled contract twice across more than one lifecycle interval without a mutation. |
| `207-native-mqtt-core-resume-health-observation-deadline-hardening-credential-persistence-acceptance.md` | Records renewed verbatim acceptance for one threshold-gated receive-only activation/restart sequence with a `+180 s` bounded recovery horizon and mandatory cleanup, without accessing Symcon. |
| `208-native-mqtt-core-resume-health-observation-deadline-hardening-passive-token-readiness.md` | Proves a passive scheduled token refresh to a 3574-second horizon with continuous REST health and credential-free disabled MQTT, without mutation. |
| `209-native-mqtt-core-resume-health-observation-deadline-hardening-temporary-activation.md` | Activates the receive-only transport once, proves two equal healthy active baselines and passes the separate 1800-second restart-arm threshold without restarting Symcon. |
| `210-native-mqtt-core-resume-health-observation-deadline-hardening-live-restart-and-cleanup.md` | Proves retained-Core adoption at the exact `+90 s` observation with zero Account reconnects, then closes immediate and 208-second delayed credential-free cleanup. |
| `211-native-mqtt-core-resume-deadline-hardening-evidence-closure.md` | Closes regression and pilot decisions: the repeated `+90 s` signature needs no new fixture or productive change, and the hardening passes within the disabled-by-default private-pilot boundary. |
| `212-native-mqtt-private-pilot-operating-policy.md` | Defines a bounded monitored receive-only pilot with a 72-hour maximum, earliest evidence-complete closure after 48 hours, two required natural mowing cycles and mandatory credential-free cleanup. |
| `213-native-mqtt-private-pilot-observation-harness-design-and-implementation.md` | Implements and offline-validates the private read-only projection and resumable 48–72-hour evidence state machine without changing Symcon or the productive module. |
| `214-native-mqtt-private-pilot-shadow-diagnostics-design.md` | Designs a versioned, identity-free MQTT-shadow observation beside REST-authoritative state for manual pilot checks without variables, archive logging or geometry retention. |
| `215-native-mqtt-private-pilot-shadow-diagnostics-implementation.md` | Implements and offline-validates the bounded version-2 MQTT hint projection and the private REST/MQTT side-by-side pilot view without publication or live access. |
| `216-native-mqtt-private-pilot-shadow-diagnostics-publication-plan.md` | Freezes deterministic one-file publication, official-schema validation, disabled Symcon update and deferred inactive-preflight gates for the version-2 MQTT hint diagnostics. |
| `217-native-mqtt-private-pilot-shadow-diagnostics-publication.md` | Publishes and remotely verifies the exact one-file version-2 MQTT shadow diagnostic delta while leaving metadata conformance, Symcon and pilot gates closed. |
| `218-native-mqtt-private-pilot-shadow-diagnostics-metadata-conformance.md` | Reproduces the official validator UI defect, then passes all five exact published metadata inputs through the established current official-schema and AJV fallback. |
| `219-native-mqtt-private-pilot-shadow-diagnostics-symcon-update.md` | Installs the exact published diagnostic commit with one supported update and proves preserved variables, Archive logging, REST authority and an empty version-2 shadow while MQTT stays disabled. |
| `220-native-mqtt-private-pilot-inactive-preflight-and-harness-initialization.md` | Passes two equal disabled and credential-free live projections 82 seconds apart, initializes the private pilot harness and stops at the separate persistence-acceptance gate. |
| `221-native-mqtt-private-pilot-persistence-acceptance-and-activation-readiness.md` | Records explicit commit-bound acceptance for one 72-hour receive-only pilot while preserving fresh token readiness and activation as separate gates. |
| `222-native-mqtt-private-pilot-token-readiness-and-activation-gate.md` | Passes the inactive compatibility contract but blocks activation after the fresh token horizon falls from a marginal 2413 to 2343 seconds. |
| `223-native-mqtt-private-pilot-passive-token-readiness.md` | Proves a scheduler-driven passive OAuth refresh to a 2883-second horizon without manual authentication while MQTT remains disabled and credential-free. |
| `224-native-mqtt-private-pilot-activation-and-active-baselines.md` | Activates the receive-only transport once, proves automatic healthy ingress with two stable active baselines and starts the fixed 72-hour pilot clock. |
| `225-native-mqtt-private-pilot-early-stability-checkpoint.md` | Uses one delayed `+63 min` checkpoint to pass early and first-hour health while recording one passive credential rotation and one complete natural mowing cycle. |
| `226-native-mqtt-private-pilot-overnight-failure-and-cleanup.md` | Closes the pilot as `FAIL` after a missing automation causes an evidence gap and three recovered disconnects exceed policy, then proves immediate and delayed credential-free cleanup. |
| `227-native-mqtt-private-pilot-failure-analysis-and-retest-decision.md` | Separates the procedural automation failure, successful rotation/reconnect accounting and missing per-episode causality, then requires bounded diagnostics and a proven automation dry-run before retest. |
| `228-native-mqtt-pilot-checkpoint-and-episode-diagnostics-design.md` | Designs restart-safe five-hour Account checkpoints and bounded transport-episode evidence without variables, logging changes or transport mutation. |
| `229-native-mqtt-pilot-checkpoint-and-episode-diagnostics-implementation.md` | Implements and offline-validates the fixed-schema checkpoint, episode and rotation diagnostics while preserving REST authority and the public variable contract. |
| `230-native-mqtt-pilot-diagnostics-publication-and-disabled-update-plan.md` | Freezes the three-file pilot-diagnostic candidate and separates publication, metadata validation and a disabled credential-free Symcon update into explicit gates. |
| `231-native-mqtt-pilot-diagnostics-publication.md` | Publishes and remotely verifies the exact three-file Account diagnostic candidate while leaving metadata, Symcon and activation gates closed. |
| `232-native-mqtt-pilot-diagnostics-metadata-conformance.md` | Passes all 13 library, module, form and locale inputs through freshly downloaded official schemas while recording the unavailable browser UI without overclaiming it. |
| `233-native-mqtt-pilot-diagnostics-disabled-symcon-update.md` | Installs the exact pilot-diagnostic commit through one supported update and proves immediate plus delayed disabled, credential-free compatibility with unchanged variables and archive logging. |
| `234-native-mqtt-pilot-diagnostics-inactive-preflight.md` | Extends the private harness with bounded native pilot diagnostics, proves two equal credential-free inactive snapshots and initializes the exact commit at `ready-for-acceptance`. |
| `235-native-mqtt-pilot-diagnostics-persistence-acceptance-and-token-readiness.md` | Records renewed commit-bound persistence acceptance and confirms passive token readiness without manual authentication while keeping MQTT activation separately closed. |
| `236-native-mqtt-pilot-diagnostics-activation-and-active-baselines.md` | Activates the receive-only diagnostic pilot once, proves two stable active baselines and starts the fixed 48-to-72-hour observation clock with mandatory cleanup armed. |
| `237-native-mqtt-pilot-checkpoint-failure-and-safe-closure.md` | Stops the pilot after the second recovered transport episode, proves immediate and delayed credential-free cleanup and reconciles the private harness with native retained diagnostics. |
| `238-native-mqtt-pilot-episode-root-cause-analysis.md` | Narrows both recovered episodes to the native WebSocket or upstream transport path, excludes direct rotation and restart triggers, and requires bounded diagnostic hardening before another pilot. |
| `239-native-mqtt-episode-diagnostic-hardening-design.md` | Designs identity-free native Core status timing, ingress and REST context plus versioned episode evidence without changing recovery, authority, variables or pilot policy. |
| `240-native-mqtt-episode-diagnostic-hardening-implementation.md` | Implements and offline-validates pilot diagnostics v2 with owned Core status timing, fixed-schema migration and preserved kernelstart, recovery, REST and variable contracts. |
| `241-native-mqtt-episode-diagnostic-hardening-publication-and-symcon-test-plan.md` | Freezes the exact one-file candidate and separates publication, metadata validation and a disabled credential-free Symcon v1-to-v2 compatibility check into explicit gates. |
| `242-native-mqtt-episode-diagnostic-hardening-publication.md` | Publishes and independently verifies the exact one-file Account diagnostic candidate on main while leaving metadata, Symcon and MQTT activation gates closed. |
| `243-native-mqtt-episode-diagnostic-hardening-metadata-conformance.md` | Passes all 13 published metadata, form and locale inputs through freshly downloaded official schemas while recording the official validator's own runtime failure without misclassifying the module. |
| `244-native-mqtt-episode-diagnostic-hardening-disabled-symcon-update.md` | Installs the exact diagnostic-hardening commit through one supported update and proves immediate plus delayed disabled, credential-free compatibility, lossless v1-to-v2 history projection and preserved archive logging. |
| `245-native-mqtt-episode-diagnostic-hardening-inactive-preflight.md` | Proves two stable disabled v2 baselines, corrects the private harness to accept bounded closed history and initializes the exact installed commit at `ready-for-acceptance`. |
| `246-native-mqtt-episode-diagnostic-hardening-persistence-acceptance-and-token-readiness.md` | Records commit-bound persistence acceptance and proves passive OAuth token readiness through one scheduled read-only check while keeping MQTT activation separately closed. |
| `247-native-mqtt-episode-diagnostic-hardening-activation-and-active-baselines.md` | Activates the hardened receive-only MQTT pilot exactly once, accepts two stable active baselines and starts the immutable 48-to-72-hour observation window with cleanup armed. |
| `248-native-mqtt-episode-diagnostic-hardening-pilot-failure-and-cleanup.md` | Closes the pilot after repeated recovered transport episodes, proves immediate and delayed credential-free cleanup and records the later correction of a checkpoint-compaction artifact. |
| `249-native-mqtt-episode-root-cause-reconciliation.md` | Reconciles eight distinct episodes with twelve disconnect observations, corrects the compacted-checkpoint evidence-gap artifact and narrows the remaining failure domain to native WebSocket or upstream WSS transport. |
| `250-native-mqtt-episode-accounting-and-bounded-projection-design.md` | Defines cumulative distinct-episode accounting, preserves disconnect observations separately and specifies an independently versioned MQTT pilot summary bounded to 16 KiB. |
| `251-native-mqtt-episode-accounting-and-bounded-projection-implementation.md` | Implements the 16-KiB operational summary, switches private pilot policy to cumulative distinct episodes and proves accounting, compatibility and privacy offline. |
| `252-native-mqtt-episode-accounting-publication-and-symcon-test-plan.md` | Freezes the one-file publication candidate and separates publication, metadata validation and a disabled credential-free summary compatibility update into explicit gates. |
| `253-navimow-mqtt-recovery-mainline-integration-plan.md` | Supersedes direct publication until the clean recovered 207-path Navimow workstream is integrated with current main, fully reviewed, retested and refrozen. |
| `254-navimow-mqtt-recovery-mainline-integration-and-refreeze.md` | Integrates current main without conflict, reviews the complete Navimow scope, passes focused and repository-wide checks and reproduces the one-file standalone candidate. |
| `255-navimow-mqtt-recovery-branch-publication-and-pr.md` | Publishes only the reviewed recovery branch, opens draft PR #22 against SAEF main and preserves standalone and live gates for separate authorization. |
| `256-navimow-mqtt-recovery-pr-review-and-merge-decision.md` | Reviews draft PR #22 and its productive receive-only delta, records no blocking findings and recommends a separately authorized SAEF-main merge. |
| `257-navimow-mqtt-recovery-pr-merge-and-canonical-verification.md` | Executes the explicitly authorized PR #22 merge contract, verifies canonical SAEF main and keeps standalone and live gates closed. |
| `258-navimow-standalone-mqtt-publication-readiness-review.md` | Revalidates the exact one-file episode-summary delta against current standalone main and issues a conditional publication GO without mutating either remote. |
| `259-native-mqtt-episode-accounting-standalone-publication.md` | Publishes and remotely verifies the exact one-file episode-summary candidate while preserving metadata, Symcon and MQTT activation as separate gates. |
| `260-native-mqtt-episode-accounting-metadata-conformance.md` | Reproduces the official validator runtime defect and passes all 13 exact published inputs through freshly downloaded official schemas and AJV 6.10.2. |
| `261-native-mqtt-episode-accounting-disabled-symcon-update.md` | Stops the authorized disabled update before mutation because three bounded preflights observe the Account at status 101 instead of the required 102. |
| `262-navimow-account-status-101-readonly-analysis.md` | Correlates the stale Account status 101 exactly with the step-248 cleanup ApplyChanges call while proving that polling, refresh, REST and disabled MQTT remain operational. |
| `263-navimow-account-status-recovery-and-update-gate-design.md` | Rejects a one-off ApplyChanges repair and designs explicit Account status finalization plus a separately gated corrective publication and 101-to-102 update. |
| `264-navimow-account-status-finalization-implementation.md` | Adds explicit successful Account status finalization, closes the missing Core-status harness coverage and passes the complete offline gate. |
| `265-navimow-account-status-correction-publication-plan.md` | Freezes the five-line Account correction and separates local candidate canonicalization, one-file standalone publication, metadata conformance and the controlled `101`-to-`102` Symcon recovery update into explicit gates. |
| `266-navimow-account-status-correction-candidate-canonicalization.md` | Canonicalizes the complete status-recovery narrative, five-line Account correction and status-aware lifecycle tests as one clean local SAEF candidate while leaving publication and all live gates closed. |
| `267-navimow-account-status-correction-standalone-publication.md` | Publishes and remotely verifies the exact five-line Account status-finalization correction as one standalone commit while leaving metadata conformance, Symcon and MQTT gates closed. |
| `268-navimow-account-status-correction-metadata-conformance.md` | Reproduces the official Validator runtime defect, then passes all 13 exact published metadata inputs through freshly downloaded official schemas and AJV 6.10.2 without accessing Symcon. |
| `269-navimow-account-status-correction-disabled-symcon-update.md` | Installs the exact corrective commit through one supported update and proves stable Account status `102`, preserved REST, variables and archive logging, plus disabled credential-free MQTT through three read-only postflights. |
| `270-navimow-account-status-correction-integration-review.md` | Consolidates publication, metadata and live-update evidence; proves Navimow-only workstream isolation and lock-identical toolchain provenance; and leaves documentation canonicalization plus all MQTT and restart gates closed. |
| `271-navimow-account-status-correction-case-study-canonicalization.md` | Canonicalizes steps 267 through 270 and their provenance clarifications as one exact local Navimow-only documentation commit while keeping push, PR, merge and all live gates closed. |
| `272-navimow-account-status-correction-saef-publication-plan.md` | Freezes the complete five-commit Navimow branch and separates final readiness, branch push, pull request, review, merge and cleanup into explicit publication gates without changing any remote or live state. |
| `273-navimow-account-status-correction-saef-push-readiness.md` | Stops Gate P0 because the publication plan is not yet canonicalized, preserves the clean-worktree rule and redirects final readiness to one self-contained local canonicalization step without pushing. |
| `274-navimow-account-status-correction-saef-push-candidate-canonicalization.md` | Canonicalizes the publication plan and stopped readiness gate, passes focused plus repository-wide validation against the final clean six-commit candidate and leaves branch push separately gated. |
| `275-navimow-account-status-correction-saef-branch-publication.md` | Publishes the exact six-commit Gate-P0 candidate once, proves remote branch identity and preserves publication-evidence canonicalization, PR, merge and all live operations as separate gates. |
| `276-navimow-account-status-correction-saef-branch-publication-evidence-canonicalization.md` | Canonicalizes and fast-forward publishes the initial branch-publication evidence as one documentation-only commit, proves final remote equality and leaves pull-request creation plus merge separately gated. |
| `277-navimow-account-status-correction-saef-pull-request-publication.md` | Opens and verifies ready-for-review PR #23, publishes one bounded documentation-only closure commit and leaves review, checks decision, merge and all live gates separately closed. |
| `278-navimow-account-status-correction-saef-pull-request-review-and-checks.md` | Reviews PR #23 without blocking findings, verifies focused and GitHub checks, publishes one report-only closure commit and recommends a separately authorized SAEF-main merge. |
| `279-navimow-account-status-correction-saef-pr-merge-and-canonical-verification.md` | Adds the final merge contract to PR #23, merges it through GitHub after terminal checks and independently verifies canonical SAEF main while retaining all cleanup and live gates. |
| `280-navimow-account-status-correction-post-merge-retention-and-next-step-review.md` | Declares the Account correction operationally complete, defines evidence and source-retention classes, rejects redundant standalone or live work and leaves canonicalization plus cleanup separately gated. |
| `281-navimow-account-status-correction-post-merge-closure-canonicalization.md` | Canonicalizes the post-merge retention decision and its own closure evidence as one exact local three-file documentation commit while keeping publication, cleanup and live gates closed. |
| `282-navimow-account-status-correction-post-merge-closure-publication-plan.md` | Freezes the documentation-only post-merge closure and separates plan canonicalization, branch push, PR review, merge and source cleanup into explicit gates without changing any remote or live state. |
| `283-navimow-account-status-correction-post-merge-closure-plan-canonicalization.md` | Canonicalizes the post-merge closure publication plan as the exact second local documentation commit and proves the final two-commit, five-path candidate while leaving every remote, cleanup and live gate closed. |
| `distribution/` | Canonical installable snapshot for the dedicated public Symcon module repository. |
| `tools/validate-distribution.php` | Repeatable validation of the Symcon distribution root. |
| `fixtures/README.md` | Fixture workspace rules before sanitized payload files are added. |

## Engineering Boundary

The future module should be designed as an owned IP-Symcon integration with
explicit configuration, clearly separated device state, command actions,
runtime diagnostics and cloud communication state.

This case study must not contain credentials, OAuth tokens, private device IDs,
private hostnames, personal ObjectIDs or local garden data.

## Related SAEF Artifacts

- `case-studies/README.md`
- `templates/module/README.md`
- `standards/SYMCON_STANDARDS.md`
- `standards/PHP_STANDARDS.md`
- `standards/DOCUMENTATION_STANDARDS.md`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `knowledge/EK-006-runtime-diagnostics.md`
