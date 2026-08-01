# Image Usage Audit security review

This directory is the persistent record for the repository security review started on 2026-08-01.
The review combines conventional static analysis, repository QA, targeted tests, GitHub API evidence,
and manual WordPress data-flow analysis. It does not claim that the plugin can be made absolutely
invulnerable.

## Scope and constraints

- Repository: `ussmarines/WP_image_usage_audit`
- Baseline commit: `798bee520a69609cd98960aca483aa787273b093`
- Working branch: `security/repository-hardening`
- Compatibility to preserve: WordPress 5.9+ and PHP 7.4+
- Runtime behavior to preserve: the plugin never deletes, moves, rewrites, or otherwise modifies media
- Deep Security Scan: prohibited for this review
- Standard Codex Security scan: optional, targeted, and allowed only after every conventional phase is complete, committed, and pushed

## Persistent checklist

- [x] Phase 0 — Record the initial local and GitHub state; create the working branch
- [x] Phase 1 — Audit and harden GitHub security settings and access
- [x] Phase 2 — Audit Dependabot and locked Composer/npm dependencies
- [x] Phase 3 — Complete the targeted manual WordPress code audit
- [x] Phase 4 — Validate or reject every candidate finding
- [x] Phase 5 — Remediate confirmed findings and verify regressions
- [x] Phase 6 — Audit and harden GitHub Actions and the software supply chain
- [x] Phase 7 — Protect `main` with a practical repository ruleset
- [ ] Phase 8 — Verify ZIP and release provenance
- [ ] Phase 9 — Run final local and GitHub validation
- [-] Phase 10 — Optional targeted standard Codex Security scan; not required for validity
- [ ] Phase 11 — Open the pull request, monitor required checks, and merge only when green

## Status notation

- `[ ]` not started
- `[~]` in progress
- `[x]` complete
- `[!]` blocked
- `[-]` not applicable

## Evidence rules

- Findings begin as candidates and become confirmed only after the complete exploitation path is validated.
- Rejected findings retain a factual justification.
- Reports contain no credentials, secret values, or unnecessary private information.
- Passing checks are reused from `.codex/test-ledger.json` until a relevant source, configuration,
  dependency, tool, command, or environment change invalidates them.
- Every newly executed check is recorded in `.codex/test-ledger.json`.

## Reports

- [Initial state](01-initial-state.md)
- [GitHub security](02-github-security.md)
- [Dependencies](03-dependencies.md)
- [WordPress code audit](04-wordpress-code-audit.md)
- [CI and supply chain](05-ci-supply-chain.md)
- [Remediation](06-remediation.md)
- [Final validation](07-final-validation.md)
- [Machine-readable findings](findings.json)
