# Changelog

All notable changes to this project will be documented in this file.

The format is inspired by Keep a Changelog.
This project adheres to Semantic Versioning.

---

## [Unreleased]

### Added

- Extended the restricted Windows deployment channel with deterministic,
  manifest-driven standalone-module packages and hash-pinned server-local
  transaction adapters while preserving the existing five-command transport,
  explicit activation gate and separate retention boundary.
- Added the V05-001 repository-only latest-command-wins implementation with
  immutable event payloads, bounded Registry-backed generation arbitration,
  supersession Statistics, explicit confirmation and lock timing bounds,
  deterministic concurrency regressions and no live activation.
- Added the fresh V05-001 read-only Symcon MCP inventory, confirming the finite
  MQTT exporter owner and consumer set, repository-identical active runtime,
  complete event and diagnostics contracts, explicit timing correction and
  admission for offline implementation without live mutation.
- Added the V05-001 latest-command-wins architecture review with complete MQTT
  exporter impact inventory, immutable event-input boundary, bounded
  Registry-backed generation model, diagnostics classification, deterministic
  test contract and separate implementation and live-activation gates.
- Added the initial v0.5 engineering inventory with one confirmed public
  candidate, explicit admission gates and separation of repository scope from
  live operations, standalone-module publication and private retention.

### Changed

- Restored deterministic Windows deployment-channel status for validation
  failures before rollback snapshot creation by allowing the mandatory
  snapshot cleanup to accept an explicit empty collection as a no-op.
- Hardened the Windows OpenSSH deployment identity against the accepted
  `.\`-qualified local-account spelling by binding both aliases to the same
  public-key-only, non-interactive forced-command block and documenting real
  negotiation as a mandatory Windows gate.
- Corrected both standalone-module target validation expressions so the
  channel-version-8 gateway remains parseable by Windows PowerShell 5.1, and
  added a regression for both manifest validation boundaries.
- Reconciled the project overview and v0.4 scope, repository and readiness
  records with the published annotated tag, exact-revision CI and successful
  GitHub Release.

## [0.4.0] - 2026-08-24

### Added

- Added a generic manifest-driven standalone Symcon module publisher with
  strict contracts, deterministic check/prepare modes, exact apply gates,
  topic-branch pull-request publication, remote-drift protection, independent
  verification and retained post-mutation recovery evidence. Open-Meteo keeps
  its proven direct-branch behavior through a compatibility adapter, while
  MediaCarousel provides the byte-exact eleven-file PR-based second contract.
- Added the deterministic MediaCarousel HTML-SDK module and standalone
  publication contract for ordered image media objects and rolling image
  categories. Bounded publication and live gates validated its initial
  preview, client lifecycle, fullscreen, position retention and camera archive
  behavior while keeping visualization placement and rollback objects under
  separate installation ownership.
- Added `SAEF_ValidateMutableObject()` as a fail-closed guard for concrete
  Symcon mutation targets, including explicit protection of ObjectID `0`, and
  applied it across the existing object-mutating Ensure helpers with direct
  contract tests.
- Added the bounded Navimow task-observation ledger, generic standalone
  publication contract and disabled-by-default rollout. The first natural
  Zone 1 gate retained privacy-safe pass, progress and area evidence through
  credential-first cleanup; Zone 2 and Zone 3 remain separate evidence gates.
- Added the confirmed `v0.4.0` development scope and repository reconciliation,
  including the post-v0.3 API, artifact, documentation and release-gate
  boundaries without changing framework version constants.
- Added reconciliation-time MQTT Client subscription coverage for every
  exporter command topic and retained owned adapter/event identities across
  runtime namespace changes. The state-only Home Assistant and Apple Home
  migration is documented with bounded authoritative feedback; live activation
  of the improved in-place candidate remains a separate gate.
- Added a guarded Windows deployment-retention tool with exact manifest-pair
  plans, runtime-reference protection, verified private backups and enforced
  one-deployment-to-one-fileset checks before simulation and after cleanup.
- Consolidated the post-v0.3 ControlLight evolution on a clean `origin/main`
  boundary: member-confirmed groups, Hue Wall and Manual-On/Pulse-Off adapters,
  HS/off-state color contracts, per-variable Statistics serialization, the
  Z2M Mired-aware Kelvin matcher, installed-instance fixtures, executable
  regressions and sanitized live-gate reports. The later live gates activated
  the per-variable Statistics helper through the earliest global owner while
  preserving the proven MQTT runtime, then verified the Mired matcher on one
  minimal CL-003 fileset with authoritative 3900-to-3906 K feedback.
- Added a bounded Open-Meteo solar calibration collector with immutable
  forecast snapshots, change-based archive alignment, deterministic private
  source generation and offline metric tests.
- Added policy-versioned curtailment classification for zero-export storage
  systems. Realized harvest remains visible while only unequivocally
  unconstrained intervals contribute to physical forecast calibration.
- Kept successful scheduled calibration cycles quiet while retaining
  structured results for interactive runs and generic failure logging.
- Added a guarded one-way Open-Meteo module publisher. SAEF remains the
  editable source of truth while check and prepare stay local and
  deterministic; publication requires exact fileset and remote-commit pins,
  an explicit repository confirmation, allowlisted staging, fast-forward push
  protection and an independent post-push clone verification.

### Changed

- Set the canonical SAEF bundle and fileset framework versions to `0.4.0`,
  regenerated every deterministic artifact and added the complete v0.4 public
  API and release-readiness audits.
- Froze the `v0.4.0` feature scope, explicitly deferred rapid-command
  latest-command-wins behavior from GitHub issue #1 to a post-v0.4 workstream
  and opened the dedicated release-preparation boundary without changing
  runtime code, framework versions or generated artifacts.
- Serialized `SAEF_IncrementStatistic()` per variable so concurrent counters
  retain their existing API while failing clearly on semaphore contention.
- Reconciled the published `v0.3.0` tag and GitHub Release with the project
  charter, current framework overview and historical release-readiness records.
- Made SAEF analyzer resolution worktree-aware through the existing
  `COMPOSER_VENDOR_DIR` contract, with repository-local defaults,
  lock-identical external toolchains, deterministic failure diagnostics and
  focused local/external/invalid-path regression coverage.
- Compacted the DWD nowcast HTML renderer for narrow titleless Ninja tiles by
  removing vertical root padding, reducing headline and axis gaps and fixing
  mobile text sizing without changing bar geometry, tooltips or forecast data.
- Pinned repository and bundle PHPStan analysis to the declared PHP 8.2
  minimum so checks remain deterministic when the local PHP runtime changes.
- Reduced DWD nowcast log noise by classifying observed TLS-handshake and HTTP
  5xx provider warnings inside the bounded transport adapter. Brief recovered
  outages remain in structured counters; operational logs are reserved for
  missing initial data, stale forecasts and exhausted retries.
- Added one bounded, request-free Solar dependency reconciliation after
  `IPS_KERNELSTARTED`, preventing a transient Weather startup-ordering gap from
  leaving an otherwise valid Solar instance permanently in configuration error.
- Recorded the guarded, idempotent creation and hourly timer activation of a
  second storage-aware Open-Meteo Solar instance without provider traffic,
  device actions or changes to the existing Weather and Solar controls.

## [0.3.0] - 2026-07-23

### Added

- Added a gated v0.2 Symcon rollout plan based on a sanitized read-only live
  inventory, including shared-helper ownership, inactive staging, rollback,
  runtime verification and selective legacy migration rules.
- Recorded the successful backup and inactive staging of the immutable v0.2.0
  MQTT and ControlLight filesets without selecting runtime code or changing
  Symcon objects.
- Recorded the successful external v0.2.0 maintenance preflight, including the
  corrected equal-length candidate token, verified rollback and explicit
  confirmation that no activation or restart occurred.
- Recorded the successful atomic activation of the final v0.2.0 MQTT owner and
  the independent read-only runtime verification, without MQTT publication or
  device actions.
- Recorded the successful Gate F MQTT idempotency verification with repeated
  preparation and no-op reconcile runs, unchanged topology and no newly
  published MQTT messages.
- Recorded the Gate F power-command verification, including the safely isolated
  local loopback duplication and the successful one-message-to-one-dispatch
  retest with an independent MQTT producer and state compensation.
- Recorded the compensated Gate F brightness finding: device feedback differed
  by one percentage point from the requested value, exposing an overly strict
  confirmation contract; retained Home Assistant discovery and runtime MQTT
  state passed independent read-only verification.
- Corrected MQTT command confirmation for bounded brightness and
  color-temperature conversion differences, authoritative feedback following a
  false action result, and coalesced state events during command dispatch;
  recorded the successful live capability retests and the bounded caveat for
  rapid superseding Home Assistant commands.
- Added a restricted Windows deployment channel with a hash-pinned OpenSSH
  forced-command gateway, deterministic bounded deployment packages, fresh
  preflight requirement, atomic bootstrap selection and coordinated rollback.
- Added one operating-system-neutral SSH client contract for macOS and suitable
  iPhone/iPad terminals without requiring PowerShell on Apple devices.
- Hardened the deployment channel with serialized mutations, persistent staging
  budgets and machine-scoped DPAPI credentials that remain usable without an
  interactive Windows profile.
- Replaced unreliable forced-command standard-input uploads with ordered,
  bounded command chunks and added bootstrap-time PowerShell source parsing.
- Made machine-scoped credential handling explicit for Windows PowerShell 5.1
  and added sanitized bootstrap failure-step diagnostics plus rollback of all
  replaced channel artifacts and byte-exact decompression limits.
- Recorded the successful restricted transport-channel security gate,
  including deep readiness, rejection tests and documented residual risks.
- Recorded the failed first runtime activation and successful byte-exact
  rollback; added a mandatory runtime-function manifest contract and a
  hash-pinned Symcon health probe so ready runlevel alone cannot pass future
  activations.
- Identified the activation failure as a candidate-token path mismatch and
  made `.saef-filesets/<targetDirectoryName>/bootstrap.php` an exact package,
  staging and preflight contract.
- Added bounded runtime-health substage diagnostics so failed deployment
  preflights distinguish probe integrity, execution and result-contract checks
  without exposing source, paths or exception messages.
- Corrected Windows PowerShell 5.1 runtime-health contract decoding so a JSON
  function array is normalized to its elements instead of being counted as one
  nested pipeline object.
- Recorded the successful non-activating preflight of the corrected immutable
  runtime candidate, including exact package-hash reconstruction, 74 preserved
  global functions and the managed source-mirror contract.
- Recorded the successful corrected runtime activation, post-restart
  verification of all 74 preserved functions, independent confirmation of 75
  active functions and creation of the non-executable SAEF source mirror.
- Prevented the runtime-mirror provenance loop from overwriting its configured
  presentation name under Windows PowerShell's case-insensitive variable
  semantics.
- Added an optional deployment-managed Symcon source mirror for the active SAEF
  helper closure, with deterministic fileset provenance, `__halt_compiler()`,
  pinned ownership, no-op reconciliation, readback verification and mirror-only
  rollback.
- Added the v0.3 release-readiness gate with explicit API, provenance, channel
  version, artifact, clean-checkout, CI and publication criteria.

### Changed

- Reconciled the v0.2 release-readiness and Symcon rollout documentation with
  the published `v0.2.0` tag and the subsequent ControlLight, Navimow and
  System Functions operational status.
- Recorded the successful scheduled-execution observation after the second
  System Functions pilot migration without executing or mutating the caller.
- Classified the final System Functions pilot call for migration after a
  read-only target-contract and exact candidate-delta check.
- Completed the final System Functions pilot migration with exact source
  read-back, unchanged target and event contracts, and successful subsequent
  scheduled execution without manual caller execution or device action.
- Closed the Navimow passive natural-transition gate with bounded power, state
  and final polling-cadence evidence and no mower command.
- Closed the v0.2 ControlLight rollout scope at seven fully tested v2 wrappers
  with an explicit retain decision for 22 heterogeneous legacy wrappers.
- Defined `v0.3.0` as the proposed next release because the post-v0.2 branch
  combines a new restricted deployment capability with the MQTT correction.
- Added `SAEF_EnsureScript()` to both deployable filesets so the optional source
  mirror can reuse existing object-creation logic after any supported fileset
  activation.
- Preserved explicit deployment status when the optional runtime-mirror
  coordinator cannot start: preflight now fails closed, while a successfully
  restarted runtime is retained as `activated_mirror_degraded`.
- Advanced the repository deployment-channel contract to version 7 so the
  mirror-launch status correction cannot be confused with the live-tested
  version 6 implementation.
- Recorded the successful guarded Windows installation and security
  reverification of deployment-channel version 7, including deep readiness,
  bounded malformed-command rejection, TTY denial and a final healthy probe.
- Documented the recommended Symcon object layout for framework-owned runtime
  objects and system-wide MQTT owners while retaining device-specific MQTT
  objects with their domain owners.

## [0.2.0] - 2026-07-20

### Added

#### Prompt Library

- Standard optimization workflow for existing Symcon scripts.
- Control script optimization workflow for actuator and automation scripts.
- Analysis script optimization workflow for archive, reporting and data scripts.

#### GitHub project workflow

- GitHub issue templates for bug reports, feature requests and engineering
  proposals.
- GitHub pull request template with SAEF verification checklist.

#### Diagnostics helper library

- Configuration hash helper for stable hashes of normalized configuration arrays.
- Recursive ignored-key handling for volatile configuration values such as
  timestamps, last run metadata or runtime state.
- Registry helper for small script-owned JSON metadata stored in a string variable.
- Defensive registry reads for empty values and explicit failures for invalid JSON.
- Statistics helper for script-owned counters and timestamp variables.
- Idempotent statistic variable creation through existing variable helpers.
- Error ring buffer helper for bounded script-owned error history.
- Defensive error ring buffer reads for empty values and explicit failures for invalid JSON.
- RI-002 reference implementation for composing runtime diagnostics helpers.

#### Licensing and governance

- PolyForm Noncommercial License 1.0.0 as the canonical public SAEF license,
  replacing the v0.1 all-rights-reserved file and conflicting MIT Composer
  metadata.
- Commercial licensing policy for uses outside the public license grant.
- Contribution provenance rules for third-party material and future external
  contributions.

#### Migration and live-system workflow

- Sanitized inventory and migration assessment for the preferred private
  `System.Functions.ips.php` library without importing inspected source code.
- Controlled first-wave migration and pilot deployment records for replacing
  compatible legacy calls with existing SAEF helpers.
- Operational MCP script read-back guidance with bounded result handling,
  explicit authorization and verified cleanup requirements.

#### Symcon helper bundle

- ADR-0005 and build design for deterministic helper bundles generated from
  canonical SAEF sources.
- Minimal self-contained `SAEF_EnsureVariable` bundle with manifest, checksum
  and source-provenance sidecar.
- Token-aware builder with canonical path enforcement, strict guard-content
  validation and generated-drift detection.
- Offline determinism, conflict, behavior and side-effect rejection tests plus
  an isolated live Symcon smoke-test record.

#### Event helper library

- Triggered script-event Ensure helper for variable updates and changes with
  explicit parent-automation action binding and deterministic contract tests.

#### MQTT Discovery Exporter case study

- Helper-first MQTT Discovery Exporter with deterministic discovery payloads,
  bounded command confirmation, indexed dispatch and ownership-exact cleanup.
- Runtime Diagnostics composition, executable offline contract tests and a
  deterministic Symcon fileset with a state-based Windows restart coordinator.

#### ControlLight case study

- Helper-first ControlLight runtime with explicit brightness semantics,
  authoritative feedback, bounded confirmation and per-wrapper serialization.
- Managed runtime mirror pattern with deterministic reference indexing,
  presentation preservation, verified rollback and no executable action path.

#### Navimow case study

- Evidence-backed bounded Pause and Resume commands with fresh-state gates,
  one-shot writes and restart-safe read-only verification.
- Adaptive REST polling with a bounded wake window and fixed-capacity hashed
  active-device observations while keeping installation-specific hints private.

### Changed

- Promoted the Symcon Reference Standard from `drafts/SYMCON_STANDARDS.md` into
  `standards/SYMCON_STANDARDS.md` as Stable Draft 1.0.
- Stabilized Knowledge Base guidance for internal state, idempotent
  configuration and runtime diagnostics.
- Refreshed Markdown documentation for the v0.2.0 documentation baseline.
- Updated contribution workflow guidance for humans and AI agents.
- Added operational agent guidance for runtime metadata diagnostics.
- Added Symcon runtime diagnostics rule for internal runtime metadata.
- Added runtime diagnostics checklist to the reference implementation prompt.
- Documented the initialization boundary for runtime diagnostics.
- Aligned Composer metadata and generated-artifact provenance with the public
  SAEF license identifier.
- Added an explicit presentation-ownership policy to object and diagnostics
  Ensure helpers while retaining the v0.1-compatible default.
- Updated templates and references to preserve user-edited presentation by
  selecting the policy explicitly.
- Bounded `SAEF_WaitForVariable()` polling to the configured sleep budget and
  aligned lookback tolerance with Symcon's Unix-second metadata resolution.
- Added direct contract tests for all diagnostics helpers, finite statistic
  arithmetic and structurally bounded error ring buffer reads.
- Documented the supported v0.2 helper API boundary, SemVer assessment and
  explicit tag-readiness gates.
- Classified the complete v0.2 working tree into reviewable commit cohorts and
  recorded the current public-tree sanitization result.
- Integrated Navimow syntax, distribution validation and executable regression
  suites into the aggregate check and extended static analysis to its module
  production code.
- Added the Composer lock file to make local, CI and release dependency
  installation reproducible.
- Clarified the evidence date and historical gate progression of the System
  Functions migration records without treating documentation review as a new
  live-system verification.

### Fixed

- Rejected false ControlLight action results, avoided unchanged Registry writes
  and prevented secondary diagnostic failures from replacing runtime errors.
- Aligned the sanitized ControlLight inventory with the completed CL-027 DIMMER
  capability activation and functional verification.
- Enforced the Navimow adaptive-polling observation capacity when new devices
  are recorded and cleared stale wake metadata after unauthenticated requests.
- Prevented MQTT exporter preparation failures from being counted twice when
  preparation runs inside a reconcile execution.
- Corrected the configuration template and example so cyclic events are owned
  below their target script as required by the current event contract.
- Aligned the RI-002 error path with the documented Diagnostics initialization
  boundary and preserved original runtime exceptions when secondary diagnostic
  writes fail.
- Rejected non-canonical fileset source aliases so generated provenance cannot
  be redirected through symlinks, and made export discovery token-aware so
  comments and strings cannot create false exports.

## [0.1.0] - 2026-07-06

### Added

#### Project foundation

- Initial project charter
- Engineering model
- Architecture Decision Records (ADR)
- Repository principles and governance
- Security policy

#### Helper library

- Validation helper
- EnsureCategory
- EnsureVariable
- EnsureEvent
- EnsureScript
- EnsureInstance
- EnsureDummy
- EnsureLink
- EnsureProfile
- WaitForVariable

#### Templates & Examples

- ConfigurationScript template
- Complete ConfigurationScript example
- Reference implementation using helper library

#### Development tooling

- Composer configuration
- PHPStan static analysis
- PHP_CodeSniffer configuration
- GitHub Actions CI workflow
- Makefile
- Symcon API stubs
- Syntax lint helper

### Changed

- Refactored reference implementation to use helper functions.
- Improved helper consistency and validation.
- Standardized configuration script structure.

### Fixed

- Conditional helper constants now use define() for compatibility.
- Various helper improvements and syntax fixes.
