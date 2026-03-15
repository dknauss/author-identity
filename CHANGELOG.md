# Changelog

All notable changes to this repository should be documented here.

This file complements:

- [README.md](README.md) for repository orientation
- [RELEASE_NOTES.md](RELEASE_NOTES.md) for release-note wording conventions
- commit history for exact implementation detail

## Release discipline

When cutting a release:

1. Move relevant entries from `Unreleased` into a dated version section.
2. Keep entries grouped under `Added`, `Changed`, `Fixed`, `Security`, and `Docs` where useful.
3. Mirror the public summary in the GitHub release notes.
4. Include the AI-assistance note from [RELEASE_NOTES.md](RELEASE_NOTES.md) when AI materially affected the release.

## Unreleased

### Added

- Release governance baseline:
  - `CHANGELOG.md`
  - `RELEASE_NOTES.md`
  - `.github/CODEOWNERS`
  - `.github/dependabot.yml`
  - issue templates
  - pull request template
- Repository governance files:
  - `CONTRIBUTING.md`
  - release and AI-attribution guidance

### Docs

- Project docs reorganized under `docs/vision`, `docs/planning`, `docs/research`, and `docs/quality`
- Root `README.md` refreshed with current repo layout, status, governance, and verification guidance
- `byline-feed/readme.txt` updated for clearer WordPress.org-facing scope and installation guidance
- Mermaid architecture source moved to `docs/research/`

### Changed

- `byline-feed` now includes a runnable WordPress PHPUnit harness, build assets, and CI-backed verification
- Adapter and feed layers now have CAP, PPA, Atom, RSS2, and author-contract coverage
- `byline-feed` now emits multi-author JSON-LD Article + Person schema on singular content, with conservative Yoast/Rank Math coexistence rules
- `byline-feed` now has browser E2E coverage for the perspective editor panel via `wp-env` + Playwright
- Root documentation now reflects the current MVP-complete state of WP-01 through WP-03 rather than the earlier pre-CI audit snapshot

### Fixed

- Composer lockfile compatibility across the GitHub Actions PHP matrix
- CI runner setup for WordPress tests by installing Subversion in the workflow
- PHP 7.4 / 8.0 reflection compatibility in adapter tests
- Broken internal Markdown links after doc moves
- Co-Authors Plus linked-user URL normalization now falls back to WordPress `user_url` when the CAP `website` field is empty

### Security

- GitHub Actions workflow hardened for public-repo use:
  - least-privilege `permissions`
  - immutable action SHA pinning
  - deterministic `npm ci`
  - Node 24 opt-in for JavaScript-based actions

- High-severity npm advisories in `byline-feed/package-lock.json` reduced via targeted overrides
- Remaining npm advisories are confined to transitive `webpack-dev-server` development dependencies and are being tracked separately
