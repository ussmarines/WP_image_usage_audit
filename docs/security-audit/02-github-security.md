# Phase 1 — GitHub security

Status: complete on 2026-08-01.

## Changes applied and verified

| Control | Initial state | Final state |
| --- | --- | --- |
| Dependency graph / vulnerability alerts | Disabled | Enabled; verification returned HTTP 204 |
| Dependabot security updates | Disabled | Enabled |
| Automated security fixes | Disabled | Enabled and not paused |
| Secret scanning | Enabled | Enabled |
| Push protection | Enabled | Enabled |
| Private vulnerability reporting | Enabled | Enabled |
| Actions allowed sources | All actions | GitHub-owned actions, verified creators, and `shivammathur/setup-php@*` |
| Required action pinning | Disabled | Full commit SHA required |
| Default `GITHUB_TOKEN` permission | Read-only | Read-only |
| Actions PR approval permission | Disabled | Disabled |

The existing workflow already pins `actions/checkout`, `actions/setup-node`, and
`shivammathur/setup-php` to full 40-character commit SHAs. The actionlint container is pinned by digest,
so the repository-level SHA requirement is compatible with the current QA workflow.

## Alerts surfaced after enabling Dependabot

GitHub reported four open, non-dismissed high-severity alerts:

1. `wp-coding-standards/wpcs` — `GHSA-3pwp-g2mj-5p3v` in `composer.lock`
2. `adm-zip` — `GHSA-xcpc-8h2w-3j85` in `package-lock.json`
3. `fast-xml-parser` — `GHSA-8r6m-32jq-jx6q` in `package-lock.json`
4. `fast-uri` — `GHSA-v2hh-gcrm-f6hx` in `package-lock.json`

No alert was dismissed, hidden, or classified as a false positive. Dependency paths, fixed versions,
and compatible minimal upgrades are handled in Phase 2.

## Access and integration review

- One collaborator is returned: repository owner `ussmarines`, with administrator access.
- No deploy keys, webhooks, Actions secrets, Dependabot secrets, Actions variables, or environments exist.
- No access was removed because no unnecessary collaborator or integration was demonstrated.
- Secret-scanning alerts returned an empty open-alert set after the feature was verified active.

## Controls deferred to later phases

- Code scanning has no analysis yet. A repository workflow is evaluated in Phase 6; PHP will not be
  represented as CodeQL-supported.
- `main` has no ruleset or branch protection. A practical single-maintainer ruleset is created in Phase 7
  only after stable green check names are confirmed.
- Dependency Review is added and validated in Phase 6.

## Limitations and manual follow-up

GitHub accepted the update request for non-provider secret patterns and secret validity checks but returned
both controls as disabled afterward. The API therefore does not prove these features are available for the
current repository/plan. Verify them manually under **Settings → Code security and analysis** and enable them
only if GitHub offers the controls without requiring an unsuitable plan change.

The current user token cannot enumerate GitHub App installations; that endpoint requires authentication
authorized to a GitHub App. Verify installed apps manually under **Settings → Integrations → GitHub Apps**.
No claim is made that no apps are installed.

GitHub does not expose a distinct repository-level malicious-dependency-detection setting in the API calls
used here. Its availability is therefore not claimed beyond the enabled dependency graph and Dependabot
controls.

No credential or secret value was requested or recorded.
