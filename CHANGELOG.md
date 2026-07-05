# Changelog

All notable changes to this project will be documented in this file.

The format is inspired by Keep a Changelog.
This project adheres to Semantic Versioning.

---

## [0.2.0] - Unreleased

### Added

#### Diagnostics helper library

- Configuration hash helper for stable hashes of normalized configuration arrays.
- Recursive ignored-key handling for volatile configuration values such as timestamps, last run metadata or runtime state.

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
