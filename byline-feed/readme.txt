=== Byline Feed ===
Contributors: dknauss
Tags: rss, atom, byline, author, attribution, feeds
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enriches RSS2, Atom, and JSON Feed output with structured author identity metadata following the Byline specification, and emits fediverse author-attribution meta tags plus multi-author JSON-LD schema on singular content.

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
* Multi-author JSON-LD Article + Person output on singular views
* Initial AI-consent signaling with `robots` meta, `TDMRep` headers, and `ai.txt`
* Filter and action hooks for output customization

Byline Feed is additive. It preserves core feed elements such as `<author>` and `<dc:creator>` and adds Byline metadata alongside them.

== Features ==

* Adds `byline:contributors` with structured contributor profiles at feed level
* Adds item-level `byline:author`, `byline:role`, and `byline:perspective` elements
* Supports `byline:profile`, `byline:now`, and `byline:uses` from plugin-owned user meta
* Emits `<meta name="fediverse:creator">` tags on singular views for authors with configured handles
* Emits JSON-LD `Article` + ordered `Person` schema on singular post views
* Auto-detects PublishPress Authors, HM Authorship, Co-Authors Plus, or falls back to core WordPress
* Adds Content Perspective, fediverse-handle, and AI-consent fields in WordPress editing/profile UI
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

No. Byline Feed focuses on author identity and attribution output. It now emits its own JSON-LD Article + Person schema on singular content, but disables that output by default when known schema-owning SEO plugins such as Yoast SEO or Rank Math are active.

= Does this block AI crawlers? =

No. The WP-06 output currently emits advisory machine-readable consent signals such as `robots` meta, `TDMRep` headers, and `ai.txt`. These signals may be ignored by crawlers and should not be described as enforcement.

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

= 0.1.0-rc1 =
* First release candidate.
* Adapter layer with Co-Authors Plus, PublishPress Authors, HM Authorship, and core WordPress support.
* RSS2, Atom, and JSON Feed Byline output.
* Content Perspective field with block editor panel and classic editor support.
* Fediverse-handle profile field and `fediverse:creator` meta tag output.
* Multi-author JSON-LD Article + Person schema with conservative Yoast/Rank Math coexistence rules.
* Initial AI-consent signaling with per-author and per-post consent resolution, `robots` meta output, `TDMRep` headers, and `ai.txt`.
* Test and CI baseline established for supported PHP and WordPress versions.
