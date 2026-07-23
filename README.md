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
- `project/SYSTEM_FUNCTIONS_CANDIDATE_INVENTORY.md`
- `project/SYSTEM_FUNCTIONS_MIGRATION_WAVE_1.md`
- `project/SYSTEM_FUNCTIONS_PILOT_DEPLOYMENT_PLAN.md`
- `project/SAEF_SYMCON_BUNDLE_BUILD_DESIGN.md`
- `project/SAEF_V0_2_PUBLIC_API_AUDIT.md`
- `project/SAEF_V0_2_CHANGE_INVENTORY.md`
- `project/SAEF_V0_2_RELEASE_READINESS.md`
- `project/SAEF_V0_3_SCOPE.md`
- `project/SAEF_V0_3_RELEASE_READINESS.md`
- `project/SAEF_DEPLOYMENT_CHANNEL_SECURITY_GATE.md`
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
- `deployments/symcon/windows/Invoke-SaefDeploymentGateway.ps1`
- `deployments/symcon/windows/Invoke-SaefRuntimeMirror.ps1`
- `deployments/symcon/windows/SaefRuntimeSourceMirror.php`
- `deployments/symcon/windows/saef-deploy`
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

SAEF is currently in early development.

The repository contains the project foundation, initial engineering knowledge
articles, reusable helpers, templates and reference implementations.

`standards/SYMCON_STANDARDS.md` is the current stable draft Symcon Reference
Standard for SAEF (`Stable Draft 1.0`). The earlier draft remains available in
`drafts/SYMCON_STANDARDS.md` for comparison.

Version 0.2.0 has passed the current local engineering gates. Its scope includes
Runtime Diagnostics, deterministic bundles and filesets, managed runtime
mirrors, migration guidance and the MQTT, ControlLight and Navimow case studies.
The Diagnostics helper set covers configuration hashes, Registry metadata,
Statistics and bounded error ring buffers; `RI-002` demonstrates their
composition.

The clean-checkout and CI gates in
`project/SAEF_V0_2_RELEASE_READINESS.md` are complete. Version `v0.2.0` was
published on 2026-07-20 from the annotated tag at release revision `be193aa`.
The current `main` branch contains subsequent unreleased work.

Post-v0.2 development includes a restricted Windows deployment channel. It
uses a hash-pinned OpenSSH forced command on Windows and the same standard SSH
protocol from macOS or a suitable iPhone/iPad terminal; it does not expose a
general remote PowerShell session. Because this is a material new framework
capability in addition to the MQTT correction, `project/SAEF_V0_3_SCOPE.md`
classifies `v0.3.0` as the next recommended release target.

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
