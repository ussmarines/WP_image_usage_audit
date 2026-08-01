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

release_path = Path(".github/workflows/release.yml")
release = release_path.read_text(encoding="utf-8")

old_trigger = """on:
  push:
    tags:
      - 'v*'
"""
new_trigger = """on:
  push:
    tags:
      - 'v*'
    branches:
      - main
    paths:
      - '.github/release-request.json'
"""
if release.count(old_trigger) != 1:
    raise SystemExit("release.yml: unexpected trigger block")
release = release.replace(old_trigger, new_trigger, 1)

old_job_header = """  build-test-attest:
    runs-on: ubuntu-latest
    timeout-minutes: 25
    permissions:
"""
new_job_header = """  build-test-attest:
    runs-on: ubuntu-latest
    timeout-minutes: 25
    outputs:
      release_tag: ${{ steps.release-context.outputs.tag }}
    permissions:
"""
if release.count(old_job_header) != 1:
    raise SystemExit("release.yml: unexpected build job header")
release = release.replace(old_job_header, new_job_header, 1)

old_identity = """      - name: Verify release identity
        env:
          RELEASE_TAG: ${{ github.ref_name }}
        run: |
          node scripts/validate-release-tag.mjs "$RELEASE_TAG"
          git fetch --no-tags origin main:refs/remotes/origin/main
          git merge-base --is-ancestor "$GITHUB_SHA" origin/main
"""
new_identity = """      - name: Resolve and verify release identity
        id: release-context
        env:
          REF_TYPE: ${{ github.ref_type }}
          REF_NAME: ${{ github.ref_name }}
        run: |
          if [[ "$REF_TYPE" == "tag" ]]; then
            release_tag="$REF_NAME"
          else
            release_version="$(node -e "const fs=require('fs');const r=JSON.parse(fs.readFileSync('.github/release-request.json','utf8'));if(!/^\\d+\\.\\d+\\.\\d+$/.test(r.version||'')){process.exit(1)}process.stdout.write(r.version)")"
            release_tag="v${release_version}"
          fi
          node scripts/validate-release-tag.mjs "$release_tag"
          git fetch --no-tags origin main:refs/remotes/origin/main
          git merge-base --is-ancestor "$GITHUB_SHA" origin/main
          echo "tag=$release_tag" >> "$GITHUB_OUTPUT"
"""
if release.count(old_identity) != 1:
    raise SystemExit("release.yml: unexpected release identity step")
release = release.replace(old_identity, new_identity, 1)

verify_anchor = """      - name: Verify checksum and provenance
        env:
          GH_TOKEN: ${{ github.token }}
        run: |
          cd release
          sha256sum --check --strict image-usage-audit.zip.sha256
          gh attestation verify image-usage-audit.zip --repo "$GITHUB_REPOSITORY"
"""
tag_step = verify_anchor + """      - name: Create release tag from an approved main release request
        if: github.ref_type != 'tag'
        env:
          GH_TOKEN: ${{ github.token }}
          RELEASE_TAG: ${{ needs.build-test-attest.outputs.release_tag }}
        run: |
          existing_sha="$(gh api "repos/$GITHUB_REPOSITORY/git/ref/tags/$RELEASE_TAG" --jq '.object.sha' 2>/dev/null || true)"
          if [[ -n "$existing_sha" ]]; then
            if [[ "$existing_sha" != "$GITHUB_SHA" ]]; then
              echo "Tag $RELEASE_TAG already points to $existing_sha, expected $GITHUB_SHA." >&2
              exit 1
            fi
          else
            gh api --method POST "repos/$GITHUB_REPOSITORY/git/refs" \
              -f ref="refs/tags/$RELEASE_TAG" \
              -f sha="$GITHUB_SHA"
          fi
"""
if release.count(verify_anchor) != 1:
    raise SystemExit("release.yml: unexpected provenance step")
release = release.replace(verify_anchor, tag_step, 1)

release = release.replace(
    "          RELEASE_TAG: ${{ github.ref_name }}\n",
    "          RELEASE_TAG: ${{ needs.build-test-attest.outputs.release_tag }}\n",
    1,
)
release_path.write_text(release, encoding="utf-8", newline="\n")

request["prepared"] = True
request_path.write_text(
    json.dumps(request, indent=2) + "\n", encoding="utf-8", newline="\n"
)
