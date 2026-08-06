# DWD Nowcast HTML Chart

## Status

Published as part of Open-Meteo module `0.8.3` and live-validated for both DWD
NowCast instances. The two existing `~HTMLBox` variables retained their object
identities during the update and use the translated default name `Regen`.
Version `0.8.4` replaces the embedded compact-chart stylesheet with the proven
inline HTMLBox presentation for normal and maximized tiles.
Version `0.8.5` uses the native Web Content presentation on Symcon 8 and newer,
removes tile padding and restores the proven CSS tooltip contract. Older Symcon
versions retain the `~HTMLBox` fallback.

## Purpose

`DwdPrecipitationNowcast` exposes its selected evaluation window as a responsive
Symcon `~HTMLBox`. The chart is derived exclusively from the last-good forecast
cache. It performs no additional provider request and introduces no Home
Assistant, JavaScript or external-asset dependency.

The presentation follows the handed-over operator contract:

- one colored segment per displayed minute;
- local DWD product time and a rain-start or dry-window headline;
- `now`, midpoint and window-end axis labels; and
- a native tooltip with minute offset and intensity in `mm/h`.

The compact presentation follows the established Symcon reference dimensions:
11-pixel base text, 14-pixel bars and 9-pixel axis labels. Every minute segment
stores the minute offset and intensity in a `data-tip` attribute. A namespaced
CSS `::after` pseudo-element exposes that immutable text on `:hover`, with no
JavaScript or DOM mutation. Inline layout dimensions remain the fallback for
legacy HTMLBox rendering.

## Resolution Semantics

The native DWD points remain authoritative five-minute values. The HTML chart
creates a presentation-only minute series using bounded smooth interpolation
between adjacent native leads. This improves readability but does not claim
additional provider accuracy. Forecast cache APIs, summary calculations and
the registered native-resolution variable remain unchanged.

For the default 60-minute evaluation window, 12 native values produce exactly
60 visible segments. Other valid configured windows from 5 through 120 minutes
remain supported.

## Absolute Color Scale

The renderer uses a deterministic absolute scale rather than normalizing each
forecast independently:

| Intensity in `mm/h` | Color |
| --- | --- |
| `0` | dark gray |
| `> 0` and `< 0.1` | light blue |
| `>= 0.1` and `< 0.5` | blue |
| `>= 0.5` and `< 1.0` | green |
| `>= 1.0` and `< 2.5` | yellow |
| `>= 2.5` and `< 5.0` | orange |
| `>= 5.0` | red |

The operational rain threshold remains independently configurable. The chart
can therefore show a trace echo below the threshold while the headline still
states that no relevant rain is expected.

## Module Lifecycle

The module owns one additional variable with the stable Ident `NowcastChart`,
string type and `~HTMLBox` profile. It is updated:

1. after a successful atomic forecast-cache replacement; and
2. during `ApplyChanges()` when a compatible last-good cache already exists.

The second path makes a library update populate the chart without contacting
DWD. An unconfigured or cache-incompatible instance receives a bounded no-data
placeholder. Repeated `ApplyChanges()` preserves the variable identity.

## Verification

Offline checks cover:

- exactly one minute segment per configured minute;
- local product-time formatting;
- rain and dry-window headlines;
- midpoint and endpoint labels;
- deterministic absolute colors;
- the `~HTMLBox` variable contract and idempotent registration;
- cache-only republishing during module lifecycle; and
- deterministic generated-fileset reproduction.

The rendered candidate was also reviewed at desktop width on a dark Symcon-like
background. Text, axis labels, segment boundaries and color transitions were
visible without clipping or external resources.

Live verification confirmed 60 segments for the configured 60-minute window,
the midpoint and endpoint labels, cache-only republishing without an extra DWD
request, and owner-safe migration of the legacy English default title. A
user-defined variable name is deliberately preserved.
