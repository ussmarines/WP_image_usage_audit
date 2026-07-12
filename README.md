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
- Exports used, draft-only, or unused results to CSV with spreadsheet-formula neutralization.
- Reports orphan image files found under the WordPress uploads directory.

The plugin does not delete, edit, move, or rewrite media files. Its only persistent writes are its own WordPress options; uninstalling the plugin removes those options, including scan results, settings, manual marks, and the temporary scan lock.

## Detection limits

The scanner is heuristic, not proof that an image can safely be deleted. It can miss references in theme or plugin files, custom CSS, dynamically generated URLs, external services, unsupported builders, attachment IDs stored outside recognized builder structures, non-standard upload paths, or CDN transformations not covered by configured aliases or rewrites. It may report false positives when a builder's generic nested `id` happens to equal an image attachment ID.

The orphan scan only covers `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, and `avif` files under the current uploads directory. Large sites may hit request time or memory limits because scans run as one authenticated AJAX request and enumerate posts, metadata, terms, attachments, and upload files. Option rows are read in bounded batches and concurrent scans are rejected, but the wider scan is not asynchronous or resumable. Always inspect provenance and make a verified backup before deleting media manually in WordPress.

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
- `includes/class-iua-cdn-settings.php` and `includes/class-iua-csv.php`: bounded CDN validation and CSV formula neutralization.
- `views/admin-page.php`: escaped administration screen, filters, pagination, and settings forms.
- `assets/admin.js` and `assets/admin.css`: admin interactions and presentation.
- `languages/image-usage-audit.pot`: translation template metadata; regenerate the complete catalog before a release.
- `uninstall.php`: removal of the plugin's five options on single-site and multisite installations.
- `docs/codex/`: persistent project map and tooling decisions for future Codex sessions.
- `.codex/test-ledger.json`: reusable, scope-aware validation history.
- `.agents/skills/`: project-scoped WordPress skills from `WordPress/agent-skills`.
- `scripts/build-zip.ps1`: allow-listed, inspected WordPress ZIP construction.

## Local development and checks

The repository keeps runtime dependencies at zero. QA dependencies are locked in `composer.lock` and `package-lock.json`; Docker provides PHP and WordPress without a global PHP or WP-CLI installation.

```bash
# Install locked development tools.
npm ci
npm run composer -- install

# Run Composer scripts on a host with PHP 7.4+.
composer qa

# Windows host without PHP: run the same QA sequence in Docker PHP 7.4.
docker run --rm --volume "%cd%:/app" --workdir /app php:7.4-cli sh -lc \
  'vendor/bin/phpcs --standard=phpcs.xml.dist && vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --memory-limit=1G && vendor/bin/phpunit --configuration=phpunit.xml.dist --testsuite=unit'

# Disposable WordPress, WP-CLI, Plugin Check and POT generation.
npm run env:start
npm run plugin-check
npm run pot
npm run validate:metadata
npm run validate:config
npm run actionlint
npm run build:zip
npm run env:stop
```

`@wordpress/env` pins WordPress 6.8.2/PHP 7.4 in `.wp-env.json`; use its documented core/PHP overrides when testing a newer supported combination. The `wordpress-smoke` CI job activates the plugin, runs Plugin Check, and rejects a POT generated from a stale catalog. `npm run build:zip` creates `dist/image-usage-audit.zip`, verifies its single root folder and required metadata, and rejects development-only paths. Consult `.codex/test-ledger.json` before rerunning checks.

## Security and privacy

The admin page and all scan, settings, manual-mark, and export actions require `manage_options`. Settings, exports, and each AJAX action have server-verified nonces. Request values are allow-listed or sanitized, bulk IDs are bounded and validated, CDN hosts/rules are structurally checked, URLs and HTML are escaped at output, and the direct options query is read-only and batched. A short-lived atomic lock prevents concurrent scans.

Scans run locally inside WordPress and do not transmit content or personal data. Scan results store attachment IDs, timestamps, orphan file paths, and short provenance labels in a non-autoloaded WordPress option. Because option names, paths, and exported filenames can reveal site structure, restrict administration and exported CSV files to trusted users. Formula-leading CSV values are prefixed defensively, but exported files should still be treated as untrusted input.

Security reports must not be filed publicly while unpatched. See [`SECURITY.md`](SECURITY.md); the repository owner still needs to enable a verified private reporting channel before public distribution.

## Contributing

Create a topic branch; never push changes directly to `main`. Preserve WordPress 5.9+ and PHP 7.4+ compatibility, follow the WordPress Coding Standards, keep the scanner non-destructive, and retain capability, nonce, validation, sanitization, and escaping controls. Add production dependencies only with a demonstrated need. Update tests and `.codex/test-ledger.json` for the affected surface.

Do not bump the plugin version for documentation or CI-only work. For a real release, synchronize the PHP header, `IUA_VERSION`, `readme.txt` stable tag and changelog, and the POT metadata/catalog.

## License

Image Usage Audit is distributed under [GPL-2.0-or-later](LICENSE).
