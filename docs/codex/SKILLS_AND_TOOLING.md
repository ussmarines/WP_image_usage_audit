# Skills and tooling

Verified on 2026-07-12. Project-specific skills are stored in `.agents/skills`, the repo location documented by current Codex customization guidance.

## 2026-07-12 corrective audit usage

The corrective audit used the existing project skills `wp-project-triage`, `wp-plugin-development`, `wp-plugin-directory-guidelines`, `wp-phpstan`, and `wp-playground`, plus the environment-provided `security-threat-model` and `openai-docs` skills. No skill was installed or updated.

The official Codex Security documentation confirms that `$codex-security:deep-security-scan` is intended for repeated repository discovery/validation and `$codex-security:security-diff-scan` for a working-tree patch. The Codex Security plugin and those callable skills were not installed in this session, so neither workflow was claimed or simulated. The fallback was a repository-grounded threat model, complete manual/static WordPress audit, targeted validation, and final diff security review.

The OpenAI Codex manual helper was attempted twice as required by `openai-docs`, but the remote response lacked the expected `x-content-sha256` integrity header. The official OpenAI developer-docs service was then used successfully for the Codex Security workflow documentation.

## Existing relevant capabilities

Before this bootstrap, no WordPress-specific skill was installed globally or in the repository. The current Codex environment already provides built-in Git/diff/editing capabilities plus these relevant general skills:

- `openai-docs`: current official Codex documentation lookup.
- `skill-installer`: inspected GitHub skill installation with explicit repository paths and destination.
- `security-best-practices`: usable for the JavaScript surface, but it has no PHP/WordPress reference and must not be treated as a WordPress security authority.
- `playwright` / `webapp-testing`: browser automation is available once a runnable WordPress environment exists.

No separate Git, diff-review, or generic correction skill was added because those capabilities are already native to Codex. No installed skill duplicated the seven WordPress candidates by name.

## Installed WordPress skills

Source: [`WordPress/agent-skills`](https://github.com/WordPress/agent-skills), commit `c212346296ce49e72d928b93e49dbfcbb34aaa3d`, GPL-2.0-or-later, last repository activity observed 2026-07-08. The repository is owned by the WordPress GitHub organization and declares a WordPress 7.0+/PHP 7.4+ target; `AGENTS.md` therefore preserves this project's broader WordPress 5.9+ compatibility as an explicit override.

| Skill | Decision | Distinct value | Executable/network review |
| --- | --- | --- | --- |
| `wp-project-triage` | Installed | Repeatable project/tool/version inventory. | Local Node filesystem scan only. Its v0.1.0 header regex currently misses standard `* Plugin Name:` PHPDoc headers; use the manual project map as truth until fixed upstream. |
| `wp-plugin-development` | Installed | WordPress lifecycle, settings, security, data, and release guardrails. | Local `detect_plugins.mjs` only; same header-detection limitation; no network. |
| `wp-plugin-directory-guidelines` | Installed | GPL, metadata, naming, and WordPress.org submission review. | Markdown/references only; no script or download. |
| `wp-phpstan` | Installed | Guides the next static-analysis setup without adding runtime dependencies. | Local config/composer inspector only; PHPStan itself would be a future Composer dev dependency. |
| `wp-playground` | Installed | Disposable compatibility and smoke-test environment. | No bundled script; documented `npx @wp-playground/cli` commands require an explicit future network install and Node 20.18+. |

The installer was pinned to the audited commit and wrote only these five folders. They become discoverable by Codex in subsequent turns.

## Official candidates not installed

| Skill | Reason |
| --- | --- |
| `wp-performance` | Good, local inspector plus read-only WP-CLI diagnostics, but there is no performance task or runnable site. Its guidance would add context without current value. |
| `wp-wpcli-and-ops` | Useful for a real WordPress installation, migrations, multisite, cron, and cache operations. This repository has no target site or WP-CLI, and operational commands have unnecessary blast radius here. |

Both are from the same audited GPL source. Their inspectors spawn only local `wp` commands; they do not download packages automatically.

## Community candidates evaluated but rejected

| Candidate | Coverage | Review and rejection |
| --- | --- | --- |
| `soderlind/prepare-wordpress` | WPCS, tests, release, i18n scaffolding | Active 2026-04-29 but no repository license was exposed. Its setup script uses `execSync` to run `npx`, Composer, and npm commands and installs skills from other repositories. Too broad, network-active, and overlapping. |
| `dcaste/agents-wordpress` | WordPress coding conventions | MIT, last observed activity 2026-02-04, but contains only `AGENTS.md`, `README.md`, and `LICENSE`, not a `SKILL.md`. Not an installable Codex skill and overlaps official guidance. |
| `Zulut30/Wordpress-skills` | Plugin build, audit, tests, release, i18n | MIT and active 2026-06-07, but it is a large multi-module suite with package/composer dependencies, fixtures, update scripts, release tooling, and broad external integrations. Excessive context and overlap for this small plugin. |
| `abolfazl-moeini/wordpress-plugin-unit-test-skill` | PHPUnit/plugin testing | Apache-2.0 and active 2026-06-13, but bundles large test libraries, private-package setup guidance, WPML/WooCommerce-specific helpers, and Composer prerequisites absent here. Reconsider only when the test architecture is chosen. |
| `majiix/wp-best-practices` | Standards/security/audit | Recently active in GitHub search, but no license was declared. Not cloned or installed; unlicensed content is rejected. |
| `adityaarsharma/orbit` | Audit/test/release orchestration | No declared license, Claude-oriented ten-agent/116-skill scope, Docker/wp-env and memory integration. Unverified and disproportionate. |

No maintained, licensed, distinct i18n-only skill appeared in the 2026-07-12 GitHub search. Prefer the official WP-CLI `i18n` command and WordPress internationalization documentation instead of adding an unverified skill.

## Current QA tools

- Available: Git, RTK command proxy, Docker 29.6.1, Docker Compose 5.2.0, Node.js `v24.18.0`, npm 11.16.0, and PowerShell 7.6.3. PHP, Composer, and WP-CLI remain unavailable on the host PATH.
- Configured in the repository: pinned Composer QA dependencies; PHPCS/WPCS/PHPCompatibilityWP; PHPStan WordPress stubs; PHPUnit/polyfills; `@wordpress/env` 11.10.0; and GitHub Actions.
- Docker PHP 7.4 runs the full local QA sequence; Docker PHP 8.3 validates PHP syntax. PHPCompatibility 9.x must run under PHP 7.4 because its own sniff emits PHP 8.1+ deprecations. PHPUnit was raised from 9.6.24 to 9.6.35 after Composer audit identified `CVE-2026-24765`; this remains a development-only dependency.
- `wp-env start` is configured but its first local build failed because Docker could not resolve `api.github.com` while installing PHPUnit. This is an external network/DNS blocker, not a project configuration failure.
- Authoritative reusable results: `.codex/test-ledger.json`.

## Recommended next tooling

1. Restore Docker DNS access to complete the local `wp-env` smoke, Plugin Check, and POT validation.
2. Add WordPress integration fixtures for the highest-risk false-unused scenarios and multisite uninstall.
3. Keep the current small PHP 7.4/8.3 CI matrix unless real compatibility coverage requires another lane.

## Verification sources

- [Codex customization — Skills](https://learn.chatgpt.com/docs/customization/overview#skills)
- [WordPress agent skills](https://github.com/WordPress/agent-skills)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [WP-CLI](https://wp-cli.org/) and [WP-CLI i18n command](https://developer.wordpress.org/cli/commands/i18n/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress requirements](https://wordpress.org/about/requirements/)

## 2026-07-12 targeted tooling update

Project skills are detected from the existing `.agents/skills/` location in this Codex installation. Added without duplicating the five existing official skills:

| Skill | Source and license | Reason |
| --- | --- | --- |
| `wp-performance` | WordPress/agent-skills `trunk`, GPL-2.0-or-later | Scanner scale, options, memory and autoload diagnostics. |
| `wp-wpcli-and-ops` | WordPress/agent-skills `trunk`, GPL-2.0-or-later | Safe WP-CLI, Plugin Check, POT and multisite operations. |
| `wp-i18n-workflow` | mralaminahamed/wp-dev-skills `trunk`, MIT | POT/PO/MO/JS translation workflow and translator comments. |
| `wp-multisite` | mralaminahamed/wp-dev-skills `trunk`, MIT | Site switching, network activation and uninstall safety. |
| `iua-quality-gate` | Local, project-specific | Scope-aware checks, ledger reuse, metadata and ZIP validation with read-only Git. |

The official source was active on 2026-07-08; the community source was active on 2026-07-04. The selected community folders contain instructions, references and evals only; no installer or executable script. Their README, licence and `SKILL.md` files were reviewed before installation.

Added tooling: Dependabot checks Composer, npm and GitHub Actions weekly in grouped updates; it has no auto-merge configuration. `npm run actionlint` runs the inspected MIT-licensed `rhysd/actionlint` 1.7.7 Linux image pinned to `sha256:1d74bfc9fd1963af8f89a7c22afaaafd42f49aad711a09951d02cb996398f61d`; the same command is a dedicated CI job. No production dependency was added.

Global availability: `gh-fix-ci`, `skill-creator` and `skill-installer` are available from this Codex installation. `security-diff-scan`, `validation` and `fix-finding` are not callable here; no unsupported or duplicate global skill was installed.

### À évaluer plus tard

- `wp-plugin-release`: only for an explicit public release with synchronized version, changelog and POT.
- `wp-org-submission`: only when runtime checks pass, a private security channel exists, and WordPress.org submission is imminent.
- `wp-admin-browser`: only when automated admin visual/functional coverage is needed.
- `blueprint`: only if Playground becomes a maintained test environment.
- `wp-background-processing`: only after measurements show the synchronous scanner is inadequate.
- `wp-database`: only for a custom table or schema migration.
- `iua-scanner-regression`: only when scanner changes become frequent/complex.
- `deep-security-scan`: only for an exceptional post-architecture or major-release audit.

WordPress Playground CLI 3.1.44 (official GPL-2.0-or-later source) was evaluated but not added: `@wordpress/env` remains the primary runtime, and adding a WebAssembly/SQLite fallback now would duplicate the test chain without proving a complementary smoke path. Its network downloads would not resolve the observed Docker DNS blocker.
