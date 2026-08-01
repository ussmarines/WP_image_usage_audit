# Phase 0 — Initial state

Recorded on 2026-08-01 before repository changes, against commit
`798bee520a69609cd98960aca483aa787273b093`.

## Local repository

- Canonical remote: `https://github.com/ussmarines/WP_image_usage_audit.git`
- Default and starting branch: `main`
- Starting state: clean and synchronized with `origin/main`
- Working branch created for this review: `security/repository-hardening`
- Plugin version: 2.2.6
- Declared compatibility: WordPress 5.9+ and PHP 7.4+
- Runtime dependencies: none; Composer and npm dependencies are development-only
- Distribution: allow-listed ZIP built by `scripts/build-zip.ps1`
- WordPress.org status: not published when last verified by the project baseline

The project-specific map identifies a standalone WordPress plugin at the repository root. The generic
WordPress triage and plugin detectors returned `unknown`/zero plugins because they expect a conventional
`wp-content/plugins` layout or a different root-plugin signal. The project map and the plugin header remain
the authoritative classification; this detector limitation is not a runtime defect.

## GitHub repository

- Repository: public, active, and administered by the authenticated `ussmarines` account
- Default branch: `main`
- Merge methods initially allowed: merge commit, squash, and rebase
- Delete head branches after merge: disabled
- Active branches: `main` and two Dependabot branches
- Open pull requests:
  - #12 updates `@wordpress/env` from 11.10.0 to 11.11.0
  - #13 updates the GitHub Actions dependency group
- Both Dependabot PRs pass actionlint, PHP 7.4, PHP 8.3, WordPress 5.9, and multisite,
  but fail the `wordpress-smoke` job. They are not treated as safe to merge.
- Latest `main` QA run at the baseline commit: successful (`29252973299`)

## Existing automation

- Repository workflow: `.github/workflows/qa.yml`
- Stable job names observed: `actionlint`, `PHP 7.4`, `PHP 8.3`, `wordpress-smoke`,
  `wordpress-59`, and `wordpress-multisite`
- Dependabot ecosystems configured: Composer, npm, and GitHub Actions
- GitHub Actions are enabled for all actions
- Repository-level SHA-pinning enforcement: disabled
- Default `GITHUB_TOKEN` permission: read-only
- Actions may create or approve pull request reviews: disabled
- Third-party workflow actions in the existing QA workflow are pinned to full commit SHAs;
  the actionlint container is pinned by image digest

## Initial security settings

| Control | Initial state | Evidence |
| --- | --- | --- |
| Dependency graph / vulnerability alerts | Disabled | Vulnerability-alerts API returned disabled |
| Dependabot alerts | Disabled | Dependabot alerts API returned HTTP 403 with the feature disabled |
| Dependabot security updates | Disabled | Repository `security_and_analysis` status |
| Automated security fixes | Disabled | Repository automated-security-fixes API |
| Secret scanning | Enabled | Repository `security_and_analysis` status |
| Push protection | Enabled | Repository `security_and_analysis` status |
| Non-provider secret patterns | Disabled | Repository `security_and_analysis` status |
| Secret validity checks | Disabled | Repository `security_and_analysis` status |
| Open secret-scanning alerts | None returned | Secret-scanning alerts API |
| Code scanning | No analysis found | Code-scanning alerts API returned HTTP 404 |
| Private vulnerability reporting | Enabled | Private-vulnerability-reporting API |
| Rulesets | None | Repository rulesets API |
| `main` branch protection | None | Branch protection API returned HTTP 404 |

## Access and repository integrations

- Collaborators returned by the repository API: one administrator, `ussmarines`
- Deploy keys: none
- Webhooks: none
- Actions secrets: none
- Dependabot secrets: none
- Actions variables: none
- Environments: none
- GitHub App enumeration could not be completed with the current user token; the API requires a token
  authorized to a GitHub App. This is retained as a Phase 1 manual/API limitation rather than inferred as
  “no apps installed.”

No secret value was queried or recorded.

## Existing QA baseline reused

`.codex/test-ledger.json` records a passing release gate for version 2.2.6 at the current source lineage,
including PHP 7.4/8.3 syntax, PHPCS/WPCS/PHPCompatibilityWP, PHPStan 2, 39 PHPUnit tests and 131 assertions,
Composer/npm audits, metadata/config validation, actionlint, deterministic POT generation, ZIP inspection,
and common secret-pattern scans. The successful `main` GitHub Actions run additionally covers WordPress
5.9.13, WordPress 7.0.1, multisite, AJAX/runtime smoke checks, Plugin Check, activation, and uninstall.

These results remain baselines only for unchanged covered files and configurations. Later phase changes will
invalidate and rerun the smallest applicable checks first.

## Initial risks and gaps

1. `main` has no branch protection or ruleset.
2. Dependabot alerts and security updates are disabled despite an existing Dependabot configuration.
3. GitHub Actions accepts all actions and does not enforce SHA pinning at repository level.
4. No Dependency Review or code-scanning workflow exists.
5. Two automated dependency PRs are red because `wordpress-smoke` fails.
6. The synchronous plugin scan remains a documented availability risk on very large sites.
7. GitHub App access requires a manual or differently authenticated inspection.

No code vulnerability is asserted by this initial-state phase.
