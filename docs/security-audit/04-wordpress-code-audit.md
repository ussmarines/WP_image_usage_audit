# Phase 3 — Targeted WordPress code audit

Status: discovery complete on 2026-08-01; candidate validation is Phase 4.

## Method and scope

This was a conventional, file-by-file manual review. No Codex Security scan was used. The reviewed
production surface was limited by the current project map to:

- `image-usage-audit.php`;
- `includes/class-iua-scanner.php`;
- `includes/class-iua-cdn-settings.php`;
- `includes/class-iua-csv.php`;
- `views/admin-page.php`;
- `uninstall.php`;
- `assets/admin.js`.

The review traced hooks, request globals, option and SQL operations, filesystem access, output sinks,
dynamic code patterns, remote requests, and deletion primitives. CSS contains no executable data
flow and was outside this security pass.

## Entry-point inventory

| Entry point | Request / caller | Authorization and CSRF | Input and sink | Result |
| --- | --- | --- | --- | --- |
| `render_admin_page()` | Media admin submenu | Menu capability and explicit `manage_options` check | Allow-listed GET filters; escaped HTML, attributes and URLs | Boundary intact. |
| `handle_save_settings()` | `admin_post_iua_save_settings` | `manage_options`; section-specific `check_admin_referer()` | Section allow-list; boolean setting; bounded CDN validator; site options | Boundary intact. |
| `export_csv()` | `admin_post_iua_export_csv` | `manage_options`; `check_admin_referer()` | Allow-listed tab/filter; read-only attachment metadata; formula-neutralized CSV | Boundary intact. |
| `ajax_run_scan()` | authenticated `wp_ajax_iua_run_scan` | POST, exact action, `manage_options`, action-specific nonce | Read-only scanner; non-autoloaded result option | Boundary intact; resource candidates retained below. |
| four manual mark handlers | authenticated AJAX | Shared POST/action/capability/nonce envelope | Strict decimal IDs, 500-item bound, attachment post-type check; option update | Boundary intact. |
| unauthenticated AJAX hooks | `wp_ajax_nopriv_*` | No state-changing handler is reached | Stable 401 JSON only | Safe negative route. |
| activation hook | plugin/network activation | WordPress activation authority | Batched per-site creation of non-autoloaded defaults | No media mutation. |
| uninstall entry | WordPress uninstall context | `WP_UNINSTALL_PLUGIN` gate | Deletes only six plugin options, batched across active multisite sites | Non-destructive to media and content. |

There is no REST route, public form, upload/import handler, arbitrary file include, remote HTTP client,
cron task, shortcode, frontend render hook, or media deletion handler.

## Request-to-sink traces

### Settings

`POST` → `manage_options` → section allow-list → section-specific nonce → WordPress sanitization →
bounded CDN validation (20 entries and byte limits) → `update_option()` → safe redirect with an
allow-listed notice. CDN values affect string normalization only; they never initiate a network
request, so no SSRF sink exists.

### AJAX

`POST` → exact `action` comparison → `manage_options` → action-specific nonce no longer than 128
bytes → strict decimal ID parsing → attachment-type validation → option update or read-only scan →
WordPress JSON response. Bulk input is an array capped at 500 entries and deduplicated.

The scan lock uses atomic option creation, compare-and-swap removal of expired values, a random owner
token, and owner-only release. Failed scans preserve the previous complete result.

### CSV

Nonce-protected export GET → tab/filter allow-list → stored attachment IDs → read-only attachment
metadata → `IUA_CSV::neutralize_formula()` → `fputcsv()` to `php://output`. Values beginning with
optional control/space bytes followed by `=`, `+`, `-`, or `@`, plus tab/newline prefixes, receive a
leading apostrophe. The response sets UTF-8 BOM, `nosniff`, a fixed sanitized filename, and no-cache
headers.

### Rendering and browser code

Stored settings use `esc_attr()` / `esc_textarea()`, provenance and filenames use `esc_html()`, URLs
use `esc_url()`, pagination output uses `wp_kses_post()`, and attachment thumbnail HTML is filtered
through `wp_kses_post()`. JavaScript creates notices with jQuery `.text()` and never inserts response
data with `innerHTML` or `.html()`. Browser storage contains column visibility preferences only.

## Data, SQL, filesystem and multisite review

- The only custom SQL read uses `$wpdb->prepare()` for the option cursor. The lock's `$wpdb->delete()`
  uses a fixed table, fixed column names, typed values, and format placeholders.
- The scanner reads posts, metadata, options, terms and the uploads tree in fixed-size database
  batches. It neither writes nor removes media files.
- `RecursiveDirectoryIterator` does not request symlink following. Files are inspected only for path,
  extension and existence; content is not loaded or executed.
- Attachment metadata paths can influence read-only `file_exists()` checks, but no content read,
  include, download, write, move or delete sink follows, so no exploitable traversal path was found.
- Network activation and uninstall use batches of 100 sites and restore blog context in `finally`.
  Runtime options remain scoped to the current site.
- Stored data consists of settings, attachment ID classifications, bounded provenance labels, orphan
  paths, a timestamp and an expiring lock. Uninstall deletes each plugin-owned option.

## Mandatory vulnerability classes

| Class | Review result |
| --- | --- |
| CSRF | State changes and exports require action-specific nonces; no candidate. |
| Authorization / IDOR | Every interactive route requires `manage_options`; attachment IDs are type-checked; no candidate. |
| XSS | Late context-aware escaping and text-only DOM insertion cover all identified sinks; no candidate. |
| SQL injection | Dynamic value is prepared; remaining query/table identifiers are fixed WordPress internals; no candidate. |
| SSRF | CDN settings perform no network request; no remote-request API exists; not applicable. |
| Path traversal / arbitrary file access | Metadata paths reach existence comparisons only; no read/write/include/delete sink; no candidate. |
| Unsafe deserialization | First-party code calls no `unserialize()`; WordPress may return already-decoded metadata, which is treated as data; recursion candidate retained. |
| Dynamic code / command execution | No `eval`, assertion execution, shell/process API, or dynamic callable from request data; no candidate. |
| Privilege escalation | No role/capability mutation and no route below `manage_options`; no candidate. |
| Media destruction | No media delete, move, rewrite or metadata mutation; invariant preserved. |

## Candidates requiring Phase 4 validation

1. `IUA-SEC-006` — a scan is intentionally exhaustive, while the lock lease is fixed at 900 seconds
   and the traversal has no wall-clock/file-count budget. The authorization boundary substantially
   limits exploitability, but overlapping long scans need a final availability decision.
2. `IUA-SEC-007` — orphan results contain absolute filesystem paths and are saved and returned to the
   authorized AJAX caller although the current view does not render them. The phase must determine
   whether this is an information disclosure or unnecessary data retention only.
3. `IUA-SEC-008` — recursive flattening of decoded nested metadata has no explicit depth/node cap.
   Validation must establish whether a lower-privileged actor can place a reachable structure and
   cause a meaningful availability impact when an administrator scans.

No candidate is described as confirmed at this stage.

## Checklist

- [x] Inventory all production hooks and entry points
- [x] Trace capability and nonce enforcement
- [x] Trace validation, sanitization, database/filesystem sinks and late escaping
- [x] Review CSV formula neutralization
- [x] Review scan batching, locking, memory and recursion
- [x] Review stored data, uninstall behavior and multisite context restoration
- [x] Search for SQL, remote request, unsafe deserialization and dynamic execution sinks
- [x] Record only evidence-backed candidates for separate validation

Next phase: validate or reject `IUA-SEC-006` through `IUA-SEC-008` individually.
