# Native visualization controls (0.3.0)

The fireplace start input is now a native timestamp variable with a module action.
Editing the input only stages a timestamp. The separate enumeration actions save
or cancel that exact start through the same bounded, semaphore-protected log used
by the instance form. Repeated saves remain idempotent. Drafts survive lifecycle
calls; observation can remain disabled while a user records a start.

No heating command, automatic calibration or forecast rewrite was added.
The action buttons return to a neutral value. Invalid actions and future/out-of-range
drafts are rejected. Both successful and failed log writes are reported through
FireplaceStatus; semaphore contention also produces visible feedback.

Installation-specific visualization setup uses the existing SAEF EnsureCategory
and EnsureLink helpers. Link ForecastValid and ForecastDate alongside ForecastKWh;
display ScoredNights beside the mean error. Diagnostics JSON is not a normal user
display. The timestamp, action, result and recorded starts form the fireplace page.
The existing legacy model is a separate cleanup decision; retain its history.

## Compatibility and engineering decisions

Native enumeration presentation requires Symcon 8.0. The existing minimum kernel
date (September 2025) and PHP 8.2 requirement remain; the pinned official metadata
schema only enumerates version strings through 6.2. The README states the actual
requirement explicitly.

The shared analysis stub now accepts the documented presentation array for
RegisterVariableInteger and declares EnableAction. This only models existing SDK
contracts for static analysis; no shared runtime helper or deployed helper owner
was changed.

Official contracts:
[Module SDK](https://www.symcon.de/de/llms/developer/sdk-tools/sdk-php/module.md),
[Object presentation](https://www.symcon.de/de/llms/components/object-presentation.md).

## Verification

- 19 core checks, 21 fireplace checks and 94 runtime assertions against the built package.
- Module PHPStan level 5, PSR-12 and three pinned official JSON schemas passed.
- All generated Symcon bundles passed PHPStan with a 1 GB analysis memory limit.
- Public PR 2 was integrated and its 17-file candidate byte-verified by the SAEF publisher.
- One guarded Module Control update to 0.3.0 succeeded on the authorized Symcon 9.1 installation.
- Configuration and journals remained unchanged; module status, repository validity
  and repository cleanliness were verified.
- Fourteen visible links and two categories were confirmed in the visualization
  data supplied by the server, including the timestamp and both action captions.
- A native timestamp RequestAction with its existing value preserved the input
  and journal. No artificial fireplace event was created.
- Actual mobile rendering and a real user fireplace entry remain untested.
- Legacy timer and legacy visualization group were checked unchanged.

Private before/after evidence and the exact additive setup script are retained
outside public artifacts. Rollback removes only the newly created links/categories,
leaf first. Preserve module configuration and journals before any module rollback.
