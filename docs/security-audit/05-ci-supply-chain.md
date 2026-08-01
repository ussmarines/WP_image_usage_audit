# Phases 6–8 — CI, supply chain, rulesets, and releases

Phase 6 status: complete on 2026-08-01. Rulesets and release provenance continue in Phases 7–8.

## Phase 6 initial workflow state

The existing `QA` workflow already had strong foundations:

- top-level `contents: read` permission;
- no `pull_request_target`, privileged deployment, release, write permission or secret-consuming step;
- GitHub Actions pinned to 40-character commits with version comments;
- `setup-php` pinned to a verified commit;
- the actionlint container pinned by SHA-256;
- PHP 7.4/8.3, WordPress 5.9/current, multisite, npm audit, Plugin Check and ZIP tests.

The remaining gaps were:

- each `actions/checkout` left the workflow token persisted in `.git/config`;
- no workflow-level concurrency cancellation;
- the `actionlint` job had no timeout and used an older Docker image;
- Plugin Check was installed without an explicit version;
- no Dependency Review gate, CodeQL coverage for the supported JavaScript asset, Scorecard result, or
  repository-enforced workflow security validator;
- Dependabot version updates had no seven-day cooldown.

The token is read-only and fork pull requests receive no repository secrets, which limited impact,
but credential persistence remained unnecessary. Zizmor 1.27.0 reported five medium `artipacked`
warnings and three medium `dependabot-cooldown` warnings.

## QA workflow hardening

- Added workflow concurrency keyed by pull-request number or ref with stale-run cancellation.
- Added a five-minute timeout to `actionlint`; every QA job now has an explicit timeout.
- Set `persist-credentials: false` on all five QA checkouts.
- Replaced the Docker-only actionlint command with `scripts/run-actionlint.ps1`. The wrapper supports
  Windows x64 and Linux x64, downloads only actionlint 1.7.12, verifies the platform archive against
  a hard-coded official SHA-256, extracts inside a validated unique system-temporary directory, runs
  the binary, and removes only that directory.
- Pinned WordPress Plugin Check to 2.0.0 in the current WordPress lane.
- Added a separate Zizmor job using the official PyPI 1.27.0 Linux wheel URL and hard-coded SHA-256,
  installed without an index or dependencies inside a runner-temporary virtual environment.

The equivalent Windows Zizmor wheel used for local validation was obtained from the official PyPI
JSON metadata, verified as SHA-256
`debc723721172c170d5922171a40eaebf5787a02ff3e69d30597dafdb66a21ba`, and installed only in a
temporary virtual environment.

## Added security workflows

### Dependency Review

`dependency-review.yml` runs only on pull requests with `contents: read`, a ten-minute timeout,
concurrency cancellation, and `actions/dependency-review-action` 5.0.0 pinned to
`a1d282b36b6f3519aa1f3fc636f609c47dddb294`. It fails when a pull request introduces a high or
critical advisory and does not request permission to comment on the pull request.

### CodeQL JavaScript

`codeql.yml` analyzes only `javascript-typescript` with the extended security query suite. It runs on
relevant JavaScript/workflow pull requests and main pushes, weekly, and on manual dispatch. The job
has only `contents: read` and `security-events: write`, uses checkout without credentials, and pins
CodeQL 4.37.4 to `f205ea1c3313d32999d8d6a48b4f6530d4437b38`.

GitHub CodeQL does not support PHP. This workflow is therefore documented and named as JavaScript
coverage only; PHP remains covered by the manual audit, PHPCS/PHPCompatibility, PHPStan, PHPUnit,
Plugin Check and WordPress runtime tests.

### OpenSSF Scorecard

`scorecard.yml` runs for branch-protection changes, main pushes, a weekly schedule and manual
dispatch. The job scopes `contents: read`, `security-events: write`, and `id-token: write`; the OIDC
permission is limited to the Scorecard job and enables signed public result publication. Scorecard
2.4.4 is pinned to `2d1146689b8cda280b9bc96326124645441f03bc`. The SARIF is retained for five days
using `actions/upload-artifact` 7.0.1 pinned to
`043fb46d1a93c77aae656e7c1c64a875d1fc6a0a`, then uploaded through the pinned CodeQL action.

The repository selected-actions policy now explicitly permits `ossf/scorecard-action@*` alongside
`shivammathur/setup-php@*`; GitHub-owned and verified actions remain allowed, and SHA pin enforcement
remains enabled.

## Persistent validation controls

`scripts/validate-config.mjs` now discovers every JSON file outside generated dependencies and every
YAML file beneath `.github`. For each workflow it requires:

- top-level permissions and concurrency;
- an integer timeout for every job;
- every remote Action pinned to a full 40-character lowercase commit;
- `persist-credentials: false` on every checkout.

It also validates the Dependabot v2 updates mapping. The current result is 11 JSON files and five
YAML files.

## Dependabot supply-chain delay

Each Composer, npm and GitHub Actions updater now has `cooldown.default-days: 7`. This delays routine
version updates long enough for newly published malicious or broken releases to be noticed. GitHub
does not apply cooldowns to security updates, so advisory remediation is not delayed.

## Decisions on other controls

- Native secret scanning and push protection are enabled and reported no open secret alert. A second
  third-party Gitleaks Action was not added because it would expand the executable supply chain for
  overlapping coverage; secret scanning is rechecked in final validation.
- CodeQL was added only for the supported JavaScript source and is not claimed to cover PHP.
- Dependency Review is the malicious/vulnerable dependency change gate. General Dependabot
  auto-merge remains disabled.
- Artifact contents, checksum, release identity and provenance are intentionally handled in Phase 8.

## Phase 6 validation

- Verified the current and new Action commits through the GitHub commit API; signatures reported
  verified for checkout, setup-node, setup-php, Dependency Review, CodeQL and Scorecard commits.
- Initial Zizmor: eight medium warnings; final `zizmor .`: no finding (offline-only audits; 11 rules
  reported as suppressed/not applicable in offline mode).
- Initial Actionlint rejected the incorrect `branch_protection` event; changing it to
  `branch_protection_rule` made the final Actionlint run pass.
- `npm run validate:config`: pass, 11 JSON and five YAML files.
- The actionlint wrapper parsed and executed successfully on Windows with the verified archive.

## Phase 6 checklist

- [x] Review permissions, triggers, expressions, logs, timeouts and concurrency
- [x] Disable checkout credential persistence
- [x] Pin all Actions and downloaded audit binaries
- [x] Execute Actionlint and Zizmor; remediate every reported warning
- [x] Add Dependency Review
- [x] Add supported JavaScript-only CodeQL analysis
- [x] Add OpenSSF Scorecard with job-scoped publication permissions
- [x] Add Dependabot cooldowns and persistent workflow invariants
- [x] Document why native secret scanning is used without a duplicate Gitleaks action

## Phase 7 — `main` ruleset

Repository ruleset `Protect main` (ID `20181296`) is active and targets `~DEFAULT_BRANCH`, currently
`main`. It has no bypass actor, and GitHub reports that the current administrator can never bypass
it. The rules therefore apply consistently to maintainers and administrators.

The ruleset:

- requires every change to reach `main` through a pull request;
- requires zero approvals, avoiding an impossible self-approval requirement for the repository's
  single maintainer;
- requires all review conversations to be resolved;
- requires the pull-request head to be current with `main`;
- blocks branch deletion and non-fast-forward pushes;
- permits the repository's three enabled merge methods;
- requires only six stable QA contexts already observed passing together on `main`:
  `actionlint`, `PHP 7.4`, `PHP 8.3`, `wordpress-smoke`, `wordpress-59`, and
  `wordpress-multisite`.

The new `zizmor`, Dependency Review, CodeQL and Scorecard jobs were deliberately not made mandatory
before their first successful repository run. They remain active checks and can be promoted to
required status after their names and behavior have been confirmed on the hardening pull request.

GitHub's effective-rules endpoint for `main` returned the deletion, non-fast-forward, pull-request,
and strict required-status-check rules immediately after creation. No legacy branch protection was
present, so there is no overlapping or contradictory protection configuration.

## Phase 7 checklist

- [x] Require a pull request without an impossible approval requirement
- [x] Require resolved conversations and an up-to-date branch
- [x] Require only previously green, stable QA contexts
- [x] Block force pushes and deletion of `main`
- [x] Apply rules to administrators with no bypass
- [x] Verify the effective rules returned for `main`

Next phase: harden artifact identity, checksums and release provenance.
