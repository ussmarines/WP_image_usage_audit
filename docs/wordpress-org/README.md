# WordPress.org submission preparation

## Submission identity

- Plugin: **Image Usage Audit**
- Preferred slug: `image-usage-audit`
- WordPress.org account: `ussmarines`
- Submission version: `2.2.9`
- Git commit used for the public 2.2.9 release: `722b7047dbd9754337dd0d7c4e360c0ff8be1267`
- Expected SHA-256 for the public `image-usage-audit.zip`: `69988a5d02090f74cc9ad09e1990bfb649a0d4cb1df0266e4476cfda53330866`

## Current preparation status

The plugin package is technically ready for manual review:

- GPL-2.0-or-later licensing;
- WordPress 5.9 minimum;
- PHP 7.4 minimum;
- tested through WordPress 7.0;
- synchronized plugin version and stable tag at 2.2.9;
- text domain and preferred slug set to `image-usage-audit`;
- non-destructive behavior;
- no telemetry, remote executable code, or external service dependency;
- capability, nonce, validation, sanitization, escaping, and CSV formula protections;
- reproducible release ZIP with checksum and GitHub attestation.

## Before submitting

1. Sign in to WordPress.org as `ussmarines` and verify the profile email address.
2. Download `image-usage-audit.zip` from the public `v2.2.9` GitHub release.
3. Run `scripts/verify-wordpress-org-submission.ps1` against the downloaded ZIP.
4. Open the official new-plugin form at `https://wordpress.org/plugins/developers/add/`.
5. Upload the verified installation ZIP, not the repository source archive.
6. Paste the overview from `submission-form.txt` into the reviewer notes or description field.
7. Confirm the preferred slug is `image-usage-audit` before final submission.
8. Keep the confirmation email and reply in the same thread if the review team requests changes.

Do not create a second submission for reviewer corrections. Send a complete corrected ZIP through the existing review thread.

## Repository layout prepared for WordPress.org

- `.wordpress-org/`: directory icon and banner source files;
- `submission-form.txt`: reviewer-facing project summary;
- `screenshot-plan.md`: sanitized real-interface capture plan;
- `readme-screenshots-section.txt`: section to add once screenshots exist;
- `svn-publication.md`: first publication procedure after approval;
- `review-response-template.txt`: reply template for review feedback;
- `scripts/verify-wordpress-org-submission.ps1`: exact ZIP verification;
- `scripts/prepare-wordpress-org-svn.ps1`: cautious SVN working-copy preparation.

## Important release constraint

The existing 2.2.9 release ZIP, checksum, tag, and attestation must remain immutable. These preparation files are repository-only material and must not be inserted into the already published 2.2.9 ZIP.
