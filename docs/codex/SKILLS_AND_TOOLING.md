# Skills and tooling

Verified on 2026-07-12. Project-specific skills are stored in `.agents/skills`, the repo location documented by current Codex customization guidance.

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

- Available: Git, RTK command proxy, Node.js `v24.18.0`, and `node --check`.
- Unavailable on PATH during this audit: PHP and WP-CLI.
- Not configured in the repository: Composer, npm/package scripts, PHPCS/WPCS, PHPStan, PHPUnit, Plugin Check, WordPress runtime, Playground blueprint, or CI.
- Authoritative reusable results: `.codex/test-ledger.json`.

## Recommended next tooling

1. Install a supported PHP runtime and Composer for development.
2. Add Composer **development** dependencies for PHPCS + `wp-coding-standards/wpcs`, and PHPStan + a WordPress extension/stubs; pin configurations to PHP 7.4 and WordPress 5.9 compatibility.
3. Add WP-CLI with the official `i18n` command to regenerate the POT and validate `readme.txt`/Plugin Check in a disposable site.
4. Add a pinned WordPress Playground blueprint or equivalent container with WordPress 5.9/PHP 7.4 and current WordPress/PHP variants.
5. Add PHPUnit/integration tests only after deciding which behavior requires WordPress bootstrap versus isolated scanner fixtures.

## Verification sources

- [Codex customization — Skills](https://learn.chatgpt.com/docs/customization/overview#skills)
- [WordPress agent skills](https://github.com/WordPress/agent-skills)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [WP-CLI](https://wp-cli.org/) and [WP-CLI i18n command](https://developer.wordpress.org/cli/commands/i18n/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress requirements](https://wordpress.org/about/requirements/)
