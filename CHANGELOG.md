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

No unreleased entries yet.

## 0.1.0-rc2 - 2026-03-21

### Added

- WP-05 JSON-LD schema enrichment across all shipped modes:
  - Yoast enrichment via `wpseo_schema_article`
  - Rank Math enrichment via `rank_math/json_ld`
  - standalone `<script type="application/ld+json">` output when no schema-owning SEO plugin is active
- richer schema field coverage, including ordered multi-author `Person` arrays, `sameAs` normalization, and byline-specific `additionalProperty` values
- WP-06 rights additions:
  - block editor AI-consent panel for per-post overrides
  - denied-item feed rights metadata in RSS2, Atom, and JSON Feed
  - admin-side AI-consent audit log under `Tools > AI Consent Audit Log`
- portable Docker-backed local PHPUnit runner via `byline-feed/bin/run-phpunit-local.sh`
- stronger PublishPress Authors integration parity tests and regression coverage for method-based guest detection

### Changed

- release-facing docs and WordPress.org-facing plugin readme now describe the shipped schema-enrichment modes, denied-item rights output, audit logging, and the current Playground demo surface
- the public Playground output-demo flow now serves as a maintained RC validation surface with pinned immutable source tags per refresh

### Fixed

- HM Authorship integration CI regression caused by manual `rest_api_init` dispatch in REST tests
- PublishPress Authors guest detection when the upstream object exposes `is_guest()` as a method rather than a property
- stale backlog and known-gaps docs that still described completed test hardening items as open
- PHPCS alignment warnings introduced during the PublishPress Authors adapter fix

### Security

- cleared the active Dependabot/npm advisory backlog on `main` and restored a clean audit baseline for the shipped dependency set

### Docs

- refreshed root README, plugin readme, Playground demo docs, release checklist references, and RC issue tracking to match the rc2 surface
- updated planning and gap-analysis documents to reflect completed audit logging, REST round-trip coverage, role-mapping coverage, empty-author handling, special-character handling, and PublishPress Authors parity testing

## 0.1.0-rc1 - 2026-03-15

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
- Adapter and feed layers now have CAP, PPA, HM Authorship, Atom, RSS2, JSON Feed, and author-contract coverage
- `byline-feed` now emits multi-author JSON-LD Article + Person schema on singular content, with conservative Yoast/Rank Math coexistence rules
- `byline-feed` now has browser E2E coverage for the perspective editor panel and AI-consent UI via `wp-env` + Playwright
- `byline-feed` now supports Human Made Authorship as a first-class adapter with real-plugin integration coverage in CI
- `byline-feed` now includes the first WP-06 slice: AI-consent user/post meta, consent resolution, `robots` meta output, `TDMRep` headers, and `ai.txt`
- Root documentation now reflects the current shipped state through WP-06 initial signaling rather than the earlier pre-CI audit snapshot

### Fixed

- Composer lockfile compatibility across the GitHub Actions PHP matrix
- CI runner setup for WordPress tests by installing Subversion in the workflow
- PHP 7.4 / 8.0 reflection compatibility in adapter tests
- Broken internal Markdown links after doc moves
- Co-Authors Plus linked-user URL normalization now falls back to WordPress `user_url` when the CAP `website` field is empty
- Playwright classic-editor save verification now waits for the real save navigation instead of brittle admin notices

### Security

- GitHub Actions workflow hardened for public-repo use:
  - least-privilege `permissions`
  - pinned action revisions
  - deterministic `npm ci`
  - Node 24 opt-in for JavaScript-based actions
- High-severity npm advisories in `byline-feed/package-lock.json` reduced via targeted overrides
- Remaining npm advisories are confined to transitive `webpack-dev-server` development dependencies and are being tracked separately
