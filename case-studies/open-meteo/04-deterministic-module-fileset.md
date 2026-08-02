# Open-Meteo Deterministic Module Fileset

## Outcome

The offline portion of Gate M2 now produces a standalone IP-Symcon library
fileset at:

```text
dist/symcon/saef-open-meteo-module/
```

The generated tree contains `library.json`, the shared-location, weather and
solar modules, their forms and translations, the complete pure Open-Meteo core,
the shared SAEF profile helper, its validation dependency and the shared
configuration-hash helper.

No source is transformed. Every generated payload file is a byte-exact copy of
one explicitly listed canonical repository source.

## Canonical Source Layout

The pure core moved from the provisional `candidate/` path to:

```text
case-studies/open-meteo/distribution/libs/OpenMeteo/
```

Both module sources now resolve the core only inside their library boundary.
The generated fileset adds shared SAEF helpers below `libs/SAEF/helpers/`, which
matches the guarded helper paths used by the modules.

Canonical SAEF helpers remain owned by `helpers/`; they are selected into the
generated tree by manifest and are not copied into the editable module source.

## Manifest and Builder

`deployments/symcon/open-meteo-module.fileset.json` is the complete allowlist.
It maps 29 sorted, unique source paths to 29 unique library targets.

`tools/build-symcon-module-fileset.php` is separate from the existing PHP
closure fileset builder because an IP-Symcon module library also needs JSON
metadata, forms and translations. The module builder enforces:

- an exact versioned manifest schema;
- sorted and unique source mappings;
- unique target paths;
- approved source roots and helper files;
- relative, normalized, traversal-free paths;
- canonical non-symlink source files;
- LF-only readable content;
- supported PHP/JSON targets;
- output strictly below `dist/symcon/`; and
- atomic per-file writes.

The builder emits 31 files: 29 payloads, `fileset.sources.json` and
`fileset.sha256`. The source map records source, target, SHA-256 and byte count
for every payload plus the builder version, licence and complete fileset hash.

Build and verify with:

```console
make open-meteo-fileset-build
make open-meteo-fileset-check
```

Generated artifacts must never be edited manually.

## Custom Profiles

`OpenMeteo\Profiles` composes the existing `SAEF_EnsureProfile` helper to create
the profiles frozen by the design:

- `OPENMETEO.DataState`
- `OPENMETEO.WeatherCode`
- `OPENMETEO.Pressure`
- `OPENMETEO.WindSpeed`
- `OPENMETEO.Direction`
- `OPENMETEO.WaterDepth`
- `OPENMETEO.Snowfall`
- `OPENMETEO.SoilMoisture`
- `OPENMETEO.Duration`
- `OPENMETEO.Power`
- `OPENMETEO.Energy`

The module scaffold test verifies that repeated creation leaves the complete
profile contract unchanged. No alternative profile helper or storage pattern
was introduced.

## Weather-Instance Reference

`OpenMeteoSolarForecast` now validates that the configured instance exists and
has the exact `OpenMeteoWeather` module GUID. It stores the last registered
reference ID in a module attribute and reconciles references as follows:

1. an unchanged reference performs no mutation;
2. a valid replacement unregisters the previous reference and registers the new
   one;
3. an unknown or incompatible replacement fails closed and retains the last
   valid reference; and
4. clearing the property removes the registered reference and returns the
   scaffold to unconfigured/inactive state.

For a fully valid solar configuration the module also writes the deterministic
SHA-256 configuration fingerprint into its script-owned `ConfigurationHash`
variable. The hash is diagnostic cache identity, not a privacy mechanism.

## Determinism and Offline Proof

`tests/module-fileset.php` builds the module library in two independent random
temporary roots and compares every relative path and SHA-256. It then compares
that result with the tracked generated fileset and verifies every generated
payload against its canonical source hash.

The test additionally proves:

- all required library, module, core and helper targets exist;
- the generated tree has no unlisted extra files;
- `--check` accepts the tracked fileset and rejects an additional stale target;
- generated payloads contain no private absolute path or ObjectID marker; and
- temporary test trees are removed through an explicitly bounded path guard.

The existing module scaffold test now also proves 11 idempotent profiles,
configuration hashing, valid reference registration, invalid-reference
retention and valid-reference replacement. Timer and transport counts remain
zero.

## Remaining Authorization Gate

Offline M2 is complete. The next step is not HTTP activation. It is a separately
authorized inactive installation test of the exact generated fileset in
IP-Symcon:

1. take a private byte-exact preflight and confirm the target library path;
2. stage the immutable generated fileset without activating timers or HTTP;
3. load the library and create only explicitly approved inactive test instances;
4. run repeated `ApplyChanges()` and verify object/profile/reference identity;
5. prove there are no timers, requests, provider changes or consumer links; and
6. either retain the inactive scaffold by explicit approval or remove every
   created object and staged file through a separately approved cleanup.

That gate creates live objects and therefore requires explicit authorization.
München/Seestall coordinates, PV equipment configuration and consumer ObjectIDs
must remain private throughout. OpenWeather and SolCast remain authoritative
and untouched.
