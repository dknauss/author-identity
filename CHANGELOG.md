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

## 0.1.0 - 2026-10-21

First stable release of Byline Feed. It includes everything shipped in the release candidates (rc2 to rc5) and the changes below.

### Added

- JSON Feed follows the Byline spec's JSON model alongside the existing output: feed-level `_byline.version` (the namespace URI) and `_byline.contributors[]` (spec Person objects with `context`, `avatar`, `now`, `uses`), and item-level `_byline.author` with `_byline.role` on single-author items. Multi-author items carry `_byline.author` as an ordered array of ids. `spec_version`, `org`, and `authors[]._byline` are unchanged.
- CI: a multisite PHPUnit lane, and `workflow_dispatch` for on-demand release verification.

### Security

- Standalone JSON-LD is encoded with `JSON_HEX_TAG`, so an author name containing `</script>` can no longer close the script element. Core WordPress strips tags from display names, so this mainly protects names from adapters, guest-author records, and the `byline_feed_authors` filter.

### Fixed

- The Co-Authors Plus integration suite never ran in CI: it checked for a global `add_coauthors()`, which CAP does not define. It now uses CAP's real API and covers byline order and guest authors. An integration suite now fails, rather than skips, when its plugin is requested but its API is missing.
- The REST user-meta round-trip test failed on multisite, where only a super admin may edit another user.

### Docs

- `docs/planning/spec-alignment-0.1.0.md` records each difference from the Byline spec as fixed, accepted, or deferred, and defines the project's terms.
- `docs/quality/LOCAL_VERIFICATION.md` maps each local check to its CI job; the release checklist describes the automated release workflow.
- `.planning/SUITE.md` is the planning hub for the authorship repositories.

## 0.1.0-rc5 - 2026-10-14

### Fixed

- Jetpack's `fediverse:creator` tag (from a Jetpack Social Mastodon connection) no longer credits a person on posts in `organization` author mode. It is also removed when Byline Feed prints its own handles, so pages carry no duplicate or conflicting handle. Jetpack's tag stays when Byline Feed has no handle for the post.

## 0.1.0-rc4 - 2026-10-14

### Added

- `organization` author mode (`byline_feed_author_mode` filter, `byline_feed_get_author_mode()`): for writing in an institutional or collective voice, JSON-LD names the publisher as author (a reference to the publisher node under Yoast SEO and Rank Math), and no `fediverse:creator` or author meta tags are printed. Feeds are not yet affected.
- Author meta tags on singular views: `author` and `og:author` with the authors' names, and one `article:author` per author profile URL. Mastodon's link cards fall back to `og:author` because they read JSON-LD bylines only from `WebPage` or `NewsArticle` nodes. Printed by default only in standalone schema mode (`byline_feed_author_meta_tags_enabled`, `byline_feed_author_meta_tags`).
- Standalone JSON-LD gives the article a stable `@id` (`<permalink>#article`) and `mainEntityOfPage`, and the publisher the `@id` `<home>/#organization`, so other plugins (a bibliography's `citation` list, say) can reference them rather than emit competing page-level nodes.

- Attribution panel (block editor sidebar and classic-editor metabox) storing an editor's explicit author mode in `_byline_author_mode` (REST-registered). "Automatic" stores nothing and leaves the decision to the `byline_feed_author_mode` filter, which now also receives the stored choice so automatic suggesters can defer to it.

### Docs

- `docs/output-reference.md` said the plugin disables its schema output under Yoast SEO or Rank Math; it enriches their Article node instead. Corrected, with the new output documented.

## 0.1.0-rc3 - 2026-03-21

### Added

- feed-level rights summaries in RSS2, Atom, and JSON Feed using the existing Byline rights vocabulary with `allow`, `deny`, and `mixed` consent states
- real ActivityPub integration coverage in CI plus PHPUnit integration tests for plugin-derived `ap_actor_url` resolution
- Playwright coverage for the fediverse profile field and the classic-editor Content Perspective metabox fallback

### Changed

- AI-consent admin UI now surfaces the current policy endpoint alongside the existing post/user controls
- local `wp-env` / Playwright defaults now use port `8896` instead of `8886` to avoid the earlier port collision in environments with forwarded services or tunnels
- adapter/unit expectations for `ap_actor_url` now accept plugin-derived values when ActivityPub is active instead of assuming an empty field

### Docs

- synced root/plugin status docs, output reference, assessment notes, gap analysis, and implementation schedule to reflect the completed WP-06 advisory surface
- drafted rc3 release notes and updated the plugin readme changelog for the new release candidate

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
