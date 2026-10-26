# Symcon HTML Presentation Template

Status: Initial SAEF presentation baseline.

Use this template for new custom HTML content and HTML-SDK tiles. The
[HTML Presentation Standards](../../standards/HTML_PRESENTATION_STANDARDS.md)
define the shared rules; this directory supplies the stylesheet and complete
examples. Existing installations adopt it through their own change workflow.

## Files

| File | Role |
| --- | --- |
| `saef-html.css` | Shared styles limited to `.saef-html` content |
| `examples.html` | Complete synthetic battery, lighting and nowcast gallery |
| `examples.css` | Gallery shell, synthetic skins and domain-specific chart layout |
| `examples.js` | Local sample data, filters and simulated actions |

Open `examples.html` in a local browser or serve this directory with a local
static development server. The gallery loads only these local assets. Its
controls change sample data only; there is no Symcon connection, persistence
or device action. Gallery files are examples, not runtime module dependencies.

## Use the stylesheet

1. Include `saef-html.css` in the HTML document through the target's existing
   asset ownership, or embed its reviewed bytes when producing an HTML variable.
2. Wrap the owned content in a `.saef-html` element. The root consumes Symcon's
   `--card-color`, `--content-color` and `--accent-color`, and inherits its font.
3. Compose the five blocks below. Keep the host's outer card, title and
   maximize/edit controls; omit a content title if the host already supplies it.
4. Verify the actual content height, host overlays and both skins before
   adopting the target. Separate embedded webpages need their own explicit
   theme bridge; CSS variables do not cross document boundaries automatically.

An embedded stylesheet copy is owned by that target's source/fileset. Editing
this template does not update installed copies. A later adoption compares the
candidate and target sources under the existing change workflow.

## Color roles and provenance

Follow the standard's [color-source rules](../../standards/HTML_PRESENTATION_STANDARDS.md#color-roles-and-provenance).
The active skin owns the surface, text and general accent. A device-state color
comes from its effective presentation/profile, not from the accent by default.
The shared stylesheet keeps these optional inputs and fallback roles central:

| Optional source input | Effective role | Absent-input fallback |
| --- | --- | --- |
| `--saef-source-light-on` | `--saef-light-on` | `#ffeb3b`, native Switch default verified in Symcon 9.1 |
| `--saef-source-warning` | `--saef-warning` | `#b96e00`, SAEF warning choice |
| `--saef-source-error` | `--saef-error` | `#ce4454`, SAEF error choice |

These are SAEF bridge inputs, not automatic Symcon theme variables. Set a
validated input on the owned `.saef-html` root or its ancestor. For different
device colors within one root, the target may override the effective role on
the owned device element. Normalize numeric RGB colors before CSS use; black
(`0`, represented as `#000000`) is a valid override. Do not insert unvalidated
configuration strings into a style attribute. Blank/invalid bridge values
must be omitted by the producer: CSS `var()` handles absent inputs, not color
validation. Filled state controls need a separately verified foreground;
ordinary text must not become yellow merely because a light is ON.

The gallery's independent color-source selector demonstrates absent inputs,
explicit handoff and valid black across all sample skins. Warning/error input
mapping is simulated; it does not claim additional documented native theme
variables. The outlined light marker uses `--saef-light-on`, while action
buttons retain their skin styling. The gallery still has no live connection.

## Five blocks

| Block | Main classes |
| --- | --- |
| Content and optional heading | `.saef-html`, `.saef-html__stack`, `.saef-html__title` |
| Text and values | `.saef-html__text`, `__meta`, `__row`, `__value-row`, `__label`, `__value`, `__number` |
| Lists and tables | `.saef-html__table`, `.saef-html__status` |
| Controls | `.saef-html__group`, `__controls`, `__button`, `__select`, `__field` |
| Chart surroundings | `.saef-html__chart`, `__chart-axis`, `__legend` |

Use `saef-html__` as the full prefix for abbreviated names in the table.
`__notice` and `__details` support these blocks. `saef-html--flush` removes only
this root's inset when the verified host already owns the necessary spacing.
It is not a general instruction to remove tile padding.

## Example behavior and adaptation

The battery table demonstrates current, weak, unknown and stale data. A missing
percentage is an em dash; filtering changes only the view.

The light overview separates status from action and uses the central color
role only for an observed ON marker. Its local buttons simulate a change
immediately. Real integrations retain the owner's existing command and
feedback contract; the example does not prescribe optimistic confirmation or
an availability interlock. Pending/error annotations must retain the observed
state's color until feedback confirms a different state.

The nowcast is a fixed 60-segment fixture, not another forecast algorithm. Its
rain color samples follow the existing
[`NowcastHtmlRenderer`](../../case-studies/open-meteo/distribution/libs/DwdNowcast/NowcastHtmlRenderer.php).
The production owner still evaluates the configured threshold and native
five-minute data. A missing example minute is hatched and selectable as “kein
Wert”; zero stays `0 mm/h`. Its minute selector provides touch/keyboard access
without making every narrow segment a tiny button.

The nowcast example needs more height than the existing approximately 41 px
compact chart. Review such a small target separately rather than shrinking text
or silently changing its native tile geometry. Maps, heatmaps, flow layouts and
media overlays similarly retain their domain-specific rendering.

## Verification

Before adoption, check:

- actual host colors and inherited font in light, dark and a changed accent;
- narrow containers, long names, scrolling and the target's real tile height;
- readable labels, explicit missing/stale states, keyboard focus and touch areas;
- unchanged command targets, data semantics and source/update ownership; and
- exact restoration of both original source and output after a temporary demo.

The baseline requires current browser support for `color-mix()` and container
queries. CSS system colors provide standalone color fallbacks, not a fallback
for missing CSS features. Historical WebFront clients require separate review.

The [dated pilot report](../../case-studies/html-presentation/README.md) records
browser and iPad design acceptance, its limits and restoration. It grants no
live rollout or cleanup authority.
