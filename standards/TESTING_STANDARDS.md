# Testing Standards

Status: Draft

## 1. Document expected behavior

Helpers and templates should describe expected inputs, outputs, and edge cases.

## 2. Prefer reproducible tests

Where possible, test cases should be executable without a live private Symcon installation.

## 3. Live-system tests

Tests requiring a real Symcon installation must be clearly marked as integration tests.

## 4. Safety

Tests must not unexpectedly switch real devices or modify production state.
