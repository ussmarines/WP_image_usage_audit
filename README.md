# Image Usage Audit

Image Usage Audit is a read-only WordPress administration plugin for reviewing image usage before a human cleans up the Media Library. It classifies registered image attachments as used in published content, used only in drafts, or unused; records where matches were found; identifies image files in uploads that are not registered attachments; and exports the current result set as CSV.

The current plugin version is `2.2.5`. It supports WordPress 5.9 or later and PHP 7.4 or later, and is tested up to the WordPress 7.0 release line.

## Features

- Scans published/private content and, optionally, draft, pending, and scheduled content.
- Detects `wp-image-{id}` references, upload URLs, featured images, WooCommerce product galleries, the site icon, and the custom logo.
- Scans post metadata, term descriptions, and WordPress options for upload paths.
- Understands metadata used by Elementor, Divi, Beaver Builder, Oxygen, SiteOrigin, Bricks, and WPBakery.
- Matches original attachment files and generated image sizes.
- Supports comma-separated CDN host aliases and read-only `FROM => TO` rewrite rules.
- Records up to 12 provenance labels per attachment.
- Supports reversible manual “used” markings and bulk actions.
- Exports used, draft-only, or unused results to CSV.
- Reports orphan image files found under the WordPress uploads directory.

The plugin does not delete, edit, move, or rewrite media files. Its only persistent writes are its own WordPress options; uninstalling the plugin removes those options, including scan results and settings.

## Detection limits

The scanner is heuristic, not proof that an image can safely be deleted. It can miss references in theme or plugin files, custom CSS, dynamically generated URLs, external services, unsupported builders, attachment IDs stored outside recognized builder structures, non-standard upload paths, or CDN transformations not covered by configured aliases or rewrites. It may report false positives when a builder's generic nested `id` happens to equal an image attachment ID.

The orphan scan only covers `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, and `avif` files under the current uploads directory. Large sites may hit request time or memory limits because scans run as one authenticated AJAX request and enumerate posts, metadata, options, terms, attachments, and upload files without batching. Always inspect provenance and make a verified backup before deleting media manually in WordPress.

## Installation

1. Download or clone this repository into `wp-content/plugins/image-usage-audit`.
2. Activate **Image Usage Audit** in WordPress.
3. Open **Media → Image Usage Audit**.
4. Save the scan settings, then select **Run scan**.

The WordPress.org slug is not currently published. The canonical project page is this GitHub repository.

## Usage

The **Unused**, **Draft-only**, and **Used (published)** tabs show the latest stored scan. Results are not live; rerun the scan after content changes. Use manual markings only to record a reviewed false negative. CSV export reflects the selected tab and, on the used tab, the optional manual-only filter.

### CDN aliases and rewrites

Aliases are comma-separated host names without a path, for example:

```text
cdn.example.com, media.example.net
```

Advanced rewrites contain one mapping per line:

```text
https://cdn.example.com/assets => /wp-content/uploads
/media => /wp-content/uploads
```

Rules are applied to text in memory while scanning. They do not change posts, options, URLs, or files. Broad replacements can create false matches, so use the narrowest stable prefix.

## Repository architecture

- `image-usage-audit.php`: plugin header, bootstrap, admin hooks, authenticated AJAX actions, settings, and CSV export.
- `includes/class-iua-scanner.php`: attachment map, content/meta/option/term scans, CDN normalization, provenance, and orphan detection.
- `views/admin-page.php`: escaped administration screen, filters, pagination, and settings forms.
- `assets/admin.js` and `assets/admin.css`: admin interactions and presentation.
- `languages/image-usage-audit.pot`: translation template metadata; regenerate the complete catalog before a release.
- `uninstall.php`: removal of the plugin's five options on single-site and multisite installations.
- `docs/codex/`: persistent project map and tooling decisions for future Codex sessions.
- `.codex/test-ledger.json`: reusable, scope-aware validation history.
- `.agents/skills/`: project-scoped WordPress skills from `WordPress/agent-skills`.

## Local development and checks

This repository currently has no Composer or npm project and no bundled WordPress runtime. Use the commands that match the tools installed on your machine:

```bash
# PHP syntax (all PHP files)
php -l image-usage-audit.php
php -l includes/class-iua-scanner.php
php -l views/admin-page.php
php -l uninstall.php

# JavaScript syntax
node --check assets/admin.js

# Regenerate translations when WP-CLI is available
wp i18n make-pot . languages/image-usage-audit.pot \
  --domain=image-usage-audit \
  --exclude=.agents,docs
```

Consult `.codex/test-ledger.json` before rerunning checks. A passing result remains a valid baseline only while its command, tool, configuration, environment, and covered files remain unchanged.

## Security and privacy

The admin page and all mutating or export actions require the `upload_files` capability. Settings and exports use WordPress admin nonces; AJAX actions use the `iua_scan` nonce. Request values are allow-listed or sanitized, attachment IDs are validated, URLs and HTML are escaped at output, and the one direct SQL query is a static read-only enumeration of the current site's options table.

Scans run locally inside WordPress and do not transmit content or personal data. Scan results store attachment IDs, timestamps, orphan file paths, and short provenance labels in the WordPress database. Because option names, paths, and exported filenames can reveal site structure, restrict administration and exported CSV files to trusted users. Treat CSV exports as untrusted input when opening them in spreadsheet software.

## Contributing

Create a topic branch; never push changes directly to `main`. Preserve WordPress 5.9+ and PHP 7.4+ compatibility, follow the WordPress Coding Standards, keep the scanner non-destructive, and retain capability, nonce, validation, sanitization, and escaping controls. Add production dependencies only with a demonstrated need. Update tests and `.codex/test-ledger.json` for the affected surface.

Do not bump the plugin version for documentation or CI-only work. For a real release, synchronize the PHP header, `IUA_VERSION`, `readme.txt` stable tag and changelog, and the POT metadata/catalog.

## License

Image Usage Audit is distributed under [GPL-2.0-or-later](LICENSE).
