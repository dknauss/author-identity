=== Byline Feed ===
Contributors: dknauss
Tags: rss, atom, byline, author, attribution, feeds
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enriches RSS2, Atom, and JSON Feed output with structured author identity metadata following the Byline specification, and emits fediverse author-attribution meta tags, multi-author JSON-LD schema, and advisory AI-consent signals on singular content.

== Description ==

Byline Feed adds machine-readable author metadata to WordPress feeds using the [Byline specification](https://bylinespec.org/). It is designed to work with multi-author editorial sites while preserving standard feed elements for compatibility.

Implementation-level output details, including example feed fragments and HTML-head metadata, are documented in the repository at `byline-feed/docs/output-reference.md`.

The plugin currently supports:

* RSS2, Atom, and JSON Feed Byline output
* Co-Authors Plus adapter
* PublishPress Authors adapter
* HM Authorship adapter
* Core WordPress fallback adapter
* Content Perspective editorial field
* `fediverse:creator` meta tags for authors with configured fediverse handles
* Multi-author JSON-LD Article + Person output on singular views, including Yoast SEO and Rank Math enrichment modes
* AI-consent signaling with feed-level and denied-item rights metadata, `robots` meta, `TDMRep` headers, `ai.txt`, and admin-side audit logging
* Filter and action hooks for output customization

Byline Feed is additive. It preserves core feed elements such as `<author>` and `<dc:creator>` and adds Byline metadata alongside them.

== Features ==

* Adds `byline:contributors` with structured contributor profiles at feed level
* Adds item-level `byline:author`, `byline:role`, and `byline:perspective` elements
* Supports `byline:profile`, `byline:now`, and `byline:uses` from plugin-owned user meta
* Emits `<meta name="fediverse:creator">` tags on singular views for authors with configured handles
* Emits JSON-LD `Article` + ordered `Person` schema on singular post views
* Enriches Yoast SEO and Rank Math schema output when present, or emits standalone JSON-LD when no schema-owning SEO plugin is active
* Auto-detects PublishPress Authors, HM Authorship, Co-Authors Plus, or falls back to core WordPress
* Adds Content Perspective, fediverse-handle, and AI-consent fields in WordPress editing/profile UI
* Stores AI-consent changes in an admin-only audit log under Tools
* Validates normalized author data before output
* Works without requiring a specific multi-author plugin

== Supported author sources ==

* Co-Authors Plus
* PublishPress Authors
* HM Authorship
* Core WordPress

== Installation ==

1. Upload the `byline-feed` folder to `/wp-content/plugins/`, or install it through the WordPress admin once published.
2. Activate the plugin through the Plugins screen in WordPress.
3. If you use Co-Authors Plus, PublishPress Authors, or HM Authorship, keep that plugin active.
4. Optionally add a fediverse handle such as `@you@example.social` to the user profile of each linked author who should receive fediverse attribution.
5. Visit your RSS2, Atom, or JSON feed and inspect the output for Byline elements.

== Frequently Asked Questions ==

= Does this replace my SEO plugin? =

No. Byline Feed focuses on author identity and attribution output. When Yoast SEO or Rank Math is active, Byline Feed enriches that plugin's schema output with multi-author attribution and related author-identity fields. When no known schema-owning SEO plugin is active, Byline Feed emits its own JSON-LD Article + Person schema on singular content.

= Does this block AI crawlers? =

No. The current rights output emits advisory machine-readable consent signals such as `robots` meta, `TDMRep` headers, and `ai.txt`. These signals may be ignored by crawlers and should not be described as enforcement.

= What is the Byline spec? =

The Byline specification defines an XML namespace for structured author attribution in feeds. It allows consumers to distinguish contributors, roles, and editorial perspective in a way standard RSS fields do not.

= Which feed formats are supported? =

RSS2, Atom, and JSON Feed are supported now.

= Does this support Mastodon author attribution? =

Yes. If an author has a fediverse handle configured in their WordPress profile, Byline Feed outputs `<meta name="fediverse:creator">` tags on singular content so compatible fediverse consumers can attribute shared links.

= Where can I see the exact XML this plugin emits? =

See `byline-feed/docs/output-reference.md` in the project repository for current examples, hook references, and field mapping notes.

= Which multi-author plugins are supported? =

Co-Authors Plus, PublishPress Authors, and HM Authorship are supported directly. If none of them are active, Byline Feed uses core WordPress author data.

= What is Content Perspective? =

Content Perspective is an editorial field that communicates the intent behind a piece of content, such as reporting, analysis, opinion, or satire. Feed consumers can use it to distinguish content types more clearly.

== Changelog ==

= 0.1.0-rc3 =
* Third release candidate.
* Feed-level rights summaries now ship in RSS2, Atom, and JSON Feed alongside the existing denied-item rights metadata.
* ActivityPub integration coverage now runs against the real plugin in CI, and adapter tests now accept plugin-derived `ap_actor_url` values when present.
* Playwright coverage now includes the fediverse profile field and the classic-editor Content Perspective metabox fallback.
* The self-contained `wp-env` E2E harness now defaults to port `8896` to avoid the earlier `8886` conflict in local environments with active tunnels or forwarded services.

= 0.1.0-rc2 =
* Second release candidate.
* Multi-author JSON-LD schema now supports Yoast SEO and Rank Math enrichment modes, with standalone output when no schema-owning SEO plugin is active.
* AI-consent signaling now includes denied-item feed rights metadata, a block-editor consent panel, and an admin-side audit log.
* Local PHPUnit workflow now uses a documented Docker-backed path for reproducible test setup.
* Integration coverage now includes stronger PublishPress Authors parity plus HM Authorship regression protection.
* Playground output-demo assets and release-facing docs were refreshed to match the shipped plugin behavior.
