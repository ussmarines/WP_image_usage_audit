# Image Usage Audit

A non-destructive WordPress plugin for reviewing image usage before cleaning up the Media Library.

[![QA](https://github.com/ussmarines/WP_image_usage_audit/actions/workflows/qa.yml/badge.svg)](https://github.com/ussmarines/WP_image_usage_audit/actions/workflows/qa.yml)
[![Latest release](https://img.shields.io/github/v/release/ussmarines/WP_image_usage_audit)](https://github.com/ussmarines/WP_image_usage_audit/releases/latest)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)

## Overview

Image Usage Audit scans a WordPress site and groups registered image attachments as used, used only in draft content, or potentially unused. It also records where matches were found, reports image files that are not registered attachments, and exports the latest results as CSV.

The plugin is a review tool, not an automatic cleanup tool. Its findings are heuristic and should always be checked before you make changes to the Media Library.

## Features

- Scans published content and, optionally, draft, pending, and scheduled content.
- Detects core image references, featured images, WooCommerce galleries, site icons, custom logos, upload URLs, and generated image sizes.
- Searches post metadata, term descriptions, WordPress options, and common builder data.
- Supports Elementor, Divi, Beaver Builder, Oxygen, Bricks, SiteOrigin, and WPBakery patterns.
- Records provenance for each match and supports reversible manual “used” markings.
- Supports CDN host aliases and read-only path rewrite rules.
- Exports used, draft-only, and unused results to CSV.
- Reports orphan image files under the WordPress uploads directory.

## Non-destructive by design

Image Usage Audit never deletes, moves, edits, or rewrites media, posts, metadata, terms, or upload files. It writes only its own WordPress options for settings, scan results, manual decisions, and a temporary scan lock. Uninstalling the plugin removes only those plugin-owned options.

Always create and verify a full backup before deleting media manually.

## Requirements

- WordPress 5.9 or later
- PHP 7.4 or later
- An administrator account with the `manage_options` capability

## Installation

1. Download `image-usage-audit.zip` from the [latest GitHub release](https://github.com/ussmarines/WP_image_usage_audit/releases/latest).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select the ZIP, install it, and activate **Image Usage Audit**.
4. Open **Media → Image Usage Audit**.

The plugin is not currently published in the WordPress.org Plugin Directory. GitHub Releases are the canonical download source.

## Quick start

1. Review the scan settings and decide whether draft content should be included.
2. Select **Run scan**.
3. Review the **Unused**, **Draft-only**, and **Used (published)** tabs.
4. Inspect each result’s provenance before taking action.
5. Use manual markings for reviewed false negatives, or export a tab to CSV.

Results are stored snapshots. Run another scan after content or settings change.

## Important detection limits

The scanner cannot prove that an image is safe to delete. It may miss references in theme or plugin files, custom CSS, dynamically generated URLs, external services, unsupported builders, unusual metadata, non-standard upload paths, or unconfigured CDN transformations. Generic builder IDs can also create false positives.

Orphan detection covers `jpg`, `jpeg`, `png`, `gif`, `webp`, `svg`, and `avif` files in the current uploads directory. Scans run in one authenticated request, so very large sites may reach server time or memory limits. Provenance is limited to 12 labels per attachment.

## CDN configuration

Add comma-separated CDN host aliases when media URLs use alternate domains:

```text
cdn.example.com, media.example.net
```

Advanced rewrites use one `FROM => TO` mapping per line:

```text
https://cdn.example.com/assets => /wp-content/uploads
/media => /wp-content/uploads
```

Rewrites are applied only to text in memory while scanning. Use the narrowest stable prefixes to reduce false matches.

## Privacy and security

Scanning happens locally inside WordPress. The plugin makes no remote requests, loads no remote executable code, and does not collect or transmit personal data.

All plugin screens and actions require `manage_options`. State-changing requests use server-verified nonces, request values are validated and sanitized, and output is escaped. CSV formula-leading values are neutralized.

Stored results and CSV exports can reveal filenames, paths, option names, and other site structure. Restrict access to trusted administrators and treat exported files as sensitive, untrusted input. Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## Local development

Runtime code has no third-party dependencies. Development and QA tools are locked in `composer.lock` and `package-lock.json`.

```bash
npm ci
composer install
composer qa
npm run test:property
npm run validate:metadata
npm run validate:config
npm run build:zip
```

The GitHub Actions matrix also tests WordPress 5.9, current WordPress, multisite, authenticated AJAX behavior, Plugin Check, translation catalog reproducibility, and the exact installation ZIP.

## Verify a release

Each GitHub release includes the plugin ZIP, a SHA-256 checksum, and a GitHub artifact attestation. Download both files, then run:

```bash
sha256sum --check image-usage-audit.zip.sha256
gh attestation verify image-usage-audit.zip --repo ussmarines/WP_image_usage_audit
gh release verify-asset TAG image-usage-audit.zip --repo ussmarines/WP_image_usage_audit
```

Replace `TAG` with the release tag you downloaded.

## Contributing

Open a topic branch and a focused pull request. Keep changes compatible with WordPress 5.9+ and PHP 7.4+, follow the WordPress Coding Standards, preserve the plugin’s non-destructive behavior, and add focused tests for changed behavior.

Security reports belong in GitHub’s private vulnerability reporting flow, not in public issues. See [SECURITY.md](SECURITY.md).

## License

Image Usage Audit is licensed under [GPL-2.0-or-later](LICENSE).
