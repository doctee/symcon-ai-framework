# Second Storage-Aware Solar Instance

**Gate:** Create and configure a second solar forecast instance

**Result:** PASS; first scheduled provider cycle pending

**Date:** 2026-08-03

## Scope

The authorized live gate created a second storage-aware Open-Meteo Solar
instance below the existing forecast category. It uses the active Weather
runtime for the matching shared location and represents a separate PV array,
storage system and microinverter group.

Installation-specific ObjectIDs, names, coordinates, ratings, orientation,
loss assumptions and component details remain in ignored private evidence.

## Guarded Creation

The preflight required:

- a positive, existing target whose exact object type was category;
- an active Weather dependency of the expected module type;
- no existing object with the intended stable Ident or presentation name;
- an unchanged active first Solar instance as control; and
- the live SAEF idempotent instance helper.

The helper created exactly one instance. Configuration was first applied with
automatic updates disabled. That stage proved active module status, the exact
Weather reference, ten owned variables, an empty forecast state and a stopped
update timer. Automatic hourly updates were enabled only after those assertions
passed.

Any failure would have deleted only the newly created instance. Rollback was
not needed.

## Postflight

The immediate postflight proved:

- active module status and the expected storage-aware output mode;
- one valid Weather reference;
- stable configuration hash and ten child variables;
- one hourly update timer with no previous run;
- zero attempt and success markers because creation issued no request;
- unchanged root and forecast-category presentation;
- unchanged Weather and first-Solar configuration, references and fetch state;
  and
- idempotent resolution of the same instance without mutation.

No provider request, device command, service restart, module reload, archive
mutation or consumer migration occurred. The first regular scheduled cycle is
a separate observation checkpoint; until then the new instance correctly
reports that no forecast has been fetched.
