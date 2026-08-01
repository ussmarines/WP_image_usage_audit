# Phase 5 — Remediation

Status: complete on 2026-08-01.

## Outcome

Phase 4 confirmed no exploitable code vulnerability. One accepted data-minimization hardening item
was suitable for a small, behavior-preserving correction; one availability hardening item remains
explicitly deferred; the rejected recursion candidate required no code change.

## IUA-SEC-007 — uploads-relative orphan results

Before this phase, `IUA_Scanner::find_upload_images()` collected absolute paths and `find_orphans()`
returned them unchanged. The complete result was saved in the non-autoloaded `iua_usage_results`
option and included in the authorized scan response even though the current admin view does not
render the orphan list.

The correction keeps absolute normalized paths only for the in-memory comparison with attachment
files. After the difference is known, each orphan is required to remain beneath the normalized
uploads prefix and is converted to an uploads-relative path before the result leaves the scanner.
Empty or out-of-prefix values are discarded and duplicates are removed.

Example:

```text
C:/sites/example/wp-content/uploads/2026/08/orphan.jpg
→ 2026/08/orphan.jpg
```

This preserves the identity of the orphan inside uploads while avoiding retention or return of the
server's absolute base path. It does not read, write, move, rewrite or delete any media file.

### Regression coverage

`ScannerNormalizationTest::test_orphan_results_are_relative_to_uploads_directory()` creates a unique
temporary uploads-shaped directory and image fixture, invokes the private orphan calculation, and
asserts both the exact relative result and the absence of the temporary absolute prefix. Cleanup
removes only the explicit fixture and its newly created empty directories.

The unit bootstrap gained a minimal `wp_normalize_path()` test double matching the WordPress slash
normalization used by this path.

## IUA-SEC-006 — scan lease and exhaustive work

No code change was applied. The route remains restricted to `manage_options` plus its action nonce,
database work is batched, and concurrent requests are locked. A renewable lease or resumable
time-budgeted scan would require an architectural change and could reduce exhaustive-result
semantics. This stays documented as a low residual availability hardening item.

## IUA-SEC-008 — nested metadata recursion

No code change was applied because the candidate was rejected: the plugin exposes no source for an
attacker-controlled decoded object graph, and the validated in-scope source-to-impact path is absent.

## Affected validation

- PHP syntax: changed production and test PHP passed under PHP 8.3.33;
- targeted PHPUnit: 1 test, 4 assertions passed;
- complete PHPUnit: 40 tests, 135 assertions passed;
- PHPCS 3.13.5, WPCS 3.4.1 and PHPCompatibilityWP 2.1.8: passed;
- PHPStan 2.2.7: no errors;
- tracked patch whitespace: passed.

The Composer PHP platform remains 7.4.0, the source uses PHP 7.4-compatible syntax, WordPress API
usage remains compatible with WordPress 5.9+, and no production dependency was added.

## Checklist

- [x] Minimize orphan-path retention without changing scan classification
- [x] Add a focused regression test
- [x] Run syntax, unit, coding-standard and static-analysis checks affected by the diff
- [x] Preserve non-destructive media behavior
- [x] Record the deferred availability hardening and rejected candidate

Next phase: audit and harden GitHub Actions and the release supply chain.
