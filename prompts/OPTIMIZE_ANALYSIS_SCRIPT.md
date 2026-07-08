# OPTIMIZE_ANALYSIS_SCRIPT

Optimize an existing IP-Symcon analysis, archive or reporting script according to SAEF.

Use this prompt for scripts that process historical data, create reports, aggregate values or analyze measurements.

Examples:

- archive processing
- counter correction
- energy analysis
- weather analysis
- heatmaps
- statistics
- HTML/report generation

## Preparation

Before making changes:

1. Read `AGENTS.md`.
2. Read `standards/SYMCON_STANDARDS.md`.
3. Read `knowledge/EK-003-archive-processing.md`.
4. Read `knowledge/EK-004-internal-state-management.md`.
5. Read `knowledge/EK-006-runtime-diagnostics.md`.
6. Analyze the complete existing script.
7. Explain the current data flow and relevant risks.

## Goals

- Preserve the full existing functionality.
- Make data processing bounded and reviewable.
- Avoid unbounded archive reads.
- Avoid excessive memory usage.
- Keep transformations deterministic.
- Preserve archive consistency.
- Use existing SAEF helpers and engineering patterns where appropriate.
- Do not introduce new public APIs.
- Do not introduce new helpers unless recurring reuse is demonstrated.

## Archive and Data Requirements

- Archive reads must be bounded by time range, count or chunking.
- Large datasets should be processed in blocks.
- Historical corrections must be explicit and reviewable.
- Destructive archive operations require defensive checks.
- Data order must be handled explicitly.
- Aggregation semantics must be documented where relevant.

## Runtime Metadata

Use existing diagnostics responsibilities:

- `ConfigurationHash` for deterministic configuration fingerprints.
- `Registry` for small structured runtime metadata.
- `Statistics` for counters, timestamps and durations.
- `ErrorRingBuffer` for bounded error or event history.

Dedicated variables are allowed only when they represent real domain state or intentionally visible UI/trigger state.

Explain every new variable.

## Verification

Before finishing:

- run `make check` where possible;
- check PHP syntax for changed private scripts where possible;
- summarize changed files;
- explain architectural decisions;
- report unrelated pre-existing working tree changes;
- do not create commits.
