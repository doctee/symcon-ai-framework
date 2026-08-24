# 340 Task Observation Ledger Publication Readiness

**Case study:** Navimow native IP-Symcon module

**Status:** Ready for SAEF and standalone PR publication

**Date:** 2026-08-24

## 1. Candidate

The candidate combines SAEF steps 335 through 340, the bounded task ledger,
synthetic fixture and tests, the generated standalone module fileset and a new
manifest-driven Navimow publication contract.

It was built in the dedicated clean worktree
`private/worktrees/navimow-task-ledger` from current `origin/main`. The older
recovery worktree and every unrelated branch remain untouched.

## 2. Generic Publisher Integration

Navimow now uses `tools/publish-symcon-module.php`; no Navimow-specific
publisher or ad-hoc copy script is introduced. The generic module-fileset
builder permits the Navimow distribution root, and the new contract uses the
default pull-request mode.

The checked candidate contains 35 standalone files:

```text
fileset SHA-256:    785c7b365b1818ab4e1af7a13c1518d88e233074ca87f523bfb35246155cafba
publication SHA-256: 084997725728794cc3990ccf959cdfef8ece6d1630ce27106f7e06bd0744f191
```

The inventory is exact and includes module metadata, four modules, required
libraries, README, license and deterministic fileset sidecars. Unknown or
additional targets fail closed.

## 3. Verification

Passed from the isolated worktree:

- focused synthetic ledger and Account ingestion tests;
- full Navimow MQTT offline harness;
- Navimow distribution validation;
- deterministic fileset and publication checks;
- repository lint, PHPCS and PHPStan;
- complete `composer check`.

The worktree has no local `vendor/`; analysis deliberately used the canonical
lock-matched tool binaries from the primary checkout. This is toolchain reuse,
not source-tree mixing. Every analyzed source path remained inside the isolated
worktree.

## 4. Gates

The operator granted the grouped implementation and publication sequence.
SAEF branch publication and PR review can proceed. Standalone publication must
still be hash-bound to the committed source candidate and current standalone
main. A disabled Symcon update follows only after both repositories are
verified.

Natural Zone 2 and Zone 3 evidence cannot be manufactured by the release
workflow. Those receive-only observations remain pending until the official
schedule selects the respective app zone; no mower command is authorized or
required.
