# Phase 9 — Final validation

Status: complete for the audited implementation on 2026-08-01. Pull request 16 was open and green
when this report was written; the final merge outcome is reported in the task handoff after GitHub
creates the merge commit.

## Validated source state

- Baseline: `798bee520a69609cd98960aca483aa787273b093` on `main`.
- Implementation SHA: `af6cb454d75b4393c7c8763ba2989412aab33526` on
  `security/repository-hardening`.
- Pull request: <https://github.com/ussmarines/WP_image_usage_audit/pull/16>.
- QA run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698198089>.
- CodeQL run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698198080>.
- Dependency Review run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698198094>.

## Test-reuse decision

The repository test protocol prohibits rerunning an unchanged command over unchanged covered files.
The final review therefore reused these still-valid local results and allowed the pull-request matrix
to independently repeat them on GitHub's clean runners:

| Reused result | Tested state | Evidence |
| --- | --- | --- |
| Composer install, strict validation and locked advisory audit | Phase 2 dependency lock | Composer 2.10.2; no advisory |
| WPCS / PHPCompatibilityWP and PHPStan | Phase 5 production source | pass under portable PHP 8.3 with PHP 7.4 compatibility target |
| PHPUnit | Phase 5 production source and regression test | 40 tests, 135 assertions |
| PHP 7.4 and 8.3 syntax; JavaScript syntax | unchanged runtime source after Phase 5 | pass |
| ZIP construction, content inspection and checksum | Phase 8 artifact source | 11 entries; deterministic SHA-256; zero targeted secret marker |
| Actionlint, Zizmor and configuration invariants | final workflow content before the CI filename correction | pass; rerun after correction also passed |

No runtime PHP, JavaScript, Composer configuration or PHPUnit test changed after the corresponding
passing entry. Exact commands, environments, durations and covered files remain in
`.codex/test-ledger.json`.

## Final local checks executed

- `npm ci`: pass under Node.js 24.18.0 / npm 11.16.0 with strict lifecycle-script enforcement.
- `npm audit --json`: zero vulnerabilities across 391 resolved packages.
- `npm outdated --json`: no direct package update reported.
- `npm approve-scripts --allow-scripts-pending --json`: no unreviewed install script.
- `npm run validate:metadata`: pass for version 2.2.6, five tags and the short description.
- `node --check` for `assets/admin.js` and all repository `.mjs` scripts: pass.
- `npm run validate:config`: pass for 11 JSON and six YAML files.
- `npm run actionlint`: pass.
- verified Zizmor 1.27.0: no finding; offline mode listed 15 suppressed/not-applicable audits.
- `npm run build:zip`: pass; the output checksum is
  `bd6fcb85a74f38eb5cfa33c0f8f99699657f7d98f641eaafb2b4e0e674f84f0d`.
- a second build from the same source produced the identical digest.
- targeted scan of every packaged entry found no private-key, GitHub-token, AWS-key or assigned
  credential marker.
- correct release tag `v2.2.6` was accepted and mismatched tag `v2.2.5` was rejected.

The npm install-script review found one WordPress Playground transitive lifecycle script. The exact
`fs-ext-extra-prebuilt@2.2.7` script and lock integrity were inspected; it loads a bundled binary or
invokes local `node-gyp` and does not download code. Only that version is approved, and any new
unreviewed script now fails installation.

## Pull-request checks

All checks for implementation SHA `af6cb45` completed successfully:

| Check | Result | Coverage |
| --- | --- | --- |
| `actionlint` | pass | workflow syntax and semantics |
| `zizmor` | pass | workflow security and Dependabot policy |
| `PHP 7.4` | pass | Composer install/validate/audit, PHPCS, PHPStan, 40 PHPUnit tests, syntax, metadata and ZIP |
| `PHP 8.3` | pass | Composer install/validate, PHPStan, PHPUnit, PHP/JS syntax and metadata |
| `wordpress-smoke` | pass | npm audit/config, exact-ZIP install/activation, AJAX, runtime scan, Plugin Check 2.0.0, POT reproducibility and uninstall preservation |
| `wordpress-59` | pass | exact-ZIP install/activation and runtime smoke on WordPress 5.9.13 |
| `wordpress-multisite` | pass | network activation, secondary/deleted sites, exact ZIP, network state, uninstall cleanup and media/content preservation |
| `dependency-review` | pass | introduced dependency advisories at high/critical threshold |
| `analyze` / `CodeQL` | pass | JavaScript `security-extended` analysis and SARIF processing |

The first pull-request Zizmor job failed before analysis because the downloaded, checksum-verified
wheel was saved under a filename that did not preserve its Python wheel tags. pip rejected the name.
The workflow now retains the official canonical filename; local workflow checks and the replacement
GitHub job passed. No scanner finding was suppressed to obtain the green result.

## GitHub security state

- Dependabot security updates, secret scanning, push protection and private vulnerability reporting
  are enabled.
- Secret scanning has zero open alert; Code scanning has zero open alert.
- GitHub Actions uses selected sources, full-SHA enforcement, a read-only default token, and cannot
  approve pull requests.
- Future GitHub releases are immutable.
- Ruleset `Protect main` is active with no bypass. It requires a pull request, resolved conversations,
  an up-to-date branch, and eight green contexts: `actionlint`, `zizmor`, `dependency-review`,
  `PHP 7.4`, `PHP 8.3`, `wordpress-smoke`, `wordpress-59`, and `wordpress-multisite`. It blocks
  deletion and force pushes.
- CodeQL remains conditional on JavaScript or CodeQL-workflow changes, so its job is not a universal
  required check. Scorecard runs on `main`, schedule, manual dispatch and ruleset changes rather than
  on every pull request.
- Four high Dependabot alerts remain on the pre-hardening default branch. They correspond to lockfile
  versions already corrected on the pull request and are expected to close only after merge and
  dependency-graph refresh; they were not prematurely dismissed.

## Optional Codex Security scan

The optional standard Codex Security scan was not run. The deep/multi-pass scan was explicitly
prohibited, and the standard scan was neither required nor justified after the complete manual data-
flow review, conventional scanners, targeted regression test, and clean GitHub matrix. Skipping it
also respects the stated token-cost constraint and does not invalidate the recorded evidence.

## Residual risks and limits

- The scanner remains synchronous. Very large sites may still reach request time or memory limits;
  attachment state and the uploads inventory remain request-wide despite bounded database batches.
- Heuristic image detection can produce false positives and false negatives. Results are not proof
  that media can be safely deleted, and the plugin remains deliberately non-destructive.
- Transitive development package `glob` 10.5.0 is deprecated through
  `@wordpress/env` → `rimraf`; npm reports no advisory and no direct update is available.
- Historical release `v2.2.6` has a valid checksum and clean inspected contents but predates immutable
  releases and build attestations; the new controls apply prospectively.
- Zizmor's final local scan ran offline, so online-only audits were not evidence; the GitHub-hosted
  Zizmor job independently passed.
- CodeQL covers the JavaScript asset, not PHP. PHP was covered by manual review, PHPCS,
  PHPCompatibilityWP, PHPStan, PHPUnit, Plugin Check and WordPress integration tests.

The evidence supports merging the pull request when the required checks on its final documentation
commit are green. It does not establish that the plugin or repository is invulnerable.
