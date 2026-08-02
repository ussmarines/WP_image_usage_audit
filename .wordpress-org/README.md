# WordPress.org directory assets

This directory stores the branding and directory assets prepared for the WordPress.org plugin listing.

Current files:

- `icon.svg` — scalable directory icon;
- `icon-128x128.png` — raster fallback icon;
- `banner-source.svg` — editable source for the directory banners.

The production pack also contains exported PNG banners at 772 × 250 and 1544 × 500. Export those from `banner-source.svg` or use the validated submission pack when preparing the WordPress.org SVN root `/assets` directory.

Future real-interface screenshots belong here as `screenshot-1.png`, `screenshot-2.png`, and so on. Screenshots must not contain private site information and must match the numbered captions in `docs/wordpress-org/readme-screenshots-section.txt`.

These resources are repository-only. They are intentionally excluded from the installable plugin ZIP by `scripts/build-zip.ps1`.

All original assets in this directory are distributed under GPL-2.0-or-later.
