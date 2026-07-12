# Test layers

- `unit/` runs without WordPress and covers deterministic scanner normalization and provenance rules.
- WordPress integration, AJAX, CSV, uninstall, and multisite tests require the `@wordpress/env` test service and are intentionally kept separate from the unit suite.
- `.github/workflows/qa.yml` provides the current WordPress smoke layer: activation, Plugin Check, and POT freshness.
