import json
from pathlib import Path

PREVIOUS_VERSION = "2.2.6"
VERSION = "2.2.7"


def replace_once(path: str, old: str, new: str) -> None:
    file_path = Path(path)
    text = file_path.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(
            f"{path}: expected one occurrence, found {count}: {old!r}"
        )
    file_path.write_text(text.replace(old, new, 1), encoding="utf-8", newline="\n")


request_path = Path(".github/release-request.json")
request = json.loads(request_path.read_text(encoding="utf-8"))

if request.get("previous_version") != PREVIOUS_VERSION or request.get("version") != VERSION:
    raise SystemExit("Unexpected release request.")

if request.get("prepared") is True:
    raise SystemExit("Release metadata is already prepared.")

replace_once("image-usage-audit.php", " * Version: 2.2.6", " * Version: 2.2.7")
replace_once(
    "image-usage-audit.php",
    "define( 'IUA_VERSION', '2.2.6' );",
    "define( 'IUA_VERSION', '2.2.7' );",
)
replace_once(
    "scripts/validate-metadata.mjs",
    "const version = '2.2.6';",
    "const version = '2.2.7';",
)
replace_once(
    "languages/image-usage-audit.pot",
    "Project-Id-Version: Image Usage Audit 2.2.6",
    "Project-Id-Version: Image Usage Audit 2.2.7",
)
replace_once("readme.txt", "Stable tag: 2.2.6", "Stable tag: 2.2.7")

changelog = "\n".join(
    [
        "== Changelog ==",
        "",
        "= 2.2.7 =",
        "* Added deterministic property-based security tests for CDN validation and CSV formula neutralization on PHP 7.4 and PHP 8.3.",
        "* Improved scanner normalization and regression coverage for encoded, relative, and scheme-relative image references.",
        "* Hardened dependency, CodeQL, Scorecard, branch-protection, and release workflows with full-SHA action pins and required checks.",
        "* Added reproducible ZIP checksums and GitHub artifact attestations to the release pipeline.",
        "* Updated the confirmed WordPress.org contributor identity and refreshed development dependencies.",
        "",
        "= 2.2.6 =",
    ]
)
replace_once("readme.txt", "== Changelog ==\n\n= 2.2.6 =", changelog)

upgrade_old = "\n".join(
    [
        "== Upgrade Notice ==",
        "",
        "= 2.2.6 =",
        "",
        "Security and robustness release with stricter administrator-only AJAX handling, bounded scanning, multisite lifecycle fixes, broader detection fixtures, and a reproducible validated package.",
    ]
)
upgrade_new = "\n".join(
    [
        "== Upgrade Notice ==",
        "",
        "= 2.2.7 =",
        "",
        "Security, validation, and release-engineering update with broader regression coverage and an attested reproducible package.",
    ]
)
replace_once("readme.txt", upgrade_old, upgrade_new)

request["prepared"] = True
request_path.write_text(
    json.dumps(request, indent=2) + "\n", encoding="utf-8", newline="\n"
)
