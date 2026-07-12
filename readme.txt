=== Image Usage Audit ===
Contributors: elliot
Tags: media, attachments, audit, images, csv
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.5
License: GPLv2 or later
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

Supported builders and editors include WordPress core, Elementor, Divi, Beaver Builder, Bricks, SiteOrigin, and WPBakery.

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

== Screenshots ==

1. Main audit screen and scan settings.
2. CSV export with provenance.
3. Manual false-negative filter.

== Changelog ==

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

= 2.2.5 =

Maintenance release focused on removing the last Plugin Check SQL preparation errors while keeping the scanner fully functional.

== Privacy ==

This plugin does not collect or transmit personal data. All processing happens on your own site.
