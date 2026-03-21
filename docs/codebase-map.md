# Codebase Map

This is a maintained, curated map of the repository structure.

It is intentionally selective:

- includes the top-level project layout
- highlights the important `byline-feed/` implementation paths
- excludes generated or dependency-heavy directories such as `node_modules/`, `vendor/`, `.tmp/`, and `test-results/`

## Top-level layout

```text
author-identity/
|-- README.md
|-- CHANGELOG.md
|-- CONTRIBUTING.md
|-- LICENSE
|-- RELEASE_NOTES.md
|-- SECURITY.md
|-- .github/
|   |-- ISSUE_TEMPLATE/
|   `-- workflows/
|-- docs/
|   |-- codebase-map.md
|   |-- README.md
|   |-- planning/
|   |-- quality/
|   |-- research/
|   |-- vision/
|   `-- legacy/
|-- implementation-strategy/
|-- byline-rss-spec-adoption/
|-- byline-feed/
`-- playground/
    |-- bin/
    `-- output-demo/
```

## `byline-feed/` plugin layout

```text
byline-feed/
|-- byline-feed.php
|-- composer.json
|-- composer.lock
|-- package.json
|-- package-lock.json
|-- phpunit.xml.dist
|-- playwright.config.js
|-- readme.txt
|-- .wp-env.json
|-- bin/
|   |-- build-plugin-zip.sh
|   |-- install-wp-tests.sh
|   |-- run-e2e.sh
|   |-- run-integration-tests.sh
|   |-- run-phpunit-local.sh
|   `-- setup-e2e.sh
|-- build/
|   |-- ai-consent-panel.tsx.asset.php
|   |-- ai-consent-panel.tsx.js
|   |-- perspective-panel.tsx.asset.php
|   `-- perspective-panel.tsx.js
|-- docs/
|   `-- output-reference.md
|-- inc/
|   |-- interface-adapter.php
|   |-- namespace.php
|   |-- class-adapter-core.php
|   |-- class-adapter-cap.php
|   |-- class-adapter-ppa.php
|   |-- class-adapter-authorship.php
|   |-- author-meta.php
|   |-- perspective.php
|   |-- rights.php
|   |-- fediverse.php
|   |-- feed-common.php
|   |-- feed-rss2.php
|   |-- feed-atom.php
|   |-- feed-json.php
|   |-- schema.php
|   |-- schema-yoast.php
|   `-- schema-rankmath.php
|-- src/
|   |-- ai-consent-panel.tsx
|   `-- perspective-panel.tsx
`-- tests/
    |-- e2e/
    `-- phpunit/
```

## Directory roles

| Path | Purpose |
| --- | --- |
| `docs/vision/` | Project framing and long-range positioning |
| `docs/planning/` | Strategy, adoption, and planning references |
| `docs/research/` | Current and exploratory research inputs |
| `docs/quality/` | Assessment, release, and testing standards |
| `implementation-strategy/` | Delivery specs and implementation roadmap |
| `byline-rss-spec-adoption/` | Legacy planning and earlier positioning documents |
| `byline-feed/inc/` | PHP runtime code: adapters, outputs, and shared logic |
| `byline-feed/src/` | Editor-facing TypeScript entry points |
| `byline-feed/build/` | Built JavaScript assets committed for the plugin |
| `byline-feed/tests/phpunit/` | PHP unit and integration coverage |
| `byline-feed/tests/e2e/` | Playwright coverage for editor and output flows |
| `playground/output-demo/` | Stable public demo bundle for shipped outputs |

## Maintenance note

Update this file when the repo gains or removes major top-level sections, or when the plugin's key implementation paths change.
