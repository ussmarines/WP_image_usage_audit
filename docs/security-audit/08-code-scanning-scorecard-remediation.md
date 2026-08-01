# Phase 12 — GitHub code-scanning and Scorecard remediation

Status: complete for the code-bearing merge `b6cd6f99a411e7313da28e681914ab9b12e7c7a8` on
2026-08-01. All pull-request and post-merge checks passed, the final Scorecard result was published,
and all seven alerts have a documented GitHub disposition.

This report treats the seven alerts that were open on commit
`41122772915682dc631aeff7c02001c014a13dbe`. All seven were produced by OpenSSF
Scorecard 5.5.0. No CodeQL alert was open, and no Codex Security scan, deep scan,
multi-agent scan, or multi-pass scan was used.

Sanitized before/after evidence is stored in
[`code-scanning-alerts-before.json`](code-scanning-alerts-before.json),
[`code-scanning-alerts-after.json`](code-scanning-alerts-after.json),
[`scorecard-before.json`](scorecard-before.json), and
[`scorecard-after.json`](scorecard-after.json). Authentication tokens and request headers are not
stored.

## Initial inventory

| Alert | Tool | Rule ID | Severity | Initial evidence | Category |
| ---: | --- | --- | --- | --- | --- |
| [1](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/1) | Scorecard 5.5.0 | `BranchProtectionID` | high | score 4; reviewer-related protections absent | single-maintainer structural limitation |
| [3](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/3) | Scorecard 5.5.0 | `FuzzingID` | medium | score 0; no recognized fuzzer | correctable test-coverage gap |
| [4](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/4) | Scorecard 5.5.0 | `SASTID` | medium | 13 of 25 commits had detected SAST | correctable prospective coverage plus historical limitation |
| [5](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/5) | Scorecard 5.5.0 | `MaintainedID` | high | repository younger than 90 days | temporal limitation |
| [6](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/6) | Scorecard 5.5.0 | `CodeReviewID` | high | 0 of 12 sampled changesets had independent approval | single-maintainer structural limitation |
| [7](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/7) | Scorecard 5.5.0 | `CIIBestPracticesID` | low | no OpenSSF Best Practices entry | manual self-certification required |
| [8](https://github.com/ussmarines/WP_image_usage_audit/security/code-scanning/8) | Scorecard 5.5.0 | `CITestsID` | low | 10 of 11 sampled merged PRs had detected CI | historical limitation |

GitHub reported `precision: null` for every rule. Every alert referred to
`refs/heads/main`, commit `41122772915682dc631aeff7c02001c014a13dbe`, and the
synthetic location `no file associated with this alert:1`. Creation, last-detection, documentation,
message, and alert URLs are retained in the sanitized JSON inventory.

## Reference Scorecard execution

- Published API result: OpenSSF Scorecard 5.5.0 (`c395761`) at commit `4112277`, global score 6.1.
- Check-specific CLI reproduction: the checksum-verified official Windows x64 release was run with
  `--show-details` and the relevant checks explicitly enabled.
- Before scores: Branch Protection 4, Fuzzing 0, SAST 8, Maintained 0, Code Review 0,
  CII Best Practices 0, CI Tests 9, Security Policy 9 in the published action result.
- The same official CLI independently scored the current `SECURITY.md` 10/10. Alert 2 had already
  been dismissed with that exact false-positive evidence and is not part of this seven-alert scope.

## Alert analysis and action

### Alert 1 — Branch Protection

Ruleset `Protect main` (`20181296`) is active for the default branch, has no bypass actor, and reports
`current_user_can_bypass: never`. It blocks deletion and non-fast-forward updates, requires a pull
request, requires resolved review threads, uses strict up-to-date status checks, and requires eight
stable contexts: `actionlint`, `zizmor`, `dependency-review`, `PHP 7.4`, `PHP 8.3`,
`wordpress-smoke`, `wordpress-59`, and `wordpress-multisite`. Repository Actions permissions default
to read-only, cannot approve pull requests, allow only selected action sources, and require SHA pins.

The four negative probes all require an independent reviewer: stale-review dismissal, an approver,
CODEOWNERS review, and last-push approval. The repository has one maintainer. Requiring any of these
without a second trusted human would make legitimate maintenance impossible; self-approval, a bot,
or a fabricated account would not be independent review. A CODEOWNERS file naming the same owner
would not change that fact. Linear history was not imposed merely for score: established maintenance
uses reviewable merge commits, while deletion and force pushes are already blocked. Future release
tags are protected by the attested release workflow and prospective immutable releases; a tag
ruleset without a safe release-automation bypass would prevent legitimate releases.

Disposition: `accepted-structural-risk`. No exploit path or plugin vulnerability exists.

### Alert 3 — Fuzzing

The repository now uses exact development dependency `fast-check@4.9.0`. The JavaScript generator
`tests/property/security-inputs.property.js` invokes a bounded PHP harness which loads the real
production classes `IUA_CDN_Settings` and `IUA_CSV`. It does not mirror their decisions in JavaScript.

The deterministic run uses seed `20260801`, 500 generated cases, and 6,500 assertions per PHP
version. Inputs cover empty values, Unicode, control bytes, formula prefixes, overlong values,
arbitrary host aliases, rewrite lists, duplicates, and unusual separators. Invariants require:

- deterministic CDN validation and a stable result schema;
- agreement between `valid` and `errors`;
- 4,096/8,192-byte and 20-item output bounds;
- valid, idempotent normalized aliases and rewrite rules;
- idempotent CSV neutralization;
- an apostrophe before every formula-capable CSV value;
- byte-for-byte preservation of safe CSV values.

The property test passes on verified PHP 7.4.33 and PHP 8.3.33. Both QA lanes install the locked npm
graph and execute it. Official Scorecard 5.5.0 detects
`JavaScriptPropertyBasedTesting integration found: tests/property/security-inputs.property.js` and
scores the local Fuzzing check 10/10.

Disposition after a successful main-branch SARIF run: `fixed`; allow SARIF to close it automatically.

### Alert 4 — SAST

CodeQL already used `javascript-typescript`, `security-extended`, minimum permissions, scheduled and
manual triggers, and full-SHA action pins. Path filters caused documentation, PHP, configuration, and
other non-JavaScript PRs to lack a CodeQL run. Those filters are removed: CodeQL now runs on every pull
request and every push to `main`, as well as weekly and on manual dispatch. After merge, the stable
`analyze` job was added to the `Protect main` required contexts.

CodeQL does not analyze PHP. PHP remains covered by PHPStan, PHPCS, WordPress Coding Standards,
PHPCompatibilityWP, PHPUnit, Plugin Check, exact-ZIP WordPress 5.9/current/multisite tests, and the new
property-based production harness. Adding an unrelated PHP scanner solely to change Scorecard would
not be evidence of improved security.

The metric improved from 13/25 to 20/30 detected commits but remains historical and cannot be repaired
without rewriting history or creating artificial commits. Prospective coverage is corrected; the
residual is `accepted-historical-risk`.

### Alert 5 — Maintained

GitHub reports repository creation at `2026-07-12T16:12:14Z`. It is public, unarchived, accepts issues,
has active Dependabot, documented support and disclosure policies, scheduled CodeQL and Scorecard,
and recent merged maintenance. Scorecard intentionally returns zero for repositories younger than 90
days. No commit can legitimately alter the creation date.

Disposition: `accepted-temporal-risk`. Revalidate on or after `2026-10-11T16:12:14Z`, after the
90-day boundary has unambiguously passed.

### Alert 6 — Code Review

Every future change must use a pull request, resolve conversations, update from `main`, and pass the
required test matrix. However, the only repository maintainer cannot provide an independent approval
of their own change. Codex, CodeQL, Zizmor, Dependabot, and GitHub Actions are automated controls and
are not represented as human review. No collaborator was invented or granted access.

Disposition: `accepted-structural-risk`. Revalidate if a second trusted human maintainer is explicitly
authorized; only then should one approval, stale dismissal, CODEOWNERS review, and last-push approval
be considered together.

### Alert 7 — CII Best Practices

The official BadgeApp query
`https://www.bestpractices.dev/projects.json?url=https%3A%2F%2Fgithub.com%2Fussmarines%2FWP_image_usage_audit`
returned an empty array on 2026-08-01. No badge exists and none is claimed in the README. BadgeApp is
a voluntary self-certification; several answers require human knowledge or attestation and must not
be invented by automation.

The following prefill covers every one of the 67 current passing-level criterion IDs. `?` means the
maintainer must confirm the statement before submission.

| Criterion IDs | Proposed answer | Evidence or required confirmation |
| --- | --- | --- |
| `description_good`, `interact`, `contribution`, `contribution_requirements` | Met | README description, installation, issues and contributing rules |
| `floss_license`, `floss_license_osi`, `license_location` | Met | GPL-2.0-or-later metadata and repository `LICENSE` |
| `documentation_basics`, `documentation_interface` | Met | README usage, settings, limits and architecture |
| `sites_https` | Met | GitHub repository, releases and reporting URLs use HTTPS |
| `discussion` | Met | GitHub issues are enabled |
| `english` | Met | Canonical README and development documentation are available in English |
| `maintained` | Met | Active unarchived repository, current PRs, scheduled maintenance workflows |
| `repo_public`, `repo_track`, `repo_interim`, `repo_distributed` | Met | Public Git repository with complete reviewable history |
| `version_unique`, `version_semver`, `version_tags` | Met | Semantic plugin versions and matching `vX.Y.Z` tags |
| `release_notes` | Met | `readme.txt` changelog and GitHub releases |
| `release_notes_vulns` | N/A | No release has fixed a confirmed publicly known vulnerability |
| `report_process`, `report_tracker`, `report_archive` | Met | Public GitHub issue process and archive |
| `report_responses`, `enhancement_responses` | ? | Maintainer must confirm the normal response practice for public issues |
| `vulnerability_report_process`, `vulnerability_report_private`, `vulnerability_report_response` | Met | Root `SECURITY.md`, private vulnerability reporting, 7/14/90-day targets |
| `build`, `build_common_tools`, `build_floss_tools` | Met | Deterministic PowerShell ZIP build using documented FLOSS tooling |
| `test`, `test_invocation`, `test_continuous_integration` | Met | Composer/npm commands and required GitHub Actions matrix |
| `test_most` | ? | Broad unit/integration coverage exists, but no numeric line-coverage claim was measured |
| `test_policy`, `tests_are_added`, `tests_documented_added` | Met | AGENTS.md diff-sensitive testing policy, tests and persistent ledger |
| `warnings`, `warnings_fixed`, `warnings_strict` | N/A | PHP/JavaScript project has no compiler warning flags; PHPCS/PHPStan failures are enforced instead |
| `know_secure_design`, `know_common_errors` | ? | Maintainer must personally attest secure-design and common-error knowledge |
| `crypto_published`, `crypto_call`, `crypto_floss`, `crypto_keylength`, `crypto_working`, `crypto_weaknesses`, `crypto_pfs`, `crypto_password_storage`, `crypto_random` | N/A | Plugin implements no cryptographic protocol, password storage, key management or random security secret |
| `delivery_mitm`, `delivery_unsigned` | Met | HTTPS delivery, published SHA-256, verified ZIP, prospective GitHub attestation and immutable releases |
| `vulnerabilities_fixed_60_days`, `vulnerabilities_critical_fixed` | Met | No open known vulnerability; Dependabot and advisory audits are clean |
| `no_leaked_credentials` | Met | Secret scanning and push protection enabled; zero open secret alert |
| `static_analysis`, `static_analysis_common_vulnerabilities`, `static_analysis_fixed`, `static_analysis_often` | Met | CodeQL security-extended, PHPStan, PHPCS/WPCS/PHPCompatibilityWP on every future PR |
| `dynamic_analysis` | Met | PHPUnit, property testing and disposable WordPress integration tests |
| `dynamic_analysis_unsafe` | N/A | No native memory-unsafe production language is used |
| `dynamic_analysis_enable_assertions`, `dynamic_analysis_fixed` | Met | Assertions are active in tests and the required matrix must be green |

Manual action: sign in at <https://www.bestpractices.dev/en/projects/new>, use project name
`Image Usage Audit`, repository/homepage URL
`https://github.com/ussmarines/WP_image_usage_audit`, prefill the answers above, and personally resolve
the five `?` entries before submission. Do not add a badge to README until BadgeApp assigns it.

Disposition until that human self-certification exists: `manual-action-required`.

### Alert 8 — CI Tests

The initial sampled result was 10 of 11 merged PRs. Current QA runs for every PR and requires
actionlint, Zizmor, PHP 7.4, PHP 8.3, WordPress current, WordPress 5.9 and multisite. Dependency Review
also runs on every PR, and CodeQL is unconditional and required.

The final Scorecard scan measured 11 of 11 merged PRs with CI, scored the check 10/10, and
automatically fixed alert 8 at `2026-08-01T13:22:50Z`. No history was rewritten and no artificial
commit was created. Disposition: `fixed`.

## Disposition matrix

| Alert | Tool | Category | Initial state | Analysis | Action | Validation | Disposition |
| ---: | --- | --- | --- | --- | --- | --- | --- |
| 1 | Scorecard | structural | open | reviewer probes require a second human | preserve functional single-maintainer ruleset | nine required checks; dismissed with evidence | `accepted-structural-risk` |
| 3 | Scorecard | correctable | open | real property coverage was feasible | fast-check generator, PHP production harness, CI execution | Scorecard 10; GitHub state `fixed` | `fixed` |
| 4 | Scorecard | historical plus prospective | open | path filters omitted many commits | CodeQL on every PR/push; require `analyze` | 20/30 detected; dismissed with evidence | `accepted-historical-risk` |
| 5 | Scorecard | temporal | open | repository created 2026-07-12 | no artificial history | timestamp; dismissed, revalidate 2026-10-11 | `accepted-temporal-risk` |
| 6 | Scorecard | structural | open | independent review impossible for sole owner | preserve automated protections | 0/7 independent reviews; dismissed with evidence | `accepted-structural-risk` |
| 7 | Scorecard | manual | open | no BadgeApp project exists | complete 67-criterion prefill | official API `[]`; dismissed pending human action | `manual-action-required` |
| 8 | Scorecard | historical, then correctable by normal history | open | one sampled PR initially predated CI | enforce complete future matrix | Scorecard 11/11 and GitHub state `fixed` | `fixed` |

## Applied GitHub classification comments

The following factual comments, all below GitHub's 280-character limit, were applied after the final
main-branch scans. Alerts 3 and 8 were not dismissed; the new SARIF fixed them automatically.

- Alert 1: `Single-maintainer structural limit: main requires PRs, strict updates, resolved threads, 9 green contexts, and blocks deletion/force-push with no bypass. Independent approval cannot be configured without locking out the sole maintainer. Evidence: audit report phase 12.`
- Alert 4: `Historical Scorecard metric: CodeQL now runs on every PR and main push and its analyze job is required. Older commits predate CodeQL and will age out of the sample; history was not rewritten. Evidence: audit report phase 12.`
- Alert 5: `Temporal Scorecard limit: GitHub reports creation at 2026-07-12T16:12:14Z. The repository is active and protected but cannot satisfy the 90-day probe before 2026-10-11. Revalidate then. Evidence: audit report phase 12.`
- Alert 6: `Single-maintainer structural limit: automated checks are required, but no independent human reviewer exists. No bot, self-approval, fake account, or unauthorized collaborator was used. Evidence: audit report phase 12.`
- Alert 7: `Manual action required: the official BadgeApp API returns no project. All 67 passing criteria are prefilled in the audit report, but 5 require maintainer attestation. No badge is claimed until OpenSSF awards it.`

## Validation record

| Check | Result |
| --- | --- |
| fast-check 4.9.0 integrity | matched npm registry lock integrity |
| Property harness, PHP 7.4.33 | pass; 500 cases, seed 20260801, 6,500 assertions |
| Property harness, PHP 8.3.33 | pass; 500 cases, seed 20260801, 6,500 assertions |
| Official Scorecard 5.5.0 local Fuzzing check | 10/10; recognized the real JavaScript property integration |
| JavaScript and PHP harness syntax | pass |
| Composer validate/audit, PHPStan, PHPUnit and PHPCS on PHP 7.4.33 | pass; 40 tests, 135 assertions, zero advisory |
| Composer validate/audit, PHPStan, PHPUnit and PHPCS on PHP 8.3.33 | pass; 40 tests, 135 assertions, zero advisory |
| npm ci and npm audit | pass; 393 packages, zero vulnerability |
| Repository JSON/YAML validator | pass |
| actionlint | pass |
| Zizmor 1.27.0 | pass; no finding, 15 offline-suppressed/not-applicable audits |
| Allow-listed release ZIP | pass; 11 runtime entries, no development files or targeted secret marker |

## Pull-request validation

Pull request [#21](https://github.com/ussmarines/WP_image_usage_audit/pull/21) was merged only after
every reported check completed successfully. At implementation/documentation head
`04ee628a8256af2a841c54b1417386d0f74bce6b`:

- [QA run 30701380357](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701380357):
  actionlint, Zizmor, PHP 7.4, PHP 8.3, WordPress current, WordPress 5.9 and multisite passed. Both PHP
  jobs ran the new property test; the exact ZIP passed activation, AJAX, Plugin Check, smoke,
  translation-catalog and uninstall-preservation checks.
- [Dependency Review run 30701380346](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701380346):
  passed with the reviewed exact `fast-check` lock update.
- [CodeQL run 30701380371](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701380371):
  the unconditional JavaScript/TypeScript `security-extended` analysis passed with no finding.

The documentation-only final PR head `0e82da7749d15312171b14e683dcf4a3ece6338a` then passed
[QA 30701496067](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701496067),
[Dependency Review 30701496070](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701496070),
and [CodeQL 30701496080](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701496080).
GitHub reported the PR clean and mergeable before it merged as
`b6cd6f99a411e7313da28e681914ab9b12e7c7a8`.

## Post-merge validation and before/after comparison

All three workflows triggered by the code-bearing merge passed:

- [QA 30701586247](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701586247):
  every PHP, WordPress, workflow and ZIP job passed, including Plugin Check and the new property test;
- [CodeQL 30701586167](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701586167):
  `security-extended` passed with zero CodeQL finding;
- [OpenSSF Scorecard 30701586170](https://github.com/ussmarines/WP_image_usage_audit/actions/runs/30701586170):
  official Scorecard 5.5.0 completed, published results and uploaded SARIF successfully.

The published global score increased from 6.1 to 6.6. The complete sanitized results are in the two
Scorecard JSON snapshots.

| Check | Before | After | Result |
| --- | ---: | ---: | --- |
| Binary-Artifacts | 10 | 10 | unchanged |
| Branch-Protection | 4 | 4 | functional controls preserved; reviewer probes remain structural |
| CII-Best-Practices | 0 | 0 | human self-certification remains |
| CI-Tests | 9 | 10 | fixed; 11/11 merged PRs detected with CI |
| Code-Review | 0 | 0 | structural single-maintainer limit; sample changed from 0/12 to 0/7 |
| Contributors | 0 | 0 | unchanged single-person project |
| Dangerous-Workflow | 10 | 10 | unchanged |
| Dependency-Update-Tool | 10 | 10 | unchanged |
| Fuzzing | 0 | 10 | fixed; real property integration detected |
| License | 9 | 9 | unchanged Scorecard recognition limitation |
| Maintained | 0 | 0 | repository remains under 90 days |
| Packaging | -1 | -1 | custom release ZIP workflow not recognized |
| Pinned-Dependencies | 10 | 10 | 22/22 GitHub, 2/2 third-party Actions and 5/5 npm commands pinned |
| SAST | 8 | 8 | detected coverage improved from 13/25 to 20/30 commits |
| Security-Policy | 9 | 9 | published result unchanged; official CLI independently scores 10 |
| Signed-Releases | 0 | 0 | historical v2.2.6 predates the attested release workflow |
| Token-Permissions | 10 | 10 | unchanged least privilege |
| Vulnerabilities | 10 | 10 | zero known unfixed vulnerability |

The active `Protect main` ruleset (`20181296`) now requires nine stable contexts: the original eight
plus CodeQL job `analyze`. It remains strict, blocks deletion and non-fast-forward updates, requires a
pull request and resolved conversations, has no bypass actor, and reports
`current_user_can_bypass: never`.

The final Code scanning query returned zero open alert. Alerts 3 and 8 were automatically `fixed` at
`2026-08-01T13:22:50Z`; alerts 1, 4, 5, 6 and 7 were dismissed as `won't fix` only after the evidence
above was published, using the exact comments recorded in this report. No CodeQL finding or plugin
vulnerability was dismissed.

## Git delivery

- branch: `security/resolve-scorecard-alerts`;
- reviewed dependency commits: `e597905` and `1c8cfb4`;
- implementation commits: `cb3cddc` and `cd253ae`;
- evidence commits before merge: `04ee628` and `0e82da7`;
- pull request: [#21](https://github.com/ussmarines/WP_image_usage_audit/pull/21);
- code-bearing merge and audited main SHA: `b6cd6f99a411e7313da28e681914ab9b12e7c7a8`.

The two `after` snapshots necessarily originate from the post-merge run and are committed through a
follow-up documentation-only pull request. That evidence-only change does not alter plugin code,
dependencies, workflows, or repository settings; its own required checks must still pass before
merge.
