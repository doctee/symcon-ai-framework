# Changelog

All notable changes to this project will be documented in this file.

The format is inspired by Keep a Changelog.
This project adheres to Semantic Versioning.

---

## [0.2.0] - Unreleased

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
