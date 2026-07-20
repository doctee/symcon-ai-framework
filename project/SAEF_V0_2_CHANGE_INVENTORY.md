# SAEF v0.2 Change Inventory

**Inventory date:** 2026-07-20
**Baseline:** `v0.1.0`
**Inventory base revision:** `de6194810e7365f767a9761e65b7552808e364fe`
**Purpose:** Pre-commit review, sanitization and commit planning

## Inventory Summary

The pre-commit working tree contained 252 changed public files, including this
inventory:

- 52 modified tracked files;
- 200 untracked files;
- 2,676 added and 247 removed lines in tracked files;
- approximately 1.75 MB of untracked public artifacts.

Git's short status collapses untracked directories and therefore shows fewer
status rows than files. The classification below uses `-uall`, assigns every
individual file exactly once and has no unmatched remainder.

## Commit Cohorts

### 1. Licensing and Governance

**Files:** 2

- `LICENSE`
- `COMMERCIAL-LICENSE.md`

Suggested commit:

```text
docs(license): adopt PolyForm noncommercial licensing
```

This cohort must be reviewed and accepted before release because it replaces
the v0.1 all-rights-reserved `LICENSE` and conflicting MIT Composer metadata
with one canonical public license.

### 2. Framework Documentation

**Files:** 13

Scope:

- `AGENTS.md`, `CONTRIBUTING.md`, `README.md` and `project/AI_PROJECT.md`;
- changed files in `standards/`, `knowledge/` and `prompts/`, except EK-007;
- helper-first, Runtime Diagnostics, live-system and contribution guidance.

Suggested commit:

```text
docs(framework): stabilize v0.2 engineering guidance
```

The README contains release links and may need a small final hunk in cohort 10.

### 3. Helper API and References

**Files:** 22

Scope:

- canonical files in `helpers/`;
- direct tests in `tests/helpers/`;
- `templates/ConfigurationScript.php` and `examples/ConfigurationScript.php`;
- RI-001 and RI-002.

Suggested commit:

```text
feat(helpers): stabilize diagnostics and object contracts
```

This cohort owns the public API, presentation policy, event correction,
bounded variable waiting and Diagnostics behavior. Stage only the matching
`composer.json` test-script hunk here; the file itself is assigned to cohort 9
for inventory accounting.

### 4. Deterministic Bundle Toolchain

**Files:** 15

Scope:

- ADR-0005;
- `bundles/`, `tools/build-symcon-*` and `tests/bundles/`;
- minimal `saef-ensure-variable` distribution artifacts;
- bundle design, readiness and smoke-test project records;
- `phpstan-bundle.neon`.

Suggested commit:

```text
feat(build): add deterministic Symcon helper bundles
```

Generated files must be built from the exact staged canonical source state.

### 5. System Functions Migration

**Files:** 4

Scope:

- System Functions candidate inventory;
- migration wave and pilot deployment plan;
- authorized MCP script read-back guidance.

Suggested commit:

```text
docs(migration): define System Functions adoption workflow
```

No inspected third-party or private helper source belongs in this cohort.

### 6. MQTT Discovery Exporter and Deployment

**Files:** 68

Scope:

- complete `case-studies/mqtt-discovery-exporter/` tree;
- matching tests under `tests/mqtt-discovery-exporter/`;
- MQTT fileset manifest and generated distribution;
- Windows restart adapter, policy, fixtures and tests.

Suggested commits:

```text
feat(case-study): add MQTT discovery exporter
feat(deployment): add deterministic MQTT deployment adapters
```

This cohort should be split into implementation/tests and chronological
engineering evidence if review size remains too large.

### 7. ControlLight Case Study

**Files:** 72

Scope:

- complete `case-studies/control-light/` tree;
- matching tests, fileset manifest and generated distribution;
- ADR-0006 and EK-007 for managed runtime mirrors.

Suggested commits:

```text
feat(case-study): add ControlLight migration runtime
docs(case-study): record supervised ControlLight migration evidence
```

Canonical runtime, tests and deterministic artifact should precede the
chronological live-system reports.

### 8. Navimow Evolution

**Files:** 46

Scope:

- changed Navimow module distribution and tests;
- Pause/Resume fixtures and command behavior;
- adaptive polling and command/publication evidence;
- reports 51 through 79 plus affected earlier summaries.

Suggested commits:

```text
feat(navimow): add bounded Pause and Resume command support
feat(navimow): add adaptive polling and recovery behavior
docs(navimow): record command and publication evidence
```

Navimow is independently releasable engineering work and must not be folded
into a generic SAEF helper commit.

### 9. Integration Tooling

**Files:** 6

Scope:

- `composer.json`, `Makefile`, `phpcs.xml`, `phpstan.neon`;
- `stubs/symcon.php` and `tools/lint.php`.

Suggested commit:

```text
test: integrate v0.2 verification suites
```

These files contain cross-cohort changes. Use hunk staging where a preceding
cohort needs its own executable test command, then leave the aggregate check
and static-analysis wiring for this integration commit.

### 10. Release Metadata

**Files:** 4

Scope:

- `CHANGELOG.md`;
- this complete change inventory;
- v0.2 public API audit;
- v0.2 release-readiness report.

Suggested commit before the release identity switch:

```text
docs(release): add v0.2 API and readiness audits
```

The final version/date change belongs in a separate release commit only after
all other cohorts are reviewed and clean.

## Dependency Order

Recommended review and commit order:

1. licensing decision;
2. framework standards and guidance;
3. canonical helpers and references;
4. bundle toolchain;
5. System Functions migration documents;
6. MQTT implementation and deployment;
7. ControlLight implementation and evidence;
8. Navimow changes;
9. aggregate verification tooling;
10. audits, changelog and final release identity.

Do not stage all files at once. Shared files such as `README.md`,
`composer.json`, `Makefile` and `CHANGELOG.md` require deliberate hunk review.

## Sanitization Review

The pre-commit 252-file public tree passed the repository-specific heuristic
review:

- no occurrences of the known authorized live script IDs supplied during
  review;
- no RFC 1918 addresses or private-key blocks;
- no absolute user paths in artifacts; matching test strings assert their
  absence;
- no tracked file under `private/`, no `.env*` file and no `*.local.*` file;
- credential-shaped values are explicit test placeholders or redacted fixture
  values;
- Basic Authorization content is derived at runtime and contains no literal
  credential;
- device IDs are synthetic placeholders such as `DEVICE_001`;
- concrete URLs are public documentation, public repositories, public vendor
  endpoints, localhost or reserved invalid/test domains;
- long hexadecimal values are generated checksums or public commit identities.

The five-digit-number review found only:

- Symcon runlevel `10103`;
- synthetic test IDs `10001`, `20002` and `30003`;
- timer, byte, Kelvin and color-domain values.

No dedicated secret-scanning executable is installed in the current
environment. The heuristic result is therefore PASS for the current tree, with
a mandatory repeat on the final staged diff and preferably a dedicated secret
scanner in clean-checkout CI.

## Execution Result

The inventory was executed as 13 focused Conventional Commits for cohorts 1
through 9. Large case-study cohorts were split into implementation, deployment
and chronological evidence where appropriate. Cohort 10 adds the changelog and
release audits in a separate pre-identity commit.

The inventory did not authorize pushing, changing the final release identity
or creating a tag. Those actions remain governed by
`project/SAEF_V0_2_RELEASE_READINESS.md`.
