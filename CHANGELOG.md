# Changelog

All notable changes to this project will be documented in this file.

The format is inspired by Keep a Changelog.
This project adheres to Semantic Versioning.

---

## [0.2.0] - Unreleased

### Added

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

### Changed

- Promoted the Symcon Reference Standard from `drafts/SYMCON_STANDARDS.md` into
  `standards/SYMCON_STANDARDS.md` as Stable Draft 1.0.
- Stabilized Knowledge Base guidance for internal state, idempotent
  configuration and runtime diagnostics.
- Refreshed Markdown documentation for the v0.2.0 documentation baseline.
- Updated contribution workflow guidance for humans and AI agents.

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
