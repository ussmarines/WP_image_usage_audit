# Codex project rules

- Read `docs/codex/PROJECT_MAP.md` and `.codex/test-ledger.json` before changing the project. Do not rescan the whole repository when the project map is current.
- Use `git diff` to identify changes since the audited commit and inspect the complete diff before delivery.
- Preserve WordPress 5.9+ and PHP 7.4+ compatibility unless an explicit decision changes them. Project compatibility overrides the installed WordPress skills' newer target baseline.
- Follow the WordPress Coding Standards.
- Never delete, move, rewrite, or otherwise modify user media. Keep the plugin's behavior non-destructive.
- Preserve capability checks, nonces, input validation, sanitization, SQL safety, and late output escaping.
- Do not add a frontend framework or pipeline without a demonstrated need.
- Do not add a production dependency without justification; use development dependencies for QA tools.
- Never push directly to `main`.
- Do not bump the plugin version for documentation-only or CI-only changes.
- For every future release, synchronize the PHP plugin header, `IUA_VERSION`, `readme.txt` stable tag and changelog, and POT version/catalog.

## Test reuse protocol

Before modifying code, do not rerun a previously passing test when its tool, exact command, configuration, relevant environment, and covered files are unchanged since the tested commit. Treat that entry in `.codex/test-ledger.json` as the valid baseline, apply the requested change first, then run only checks affected by the new diff.

A pre-modification rerun is allowed only when the result is missing, failed, stale, or invalidated by a code, configuration, dependency, tool, command, or environment change. Record every executed check in `.codex/test-ledger.json` with its exact command, tool version, date, tested commit, result, duration, coverage, configuration, and environment signature.
