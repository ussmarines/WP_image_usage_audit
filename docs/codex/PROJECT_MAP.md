# Project map

## Audit baseline

- Audited source commit: `adf788ce39ab6b47e84b983dd5ddb944a4a97384` (`main`, fetched from `origin/main` on 2026-07-12).
- Plugin version: `2.2.5`.
- Declared compatibility: WordPress 5.9+, PHP 7.4+, tested through WordPress 7.0.
- Entry point: `image-usage-audit.php`.
- Text domain: `image-usage-audit`; translations live under `languages/`.
- Canonical project URL: `https://github.com/ussmarines/WP_image_usage_audit` (the WordPress.org slug was not published when checked on 2026-07-12).

## Architecture and responsibilities

| Path | Responsibility |
| --- | --- |
| `image-usage-audit.php` | Metadata, constants, autoloader, plugin lifecycle, Media submenu, admin assets, settings handlers, AJAX scan/manual actions, and CSV export. |
| `includes/class-iua-scanner.php` | Image attachment discovery, path map, content/meta/options/terms/site-identity scans, CDN normalization, provenance, classification, and orphan-file enumeration. |
| `views/admin-page.php` | Admin settings, result tabs, pagination, filters, escaped output, bulk/manual controls, and export link. |
| `assets/admin.js` | Authenticated AJAX calls, result-row updates, quick filtering, column preferences in browser local storage, density controls, and notices. |
| `assets/admin.css` | Admin-only layout and responsive presentation. |
| `uninstall.php` | Deletes only plugin-owned options for the current site and every multisite site. |
| `readme.txt` | WordPress plugin metadata, end-user description, changelog, and privacy statement. |
| `languages/image-usage-audit.pot` | Translation-template metadata. The audited file contains no source messages and must be fully regenerated with WP-CLI before release. |

There is no Composer project, npm project, bundled WordPress runtime, automated test suite, or CI configuration at the audited baseline.

## Data flow

1. A user with `upload_files` opens **Media → Image Usage Audit**.
2. WordPress localizes the admin AJAX URL, an `iua_scan` nonce, last-scan time, page URLs, and UI strings into `IUAAdmin`.
3. **Run scan** posts to `wp_ajax_iua_run_scan`; the handler verifies nonce and capability, then calls `IUA_Scanner::run()`.
4. The scanner loads settings, enumerates image attachments, maps originals/generated sizes, scans supported sources, classifies IDs, enumerates orphan files, and limits provenance to 12 labels per attachment.
5. Results are stored in `iua_usage_results` and rendered from the saved snapshot. Manual decisions are merged into display/export classifications.
6. Settings and CSV exports use authenticated `admin-post.php` handlers. CSV generation reads saved results and attachment metadata; it does not alter media.

## Sources inspected by the scanner

- All registered image attachments (`post_status=inherit`) and their `_wp_attached_file`/generated-size metadata.
- `post_content` for all public and non-public post types except attachments, revisions, and menu items; published/private and optionally draft/pending/future statuses.
- `wp-image-{id}` CSS classes and `/wp-content/uploads/...` paths in content.
- Featured image `_thumbnail_id` and WooCommerce `_product_image_gallery`.
- Builder metadata keys for Elementor, Beaver Builder, Oxygen, SiteOrigin, Bricks, WPBakery, and Divi; upload paths plus generic nested/JSON `id` values.
- Any post metadata value that contains an upload/CDN search pattern.
- All option names/values through one static read-only query against `$wpdb->options`, scanning values for upload paths.
- All taxonomy term descriptions.
- `site_icon` and the active theme's `custom_logo`.
- Files under the uploads directory with `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, or `avif` extensions for orphan reporting.

## WordPress options

| Option | Shape / purpose | Lifecycle |
| --- | --- | --- |
| `iua_include_drafts` | `'1'` or `'0'`; enables draft-only scanning. | Defaulted on bootstrap; deleted on uninstall. |
| `iua_manual_used_ids` | Array of validated attachment IDs manually treated as used. | Defaulted on bootstrap; deleted on uninstall. |
| `iua_cdn_aliases` | Comma-separated CDN hosts. | Defaulted on bootstrap; deleted on uninstall. |
| `iua_cdn_rewrites` | Newline-separated `FROM => TO` rules. | Defaulted on bootstrap; deleted on uninstall. |
| `iua_usage_results` | Used/draft-only/unused IDs, orphan paths, timestamp, draft flag, and provenance. | Written after scans; deleted on uninstall. |

## Security-sensitive surfaces

- Capability: every admin render/action uses `upload_files`.
- CSRF: settings use section-specific admin nonces; CSV uses `iua_export_csv`; AJAX uses `iua_scan`.
- Input: tabs/filters/sections are sanitized then allow-listed; IDs use `absint` and attachment validation; settings use WordPress text sanitizers.
- SQL: the sole direct query is static `SELECT option_name, option_value FROM {$wpdb->options}`. No user input enters SQL.
- Output: admin HTML uses `esc_html*`, `esc_attr*`, `esc_url`, `esc_textarea`, or constrained `wp_kses_post`; redirects use `wp_safe_redirect`.
- Privacy: no remote requests or telemetry. Saved provenance exposes IDs, option names, and source locations to authorized administrators; CSV and orphan paths should be treated as sensitive operational data.
- CSV: filenames/URLs originate from site data and are not prefixed to neutralize spreadsheet formulas. Treat exported CSV as untrusted; consider explicit formula-injection hardening in a separate behavior change.

## Known functional limits

- Heuristic results can contain false negatives for theme/plugin files, custom CSS, dynamic/external data, unsupported builders, IDs outside recognized structures, and unconfigured CDN transformations.
- Generic builder `id` extraction can create false positives when an unrelated numeric ID equals an image attachment ID.
- Scan work is not batched and may exhaust time/memory on large sites; all posts, relevant metadata, options, terms, attachments, and upload files may be enumerated in one AJAX request.
- Only a fixed image-extension list participates in orphan detection.
- Results are snapshots and become stale until the next manual scan.
- Provenance is capped at 12 labels per attachment.
- The POT at the audit baseline has metadata only, so runtime strings are not available to translators yet.

## Commands and decisions

- Current executable QA: `node --check assets/admin.js` and Git metadata/diff/secret checks.
- Expected when PHP is installed: `php -l` for every PHP file.
- Expected when WP-CLI is installed: `wp i18n make-pot . languages/image-usage-audit.pot --domain=image-usage-audit --exclude=.agents,docs` and Plugin Check in a disposable WordPress instance.
- Read `.codex/test-ledger.json` before testing and reuse valid passing baselines according to `AGENTS.md`.
- Keep runtime dependency-free and the admin UI on WordPress/jQuery primitives.
- Keep scans and settings non-destructive to media; only plugin options may be written or removed.
- Preserve WordPress 5.9+ and PHP 7.4+ until explicitly changed, even though installed WordPress skills target WordPress 7.0+.

## Next implementation steps

1. Add Composer development tooling for WordPress Coding Standards and PHPStan WordPress stubs, without production dependencies.
2. Add a disposable WordPress Playground or equivalent smoke environment covering activation, nonce/capability failures, settings, scan, CSV, and uninstall.
3. Regenerate and validate the full POT catalog with WP-CLI.
4. Add targeted scanner tests for path normalization, CDN rules, builder IDs, classification, and large-data behavior.
5. Add release/CI checks only after the local commands and compatibility matrix are agreed.
