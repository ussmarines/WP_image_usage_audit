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

- QA configuration: `composer.json`/`composer.lock`, `phpcs.xml.dist`, `phpstan.neon.dist`, `phpunit.xml.dist`, `package.json`/`package-lock.json`, `.wp-env.json`, and `.github/workflows/qa.yml`.
- Composer development tools: PHPCS + WPCS + PHPCompatibilityWP, PHPStan with WordPress stubs, PHPUnit, and PHPUnit polyfills. `composer qa` runs lint, analysis, and isolated scanner tests; PHPStan uses a 1G limit for the WordPress stubs under PHP 7.4.
- Reproducible runtime: `@wordpress/env` 11.10.0 with WordPress 6.8.2/PHP 7.4. It supplies WP-CLI, Plugin Check, and POT generation; CI also runs a current PHP 8.3 static/test lane.
- Tests: `tests/unit` isolates CDN aliases/rewrites, generated image-size URLs, builder IDs, and capped provenance. Full WordPress/AJAX, CSV, uninstall, and multisite cases remain the next integration layer.
- The local 2026-07-12 `wp-env start` attempt was blocked while Docker resolved `api.github.com` during image construction. No plugin, Plugin Check, or POT result is available locally until that external DNS failure is resolved.
- Read `.codex/test-ledger.json` before testing and reuse valid passing baselines according to `AGENTS.md`.
- Keep runtime dependency-free and the admin UI on WordPress/jQuery primitives.
- Keep scans and settings non-destructive to media; only plugin options may be written or removed.
- Preserve WordPress 5.9+ and PHP 7.4+ until explicitly changed, even though installed WordPress skills target WordPress 7.0+.

## Next implementation steps

1. Resolve the Docker DNS failure, then run the configured WordPress smoke, Plugin Check, and POT generation locally.
2. Add full WordPress integration coverage for posts/meta/options/drafts, manual marks, CSV, uninstall, and multisite.
3. Expand scanner fixtures for classification and false-negative cases before adding broader coverage goals.
