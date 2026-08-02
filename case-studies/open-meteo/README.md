# Open-Meteo Weather and Solar Forecast Case Study

This case study defines a SAEF-aligned path for replacing provider-specific
OpenWeather and SolCast consumers with read-only Open-Meteo weather, soil and
photovoltaic forecasts.

The current case-study state includes the published provider-neutral shared
location, weather and solar runtimes. A controlled manual-only pilot has proved
the storage-coupled solar path without enabling recurring requests or disabling
an existing provider.

## Scope

The target design covers:

- multiple independently configured locations;
- current model conditions, hourly forecasts and daily summaries;
- modelled soil temperature and volumetric soil moisture;
- photovoltaic forecasts for one or more orientations and inverter groups;
- explicit forecast interval semantics;
- bounded persistent caches and runtime diagnostics;
- provider-parallel migration without destructive object replacement; and
- a pure-PHP forecast core that can be verified without IP-Symcon or network
  access.

Private coordinates, ObjectIDs, local sensor names, PV installation details
and consumer mappings belong in `private/` or an ignored `*.local.*` file.

## Artifacts

| File | Purpose |
| --- | --- |
| `01-module-design.md` | Freezes the initial module contract, object and variable model, Open-Meteo request profiles, time semantics, offline core and migration gates. |
| `02-offline-core-and-fixtures.md` | Describes the implemented pure-PHP core, synthetic fixtures, verification and remaining boundaries. |
| `03-inactive-module-scaffold.md` | Describes the weather and solar module metadata, inert lifecycle and offline scaffold proof. |
| `04-deterministic-module-fileset.md` | Describes the standalone generated module fileset, custom profiles and weather-reference reconciliation. |
| `05-inactive-live-preflight.md` | Records the authorized read-only live preflight and the repository-delivery boundary that stopped the installation before mutation. |
| `06-publication-and-inactive-live-install.md` | Records public standalone publication, the corrected module-name defect and the successful inactive live idempotency gate. |
| `07-controlled-publication-workflow.md` | Defines the one-way checked/prepare/apply publisher from canonical SAEF sources to the public module repository. |
| `08-weather-runtime.md` | Describes the bounded weather transport, last-good cache, stale/retry behavior and offline runtime proof. |
| `11-shared-location-instances.md` | Defines the provider-neutral system-wide location contract and compatible weather migration path. |
| `12-solar-runtime.md` | Defines the manual-first solar transport, atomic multi-orientation calculation, cache API and deferred calibration boundary. |
| `13-solar-manual-pilot.md` | Records the sanitized first storage-coupled solar request, cache verification and remaining activation boundaries. |
| `distribution/libs/OpenMeteo/` | Pure request, parsing, interval, PV and runtime-state domain classes. |
| `distribution/` | Canonical candidate IP-Symcon library source with shared-location, weather and solar modules. |
| `../../dist/symcon/saef-open-meteo-module/` | Generated standalone module fileset; never edit it directly. |
| `../../tools/publish-open-meteo-module.php` | Guarded one-way publisher; check and prepare are local, apply requires explicit immutable gates. |
| `fixtures/` | Synthetic provider responses without installation data. |
| `tests/` | Standalone offline contract tests. |
| `tools/check-offline.sh` | Focused lint, test, PHPStan and PHPCS gate. |

## Current Decision

The generated module is published and its manual-first weather and solar paths
have passed controlled live pilots:

1. pure-PHP request, response, projection and forecast-domain classes are present;
2. sanitized synthetic fixtures cover weather, soil, solar and provider errors;
3. weather parsing, interval alignment, PV calculation and state reduction are
   verified offline;
4. the weather module has a bounded timer/HTTP adapter, atomic candidate
   validation, last-good cache and explicit stale/retry behavior; and
5. the solar module has a manual-first, atomic multi-orientation runtime with
   automatic updates disabled by default; and
6. one storage-coupled `pv_harvest` pilot produced a bounded, current cache
   while its timer, configuration and provider-parallel boundary remained
   unchanged.

The generated fileset contains the shared profile and configuration-hash
helpers required by the modules without duplicating their canonical sources.

SAEF remains the editable source of truth. The public module repository is a
generated release mirror and must not be edited independently. The controlled
workflow is documented in `07-controlled-publication-workflow.md`.

Further live API traffic, automatic polling, forecast observation, provider
deactivation and consumer migration remain separate later gates.

The authorized inactive live preflight found no candidate collision, but the
installation stopped before mutation because Module Control requires an
accessible repository URL. See `05-inactive-live-preflight.md` for the
historical preflight result. Public repository publication and the corrected
inactive installation are recorded in `06-publication-and-inactive-live-install.md`.
The first controlled weather request, exact rollback and visibility-gap
correction are recorded in `09-weather-pilot-and-visibility-gap.md`.
The corrected root-name incident and the new ObjectID-zero guardrail are
recorded in `10-root-object-name-incident.md`.
The first controlled storage-coupled solar request and its sanitized acceptance
evidence are recorded in `13-solar-manual-pilot.md`.

Run the focused gate from the repository root:

```console
case-studies/open-meteo/tools/check-offline.sh
```
