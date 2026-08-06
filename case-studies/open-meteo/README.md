# Open-Meteo Forecast and DWD Nowcast Case Study

This case study defines a SAEF-aligned path for replacing provider-specific
OpenWeather and SolCast consumers with read-only Open-Meteo weather, soil and
photovoltaic forecasts.

The same installable preview now also contains an offline candidate for a
direct DWD radar precipitation nowcast. Provider processing remains separated;
only the provider-neutral shared-location contract is reused.

The current case-study state includes the published provider-neutral shared
location, weather and solar runtimes. Two shared locations and weather runtimes
are active. One storage-aware Solar runtime has passed manual and automatic
update observation, and a second has passed guarded creation with its first
scheduled cycle pending. A read-only calibration collector now preserves
prospective forecast snapshots for both systems. Existing providers remain
enabled.

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
  access; and
- a native five-minute DWD radar nowcast with a configurable 5-to-120-minute
  evaluation window and no Home Assistant, Python or HDF5 dependency; and
- a cache-only responsive DWD nowcast `~HTMLBox` with one presentation segment
  per minute, tooltips and a deterministic absolute intensity scale.

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
| `13-soil-variable-visibility-live-activation.md` | Records the controlled soil-variable presentation activation and module-update reconciliation. |
| `14-solar-pilot-and-automatic-observation.md` | Records the sanitized storage-coupled solar pilot, scheduled-cycle observation and calibration boundary. |
| `15-live-object-presentation-cleanup.md` | Records the guarded live rename and reparent cleanup without changing runtime configuration or provider traffic. |
| `16-second-storage-solar-instance.md` | Records the idempotent creation and timer activation of a second storage-aware Solar instance. |
| `17-forecast-calibration-collector.md` | Defines and records immutable forecast snapshotting plus bounded read-only alignment with logged PV actuals. |
| `18-dwd-precipitation-nowcast.md` | Defines the direct DWD WMS contract, native five-minute semantics, configurable evaluation window and runtime safety boundary. |
| `19-nowcast-html-chart.md` | Defines the cache-only minute presentation, absolute colors, HTMLBox ownership and lifecycle behavior. |
| `candidate/SolarCalibrationCore.php` | Pure snapshot normalization, archive-event alignment and calibration metrics. |
| `candidate/SolarCalibrationCollectorRuntime.php` | Bounded cache and archive adapter with immutable private evidence files. |
| `tools/build-calibration-collector.php` | Deterministically combines public runtime code with ignored installation-local configuration. |
| `distribution/libs/OpenMeteo/` | Pure request, parsing, interval, PV and runtime-state domain classes. |
| `distribution/libs/DwdNowcast/` | Pure DWD WMS request, response, window-projection and HTML-rendering classes. |
| `distribution/` | Canonical candidate IP-Symcon library source with shared-location, weather, solar and DWD-nowcast modules. |
| `../../dist/symcon/saef-open-meteo-module/` | Generated standalone module fileset; never edit it directly. |
| `../../tools/publish-open-meteo-module.php` | Guarded one-way publisher; check and prepare are local, apply requires explicit immutable gates. |
| `fixtures/` | Synthetic provider responses without installation data. |
| `tests/` | Standalone offline contract tests. |
| `tools/check-offline.sh` | Focused lint, test, PHPStan and PHPCS gate. |

## Current Decision

The generated module is published and its weather and solar paths have passed
controlled live pilots and scheduled-cycle observation:

1. pure-PHP request, response, projection and forecast-domain classes are present;
2. sanitized synthetic fixtures cover weather, soil, solar and provider errors;
3. weather parsing, interval alignment, PV calculation and state reduction are
   verified offline;
4. the weather module has a bounded timer/HTTP adapter, atomic candidate
   validation, last-good cache and explicit stale/retry behavior; and
5. two provider-neutral shared locations and two Weather consumers are active;
6. soil-variable visibility is module-managed only where explicitly enabled;
7. the solar module has an atomic multi-orientation, storage-aware runtime; and
8. one storage-coupled `pv_harvest` pilot and a later scheduled cycle produced
   bounded current caches while configuration, references and the
   provider-parallel boundary remained unchanged; and
9. a second storage-aware Solar instance is active with an hourly timer, an
   unchanged Weather dependency and no provider request during creation; its
   first regular cycle remains an observation checkpoint; and
10. a five-minute read-only calibration collector is active, stores immutable
    forecast snapshots and waits for complete horizons before calculating
    forecast-to-actual metrics.

The generated fileset contains the shared profile and configuration-hash
helpers required by the modules without duplicating their canonical sources.

SAEF remains the editable source of truth. The public module repository is a
generated release mirror and must not be edited independently. The controlled
workflow is documented in `07-controlled-publication-workflow.md`.

Provider deactivation, consumer migration, shading and any change to calibrated
model parameters remain separate later gates.

The DWD nowcast runtime was published separately. The HTML chart introduced by
this workstream remains an offline candidate; publication, a live library
update and live chart validation are not part of the implementation gate.

The authorized inactive live preflight found no candidate collision, but the
installation stopped before mutation because Module Control requires an
accessible repository URL. See `05-inactive-live-preflight.md` for the
historical preflight result. Public repository publication and the corrected
inactive installation are recorded in `06-publication-and-inactive-live-install.md`.
The first controlled weather request, exact rollback and visibility-gap
correction are recorded in `09-weather-pilot-and-visibility-gap.md`.
The corrected root-name incident and the new ObjectID-zero guardrail are
recorded in `10-root-object-name-incident.md`.
Soil-variable visibility and module-update reconciliation are recorded in
`13-soil-variable-visibility-live-activation.md`. The storage-coupled solar
request and scheduled-cycle evidence are recorded in
`14-solar-pilot-and-automatic-observation.md`. The later object-tree presentation
cleanup is recorded in `15-live-object-presentation-cleanup.md`.
The guarded second Solar creation is recorded in
`16-second-storage-solar-instance.md`.
The prospective snapshot and actual-value calibration path is recorded in
`17-forecast-calibration-collector.md`.

Run the focused gate from the repository root:

```console
case-studies/open-meteo/tools/check-offline.sh
```
