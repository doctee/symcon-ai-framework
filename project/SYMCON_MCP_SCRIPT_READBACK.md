# Symcon MCP Script Read-Back Workflow

## Purpose

The connected Symcon MCP server provides direct script-content read-back and a
bounded text-execution result channel. Direct read-back is read-only. Text
execution runs supplied PHP and may be used only for a bounded probe whose code
and authorized purpose are read-only with respect to the installation.

This workflow is operational guidance. It must not be used to persist private
script content or installation metadata in public artifacts.

## Preferred read-back workflow

1. Obtain authorization for the target and purpose.
2. Confirm that the required Symcon MCP tools are callable before selecting any
   other live-system tool.
3. If the expected tools are not visible, repeat complete tool discovery once.
4. If they remain unavailable, stop and report that the Symcon MCP binding is
   missing. Do not silently change channels.
5. Read authorized source directly with `symcon_get_script_content`.
6. Inspect the result only in transient working context.
7. Do not execute or modify the target merely to read it.
8. Do not reproduce source or installation metadata in public SAEF artifacts.

For small authorized probes that require captured output or a structured return
value, use `symcon_run_script_text_ex` only after reviewing the supplied PHP for
side effects. Keep output bounded and inspect `transportError` and
`executionError` separately. A successful MCP transport may still contain a PHP
execution error.

Set `maxOutputBytes` according to the expected result size and always inspect
`truncated`. Truncation is UTF-8-safe, but bytes beyond the configured limit are
intentionally discarded.

## Safety constraints

- Obtain authorization for the target and purpose before reading source.
- Prefer direct read-only MCP operations over stateful marker workarounds.
- Treat `symcon_run_script_text_ex` as code execution, not as source read-back.
  Its probe text must not mutate production state.
- Do not return or record object IDs, object names, topics, hostnames,
  credentials or source content in public SAEF documents.
- If an explicitly authorized fallback requires a temporary object, use a
  unique name and keep its lifetime as short as possible.
- Do not alter the target script content or execute the target merely to read
  it.
- Treat cleanup verification as part of the operation, not as an optional final
  step.
- For inventory work, derive sanitized aggregates in Symcon where practical and
  read back only those aggregates.

## Legacy fallback

If direct read-back is temporarily unavailable, do not silently recreate the
former marker-variable workflow. Creating temporary variables or scripts is a
live-system mutation and requires explicit user authorization, immediate
cleanup and verification that no temporary object remains.

Computer Use, browser access, SSH and PowerShell are likewise separate live
channels, not implicit MCP substitutes. Report the MCP failure first and use
one of those channels only after the user has explicitly authorized the named
fallback and its scope.

## Required result validation

For every `symcon_run_script_text_ex` result, record or explicitly verify all
three independent conditions:

- `transportError` is empty;
- `executionError` is empty; and
- `truncated` is `false`.

A transport success with a PHP execution error is a failed probe. Correct the
probe or stop; never interpret it as valid live evidence.

## Longer read-only probes

Keep `symcon_run_script_text_ex` probes small and bounded. If an analyzer cannot
reasonably run through that channel, a temporary script is a live-system code
mutation and may be used only after explicit authorization:

1. obtain explicit user authorization because creating a temporary script is a
   live-system code mutation;
2. create a clearly named temporary PHP script in an approved container;
3. set complete script content including the PHP opening tag;
4. execute it once and read only a sanitized aggregate marker;
5. delete the temporary script and marker immediately;
6. verify through MCP that both objects no longer exist.

This fallback does not broaden the authorized task. The temporary script must
remain read-only with respect to production objects and must not execute the
caller scripts being analyzed.
