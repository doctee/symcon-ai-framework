# Plan cancellation and replacement (0.9.1)

Comparison eligibility is independent of issuing another forecast. Cancelling a
selection or replacing its arrival/pending stage excludes the old selection even
when the replacement is too short to contain a charging night, weather is missing,
or the pre-phase deadline has passed. This fixes the earlier dependence on
`supersededAt`, which only recorded a newly issued curve.

The existing bounded `PlanJournal` provides chronological evidence. The normal
observer reconciles all retained warming, fan and profile records, including
settled records. No new control binding, timer, action or archive query is added.
Normal consumption of a one-shot timer, repeated scheduling of an already active
stage, and clearing the selection on observed arrival are not cancellations.
Changes at or after a curve's end cannot invalidate its completed horizon.
All times use Unix timestamps, including across daylight saving transitions.

Each record receives `evaluation` outside the immutable `forecast`:

- `state`: active, completed, arrived, cancelled, replaced or unknown.
- `eligible`: eligibility of this plan for regular prospective evaluation.
- `reason`, `observedAt`, `evidencePlanHash`: the observed transition and its evidence.
- `phaseKey`: selection identity shared by revisions; never count curves as independent phases.
- `checkedThrough`: covered observation horizon.

An incomplete retained history remains unknown and excluded. Terminal results
survive restart and plan retention. Existing forecasts, hashes, measurements and
models are unchanged. Classification of older records is retrospective metadata,
not a new forecast. Brief transitions missed between observer snapshots cannot
be reconstructed from unarchived inputs.

Excluded records have no regular `metrics`/`profileMetrics` samples or fan
`nightActual.errors`. Numerical comparisons are retained separately as
`diagnosticMetrics`, `diagnosticProfileMetrics` and `nightActual.diagnosticErrors`.
The existing charts display these as **Nur Diagnose** with the cancellation or
replacement label. `settledAt` still means measurement completion, not eligibility.
Do not use it alone to include a phase in validation.

Consumers must require `evaluation.eligible === true`, then independently check
pre-phase timing, complete night coverage, measurement availability, actual
operation and other confounders. Eligibility is not proof of model quality.
Continue selecting the last valid pre-phase revision of each selection and group
multiple nights into one phase. The official daily energy model and its scoring
remain independent and unchanged.

Regression checks cover cancellation without a replacement forecast, short
replacement plans, changed pending starts, same-second snapshots, normal timer
execution, repeated active-stage scheduling, arrival cleanup, restart, incomplete
history and immutable forecasts. Test fixtures have no device commands. This is
a correction of the existing static displays; their styling and controls are
preserved without a broader presentation migration.
