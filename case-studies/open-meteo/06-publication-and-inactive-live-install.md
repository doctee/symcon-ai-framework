# Open-Meteo Publication and Inactive Live Installation

**Gate:** Public repository publication and inactive IP-Symcon installation
**Result:** PASS after metadata correction
**Date:** 2026-08-02
**Activation state:** Two retained, unconfigured and inactive test instances

## Publication

The standalone library is publicly available at:

```text
https://github.com/doctee/saef-open-meteo
```

The tested `main` revision is `16552dc9355bf5e3e7382db1d1421bfe63850aaa`.
An independent fresh clone verified all 23 generated payloads against the
source map. The tested aggregate fileset identity is:

```text
2e2418f62f953d7bc8674417e4479f0030dbc977ddceb2a68d1404584b4de2f7
```

README and the repository's PolyForm Noncommercial License are publication
metadata outside that generated payload identity.

## Corrected First Attempt

The first instance-creation attempt stopped with no returned instance because
both `module.json` names contained hyphens. IP-Symcon permits letters, digits,
spaces and underscores in that field and derives the PHP class identity from
the module name. It consequently attempted to resolve an invalid class name.

No candidate instance or profile remained after that failure. The attempt was
not repeated against the same revision. Instead:

1. both metadata names were aligned with their folder and PHP class names;
2. the offline scaffold test gained an explicit allowed-character and
   name/class/folder identity regression;
3. the complete fileset was regenerated and retested;
4. the corrected public revision was independently cloned and hash-checked;
5. Module Control updated the installed library to that exact revision; and
6. only then was instance creation resumed in isolated weather and solar
   stages.

## Inactive Live Result

The weather instance passed two identical `ApplyChanges()` snapshots with:

- status `104` (inactive);
- 41 stable child variables;
- the complete set of eleven `OPENMETEO.*` profiles;
- no event or timer;
- no reference, variable action or link; and
- no configured location or enabled soil forecast.

The solar instance then passed the same repeated verification with:

- status `104` (inactive);
- ten stable child variables;
- empty array and inverter configuration;
- no weather-instance reference;
- no event, timer, action or link; and
- no calibration or shading activation.

The final independent postflight confirmed that Module Control is on the
tested `main` revision, the repository is valid and clean, and no update is
pending. There was no HTTP request, device action, service restart, productive
provider change or consumer connection.

## Retention and Next Gate

The public repository, installed library and both inactive test instances are
retained. Cleanup was not inferred from the installation authorization and was
not attempted.

OpenWeather and SolCast remain authoritative. Productive München/Seestall
configuration, weather-to-solar reference wiring, Open-Meteo HTTP transport,
timers and consumer migration remain separate future gates.
