# Official Symcon JSON Schema Snapshots

These files are reviewed offline snapshots of the official IP-Symcon schemas
for module metadata. The source URLs and exact SHA-256 identities are recorded
in `manifest.json`.

Module metadata declares the public official URL in `$schema` so editors can
provide completion and diagnostics. SAEF CI validates against these local bytes
instead of downloading a floating remote dependency.

To update the snapshots:

1. download all four schemas from their official URLs;
2. review the semantic differences and minimum platform implications;
3. replace the snapshots and update `retrievedAtUtc` and every SHA-256 in
   `manifest.json`;
4. run `composer symcon-schema:check` and the complete `composer check`.

An update changes an external platform contract. Do not refresh these files as
an unrelated formatting or dependency change.

The current `librarySchema.json` enumerates `compatibility.version` only through
`6.2`, while the official library documentation permits an additional minimum
build date. SAEF modules that require IP-Symcon 8.1 therefore retain the latest
schema-supported version and also require the official 8.1 release date
(`2025-08-27`, Unix timestamp `1756252800`). This preserves the effective
minimum without weakening or locally rewriting the official schema snapshot.
