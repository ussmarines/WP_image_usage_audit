from pathlib import Path

OLD_VERSION = "2.2.8"
NEW_VERSION = "2.2.9"
DONATION_URL = "https://paypal.me/ussmarinesdot"


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8", newline="\n")


def replace_exact(content: str, old: str, new: str, label: str, expected: int = 1) -> str:
    count = content.count(old)
    if count != expected:
        raise RuntimeError(f"{label}: expected {expected} occurrence(s), found {count}")
    return content.replace(old, new)


main = read("image-usage-audit.php")
main = replace_exact(main, " * Version: 2.2.8", " * Version: 2.2.9", "plugin header")
main = replace_exact(
    main,
    "define( 'IUA_VERSION', '2.2.8' );",
    "define( 'IUA_VERSION', '2.2.9' );",
    "IUA_VERSION",
)
write("image-usage-audit.php", main)

validator = read("scripts/validate-metadata.mjs")
validator = replace_exact(
    validator,
    "const version = '2.2.8';",
    "const version = '2.2.9';",
    "metadata validator version",
)
write("scripts/validate-metadata.mjs", validator)

readme = read("README.md")
support_section = f"""## Support the project

If Image Usage Audit has been useful to you, you can support its continued development with an optional donation:

[Support the project via PayPal]({DONATION_URL})

Thank you for helping maintain and improve the plugin.

"""
if "## Support the project" not in readme:
    readme = replace_exact(
        readme,
        "## Contributing\n",
        support_section + "## Contributing\n",
        "README support insertion point",
    )
write("README.md", readme)

wp_readme = read("readme.txt")
if "Donate link:" not in wp_readme:
    wp_readme = replace_exact(
        wp_readme,
        "Contributors: ussmarines\n",
        f"Contributors: ussmarines\nDonate link: {DONATION_URL}\n",
        "WordPress donate header",
    )
wp_readme = replace_exact(
    wp_readme,
    "Stable tag: 2.2.8",
    "Stable tag: 2.2.9",
    "WordPress stable tag",
)

support_description = f"""= Support the project =

If Image Usage Audit has been useful to you, you can support its continued development with an optional donation: {DONATION_URL}

"""
if "= Support the project =" not in wp_readme:
    wp_readme = replace_exact(
        wp_readme,
        "Important:\n",
        support_description + "Important:\n",
        "WordPress support description insertion point",
    )

changelog = """= 2.2.9 =
* Added optional PayPal support links to the GitHub repository, WordPress.org metadata, and the plugin administration page.
* Added GitHub Sponsor button configuration through the repository funding file.
* Refreshed translation and release metadata for the new support section.

"""
if "= 2.2.9 =" not in wp_readme:
    wp_readme = replace_exact(
        wp_readme,
        "== Changelog ==\n\n",
        "== Changelog ==\n\n" + changelog,
        "WordPress changelog insertion point",
    )

upgrade = """= 2.2.9 =

Adds optional, non-intrusive donation links to support continued plugin development.

"""
if "== Upgrade Notice ==\n\n= 2.2.9 =" not in wp_readme:
    wp_readme = replace_exact(
        wp_readme,
        "== Upgrade Notice ==\n\n",
        "== Upgrade Notice ==\n\n" + upgrade,
        "WordPress upgrade notice insertion point",
    )
write("readme.txt", wp_readme)

admin_page = read("views/admin-page.php")
support_card = f"""

\t<div class=\"iua-card\" style=\"margin-top: 20px;\">
\t\t<h2><?php esc_html_e( 'Support the project', 'image-usage-audit' ); ?></h2>
\t\t<p><?php esc_html_e( 'If Image Usage Audit has been useful to you, you can support its continued development with an optional donation.', 'image-usage-audit' ); ?></p>
\t\t<p>
\t\t\t<a class=\"button button-secondary\" href=\"<?php echo esc_url( '{DONATION_URL}' ); ?>\" target=\"_blank\" rel=\"noopener noreferrer\">
\t\t\t\t<?php esc_html_e( 'Support via PayPal', 'image-usage-audit' ); ?>
\t\t\t</a>
\t\t</p>
\t</div>
"""
if "Support via PayPal" not in admin_page:
    closing = "\n</div>\n"
    if not admin_page.endswith(closing):
        raise RuntimeError("admin page: unexpected closing structure")
    admin_page = admin_page[: -len(closing)] + support_card + closing
write("views/admin-page.php", admin_page)

for temporary_path in (
    Path("scripts/apply-donation-release.py"),
    Path(".github/workflows/apply-donation-release.yml"),
):
    if temporary_path.exists():
        temporary_path.unlink()

print(f"Prepared Image Usage Audit {NEW_VERSION} donation release changes.")
