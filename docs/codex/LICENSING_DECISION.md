# Licensing and distribution decision

## Status and scope

This document records the evidence available on 2026-07-12. It is a technical distribution inventory, not legal advice and not a proprietary license. Any restrictive-license text must be marked `DRAFT — LEGAL REVIEW REQUIRED` and reviewed by a lawyer before use.

## Current license

- The repository, plugin header, `readme.txt`, Composer metadata, npm metadata, and distribution ZIP consistently declare `GPL-2.0-or-later`.
- `LICENSE` contains the GNU GPL version 2 text and the project-level `GPL-2.0-or-later` declaration.
- Guideline 1 of the WordPress.org Plugin Directory requires all code, data, images, and bundled components to be GPL-compatible. The current declaration satisfies that technical requirement.
- The current GPL license permits use, modification, redistribution, and commercial redistribution. Copyright and license notices must be preserved as required by the GPL, but a frontend credit or backlink must not be mandatory.

## Versions already made available

- The public Git history contains version 2.2.5 from the first tracked commit, `adf788ce39ab6b47e84b983dd5ddb944a4a97384`, under a GPL-compatible declaration. The repository remains publicly accessible under `ussmarines/WP_image_usage_audit`.
- The `readme.txt` changelog describes 2.2.1 as the first public release and also lists 2.2.2 and 2.2.3. The available Git history does not contain those version headers or their release artifacts, so their exact publication state cannot be proven from this repository alone.
- The GitHub API returned no tags and no GitHub Releases on 2026-07-12. Absence of those records does not prove that no ZIP or other copy was distributed elsewhere.
- GPL permissions already granted with any distributed GPL copy are not revocable retroactively. Changing a future `LICENSE` file cannot withdraw the rights attached to copies already received.

## Rights-holder inventory

| Evidence | Finding | Required conclusion |
| --- | --- | --- |
| Plugin header and `readme.txt` | Author/contributor name is `elliot` | Confirm the legal identity and preferred copyright notice. |
| Git history and GitHub contributor API | Commits are attributed to `ussmarines`; the API lists only that account | This supports repository control, not sole copyright ownership. Confirm whether `elliot` and `ussmarines` are the same rights holder. |
| Repository records | No CLA, assignment, employment statement, or contributor agreement was found | Sole ownership is not demonstrated. Obtain and retain written evidence before relicensing. |
| Runtime PHP, JS, and CSS | No bundled vendor directory, remote executable code, fonts, images, or third-party runtime library was found; jQuery is supplied by WordPress | Record the provenance of any code copied or generated outside this repository even if no notice is currently present. |
| Development dependencies | Composer/npm packages are development-only and excluded from the ZIP | Their licenses do not become runtime package content, but lockfile audits and notices must remain part of release QA. |
| Assets and branding | No logo or separately licensed media asset is distributed | Any future logo, screenshots, fonts, or artwork need a documented license and ownership record. |

The current evidence is insufficient to conclude that the user is the sole rights holder. Relicensing therefore requires legal verification of authorship, assignments, employment obligations, copied/generated code provenance, and any off-repository contributions.

## Path A — WordPress.org and GPL distribution

Use this path to submit to WordPress.org or publish a directory-compatible ZIP:

1. Keep `GPL-2.0-or-later` for the whole distributed plugin and all bundled assets.
2. Permit modification, redistribution, forks, and sale under the GPL.
3. Preserve applicable copyright and license notices without requiring a frontend credit.
4. Build only with `npm run build:zip` and distribute `dist/image-usage-audit.zip`.
5. Submit the validated ZIP through the WordPress.org plugin process, then use the assigned SVN repository and release tags if accepted.

This path is technically compatible with WordPress.org but cannot prevent forks, redistribution, or third-party resale. Directory review remains discretionary and technical readiness is not an acceptance guarantee.

## Path B — future private restrictive distribution

Use this path only after a separate, explicit release decision:

1. Do not submit the restrictive build to WordPress.org and do not describe it as compliant with WordPress.org distribution rules.
2. Confirm that the relicensing party owns or has written permission to relicense every relevant contribution and asset.
3. Have specialist software counsel draft or approve the terms. Any working text must remain `DRAFT — LEGAL REVIEW REQUIRED` until approval.
4. Distribute future builds only through a controlled private channel and decide separately whether the future source repository should be private.
5. Accept that previously distributed GPL versions remain usable, modifiable, redistributable, forkable, and sellable under their existing GPL terms.
6. Do not combine `GPL-2.0-or-later` with no-modification, no-redistribution, no-resale, or prior-authorization clauses in one license.

A restrictive future build can remain technically installable on WordPress, but its copyright/licensing relationship with WordPress and any third-party material requires independent legal review.

## Attribution, official versions, name, and logo

- A coherent project copyright notice can identify the verified rights holder and years once identity and ownership are documented. The current evidence does not justify inventing a legal name or ownership claim.
- `CREDITS.md` is not added now because the repository contains no verified third-party runtime credit beyond the existing author/contributor metadata. Add it only when verified names, components, or asset licenses make it useful.
- Official releases can be distinguished through the canonical repository, signed release artifacts, checksums, and an explicit official-distribution statement. Those measures do not remove GPL redistribution rights.
- Copyright protects original code and artwork; it does not by itself reserve exclusive control over redistribution already licensed under the GPL.
- A separate trademark policy can control use of a protected project name or logo in ways that avoid confusion about modified builds. No evidence shows that `Image Usage Audit` or a logo is a registered trademark, so no registration claim should be made.
- The current name does not imply affiliation with WordPress.org. A special no-affiliation notice is not technically necessary now; counsel may recommend one for future branding or private distribution.

## Files to change only after a restrictive-license decision

A coordinated future release would need an explicit review of at least:

- `LICENSE` and any new legal notices;
- the `License` and `License URI` fields in `image-usage-audit.php` and `readme.txt`;
- `composer.json`, `package.json`, and `README.md` license declarations;
- `scripts/validate-metadata.mjs` and `scripts/build-zip.ps1`, which currently enforce GPL metadata;
- `SECURITY.md`, distribution documentation, release notes, and any website terms;
- plugin version, `IUA_VERSION`, stable tag, changelog, upgrade notice, and POT project version for the deliberate release.

Do not put a draft proprietary license into the final ZIP. Keep the present GPL license for every WordPress.org-targeted build.

## Legal checks still required

- Verify the identity relationship between `elliot` and `ussmarines`.
- Collect authorship/assignment records for every contribution, including any code obtained outside Git.
- Locate any previously distributed 2.2.1, 2.2.2, 2.2.3, or 2.2.5 artifacts and preserve their license evidence.
- Confirm whether employment, contractor, AI-tool, template, or copied-code terms affect ownership.
- Review the derivative-work and compatibility questions for a proprietary WordPress plugin in the intended jurisdictions.
- Have counsel draft the restrictive license and any trademark policy; do not rely on this document as operative legal terms.

## Recommendation

Choose Path A if WordPress.org reach, community compatibility, and the already validated distribution process matter most. Choose Path B only if preventing future authorized forks/resale is the overriding requirement and the owner accepts private distribution, a legal ownership audit, counsel cost, and the permanent availability of prior GPL copies. The two goals cannot be satisfied by one contradictory license.
