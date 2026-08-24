# 341 Task Observation Ledger Standalone Publication

**Case study:** Navimow native IP-Symcon module

**Status:** Published and integrated

**Date:** 2026-08-24

## 1. SAEF Publication

The complete task-ledger candidate was published as SAEF PR #63 from commit
`5adbea1079b139a45e181ca0aaa976127d261327`. Both GitHub validation jobs passed,
the PR was merged by merge commit and canonical `origin/main` was independently
verified at `c5de90d970eead8f2cb0c8140f3ee8aab8cff93e`.

The generated Navimow distribution on canonical main is byte-equal to the
reviewed candidate.

## 2. Standalone Publication

The generic manifest-driven publisher used immutable preconditions:

```text
standalone base:       6f8a6a9e139b64881eadd6527b5f7b883bf2f3df
fileset SHA-256:       785c7b365b1818ab4e1af7a13c1518d88e233074ca87f523bfb35246155cafba
publication SHA-256:   084997725728794cc3990ccf959cdfef8ece6d1630ce27106f7e06bd0744f191
publication head:      342c1f2d3e05099e1e980540f415cfa59703a099
```

It created standalone PR #1 with exactly six changed targets: the new ledger
library, Account integration, README, license and deterministic fileset
sidecars. No check suite is configured in the standalone repository; the
publisher recorded `checkCount=0` explicitly rather than assuming success.

The separately authorized integration phase merged the PR and independently
cloned and byte-verified standalone main at
`865ed9230973aa3a84af4464bae2f3f59de0fab9`.

## 3. Boundary

No tag, Symcon mutation, MQTT activation, OAuth action, restart or mower command
was part of either repository publication. REST authority and receive-only MQTT
semantics are unchanged.
