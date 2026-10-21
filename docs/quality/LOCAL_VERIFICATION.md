# Local Verification

What to run before cutting a Byline Feed release, and which CI job each local
command stands in for. All commands run from `byline-feed/` unless noted.

## Prerequisites

| Need | Used by | Notes |
|------|---------|-------|
| PHP 7.4+ with `mysqli` | PHPUnit, PHPCS | CI runs PHP 7.4–8.3. |
| Composer | PHPUnit, PHPCS | `composer install` |
| MySQL or MariaDB | PHPUnit | Or Docker, via `bin/run-phpunit-local.sh`. |
| WordPress test suite | PHPUnit | `bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest` (needs Subversion and access to wordpress.org). Without wordpress.org access, a `wordpress-develop` checkout from GitHub works: point `WP_TESTS_DIR` at its `tests/phpunit` and write a `wp-tests-config.php` beside `src/`. |
| Node 20+ and npm | Build, E2E | `npm ci` |
| Docker | E2E (`wp-env`), Docker-backed PHPUnit | `@wordpress/env` is pinned in `package.json`. |

## The release path

Run these in order. Each must pass.

| # | Command | Proves | CI job |
|---|---------|--------|--------|
| 1 | `composer lint` | WordPress coding standards | PHPCS |
| 2 | `WP_TESTS_DIR=… vendor/bin/phpunit` (or `bin/run-phpunit-local.sh` with Docker) | Single-site behavior: adapters, feeds, JSON-LD, meta tags, rights | PHPUnit — PHP x / WP y |
| 3 | `WP_MULTISITE=1 WP_TESTS_DIR=… vendor/bin/phpunit` | The same suite on multisite | PHPUnit — PHP 8.3 / WP latest / multisite |
| 4 | `npm run build` | Editor panels compile | Node build |
| 5 | `npm run test:e2e` | Editor and profile UI in a real browser | E2E — Playwright |
| 6 | `bin/build-plugin-zip.sh` | The release zip builds and contains only runtime files | Release (on merge) |

A run of step 2 or 3 ends with `OK, but incomplete, skipped, or risky tests!`
and about 20 skips. That is expected: the integration classes skip when their
plugin is not loaded.

## Integration runs

Each adapter has a real-plugin suite. Load the plugin by pointing an
environment variable at its main file, then run step 2:

| Variable | Plugin | Where CI gets it |
|----------|--------|------------------|
| `BYLINE_CAP_PLUGIN` | Co-Authors Plus | wordpress.org latest stable |
| `BYLINE_PPA_PLUGIN` | PublishPress Authors | wordpress.org latest stable |
| `BYLINE_HM_PLUGIN` | Authorship (`dknauss/authorship` `develop`, plus `humanmade/asset-loader` in `lib/asset-loader`) | GitHub |
| `BYLINE_ACTIVITYPUB_PLUGIN` | ActivityPub | wordpress.org latest stable |

When one of these variables is set, that plugin's integration tests **fail**
rather than skip if the plugin's API is missing. Before 0.1.0 a skip passed
silently, which hid the fact that the Co-Authors Plus suite never ran in CI.

## When to re-verify

CI runs on every push and pull request to `main` that touches `byline-feed/**`
or `.github/workflows/**`. Changes elsewhere (docs, planning) do not affect the
shipped plugin and do not trigger it.

Run CI by hand (Actions → CI → Run workflow) before a release when:

- the last CI run on `main` is older than the latest WordPress or adapter
  plugin release, since `latest` and `latest-stable` move underneath it;
- the release branch only changed docs since its last green run.

## Evidence to keep

For the release record, note in the release PR:

- the PHPUnit summary line for steps 2 and 3, and for any integration run;
- the WordPress version tested;
- the zip file name and its SHA-256 from step 6.
