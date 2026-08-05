import fs from 'node:fs';
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
  'wp_verify_nonce(',
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
