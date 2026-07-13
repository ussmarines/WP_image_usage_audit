=== Image Usage Audit ===
Contributors: elliot
Tags: media, attachments, audit, images, csv
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit image usage in your WordPress Media Library with provenance, CSV export, manual review tools, and CDN rewrite support.

== Description ==

Image Usage Audit helps you review where images are used before you clean up the Media Library.

Features:
* Scan published content and optionally drafts.
* Track provenance for matches found in post content, post meta, options, term descriptions, and common builders.
* Mark false negatives manually as used, with reversible actions and a dedicated filter.
* Export each tab to CSV, including provenance and match count.
* Support CDN aliases and advanced read-only rewrite rules during scans.
* Stay non-destructive: the plugin does not delete attachments or modify Media Library behavior.

Supported builders and editors include WordPress core, Elementor, Divi, Beaver Builder, Oxygen, Bricks, SiteOrigin, and WPBakery.

Important:
Images referenced only in custom CSS, raw HTML widgets, theme files, plugin files, or some external/CDN setups may still require manual review. Always make a full backup before deleting media.

== Installation ==

1. Upload and activate the plugin.
2. Open **Media → Image Usage Audit**.
3. Click **Run scan**.

== Frequently Asked Questions ==

= Is the scan live? =

No. Run a new scan to refresh the results.

= Why can a used image still appear as unused? =

Typical cases include custom CSS, HTML widgets, theme files, rewritten CDN domains, or third-party integrations. Use the manual mark feature when needed.

== Changelog ==

= 2.2.6 =
* Restricted every audit action to administrators with `manage_options` and gave each AJAX action its own nonce and stable validation responses.
* Neutralized spreadsheet formulas in CSV exports and strictly bounded CDN aliases, rewrite rules, manual selections, and request values.
* Added an atomic expiring scan lock, preserved the last complete result after interrupted scans, and kept large result options out of autoload.
* Bounded attachment, post, term, metadata, and option processing while retaining the synchronous, dependency-free scanner.
* Expanded detection for encoded and relative upload URLs, `srcset`, lazy-load fields, JSON, serialized values, CSS, shortcodes, blocks, builders, CDN aliases, query strings, and fragments.
* Corrected network activation and multisite uninstall context restoration while preserving all media and content.
* Strengthened GitHub Actions with pinned actions, reproducible ZIP/POT checks, PHP 7.4 and 8.3 QA, WordPress 5.9/current smoke tests, AJAX, multisite, large-site, uninstall, and heuristic coverage.
* Updated PHPUnit and compatibility stubs, migrated static analysis to PHPStan 2, and enabled GitHub private vulnerability reporting.

= 2.2.5 =
* Removed the remaining Plugin Check SQL preparation and direct-parameter findings in the scanner.
* Reworked scan queries to use WordPress query APIs where possible.
* Kept options scanning functional with a constrained read-only core options query.
* Preserved provenance, draft handling, CDN rewrite support, CSV export, and manual review workflows.

= 2.2.3 =
* Fixed all reported Plugin Check issues from the latest audit CSV.
* Reworked SQL preparation and request sanitization.
* Removed discouraged translation loading and time limit handling.
* Updated the readme to WordPress.org directory standards.
* Kept bulk actions, scan flow, CSV export, and manual review features fully functional.

= 2.2.2 =
* Fixed admin settings forms so saving one section no longer clears the other.
* Fixed broken CSS rules in the admin UI.
* Fixed the “select all” bulk-action behavior.
* Hardened AJAX and CSV handling.
* Updated readme and plugin headers for WordPress 7.0 compatibility.

= 2.2.1 =
* First public release based on the internal stable branch.

== Upgrade Notice ==

= 2.2.6 =

Security and robustness release with stricter administrator-only AJAX handling, bounded scanning, multisite lifecycle fixes, broader detection fixtures, and a reproducible validated package.

== Privacy ==

This plugin does not collect, track, or transmit personal data. It makes no remote requests and loads no remote executable code.

It stores plugin settings, manual image decisions, scan timestamps, attachment classifications, orphan upload paths, and short provenance labels in WordPress options. Administrators can export the current snapshot as CSV. Uninstalling the plugin removes only these plugin-owned options; it never deletes or modifies media, posts, metadata, terms, or files.

Option names, filenames, paths, and provenance may reveal private site structure. Restrict plugin access and exported CSV files to trusted administrators. Formula-leading CSV values are neutralized, but exports should still be treated as untrusted files.

== Development ==

Human-readable source, development instructions, tests, and the reproducible ZIP command are available at https://github.com/ussmarines/WP_image_usage_audit.
