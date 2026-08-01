# Phase 2 — Dependencies

Status: complete on 2026-08-01.

## Scope and exposure

The plugin ships no Composer or npm runtime dependency. Both lockfiles describe development and QA
tooling only. The affected packages therefore had no WordPress production request path, but they were
still relevant to local development, CI, archive handling, and analysis of repository-controlled input.

The baseline audits used the locks from commit
`2b9770e` on branch `security/repository-hardening`:

- Composer: one high advisory, WPCS 3.1.0 / CVE-2026-45293;
- npm: five high advisories affecting `adm-zip`, `brace-expansion`, `fast-uri`, and
  `fast-xml-parser`;
- GitHub Dependabot: four open high alerts corresponding to WPCS, `adm-zip`, `fast-uri`, and
  `fast-xml-parser` after repository alerts were enabled;
- direct versions behind their compatible line: `@wordpress/env` 11.10.0, PHPStan 2.2.5, and
  WPCS 3.1.0. PHPUnit 9.6.35 remains the supported PHP 7.4-compatible major.

## Dependency paths and decisions

| Package | Baseline path | Fixed version | Decision |
| --- | --- | --- | --- |
| `wp-coding-standards/wpcs` | direct Composer dev dependency | 3.4.1 | Updated to the first fixed release. |
| `squizlabs/php_codesniffer` | direct Composer dev dependency required by WPCS | 3.13.5 | Updated to satisfy WPCS 3.4.1. |
| `phpcsstandards/phpcsextra` / `phpcsutils` | transitive WPCS tooling | 1.5.1 / 1.2.3 | Updated by the constrained Composer resolution. |
| `phpstan/phpstan` | direct Composer dev dependency | 2.2.7 | Applied the available compatible patch update. |
| `adm-zip` | `@wordpress/env` | 0.6.0 | Forced with an npm override because `@wordpress/env` 11.12.0 still declares `^0.5.9`. Remove the override when upstream accepts 0.6.x. |
| `brace-expansion` | `@wordpress/env` → `rimraf` → `glob` → `minimatch` | 2.1.4 | Updated inside the existing compatible range. |
| `fast-uri` | `@wordpress/env` → Playground blueprints → `ajv` | 3.1.5 | Updated inside the existing compatible range. |
| `fast-xml-parser` | `@wordpress/env` → Playground CLI | 5.10.1 | Updated to the first fixed release. |
| `@wordpress/env` | direct npm dev dependency | 11.12.0 | Updated to the current direct version observed during the audit. |

No `npm audit fix --force` command was used. The compatible transitive npm fixes were applied with
the lockfile-aware non-force command. Composer retained `config.platform.php` at 7.4.0, and all
selected PHP packages remain resolvable for PHP 7.4.

## QA configuration corrections

- `phpcs.xml.dist` now sets PHPCompatibility's `testVersion` as a PHPCS configuration value. The
  previous sniff property did not populate the global setting under the updated PHPCS runtime.
- `.gitattributes` fixes PHP source checkout line endings to LF so WPCS results are consistent on
  Windows and Linux.
- Composer's PHPUnit script uses Composer's `@php` interpreter token, avoiding a Windows-only
  executable-resolution failure while preserving the same PHPUnit command.

These are tooling corrections; no plugin runtime behavior or media operation changed.

## Dependabot policy

The existing `.github/dependabot.yml` was extended rather than replaced. Composer, npm, and GitHub
Actions now each run weekly on Monday at 06:00 in `Europe/Paris`, use an open-PR limit of five,
apply ecosystem labels and a `build(deps)` commit prefix, and group only minor and patch updates.
Major updates remain isolated. The configuration contains no credential and enables no general
auto-merge policy.

## Validation

Passed after remediation:

- `composer validate --strict` with Composer 2.10.2;
- `composer audit --locked`: no advisory;
- `npm ci --ignore-scripts` and `npm audit`: zero vulnerabilities across 391 packages;
- resolved npm tree: `adm-zip` 0.6.0, `brace-expansion` 2.1.4, `fast-uri` 3.1.5,
  `fast-xml-parser` 5.10.1;
- PHPCS 3.13.5 / WPCS 3.4.1 / PHPCompatibilityWP 2.1.8;
- PHPStan 2.2.7: no error;
- PHPUnit 9.6.35: 39 tests and 131 assertions;
- repository metadata, JSON/YAML configuration, and Dependabot YAML parsing;
- `wp-env --version`: 11.12.0.

Final validation with npm 11.16.0 identified one dependency install script that was not covered by
an explicit project policy: `fs-ext-extra-prebuilt@2.2.7`, used transitively by WordPress Playground.
Its lockfile URL and SHA-512 integrity are fixed, and manual review confirmed that the script loads a
packaged platform binary or falls back to `node-gyp rebuild`; it does not download code. The exact
version is now approved in `package.json`, while `.npmrc` enables `strict-allow-scripts=true` so a
future unreviewed install script fails instead of merely warning. `npm ci` then completed with no
pending script and `npm audit` continued to report zero vulnerabilities.

The detailed commands, failed diagnostic attempts, environment signatures, and reruns are retained
in `.codex/test-ledger.json`.

## Residual considerations

- npm emits a deprecation warning for transitive `glob` 10.5.0. npm reports no advisory for the
  resolved graph, `npm outdated` reports no available direct update, and the path is
  `@wordpress/env` → `rimraf` → `glob`. Replacing it requires an upstream or major transitive change
  and is deferred.
- `phpcompatibility/php-compatibility` 9.3.5 is the newest stable release visible to Composer. Its
  pre-release 10.x line was not adopted.
- GitHub's existing Dependabot alerts will remain visible until the corrected lockfiles reach the
  default branch and GitHub refreshes the dependency graph. They must not be dismissed manually in
  the interim.

## Checklist

- [x] Inspect the existing Composer, npm, and GitHub Actions Dependabot entries
- [x] Record direct and transitive vulnerable paths
- [x] Apply minimal compatible fixes without force-updating npm
- [x] Preserve PHP 7.4 and WordPress 5.9+ compatibility constraints
- [x] Validate both lockfiles and affected QA tooling
- [x] Document residual upstream constraints

Next phase: file-by-file manual audit of the WordPress entry points and sensitive data flows.
