# Shared HTML Presentation Baseline

Date: 2026-10-26

Status: Populated design preview accepted in both skins on the native iPad
client. The original production renderer and output were restored. There is no
active installed consumer of the candidate from this pilot.

## Context and scope

Custom HTML displays had accumulated different fonts, fixed dark colors,
gradient buttons, table layouts and chart labels. The engineering goal was a
small shared vocabulary that follows Symcon's skin, without merging display
work into device-control or domain-data changes.

The resulting [standard](../../standards/HTML_PRESENTATION_STANDARDS.md) and
[template](../../templates/html-presentation/README.md) cover content, text,
tables, controls and chart surroundings. Three complete synthetic examples
demonstrate a battery table, a light overview and a minute rain strip.

## Decisions

- Reuse Symcon's card/content/accent colors and the inherited font. Derive
  subdued surfaces locally rather than maintaining another runtime skin map.
- Keep the shared stylesheet scoped and small. Gallery skins and sample
  interactions remain example assets, not a new runtime framework or PHP API.
- Preserve the rain renderer's absolute intensity colors and missing/zero
  distinction. A presentation fixture is not another forecast implementation.
- Respect source ownership. A Store-owned battery presentation was not edited
  or forked; a script-owned lighting renderer provided the bounded live pilot.
- Embed the reviewed CSS in that private renderer so the pilot introduced no
  shared runtime file, global autoload owner or module reload boundary.

## Verification

The initial browser matrix covered 27 combinations: light/dark/custom-accent
palettes, 320/480/736 px tile containers and normal/empty/stale data. It found no
horizontal content overflow. Container tests are not device emulation.

Browser interactions covered the attention filter, keyboard focus, Enter on a
simulated light action, a missing minute and a real zero value. Example buttons
measured at least 44 px; measured body/secondary/selected text contrast in those
fixture palettes was at least 6.71:1. This is not certification for arbitrary
user themes. The fixture's fixed rain samples matched the existing canonical
renderer.

The private lighting candidate passed eight before/after renderer cases,
local PHP lint, a non-executing server syntax check, exact source readback and
generated HTML comparison. Grouping, action targets, event/input bindings and
the existing webhook handler were preserved. No device action was performed.

The first native observation showed only an empty-state line. That established
neither populated layout nor control acceptance. A clearly labeled synthetic
three-pattern preview then occupied the existing HTML output temporarily;
its controls changed only local sample data. The user accepted this populated
design in both skins on the native iPad client.

## Restoration and outcome

The initial return to real data still used the experimental renderer. The user
correctly distinguished this from the original display. Final restoration
recovered the complete original renderer source and its current empty-state
HTML byte-exactly, with fresh ownership/source checks and independent readback.
No objects were created or deleted and no module ownership was changed.

The accepted design remains a reusable framework asset. Visual approval of a
preview is separate from adopting an experimental renderer into production.
Explicitly identify both the source and output that a temporary review must
restore; a “real data” label does not prove the original implementation is back.

Exact installation source, hashes, backups, browser evidence and operation
results remain in the private workstream record. They are retained for recovery;
completion does not authorize deletion. The installed lighting candidate's
active/grouped operation and physical switching were not qualified by this
visual pilot. Later targets, Store integration and production rollout keep
their own change scope.
