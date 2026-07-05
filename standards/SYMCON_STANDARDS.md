# Symcon Standards

Status: Draft

## 1. Object references

Reusable code must avoid scattered hardcoded object IDs.

Preferred order:

1. Ident below a known parent
2. configured object path
3. explicit configuration value
4. hardcoded object ID only for local/private scripts

## 2. Variable writes

Use `RequestAction()` for controllable device or module variables.

Use `SetValue()` only for internal script-owned state, calculated values, cache values, or variables without action semantics.

## 3. Event creation

Automatically created events must be identifiable and safe to recreate.

For script-executing events in Symcon 6.0+, set the event action binding in addition to trigger and script assignment.

## 4. Logging

Default logging should include errors and important warnings only.

Debug logging must be optional.

## 5. Archive access

Archive reads and writes must be bounded and should use block processing for large data sets.
