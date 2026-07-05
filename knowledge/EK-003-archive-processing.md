# Engineering Knowledge EK-003

# Archive Processing in IP-Symcon

**Status:** Draft 1.0

## Purpose

This Engineering Knowledge article explains how archive data should be read, processed and corrected in professional IP-Symcon solutions.

Archive processing is a recurring engineering task in Symcon projects. It is used for energy analysis, counter correction, sensor replacement, diagnostics, long-term statistics and data migration.

This document focuses on engineering patterns and operational safety, not on replacing the official Archive Control documentation.

---

## Problem

Archive data grows continuously over time.

A variable that is updated frequently can accumulate thousands or millions of raw values. Processing such data without clear limits may lead to slow scripts, excessive memory usage, blocked automation or inconsistent historical data.

Archive processing becomes especially sensitive when scripts modify historical values. Historical corrections can affect charts, aggregations, statistics and downstream calculations.

---

## Engineering Context

Archive processing is relevant when automation needs to:

- analyse historical values,
- calculate consumption or runtime,
- detect anomalies,
- correct sensor or meter data,
- migrate data to another variable,
- import missing values,
- rebuild derived values,
- verify long-term behaviour.

In IP-Symcon, the Archive Control stores raw logged values and aggregated values. Raw data and aggregated data serve different engineering purposes and should not be treated as interchangeable.

---

## Recommended Pattern

A safe archive-processing script usually follows this pattern:

```text
Define Scope
    ↓
Validate Inputs
    ↓
Read Bounded Data
    ↓
Process in Blocks
    ↓
Write or Report Results
    ↓
Reaggregate if Required
    ↓
Store Correction / Processing Metadata
```

The most important design decision is whether the script only reads archive data or also modifies historical values.

Read-only analysis can usually be simpler. Write or correction scripts require stronger safeguards.

---

## Design Decisions

### Raw Values vs. Aggregated Values

Use raw values when exact historical records are required.

Use aggregated values when the task operates on periods such as minutes, hours, days, weeks, months or years.

Raw values preserve exact recorded changes. Aggregated values are usually better suited for charts, statistics and period-based consumption analysis.

### Time Range

Archive processing should always define a clear time range.

Avoid open-ended processing unless the script is intentionally designed as a full-history maintenance tool.

### Block Size

Large raw-data reads should be processed in blocks.

The block size should be chosen so that memory use remains predictable and the script remains responsive.

A practical default for many maintenance scripts is to process approximately 1,000 to 10,000 records per block, depending on the operation.

### Ordering

Raw archive values returned by `AC_GetLoggedValues()` are ordered from newest to oldest.

Scripts that require chronological processing must explicitly reverse or otherwise handle this order.

### Updates vs. Changes

The archive logs changed values. Variable updates that do not change the value are not represented as separate raw archive entries.

Scripts that depend on update timestamps must not assume that every variable update exists in the archive.

### Correction Metadata

Archive corrections should leave traceable metadata.

Examples:

- affected variable,
- time range,
- correction amount,
- timestamp of the correction,
- script or tool version,
- dry-run result.

---

## Trade-offs

Archive processing can provide powerful analysis and correction capabilities, but it increases operational risk.

Benefits:

- long-term diagnostics,
- historical correction,
- reproducible calculations,
- sensor replacement support,
- better engineering insight.

Costs:

- performance impact,
- risk of inconsistent aggregations,
- risk of accidental historical modification,
- additional state and metadata requirements.

A correction script should therefore be treated as an engineering maintenance tool, not as a normal automation script.

---

## Common Anti-Patterns

### Reading the Entire Archive by Default

Reading all raw values of a frequently updated variable can be expensive and unnecessary.

Use explicit time ranges and limits.

### Ignoring Archive Order

Assuming oldest-to-newest order when the data is returned newest-to-oldest can produce wrong deltas, wrong consumption calculations or incorrect correction distribution.

### Modifying Raw Values without Reaggregation

Adding, deleting or changing historical data may invalidate aggregated values.

Reaggregation must be considered whenever historical raw data changes.

### Silent Historical Corrections

Changing historical data without metadata makes later diagnosis difficult.

Every correction should be traceable.

### Mixing Analysis and Correction

A script that both analyses and modifies archive data without a clear mode can be unsafe.

Prefer explicit modes such as `dryRun`, `analyse`, `apply` or `repair`.

### Treating Missing Data as Zero

Missing archive data does not automatically mean zero consumption, zero runtime or zero sensor value.

Missing data should be handled explicitly.

---

## Practical Checklist

Before writing an archive-processing script, ask:

- Is the script read-only or does it modify historical data?
- Which variable is processed?
- Is the variable logged?
- Which time range is processed?
- Is the number of records bounded?
- Is chronological order required?
- Are raw values or aggregated values the correct data source?
- Does the script need block processing?
- Does the script need a dry-run mode?
- Is reaggregation required after modifications?
- Is correction metadata stored?
- Can the script be safely re-run?

---

## Recommended Script Modes

Archive maintenance scripts should usually support one or more explicit modes.

### Analyse

Read archive data and report findings without modifying anything.

### Dry Run

Calculate planned changes and show what would be modified.

### Apply

Perform the change after validation.

### Resume

Continue a long-running operation from stored progress metadata.

### Repair

Apply a targeted fix for a known issue.

Not every script needs all modes. However, scripts that modify historical data should at least distinguish between analysis and application.

---

## Implementation Notes

### Bounded Reads

Use explicit start and end timestamps wherever possible.

When using `AC_GetLoggedValues()`, also use the limit parameter intentionally. Remember that there is a hard maximum number of returned records per query.

### Aggregated Reads

Use `AC_GetAggregatedValues()` when the task only requires period-level data.

This is often more appropriate for daily, monthly or yearly statistics than processing raw values manually.

### Reaggregation

When historical raw data is added, deleted or changed, affected aggregations may need to be rebuilt.

The affected range should be as small as practical.

### Progress State

Long-running archive operations should store progress state if they may need to resume.

Typical progress state:

- current timestamp,
- last processed record,
- processed count,
- accumulated correction,
- current phase.

---

## Relationship to RS-001

RS-001 defines archive-management rules such as bounded reads, archive consistency and historical corrections as engineering operations.

This article explains the underlying engineering patterns and design decisions.

---

## Related Standards

- RS-001 Symcon Engineering Standards
- PHP Standards
- Documentation Standards
- Testing Standards

---

## Related ADRs

- ADR-0002 — Use Ident over ObjectID where practical
- ADR-0003 — Keep private installation data out of public framework artifacts

---

## Related Knowledge

- EK-002 — Retry Mechanisms in IP-Symcon
- EK-004 — Internal State Management
- EK-005 — Idempotent Configuration

