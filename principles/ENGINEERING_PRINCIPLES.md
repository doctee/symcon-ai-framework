# Engineering Principles

Status: Draft

This document defines the engineering principles behind the Symcon AI Engineering Framework.

## 1. Reliability before convenience

Automation code controls real systems. Reliability, predictability, and safe failure behavior are more important than short code or quick implementation.

## 2. Prefer explicit structure

Code and documentation should be structured so that both humans and AI assistants can understand intent, dependencies, and side effects.

## 3. Make decisions traceable

Important design decisions must be explained and, where appropriate, documented as ADRs.

## 4. Optimize for maintainability

Solutions should be easy to review, extend, migrate, and debug.

## 5. Reuse proven patterns

Recurring solutions should become helpers, templates, examples, or documented patterns.

## Reuse Before Extend

SAEF evolves by refining a small set of reusable building blocks rather than continuously introducing new abstractions.

Before adding any new public helper, API, pattern or other reusable component, contributors must first evaluate whether the requirement can be fulfilled by composing or extending existing SAEF functionality.

New public APIs should only be introduced when they encapsulate a recurring engineering pattern that cannot be expressed clearly and maintainably with the existing framework.

Convenience wrappers around one or two existing helper calls are generally not sufficient justification for a new public API.

Every reusable abstraction should demonstrably reduce duplication across multiple reference implementations. If an abstraction is only used by a single implementation, it should normally remain local until a recurring pattern has been established.
