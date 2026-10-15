# MCP Workflow Decision Examples

Run `composer test:mcp-workflow` or `make test-mcp-workflow`.
The suite is also included in `composer check`.

This is an executable specification for reviewing result evidence, not a new
MCP wrapper, deployer, runtime API or authorization engine. It reads synthetic
fixtures and local documentation only. It has no network, process-dispatch,
filesystem-write or cleanup operations.

The fixtures contain representative response envelopes and explicit review
annotations. Fields such as `semanticVerdict`, `complete` and
`allContentReviewed` belong to the example reviewer, NOT to the Symcon API.
They do not automatically parse free text or prove that all response blocks
were reviewed. A real review must establish those facts from the complete
tool result and independent checks; it cannot assume them from this fixture.

Cases cover native/legacy distinction, optional native error flags, saved
invalid PHP, file/folder lint, semantic errors despite OK, missing evidence,
timeout, readback drift, metadata, partial reload failure, domain regression
and unproven rollback. The edit example is synthetic and does not claim a
live-tested server result. Missing/invalid evidence sweeps guard against
truthiness-based acceptance. Local Markdown links are checked as well.

These tests do not qualify any Symcon build, enforce production permissions,
prove test isolation, inspect an installation or demonstrate physical safety.
No native tool implementation is included. Current schemas and separately
authorized integration evidence remain necessary before live use.
