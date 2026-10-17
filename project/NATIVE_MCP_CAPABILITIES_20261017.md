# Native MCP Capability Review

Date: 2026-10-17. Scope: documentation, exposed schemas and read-only
documentation queries; no mutation, reload, installation or cleanup tests.

## Evidence Boundary

The maintainer's [post 55, dated 2026-10-16](https://community.symcon.de/t/symcon-mcp-server-rust-only/144513/55)
announces six implemented improvements for forthcoming builds. This is not a
minimum build number or proof that every connected server has them. The topic
requires access to the forum's Beta area; an unauthenticated 404 does not prove
the post is absent.

On the review date, the available tool schemas exposed structured module
results and HTML preview parameters. Read-only `symcon_documentation` calls
returned `Guide`, `IPSModule::GetValue` and the requested Forms/List section.
Calls with `module_write` or `module_lint` as documentation topics returned
no documentation: use the exposed schemas and Guide for those tools. No
invalid PHP was written, no library lint/reload was run and no log-budget or
preview rendering behavior was exercised in this review.

Keep announcement, advertised schema, successful documentation lookup and
behavioral qualification as separate evidence levels. Refresh discovery after
an operator-managed update; never change tokens, privileges or connections to
make a missing capability appear.

## Reconciliation

| Previous limitation | Maintainer update / current evidence | SAEF consequence |
| --- | --- | --- |
| PHP write warnings required text interpretation | Write/edit/lint now advertise structured results; a saved invalid write still has `isError=false` | Structured syntax handling is available at the contract level. Separate lint, readback and recovery remain required; negative write behavior was not retested. |
| Whole-library lint could encounter test stubs and misleading success text | Post 55 reports test/hidden-directory exclusion and removal of trailing success after compile failure; schema exposes checked/skipped/errors | Treat false success as announced fixed, not newly behavior-tested. Check coverage explicitly; changed skipped PHP paths need their own appropriate validation. |
| SDK lookup required a separate website workflow | SDK method and section queries returned official documentation | Use direct lookup to reduce search overhead. Verify the documented minimum version against the target; website SDK content is current web documentation, not necessarily the installed API version. |
| Client truncation could hide server guidance | Post 55 describes shorter instructions; `Guide` is callable and returned the complete guide | Read the relevant Guide section before using specialized tools. This does not eliminate truncation of tool responses or local client limits. |
| Log search cutoff could resemble a complete search | Post 55 reports coverage/continuation information at the 256 MB scan bound and incomplete grouped counts | Record covered time/files, continue with bounded ranges and avoid double-counting overlaps. Partial or empty results cannot prove absence of errors or references. Behavior at the bound remains untested here. |
| HTML tile previews lacked theme and size controls | Schema exposes theme, tile_width and tile_height; post 55 specifies 100-1200 pixels, default 300 | Review light/dark and representative sizes for authorized HTML targets. Variable tiles instead use browser theme and viewport sizing. No preview was generated in this review. |

## Practical Workflow Changes

### Documentation Without Guessing

Start with `symcon_documentation("Guide")` for server usage, `"SDK"` for the
module SDK overview, and exact method/topic names for implementation work.
Disambiguate module methods with `IPSModule::GetValue` rather than the kernel
function of the same name. For a long page, request an exact heading, e.g.
`Forms/List#Individual Forms for Editing`. A fallback summary/link or a
shortened page is not the full contract; fetch the linked official page or
required section before relying on omitted parameters or constraints.

The [official index](https://www.symcon.de/de/llms.txt) and
[function index](https://www.symcon.de/de/llms/function-index.md) remain the
website entry points. Current kernel documentation can come from the installed
server; SDK website documentation still needs a platform-version check.
Internal APIs do not become approved runtime dependencies through discovery.

### Shorter Feedback, Same Recovery

Use the [existing risk-tiered workflow](SYMCON_CHANGE_WORKFLOW.md), not a new
wrapper. Local lint/tests precede an authorized write. Inspect typed syntax
results, run separate file and library lint with explicit coverage, then
independently read back the candidate. Reload only within its approved
all-instance impact. A successful syntax verdict does not prove behavior.

For UI work, preview theme/size combinations before physical-device acceptance.
A preview may invoke module visualization code and expose private HTML; it is
not a sandbox, a public sharing approval or proof of live updates and controls.
The current Guide describes initial HTML content only. Keep dynamic behavior
and mobile/browser acceptance as distinct checks.

For incident analysis, use bounded `symcon_log` windows and the existing
history/anomaly tools where their contracts suffice. Do not equate a scan
budget with a complete audit. This review adds no scheduled observation.

## Still Open / Not Superseded

- No server-side undo or whole-file expected-baseline hash was added to the
  exposed write/edit schemas. Preserve private backups and detect drift.
- Reload can affect every instance of a library. Physical effects, persistent
  data migrations, publication and destructive cleanup retain separate gates.
- These six changes do not document a general Windows shell or native
  NTFS ACL/SACL, ADS, reparse-point, hardlink and process-consumer inspection.
  They do not close the outstanding filesystem quarantine preflight.
- No retention safety hold, active-consumer proof, managed deployment ownership
  or existing connection configuration is changed by this document.
- Test the new runtime behaviors only within a separately authorized isolated
  scope or as part of the next already-authorized operation, not by repeatedly
  writing invalid PHP to production merely to confirm the release notes.

The [MCP result workflow](SYMCON_MCP_SCRIPT_READBACK.md) and offline
`composer test:mcp-workflow` fixtures encode the corresponding decisions.
