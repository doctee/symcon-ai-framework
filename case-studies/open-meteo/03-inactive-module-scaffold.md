# Open-Meteo Inactive Module Scaffold

## Outcome

The offline portion of Gate M2 now contains two candidate IP-Symcon modules:

| Module | Ownership |
| --- | --- |
| `OpenMeteoWeather` | One independently configured forecast location and its weather, daily and model-grid soil presentation |
| `OpenMeteoSolarForecast` | One PV system, its array/inverter configuration and derived power/energy presentation |

Both modules deliberately remain inactive. Their source contains no timer
registration, update method, parent transport, cURL call or other path that can
contact Open-Meteo. No module was loaded into an IP-Symcon installation during
this increment.

## Library Identity

The candidate library is rooted at `distribution/` and has stable, unique
library and module GUIDs. Both modules are device-type instances without parent,
child or implemented transport interfaces.

The solar configuration references the weather instance through an explicit
`WeatherInstanceId` property. M2 does not yet register a live object reference
or call the weather instance. Exact reference reconciliation and compatible
module-type validation belong to the packaging/runtime adapter step.

## Inactive Lifecycle

`Create()` registers configuration properties only. `ApplyChanges()`:

1. calls the parent lifecycle method;
2. idempotently registers the stable variable contract;
3. validates only local configuration values;
4. remains at IP-Symcon status `104` when unconfigured or valid; and
5. fails closed with status `200` for malformed configured input.

There is intentionally no `UpdateData()` method and no form action that could
start a request. Planned polling and timeout values are configuration contract
placeholders only.

## Weather Scaffold

The weather properties cover:

- explicit completion of location configuration;
- latitude, longitude and optional elevation;
- IANA time zone;
- bounded forecast days;
- optional soil request intent; and
- planned polling, timeout and stale thresholds.

The module registers 41 stable variables: six operational/freshness variables,
17 curated current-weather variables, nine today variables and nine soil
variables. Soil variable identity is always preserved; `WithSoil` controls the
future request profile rather than deleting variables and possible user archive
configuration. `ManageSoilVariableVisibility` is an opt-in declaration of
module-managed presentation, preserving existing user visibility after a
library update. When opted in, soil variables are visible only when soil
requests and `ShowSoilVariables` are both enabled. Reconciliation validates a
positive owned variable target and never mutates the root object.

## Solar Scaffold

The solar properties cover:

- the weather-instance reference;
- bounded forecast days and planned runtime policy;
- private arrays and inverter groups as JSON configuration;
- shading-profile intent; and
- calibration intent.

Array and inverter JSON is decoded into the existing pure `PvConfiguration`
contract. Empty configuration stays inactive. Malformed equipment data,
enabled calibration or enabled shading fails closed because those two extension
contracts are deliberately deferred.

The module registers ten stable operational, power, energy and configuration
hash variables.

## Profiles and Helper Reuse

This scaffold uses verified built-in profiles where suitable and otherwise a
neutral profile. It does not duplicate SAEF's `EnsureProfile` helper in the
module library.

The custom `OPENMETEO.*` profiles from the design are deferred until the
packaging step can include and exercise the shared helper correctly. Likewise,
runtime configuration hashing, counters and bounded diagnostics must compose
the existing ConfigurationHash, Statistics, Registry and ErrorRingBuffer
helpers rather than create module-specific replacements.

## Offline Proof

`tests/module-scaffold.php` provides a bounded Symcon test double and verifies:

- library and module metadata JSON is valid;
- GUIDs are stable and unique;
- neither module exposes a transport interface;
- source contains no timer, update or transport activation surface;
- default instances remain inactive;
- configured valid instances still remain inactive;
- malformed configured input fails closed;
- weather registers exactly 41 variables and solar exactly ten;
- repeated `ApplyChanges()` preserves every synthetic variable ID and contract;
- no timer registration occurs; and
- deferred calibration cannot be enabled accidentally.

The focused test is part of `tools/check-offline.sh`, PHPStan level 8 and PHPCS.
The repository `make check` target invokes that complete focused gate first.

## Offline Gate M2 Closure

The canonical pure core now lives below `distribution/libs/OpenMeteo` so the
module source no longer reaches outside its library boundary. The deterministic
generated fileset described in `04-deterministic-module-fileset.md` adds the
required shared helpers without source duplication.

The completed offline M2 work now includes:

1. a deterministic generated package/fileset;
2. custom profiles through the existing SAEF helper;
3. idempotent reconciliation of the configured weather-instance reference; and
4. repeated offline proof that there are no timers, HTTP requests or provider
   side effects.

Metadata and repeated inactive `ApplyChanges()` still need verification in an
explicitly authorized IP-Symcon environment. That inactive installation test is
a separate live-object mutation gate. It is not authorized or performed by
this offline increment. OpenWeather and SolCast remain untouched.
