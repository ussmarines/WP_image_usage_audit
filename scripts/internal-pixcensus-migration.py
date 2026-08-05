from __future__ import annotations

from pathlib import Path
import json
import re
import shutil

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
PLUGIN_NAME = "PixCensus — Media Usage Audit"
PLUGIN_SLUG = "pixcensus-media-audit"
VERSION = "3.0.1"

EXCLUDED_PARTS = {
    ".git",
    "node_modules",
    "vendor",
    "dist",
}
EXCLUDED_PREFIXES = (
    ".github/workflows/",
    ".security/",
    ".codex/",
    ".agents/",
    "docs/security-audit/",
    "tests/security/",
)
TEXT_SUFFIXES = {
    ".php",
    ".js",
    ".css",
    ".md",
    ".txt",
    ".json",
    ".yml",
    ".yaml",
    ".xml",
    ".dist",
    ".ps1",
    ".mjs",
    ".py",
    ".svg",
}

REPLACEMENTS = (
    ("IUAAdmin", "PixCensusAdmin"),
    ("Image Usage Audit", PLUGIN_NAME),
    ("image-usage-audit", PLUGIN_SLUG),
    ("IUA_", "PIXCENSUS_"),
    ("iua_", "pixcensus_"),
    ("iua-", "pixcensus-"),
)


def relative(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def is_text_target(path: Path) -> bool:
    rel = relative(path)
    if path.name == "internal-pixcensus-migration.py":
        return False
    if any(part in EXCLUDED_PARTS for part in path.parts):
        return False
    if rel.startswith(EXCLUDED_PREFIXES):
        return False
    return path.suffix.lower() in TEXT_SUFFIXES or path.name in {
        "README.md",
        "AGENTS.md",
        "SECURITY.md",
        "SECURITY_PRODUCTION_RULES.md",
        "composer.json",
        "package.json",
        "package-lock.json",
        "phpcs.xml.dist",
        "phpstan.neon.dist",
        "phpunit.xml.dist",
        ".distignore",
        ".gitignore",
        ".wp-env.json",
        ".wp-env.wp59.json",
        ".wp-env.multisite.json",
    }


def replace_identity() -> None:
    for path in ROOT.rglob("*"):
        if not path.is_file() or not is_text_target(path):
            continue
        try:
            content = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        updated = content
        for old, new in REPLACEMENTS:
            updated = updated.replace(old, new)
        if path.name != "readme.txt":
            updated = updated.replace("3.0.0", VERSION)
        if updated != content:
            path.write_text(updated, encoding="utf-8", newline="\n")


def rename_runtime_files() -> None:
    renames = {
        "image-usage-audit.php": "pixcensus-media-audit.php",
        "includes/class-iua-cdn-settings.php": "includes/class-pixcensus-cdn-settings.php",
        "includes/class-iua-csv.php": "includes/class-pixcensus-csv.php",
        "includes/class-iua-scanner.php": "includes/class-pixcensus-scanner.php",
        "languages/image-usage-audit.pot": "languages/pixcensus-media-audit.pot",
        "assets/image-usage-audit-mark.svg": "assets/pixcensus-media-audit-mark.svg",
    }
    for old, new in renames.items():
        source = ROOT / old
        target = ROOT / new
        if source.exists():
            target.parent.mkdir(parents=True, exist_ok=True)
            source.rename(target)


def update_main_plugin() -> None:
    path = ROOT / "pixcensus-media-audit.php"
    content = path.read_text(encoding="utf-8")
    content = re.sub(r"(?m)^ \* Description:.*$", " * Description: Inventory media usage with provenance, CSV export, manual review tools, and CDN rewrite support.", content)
    content = re.sub(r"(?m)^ \* Version:\s*\d+\.\d+\.\d+\s*$", f" * Version: {VERSION}", content)
    content = re.sub(
        r"define\(\s*'PIXCENSUS_VERSION'\s*,\s*'\d+\.\d+\.\d+'\s*\)",
        f"define( 'PIXCENSUS_VERSION', '{VERSION}' )",
        content,
    )
    content = content.replace(
        "__( 'PixCensus — Media Usage Audit', 'pixcensus-media-audit' ),\n\t\t\t__( 'PixCensus — Media Usage Audit', 'pixcensus-media-audit' ),",
        "__( 'PixCensus — Media Usage Audit', 'pixcensus-media-audit' ),\n\t\t\t__( 'PixCensus', 'pixcensus-media-audit' ),",
    )
    path.write_text(content, encoding="utf-8", newline="\n")


def update_readme() -> None:
    path = ROOT / "readme.txt"
    content = path.read_text(encoding="utf-8")
    content = re.sub(r"(?m)^=== .* ===$", "=== PixCensus — Media Usage Audit ===", content, count=1)
    content = re.sub(r"(?m)^Stable tag:\s*\d+\.\d+\.\d+\s*$", f"Stable tag: {VERSION}", content)
    lines = content.splitlines()
    license_index = next(i for i, line in enumerate(lines) if line.startswith("License URI:"))
    if license_index + 2 < len(lines):
        lines[license_index + 2] = (
            "Inventory media usage with provenance, CSV export, manual review tools, and CDN rewrite support."
        )
    content = "\n".join(lines) + "\n"
    content = content.replace(
        "== Changelog ==\n\n",
        "== Changelog ==\n\n"
        "= 3.0.1 =\n"
        "* Renamed the plugin to PixCensus — Media Usage Audit with the distinctive `pixcensus-media-audit` slug.\n"
        "* Replaced all active PHP, WordPress, JavaScript, CSS, option, nonce, and AJAX prefixes with `pixcensus_` / `PIXCENSUS_`.\n"
        "* Introduced a new self-contained PixCensus visual identity for the administration screen, GitHub README, and WordPress.org directory assets.\n"
        "* Revalidated administrator capabilities, action-specific nonces, package metadata, multisite behavior, and the non-destructive scan workflow.\n\n",
        1,
    )
    content = content.replace(
        "== Upgrade Notice ==\n\n",
        "== Upgrade Notice ==\n\n"
        "= 3.0.1 =\n\n"
        "Renames the plugin and its identifiers to PixCensus, adds the new directory artwork, and preserves the audited non-destructive workflow.\n\n",
        1,
    )
    path.write_text(content, encoding="utf-8", newline="\n")


def update_readme_markdown() -> None:
    path = ROOT / "README.md"
    content = path.read_text(encoding="utf-8")
    content = re.sub(
        r'<img src="[^"]+" alt="[^"]+" width="772">',
        '<img src=".wordpress-org/banner-1544x500.png" alt="PixCensus — Media Usage Audit" width="772">',
        content,
        count=1,
    )
    content = content.replace(
        "A non-destructive WordPress plugin for reviewing image usage before cleaning up the Media Library.",
        "A non-destructive WordPress plugin that maps where media is referenced before you clean up the Media Library.",
    )
    content = content.replace("Version 3.0.0", f"Version {VERSION}")
    path.write_text(content, encoding="utf-8", newline="\n")


def update_admin_branding() -> None:
    css_path = ROOT / "assets/admin.css"
    css = css_path.read_text(encoding="utf-8")
    css = re.sub(
        r":root \{.*?\}\n",
        ":root {\n"
        "\t--pixcensus-border: #cfdae8;\n"
        "\t--pixcensus-soft: #f4f7fb;\n"
        "\t--pixcensus-accent: #177f88;\n"
        "\t--pixcensus-accent-strong: #075a67;\n"
        "\t--pixcensus-teal: #38e4c4;\n"
        "\t--pixcensus-cyan: #55c9ff;\n"
        "\t--pixcensus-violet: #786fff;\n"
        "\t--pixcensus-navy: #07162b;\n"
        "\t--pixcensus-muted: #526174;\n"
        "}\n",
        css,
        count=1,
        flags=re.S,
    )
    css = re.sub(
        r"background: radial-gradient\(circle at 82% 35%.*?;",
        "background: radial-gradient(circle at 78% 28%, rgba(56, 228, 196, 0.28), transparent 30%), radial-gradient(circle at 92% 78%, rgba(120, 111, 255, 0.24), transparent 34%), linear-gradient(135deg, var(--pixcensus-navy), #102f52 58%, #0b6670);",
        css,
        count=1,
    )
    css = css.replace("border: 1px solid #1d6079;", "border: 1px solid rgba(85, 201, 255, 0.42);")
    css = css.replace("border-radius: 16px;", "border-radius: 18px;", 1)
    css = css.replace("background-size: 34px 34px;", "background-size: 30px 30px;")
    css += (
        "\n#pixcensus-admin .pixcensus-hero::before {\n"
        "\tcontent: \"\";\n"
        "\tposition: absolute;\n"
        "\tleft: 0;\n"
        "\ttop: 0;\n"
        "\tbottom: 0;\n"
        "\twidth: 4px;\n"
        "\tbackground: linear-gradient(var(--pixcensus-teal), var(--pixcensus-violet));\n"
        "}\n"
        "#pixcensus-admin .pixcensus-card { transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease; }\n"
        "#pixcensus-admin .pixcensus-card:hover { border-color: #a9c7d9; box-shadow: 0 9px 24px rgba(7, 22, 43, .08); transform: translateY(-1px); }\n"
    )
    css_path.write_text(css, encoding="utf-8", newline="\n")

    view_path = ROOT / "views/admin-page.php"
    view = view_path.read_text(encoding="utf-8")
    view = view.replace("Review media usage before cleanup.", "Map every media reference before cleanup.")
    view_path.write_text(view, encoding="utf-8", newline="\n")


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf",
    ]
    for candidate in candidates:
        if Path(candidate).exists():
            return ImageFont.truetype(candidate, size=size)
    return ImageFont.load_default()


def draw_gradient(image: Image.Image, start: tuple[int, int, int], end: tuple[int, int, int]) -> None:
    draw = ImageDraw.Draw(image)
    width, height = image.size
    for y in range(height):
        ratio = y / max(1, height - 1)
        color = tuple(round(start[i] * (1 - ratio) + end[i] * ratio) for i in range(3))
        draw.line((0, y, width, y), fill=color)


def draw_mark(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], scale: int = 1) -> None:
    x0, y0, x1, y1 = box
    w = x1 - x0
    h = y1 - y0
    radius = max(8, int(w * 0.16))
    draw.rounded_rectangle(box, radius=radius, fill="#091b35", outline="#55c9ff", width=max(2, int(w * 0.018)))
    pad = int(w * 0.19)
    gap = int(w * 0.055)
    cell = int((w - 2 * pad - 2 * gap) / 3)
    colors = ["#1b4c70", "#38e4c4", "#786fff"]
    for row in range(3):
        for col in range(3):
            cx = x0 + pad + col * (cell + gap)
            cy = y0 + pad + row * (cell + gap)
            fill = colors[0]
            if (row, col) in {(0, 2), (1, 1), (2, 0)}:
                fill = colors[1]
            if (row, col) == (2, 2):
                fill = colors[2]
            draw.rounded_rectangle((cx, cy, cx + cell, cy + cell), radius=max(2, cell // 5), fill=fill)
    bracket = int(w * 0.12)
    stroke = max(3, int(w * 0.025))
    cyan = "#d7fffa"
    draw.line((x0 + bracket, y0 + int(h * .28), x0 + bracket, y0 + bracket), fill=cyan, width=stroke)
    draw.line((x0 + bracket, y0 + bracket, x0 + int(w * .28), y0 + bracket), fill=cyan, width=stroke)
    draw.line((x1 - bracket, y1 - int(h * .28), x1 - bracket, y1 - bracket), fill=cyan, width=stroke)
    draw.line((x1 - bracket, y1 - bracket, x1 - int(w * .28), y1 - bracket), fill=cyan, width=stroke)


def make_icon(size: int, path: Path) -> None:
    scale = 4
    image = Image.new("RGB", (size * scale, size * scale), "#07162b")
    draw = ImageDraw.Draw(image)
    draw_mark(draw, (8 * scale, 8 * scale, (size - 8) * scale, (size - 8) * scale), scale)
    image = image.resize((size, size), Image.Resampling.LANCZOS)
    image.save(path, optimize=True)


def make_banner(width: int, height: int, path: Path) -> None:
    scale = 2
    image = Image.new("RGB", (width * scale, height * scale))
    draw_gradient(image, (7, 22, 43), (12, 89, 101))
    draw = ImageDraw.Draw(image, "RGBA")
    for x in range(0, width * scale, 60 * scale):
        draw.line((x, 0, x, height * scale), fill=(85, 201, 255, 16), width=1 * scale)
    for y in range(0, height * scale, 60 * scale):
        draw.line((0, y, width * scale, y), fill=(85, 201, 255, 16), width=1 * scale)
    draw.ellipse((width * scale * .68, -height * scale * .22, width * scale * 1.04, height * scale * .92), fill=(56, 228, 196, 38))
    draw.ellipse((width * scale * .82, height * scale * .42, width * scale * 1.08, height * scale * 1.18), fill=(120, 111, 255, 34))

    mark_size = int(height * scale * .60)
    draw_mark(draw, (int(width * scale * .70), int(height * scale * .20), int(width * scale * .70) + mark_size, int(height * scale * .20) + mark_size), scale)

    title_font = font(int(height * scale * .19), bold=True)
    sub_font = font(int(height * scale * .075), bold=True)
    copy_font = font(int(height * scale * .055), bold=False)
    x = int(width * scale * .065)
    draw.text((x, int(height * scale * .18)), "PixCensus", font=title_font, fill="#f6fbff")
    draw.text((x, int(height * scale * .46)), "MEDIA USAGE AUDIT", font=sub_font, fill="#38e4c4")
    draw.text((x, int(height * scale * .66)), "Map every media reference before cleanup", font=copy_font, fill="#d6e5f3")

    image = image.resize((width, height), Image.Resampling.LANCZOS)
    image.save(path, optimize=True)


def write_svg_assets() -> None:
    runtime_svg = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" role="img" aria-labelledby="title desc">
<title id="title">PixCensus mark</title><desc id="desc">A scanned census grid representing mapped media references.</desc>
<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#07162b"/><stop offset="1" stop-color="#0c5965"/></linearGradient></defs>
<rect x="8" y="8" width="240" height="240" rx="42" fill="url(#bg)" stroke="#55c9ff" stroke-width="5"/>
<g fill="#1b4c70"><rect x="56" y="55" width="38" height="38" rx="8"/><rect x="109" y="55" width="38" height="38" rx="8"/><rect x="56" y="108" width="38" height="38" rx="8"/><rect x="162" y="108" width="38" height="38" rx="8"/><rect x="109" y="161" width="38" height="38" rx="8"/></g>
<g fill="#38e4c4"><rect x="162" y="55" width="38" height="38" rx="8"/><rect x="109" y="108" width="38" height="38" rx="8"/><rect x="56" y="161" width="38" height="38" rx="8"/></g>
<rect x="162" y="161" width="38" height="38" rx="8" fill="#786fff"/>
<path d="M39 83V39h44M217 173v44h-44" fill="none" stroke="#d7fffa" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
</svg>'''
    banner_svg = '''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1544 500" role="img" aria-labelledby="title desc">
<title id="title">PixCensus — Media Usage Audit</title><desc id="desc">PixCensus branding with a scanned media census grid.</desc>
<defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#07162b"/><stop offset=".62" stop-color="#102f52"/><stop offset="1" stop-color="#0b6670"/></linearGradient><radialGradient id="glow"><stop stop-color="#38e4c4" stop-opacity=".38"/><stop offset="1" stop-color="#38e4c4" stop-opacity="0"/></radialGradient></defs>
<rect width="1544" height="500" fill="url(#bg)"/><circle cx="1280" cy="120" r="360" fill="url(#glow)"/>
<g opacity=".12" stroke="#55c9ff"><path d="M0 70h1544M0 140h1544M0 210h1544M0 280h1544M0 350h1544M0 420h1544"/><path d="M70 0v500M140 0v500M210 0v500M280 0v500M350 0v500M420 0v500M490 0v500M560 0v500M630 0v500M700 0v500M770 0v500M840 0v500M910 0v500M980 0v500M1050 0v500M1120 0v500M1190 0v500M1260 0v500M1330 0v500M1400 0v500M1470 0v500"/></g>
<g font-family="DejaVu Sans,Arial,sans-serif"><text x="95" y="190" fill="#f6fbff" font-size="94" font-weight="700">PixCensus</text><text x="100" y="278" fill="#38e4c4" font-size="42" font-weight="700" letter-spacing="5">MEDIA USAGE AUDIT</text><text x="100" y="355" fill="#d6e5f3" font-size="31">Map every media reference before cleanup</text></g>
<g transform="translate(1120 92)"><rect width="300" height="300" rx="48" fill="#091b35" stroke="#55c9ff" stroke-width="5"/><g fill="#1b4c70"><rect x="63" y="61" width="48" height="48" rx="10"/><rect x="126" y="61" width="48" height="48" rx="10"/><rect x="63" y="124" width="48" height="48" rx="10"/><rect x="189" y="124" width="48" height="48" rx="10"/><rect x="126" y="187" width="48" height="48" rx="10"/></g><g fill="#38e4c4"><rect x="189" y="61" width="48" height="48" rx="10"/><rect x="126" y="124" width="48" height="48" rx="10"/><rect x="63" y="187" width="48" height="48" rx="10"/></g><rect x="189" y="187" width="48" height="48" rx="10" fill="#786fff"/><path d="M42 98V42h56M258 202v56h-56" fill="none" stroke="#d7fffa" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/></g>
</svg>'''
    (ROOT / "assets/pixcensus-media-audit-mark.svg").write_text(runtime_svg, encoding="utf-8", newline="\n")
    (ROOT / ".wordpress-org/icon.svg").write_text(runtime_svg, encoding="utf-8", newline="\n")
    (ROOT / ".wordpress-org/banner-source.svg").write_text(banner_svg, encoding="utf-8", newline="\n")


def generate_png_assets() -> None:
    target = ROOT / ".wordpress-org"
    target.mkdir(parents=True, exist_ok=True)
    make_icon(128, target / "icon-128x128.png")
    make_icon(256, target / "icon-256x256.png")
    make_banner(772, 250, target / "banner-772x250.png")
    make_banner(1544, 500, target / "banner-1544x500.png")


def add_brand_validator() -> None:
    validator = r'''import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const runtimePaths = [
  'pixcensus-media-audit.php',
  'uninstall.php',
  'readme.txt',
  'assets/admin.css',
  'assets/admin.js',
  'assets/pixcensus-media-audit-mark.svg',
  'views/admin-page.php',
  'includes/class-pixcensus-cdn-settings.php',
  'includes/class-pixcensus-csv.php',
  'includes/class-pixcensus-scanner.php',
  'languages/pixcensus-media-audit.pot',
];

const forbidden = ['Image Usage Audit', 'image-usage-audit', 'IUA_', 'iua_', 'iua-'];
for (const relativePath of runtimePaths) {
  const fullPath = path.join(root, relativePath);
  if (!fs.existsSync(fullPath)) throw new Error(`Missing PixCensus runtime file: ${relativePath}`);
  const content = fs.readFileSync(fullPath, 'utf8');
  for (const token of forbidden) {
    if (content.includes(token)) throw new Error(`Legacy identifier ${token} remains in ${relativePath}`);
  }
}

const main = fs.readFileSync(path.join(root, 'pixcensus-media-audit.php'), 'utf8');
const readme = fs.readFileSync(path.join(root, 'readme.txt'), 'utf8');
const requiredMain = [
  'Plugin Name: PixCensus — Media Usage Audit',
  'Version: 3.0.1',
  'Text Domain: pixcensus-media-audit',
  "define( 'PIXCENSUS_VERSION', '3.0.1' )",
  "define( 'PIXCENSUS_SLUG', 'pixcensus-media-audit' )",
  "current_user_can( 'manage_options' )",
  'check_admin_referer(',
  'check_ajax_referer(',
];
for (const token of requiredMain) {
  if (!main.includes(token)) throw new Error(`Required main-plugin control is missing: ${token}`);
}
if (!readme.includes('Stable tag: 3.0.1')) throw new Error('The WordPress.org stable tag is not 3.0.1.');

const ajaxMethods = [
  'ajax_run_scan',
  'ajax_mark_manual_used',
  'ajax_unmark_manual_used',
  'ajax_mark_manual_used_bulk',
  'ajax_unmark_manual_used_bulk',
];
for (const method of ajaxMethods) {
  const start = main.indexOf(`public function ${method}`);
  if (start < 0) throw new Error(`Missing AJAX method: ${method}`);
  const excerpt = main.slice(start, start + 700);
  if (!excerpt.includes('verify_ajax_request(')) throw new Error(`${method} does not verify capability, action, method, and nonce.`);
}

console.log(JSON.stringify({ result: 'pass', name: 'PixCensus — Media Usage Audit', slug: 'pixcensus-media-audit', version: '3.0.1' }));
'''
    (ROOT / "scripts/validate-branding.mjs").write_text(validator, encoding="utf-8", newline="\n")
    package_path = ROOT / "package.json"
    package = json.loads(package_path.read_text(encoding="utf-8"))
    package.setdefault("scripts", {})["validate:branding"] = "node scripts/validate-branding.mjs"
    package_path.write_text(json.dumps(package, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def update_wordpress_org_docs() -> None:
    response = """Hello,\n\nI renamed the plugin to “PixCensus — Media Usage Audit”.\n\nPlease reserve and assign the new plugin slug:\n\npixcensus-media-audit\n\nI updated the plugin name, slug, text domain, distinctive prefixes, local assets, and related metadata. I also reviewed the nonce and administrator capability checks and uploaded the corrected package through the Add Your Plugin page.\n\nThank you.\n"""
    (ROOT / "docs/wordpress-org/review-response-template.txt").write_text(response, encoding="utf-8", newline="\n")

    branding = """# PixCensus visual identity\n\nPixCensus uses a scanned census-grid motif to represent media inventory, provenance, and review status. The identity is self-contained and does not load remote fonts, images, scripts, or tracking resources.\n\n## Palette\n\n- Navy: `#07162b`\n- Structural blue: `#102f52`\n- Teal: `#38e4c4`\n- Cyan: `#55c9ff`\n- Violet: `#786fff`\n- Light text: `#f6fbff`\n\nThe wide banner is used in the GitHub README and WordPress.org directory. The compact mark is bundled in the plugin administration screen. Directory PNG assets are stored in `.wordpress-org/`; SVG files remain the editable source.\n"""
    (ROOT / "docs/wordpress-org/branding.md").write_text(branding, encoding="utf-8", newline="\n")


def verify_no_legacy_runtime_paths() -> None:
    required = [
        ROOT / "pixcensus-media-audit.php",
        ROOT / "assets/pixcensus-media-audit-mark.svg",
        ROOT / "includes/class-pixcensus-cdn-settings.php",
        ROOT / "includes/class-pixcensus-csv.php",
        ROOT / "includes/class-pixcensus-scanner.php",
        ROOT / "languages/pixcensus-media-audit.pot",
    ]
    for path in required:
        if not path.exists():
            raise SystemExit(f"Required migrated path is missing: {relative(path)}")
    old_paths = [
        ROOT / "image-usage-audit.php",
        ROOT / "assets/image-usage-audit-mark.svg",
        ROOT / "includes/class-iua-cdn-settings.php",
        ROOT / "includes/class-iua-csv.php",
        ROOT / "includes/class-iua-scanner.php",
        ROOT / "languages/image-usage-audit.pot",
    ]
    for path in old_paths:
        if path.exists():
            raise SystemExit(f"Legacy runtime path remains: {relative(path)}")


def main() -> None:
    replace_identity()
    rename_runtime_files()
    update_main_plugin()
    update_readme()
    update_readme_markdown()
    update_admin_branding()
    write_svg_assets()
    generate_png_assets()
    add_brand_validator()
    update_wordpress_org_docs()
    verify_no_legacy_runtime_paths()
    print(json.dumps({"result": "pass", "name": PLUGIN_NAME, "slug": PLUGIN_SLUG, "version": VERSION}))


if __name__ == "__main__":
    main()
