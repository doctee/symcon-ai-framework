# Open-Meteo Weather and Solar Forecast Case Study

This case study defines a SAEF-aligned path for replacing provider-specific
OpenWeather and SolCast consumers with read-only Open-Meteo weather, soil and
photovoltaic forecasts.

The current case-study state includes the offline core, a productive weather
runtime candidate and an inactive solar scaffold. The previously published
standalone revision remains inactive; this newer runtime candidate has only
been verified offline and has not been published or installed. It does not
disable any existing provider.

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
| `distribution/libs/OpenMeteo/` | Pure request, parsing, interval, PV and runtime-state domain classes. |
| `distribution/` | Canonical candidate IP-Symcon library source with inactive weather/solar module scaffolds. |
| `../../dist/symcon/saef-open-meteo-module/` | Generated standalone module fileset; never edit it directly. |
| `../../tools/publish-open-meteo-module.php` | Guarded one-way publisher; check and prepare are local, apply requires explicit immutable gates. |
| `fixtures/` | Synthetic provider responses without installation data. |
| `tests/` | Standalone offline contract tests. |
| `tools/check-offline.sh` | Focused lint, test, PHPStan and PHPCS gate. |

## Current Decision

The weather runtime candidate remains offline-verified only:

1. pure-PHP request, response, projection and forecast-domain classes are present;
2. sanitized synthetic fixtures cover weather, soil, solar and provider errors;
3. weather parsing, interval alignment, PV calculation and state reduction are
   verified offline;
4. the weather module has a bounded timer/HTTP adapter, atomic candidate
   validation, last-good cache and explicit stale/retry behavior; and
5. the solar module remains inactive until the weather runtime contract is
   accepted and separately exercised.

The generated fileset contains the shared profile and configuration-hash
helpers required by the modules without duplicating their canonical sources.

SAEF remains the editable source of truth. The public module repository is a
generated release mirror and must not be edited independently. The controlled
workflow is documented in `07-controlled-publication-workflow.md`.

Public mirror publication, installed-library update, live API traffic,
productive configuration, provider deactivation and consumer migration are
separate later gates.

The authorized inactive live preflight found no candidate collision, but the
installation stopped before mutation because Module Control requires an
accessible repository URL. See `05-inactive-live-preflight.md` for the
historical preflight result. Public repository publication and the corrected
inactive installation are recorded in `06-publication-and-inactive-live-install.md`.

Run the focused gate from the repository root:

```console
case-studies/open-meteo/tools/check-offline.sh
```
