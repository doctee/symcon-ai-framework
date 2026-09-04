# Symcon AI Engineering Framework

The Symcon AI Engineering Framework (SAEF) is a structured engineering
knowledge base for professional, AI-assisted IP-Symcon development.

SAEF is not a prompt collection. It combines engineering principles,
architecture decisions, standards, reusable knowledge, reference
implementations, helper libraries, implementation prompts and agent
instructions into a consistent development framework.

## Goals

SAEF helps engineers and AI coding agents create Symcon solutions that are:

- reliable,
- maintainable,
- reusable,
- reviewable,
- safe for real installations,
- compatible with AI-assisted development.

## Repository Structure

| Path | Purpose |
| --- | --- |
| `project/` | Project charter and framework-level project documents |
| `principles/` | Engineering and AI principles |
| `adr/` | Architecture Decision Records |
| `standards/` | Stable engineering, documentation, PHP and testing standards |
| `drafts/` | Larger artifacts currently under development and review |
| `knowledge/` | Reusable engineering knowledge and design guidance |
| `helpers/` | Reusable PHP helper functions for IP-Symcon engineering patterns |
| `templates/` | Reusable script and artifact templates |
| `references/` | Complete reference implementations |
| `prompts/` | Reusable implementation prompts for SAEF development workflows |
| `glossary/` | Shared terminology |
| `private/` | Local-only private overlay, excluded from Git |

## Start Here

For humans:

1. `project/AI_PROJECT.md`
2. `ARCHITECTURE.md`
3. `principles/ENGINEERING_PRINCIPLES.md`
4. `standards/README.md`
5. `knowledge/README.md`
6. `references/README.md`

For AI coding agents:

1. `AGENTS.md`
2. `project/AI_PROJECT.md`
3. `ARCHITECTURE.md`
4. Relevant standards, ADRs, knowledge articles and reference implementations

## Current Core Artifacts

Important current artifacts include:

- `AGENTS.md`
- `ARCHITECTURE.md`
- `project/AI_PROJECT.md`
- `project/SYMCON_MCP_SCRIPT_READBACK.md`
- `project/WORKSTREAM_COORDINATION.md`
- `project/SYSTEM_FUNCTIONS_CANDIDATE_INVENTORY.md`
- `project/SYSTEM_FUNCTIONS_MIGRATION_WAVE_1.md`
- `project/SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md`
- `project/SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md`
- `project/SAEF_V0_2_PUBLIC_API_AUDIT.md`
- `project/SAEF_V0_2_CHANGE_INVENTORY.md`
- `project/SAEF_V0_2_RELEASE_READINESS.md`
- `project/SAEF_V0_3_SCOPE.md`
- `project/SAEF_V0_3_RELEASE_READINESS.md`
- `project/SAEF_V0_4_SCOPE.md`
- `project/SAEF_V0_4_REPOSITORY_RECONCILIATION.md`
- `project/SAEF_V0_4_PUBLIC_API_AUDIT.md`
- `project/SAEF_V0_4_RELEASE_READINESS.md`
- `project/SAEF_V0_5_INVENTORY.md`
- `project/SAEF_DEPLOYMENT_CHANNEL_SECURITY_GATE.md`
- `project/STANDALONE_MODULE_DEPLOYMENT_CHANNEL.md`
- `adr/ADR-0005-generate-symcon-helper-bundles.md`
- `bundles/symcon/ensure-variable.bundle.json`
- `principles/ENGINEERING_PRINCIPLES.md`
- `principles/AI_PRINCIPLES.md`
- `standards/SYMCON_STANDARDS.md`
- `helpers/diagnostics/ConfigurationHash.php`
- `helpers/diagnostics/Registry.php`
- `helpers/diagnostics/Statistics.php`
- `helpers/diagnostics/ErrorRingBuffer.php`
- `knowledge/EK-001-state-machines.md`
- `knowledge/EK-002-retry-mechanisms.md`
- `knowledge/EK-003-archive-processing.md`
- `knowledge/EK-004-internal-state-management.md`
- `knowledge/EK-005-idempotent-configuration.md`
- `knowledge/EK-006-runtime-diagnostics.md`
- `knowledge/EK-007-managed-runtime-mirrors.md`
- `adr/ADR-0006-managed-symcon-runtime-mirrors.md`
- `adr/ADR-0007-use-restricted-windows-deployment-channel.md`
- `adr/ADR-0009-use-target-bound-standalone-module-deployment.md`
- `deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1`
- `deployments/symcon/windows/Invoke-SaefRuntimeMirror.ps1`
- `deployments/symcon/windows/SaefRuntimeSourceMirror.php`
- `deployments/symcon/windows/saef-deploy`
- `tools/build-symcon-module-deployment-package.php`
- `tools/publish-symcon-module.php`
- `deployments/symcon/publication/README.md`
- `adr/ADR-0008-use-manifest-driven-module-publication.md`
- `case-studies/media-carousel/README.md`
- `dist/symcon/saef-media-carousel-module/`
- `references/RI-001-idempotent-configuration-script.md`
- `references/RI-002-runtime-diagnostics-internal-state.md`
- `prompts/IMPLEMENT_REFERENCE.md`

## Private Data

Private installation data must never be committed.

This includes:

- credentials,
- tokens,
- private IP addresses,
- hostnames,
- personal IP-Symcon ObjectIDs,
- private MQTT topics,
- local system descriptions.

Private data belongs only in:

- `private/`
- `*.local.*`
- `.env*`

## Development Status

SAEF is an operational pre-1.0 engineering platform under active development.

The repository contains the project foundation, initial engineering knowledge
articles, reusable helpers, templates and reference implementations.

`standards/SYMCON_STANDARDS.md` is the current stable draft Symcon Reference
Standard for SAEF (`Stable Draft 1.0`). The earlier draft remains available in
`drafts/SYMCON_STANDARDS.md` for comparison.

Versions `v0.2.0`, `v0.3.0` and `v0.4.0` are published and immutable. Their
dated scope and release-readiness documents remain historical evidence.
Version `v0.4.0` adds the manifest-driven standalone-module publisher,
complete module distributions, worktree-isolated tooling, safer object
mutation, serialized Statistics updates and substantial MediaCarousel,
Open-Meteo, Navimow, ControlLight and MQTT case-study evolution.

Current `main` starts the post-v0.4 development line. The initial v0.5
inventory admits no feature automatically: GitHub issue #1 is the only
confirmed public engineering candidate. Its bounded latest-command-wins
architecture, live inventory and deterministic offline implementation are now
complete, while all live gates remain closed. Live
Symcon operations, standalone-module publication, private observations and
local retention remain separate gates. See `project/SAEF_V0_5_INVENTORY.md`
for the current intake and explicit non-commitments.

The Diagnostics helper set covers configuration hashes, Registry metadata,
Statistics and bounded error ring buffers; `RI-002` demonstrates their
composition. The restricted deployment channel uses a hash-pinned OpenSSH
forced command on Windows and the same SSH protocol from macOS or a suitable
iPhone/iPad terminal. It does not expose a general remote PowerShell session.

## Licensing

Unless a file or third-party notice states otherwise, original SAEF repository
content is available under the
[PolyForm Noncommercial License 1.0.0](LICENSE).

Free use includes:

- personal study, research, experiments and hobby projects without anticipated
  commercial application;
- charitable and other noncommercial organizations;
- educational institutions;
- public research organizations;
- public safety, health and environmental protection organizations;
- government institutions.

A separate written commercial license is required for:

- any use by a for-profit business, including purely internal use;
- use by a sole proprietor or freelancer in a professional context;
- paid consulting, installation, integration, training or support;
- commercial hosting, distribution, resale or incorporation into a paid
  product or service.

An otherwise eligible noncommercial or public organization may use SAEF free of
charge. A commercial contractor working for that organization still requires
its own commercial license.

See [COMMERCIAL-LICENSE.md](COMMERCIAL-LICENSE.md) for the commercial licensing
policy. SAEF is source-available software and is not OSI-approved open source,
because commercial use is restricted.

Third-party material is not relicensed under SAEF terms. Its original notices
and licenses continue to apply.

This overview is provided for orientation and does not replace the license
terms. In case of a conflict, `LICENSE` controls the public license grant. A
commercial license exists only through a separate written agreement.
