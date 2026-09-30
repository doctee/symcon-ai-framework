# Rust lifecycle reference compatibility

The module previously queried its own references through the global
`IPS_GetReferenceList()` function during `ApplyChanges()`. Symcon's Rust kernel
can reject this as a reentrant call while the instance is already executing.

Reference reconciliation now uses the documented module method
`$this->GetReferenceList()`. The returned references are reconciled with the
existing desired set; configuration, object identity and archive behavior are
unchanged. The runtime fake rejects the native self-query to prevent regression.

The generated module fileset contains the same correction. OwnTracks retains
its existing target-bound SAEF package ownership and update channel; this fix
does not migrate the installation to Git Module Control. Package and source
identities provide the revision binding for this correction.
