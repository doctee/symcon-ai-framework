# SAEF Step 411: HTML SDK Compact Controls, Statistics and Exact Preview

## 1. Goal

This step refines the existing Navimow HTML SDK local-map presentation after
physical iPad evidence showed two remaining layout defects:

- the complete navigation group occupied too much of the upper-left map area;
- zone-statistics text crossed card boundaries on the physical client.

It also adds a reproducible browser-preview path that can render the exact
candidate assets with either the public synthetic scene or an ignored private
garden scene. No standalone publication or live Symcon mutation is part of
this step.

## 2. Interaction-Boundary Evidence

The upper interaction boundary is empirical installation evidence, not an HTML
SDK constant. The affected touch clients are reliable from `46px`; the earlier
Safari heatmap investigation established a conservative `48px` boundary for a
fine mouse pointer.

The candidate records that evidence as:

```css
--nav-host-interaction-inset: 46px;
--nav-navigation-frame-lift: 3px;

@media (hover: hover) and (pointer: fine) {
    .nav-map {
        --nav-host-interaction-inset: 48px;
    }
}
```

The touch navigation frame begins at `43px`; its mouse equivalent begins at
`45px`. A one-pixel border and symmetric two-pixel vertical padding place each
interactive button at exactly `46px` for touch and `48px` for a fine pointer.
Therefore only the non-interactive frame occupies the respective unreliable
area.

If a future client demonstrates another host boundary, this named property is
the single presentation value to requalify. It does not change map geometry,
transport or runtime state.

## 3. Presentation Changes

### 3.1 Compact navigation

The zone selector is removed from the first version of the compact control
surface. The remaining one-row controls are:

1. zoom in;
2. zoom out;
3. fit complete map;
4. follow mower.

Normal and coarse-pointer layouts share a maximum measured width of `156px`.
Coarse-pointer buttons are `30px` high with explicit one-line centering and
reduced frame padding. Removing the selector also removes its JavaScript
population, focus and change-listener paths.

The `Mäher` button is a local follow-mode toggle. Its first activation centers
the current map view on the mower without changing the zoom level. Subsequent
map updates keep that position centered. A second activation, manual map
movement, wheel zoom, pinch gesture or `Fit` disables follow mode. It never
sends a mower command or changes transport state.

### 3.2 Bounded statistics cards

Each zone card now uses one bounded column instead of two unconstrained text
columns. The retained font size is unchanged. Labels are shortened to:

- `Stand heute`, `Stand 8 T.` or `Stand –`;
- `Lauf 47 %` or `Lauf –`;
- `Woche 120,5 m²`, `Fläche 192,1 m²` or `Fläche –`.

Every line owns the full card width and uses bounded ellipsis behavior. Cards
remain horizontally scrollable if the tile cannot show all zones at once.
The measured statistics height still reserves map space through the existing
`--nav-statistics-height` contract.

Cards are ordered by the horizontal center of their corresponding rendered
zone. This matches the visible left-to-right map order even when the transport
or geometry scene uses another technical order. Each heading inherits the
base stroke color of its corresponding zone. The rendered SVG title is the
presentation authority for the heading text, preventing map and statistics
labels from drifting apart.

## 4. Exact Preview Workflow

`tools/render-local-map-preview.cjs` assembles the actual distribution HTML,
CSS, JavaScript and SVG renderer output. It creates screenshots and checks
browser rectangles for:

- exactly four controls and no zone selector;
- frame placement above the interaction boundary;
- every button starting at the pointer-specific `46px` or `48px` boundary;
- a real activation and deactivation click at the first half-pixel inside that
  button boundary;
- a navigation width no greater than `160px`;
- all statistics text remaining inside its card; and
- bounded horizontal statistics overflow.

The default run uses the public synthetic fixture. An optional `--scene` input
accepts the existing local-map scene envelope and allows the same checks with
realistic private garden geometry.

Example with public geometry:

```sh
NODE_PATH=/path/to/node_modules node \
  case-studies/navimow/tools/render-local-map-preview.cjs \
  --output=/ignored/private/output
```

Example with a private scene:

```sh
NODE_PATH=/path/to/node_modules node \
  case-studies/navimow/tools/render-local-map-preview.cjs \
  --output=/ignored/private/output \
  --scene=/ignored/private/local-map-scene.json \
  --label-map=/ignored/private/label-map.local.json
```

Private geometry, labels, generated HTML and screenshots remain below the
ignored `private/` overlay. They are never copied into this public step or the
standalone module fileset.

## 5. Preview Profiles

The controlled browser run covers:

| Profile | CSS viewport | Device scale | Output relevance |
|---|---:|---:|---|
| desktop | `1280 x 720` | `1` | ordinary browser tile |
| observed iPad tile | `590 x 490` | `2` | `1180 x 980` physical screenshot |
| iPhone portrait | `390 x 844` | `3` | narrow touch layout |
| narrow iPhone | `375 x 667` | `2` | narrowest navigation media query |

The observed iPad profile deliberately reproduces both the physical output
dimensions and the effective CSS viewport. Testing only an `1180px` CSS width
would materially understate control and text sizes.

## 6. Architecture Decisions

### AD-NAV-411-01: Separate host evidence from platform contract

**Decision:** Store `46px` as the touch inset and override it with `48px` only
for `(hover: hover) and (pointer: fine)`.

**Reason:** The two values reflect separately observed client behavior and are
not guaranteed by the HTML SDK or every browser host.

### AD-NAV-411-02: Let decoration cross the boundary, not interaction

**Decision:** Lift only the navigation frame while all button rectangles begin
at the measured boundary.

**Reason:** This saves visible map space without relying on unreliable pointer
delivery above the established edge.

### AD-NAV-411-03: Remove zone focus before compressing it

**Decision:** Omit the optional zone selector instead of shrinking a native
select below a reliable touch size.

**Reason:** Pan, zoom, fit and mower follow retain the primary map workflow.
Zone focus can return later only with a separately qualified compact control.

### AD-NAV-411-04: Preview the actual assets and private geometry separately

**Decision:** Use one renderer for public synthetic and ignored private scene
inputs.

**Reason:** Synthetic fixtures keep CI and public review reproducible. Private
geometry catches real spatial collisions without disclosing installation data.

### AD-NAV-411-05: Derive statistics presentation from rendered zones

**Decision:** Resolve card order, heading color and heading text from the
matching rendered SVG zone while retaining the statistics payload as the value
authority.

**Reason:** Geometry order, visual order and statistics order are independent
inputs. Joining them by stable zone ID prevents stale names and mismatched
colors without hard-coding installation-specific labels.

## 7. Verification

The synthetic and private-scene browser runs both passed all four viewport
profiles. Their shared measurements were:

| Measurement | Desktop | Observed iPad | iPhone | Narrow iPhone |
|---|---:|---:|---:|---:|
| navigation top | `45px` | `43px` | `43px` | `43px` |
| button top | `48px` | `46px` | `46px` | `46px` |
| navigation width | `156px` | `154px` | `154px` | `144px` |
| navigation height | `34px` | `36px` | `36px` | `36px` |
| statistics height | `64.75px` | `64.75px` | `64.75px` | `64.75px` |
| text outside card | none | none | none | none |
| map order and color | pass | pass | pass | pass |
| boundary activation | pass | pass | pass | pass |

The focused Device contract, JavaScript syntax checks, complete Navimow suite,
generated fileset check and repository-wide `make check` all pass.

## 8. Safety and Gate Status

| Gate | Status |
|---|---|
| Local implementation | PASS |
| Synthetic exact preview | PASS |
| Private realistic preview | PASS, retained privately |
| Focused Device contract | PASS |
| Full repository validation | PASS |
| SAEF commit, push and pull request | CLOSED |
| Standalone publication | CLOSED |
| Symcon update | CLOSED |

## 9. Next Gate

Review the exact candidate diff and then request one separate SAEF commit and
pull-request gate. Module publication and physical iPad verification remain
later independent gates.
