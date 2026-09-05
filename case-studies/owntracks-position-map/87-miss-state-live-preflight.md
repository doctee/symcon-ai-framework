# Gate 87 — miss-state live preflight

**Status:** The corrected read-only live format-1-to-format-2 preflight passed
on the first attempt. The separately authorized live adoption is complete in
[Gate 88](88-miss-state-live-adoption.md); every installation and
module-operation gate remains closed, 2026-09-05.

## Scope

This gate reads the active OwnTracks package identity, the authoritative
format-1 miss state, the five runtime roots and locks, and the installed
empty-target deployment-channel baseline. It runs only the adoption adapter's
`preflight` operation and retains its protected private plan and status
evidence on the Windows host.

The wrapper has no adoption code path or adoption confirmation. It may write
only protected evidence inside its extracted transfer directory. It may not
replace authoritative state, create an adoption transaction, install a target,
change the allowlist, reload the module, call Symcon RPC, contact a provider,
publish or clean up retained evidence.

## Interoperability correction

The first two read-only attempts stopped at the quiescence boundary without
reading state inside the adapter or changing any live artifact. A bounded
diagnosis established that:

- the Windows process could acquire all five runtime locks simultaneously in
  the required order;
- the two request budgets contained no active leases; and
- PHP represented each empty associative `leases` map as the JSON array `[]`.

The PowerShell adapter had treated only an empty JSON object as a valid empty
map. It therefore rejected the runtime-compatible PHP representation and
reported the deliberately coarse `quiescence` failure code. The correction
accepts `[]` only when the array is empty; non-empty arrays remain invalid. The
same compatibility rule is applied to the later module adapter's runtime maps.

The corrected source was then requalified under Windows PowerShell 5.1 in Gate
86 with an explicit PHP-empty-map scenario, the existing lossless-adoption and
lock-recovery checks, and the forced byte-exact rollback.

## Result

The corrected live preflight returned exit code `0` in one attempt. It verified:

- the checksum-protected bundle and Windows PowerShell 5.1 parser;
- the unchanged installed empty-target channel baseline;
- the unchanged active-package identity;
- authoritative source format 1 and candidate format 2;
- two preserved selection records;
- independently derived source, candidate and semantic hashes; and
- an unchanged authoritative state and active package after preflight.

The adapter reported no transaction creation, state mutation or rollback. The
wrapper and adapter also reported no adoption, channel or allowlist mutation,
module reload, Symcon RPC, provider contact, publication or cleanup attempt.
The private hashes, paths and installation identity remain only in the retained
protected Windows evidence and are intentionally omitted here.

## Remaining gates

1. Repeat the target-allowlist preflight and require
   `adapterPreflightReady: true` before considering target installation.
2. Keep target installation, channel `probe`, inactive `stage`, module
   `preflight`, `activate`, independent UI/Safari health, rollback retention,
   publication and evidence cleanup as separate authorizations.

Gate 87 did not authorize the later adoption recorded by Gate 88 or any of the
remaining actions.
