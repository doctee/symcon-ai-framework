# HTML Presentation Standards

Status: Initial SAEF baseline for new custom HTML content.

## Purpose and scope

New SAEF HTML content and HTML-SDK tiles use the shared
[presentation template](../templates/html-presentation/README.md) by default.
This keeps custom elements consistent with Symcon while leaving domain logic
with its existing owner. Prefer a native Symcon presentation when it already
provides the required control or value display.

Existing displays are adopted individually under the
[Symcon change workflow](../project/SYMCON_CHANGE_WORKFLOW.md). Integrating this
standard does not deploy styles, change a Store library or migrate any live
object. A target-specific exception records its reason and verification limit;
it does not require extending the common base immediately.

## Five building blocks

Compose a small set of blocks rather than inventing a separate visual system
for each display:

1. Content area with an optional heading.
2. Text, labels, values and units.
3. Lists and tables with explicit status text.
4. Related controls with a shared group label.
5. Chart surroundings: axes, legend and period controls.

Notices and expandable details support these blocks. Maps, heatmaps, flow
layouts and media overlays retain their specialized rendering; they can adopt
the common text and controls without becoming new core blocks.

## Skin and containment

- Read Symcon's `--card-color`, `--content-color` and `--accent-color`; inherit
  the host font. The actual visualization skin takes precedence over the
  operating system's preferred appearance.
- Derive borders, secondary text and selection fills from those colors. Keep
  selected text readable instead of assuming white text fits every accent.
- Limit styles to an owned `.saef-html` root and its descendants. Do not reset
  another document's body, generic controls, tables or native host chrome.
- The host owns the outer card, title and maximize/edit controls. Show a title
  once and verify the host's reserved control area before placing interactions.
- A separate embedded webpage needs an explicit theme bridge; parent document
  CSS variables are not inherited across an iframe boundary.

These rules preserve user-customized skins and avoid nested card decorations
or CSS changes escaping into neighboring elements. The base uses `color-mix()`
and container queries; verify support in the intended client. Older clients
require a documented compatibility decision, not a claimed universal fallback.

## Geometry and text

- Use the 4/8/12/16 px spacing scale and a 6 px radius for internal controls.
- Inherit base text; use one modest heading level and secondary labels of at
  least 12 px. Keep numbers and units legible and align tabular values.
- Avoid added gradients, shadows and heavy frames in the common blocks.
- Wrap long names and control groups; keep horizontal overflow out of the
  surrounding page. Contain table scrolling only when its columns genuinely
  cannot reflow.
- Provide at least 44 px effective touch height for controls. A compact chart
  does not require every data mark to become a button; use a larger shared
  detail control when needed.

Tiny pre-existing slots may need a separate compact variant or a deliberate
layout adjustment. Do not silently shrink text or overwrite native tile sizes
to claim that the general template fits them.

## Meaning and interaction

- Keep status and action separate. A status describes an observed value; a
  button names an operation. Group related choices under a normal label.
- Use native HTML controls, accessible labels, visible keyboard focus and
  explicit selected/disabled states. Selection is not command confirmation.
- Encode warning, error and unknown states with text or a symbol as well as
  color. Missing data is not zero, stale data is not current, and an empty
  forecast is not evidence of dry weather.
- Preserve units, resolution, thresholds and fixed domain color meanings.
  Chart surfaces and labels follow the skin; an absolute rain-intensity scale
  must not be replaced with forecast-relative or decorative accent colors.
- Keep state evaluation, device actions and confirmation with their existing
  script/module owner. A presentation change must not add a command bridge,
  availability interlock, retry policy or optimistic success claim.

## Adoption and acceptance

Include the reviewed stylesheet through the target's existing source/asset
ownership, or embed its bytes in the HTML producer. Record an embedded copy's
source identity; updating the template must not silently update live consumers.
Keep Store and managed-fileset update contracts intact.

Check both skins and a changed accent, narrow containers, long labels, actual
tile height, keyboard/touch access and representative populated, empty and
stale states. Report synthetic browser checks separately from native/iPad
observation. A populated design preview can establish visual acceptance without
switching a device; it does not qualify the target's real action behavior.

Label temporary examples clearly and keep them disconnected from device
actions. After review, restore the agreed original source **and** output with
fresh drift checks and independent readback. Returning to real data while
leaving an experimental renderer installed is not restoration of the original
display.

## Sources

- [Symcon designs](https://www.symcon.de/de/service/dokumentation/komponenten/kachel-visualisierung/individualisierung/designs/)
- [Symcon 8.0 CSS color variables](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v72-v80-q1-2025/)
- [Symcon HTML-SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/)
- [Symcon web content](https://www.symcon.de/de/service/dokumentation/komponenten/objekt-darstellung/webinhalt/)
- [Dated pilot evidence](../case-studies/html-presentation/README.md)
