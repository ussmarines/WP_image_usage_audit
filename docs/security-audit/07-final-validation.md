# Phase 9 — Final validation

Status: complete for the audited implementation and its merge on 2026-08-01. Pull request 16 merged
only after its required checks passed. Post-merge workflows on `main` also completed successfully.

## Validated source state

- Baseline: `798bee520a69609cd98960aca483aa787273b093` on `main`.
- Final pull-request SHA: `07d369984a4aa05cdfe74ccc037edafd807539dc` on
  `security/repository-hardening`.
- Merge commit: `8437b5441800ac7415fdccd74df1bc739627cbb2` on `main`.
- Pull request: <https://github.com/ussmarines/WP_image_usage_audit/pull/16>.
- Final pull-request QA run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698403309>.
- Final pull-request CodeQL run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698403303>.
- Final pull-request Dependency Review run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698403301>.
- Post-merge QA run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698479350>.
- Post-merge CodeQL run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698479354>.
- Post-merge Scorecard run: <https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30698479339>.

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

All checks for final pull-request SHA `07d3699` completed successfully:

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
- Dependabot and secret scanning have zero open alert after the merge.
- CodeQL has zero open finding. The post-merge Scorecard SARIF upload created eight code-scanning
  posture alerts; these are classified separately below and are not CodeQL vulnerability findings.
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

## Post-merge Scorecard triage

The first Scorecard run on hardened `main` passed and produced eight repository-posture alerts. Each
was reviewed rather than treated as a plugin vulnerability:

| Check | Score | Disposition |
| --- | ---: | --- |
| Branch Protection | 4/10 | Residual: zero required approvals is intentional for the current single-maintainer repository; PRs, green checks, resolved conversations and strict updates remain mandatory. |
| Security Policy | 9/10 | Remediated in the follow-up: `SECURITY.md` now documents acknowledgement, triage and coordinated-disclosure targets of 7, 14 and 90 calendar days. |
| Fuzzing | 0/10 | Residual: no stable, meaningful fuzz harness exists for this small WordPress plugin; targeted unit and integration coverage remains the proportionate control. |
| SAST | 8/10 | Historical metric: CodeQL was introduced during this audit and has not analyzed older commits; current eligible JavaScript changes are covered. |
| Maintained | 0/10 | Time-bound metric: the repository was created less than 90 days ago. |
| Code Review | 0/10 | Structural metric: a sole maintainer cannot provide independent approval; self-review is not represented as independent review. |
| CII Best Practices | 0/10 | Informational: no OpenSSF Best Practices badge is claimed. |
| CI Tests | 8/10 | Historical metric: 8 of 9 sampled merged changesets had tests; the active ruleset now requires the complete QA matrix prospectively. |

The seven residual alerts are explicitly accepted as structural, historical, time-bound or
proportionate limitations. The Security Policy content deficiency is the only directly actionable
alert identified by this Scorecard run and is addressed without changing the repository's support
scope or disclosure channel.

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
