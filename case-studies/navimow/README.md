# Navimow Native IP-Symcon Module Case Study

**Status:** Adaptive polling published as immutable `pilot-0.1.0.4`; one Stop follow-up published and second waiting window active
**Scope:** Native IP-Symcon module for Segway Navimow robotic mowers  
**Implementation state:** The canonical case-study distribution implements
OAuth, discovery, adaptive read-only status polling and bounded Dock, Pause and
Resume commands.

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
