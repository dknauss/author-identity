=== Author Identity ===
Contributors: dknauss
Tags: author, structured data, schema, fediverse, mastodon, rss, json-ld, seo, ActivityPub
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Structured author identity that travels with the work — across feeds, search, the fediverse, and AI — from one source of truth in WordPress.

== Description ==

**Author Identity** gives WordPress a single source of truth for the person (or people) who publish on a site. Once configured, that identity automatically travels with every post through every channel — web pages, RSS feeds, search engines, the fediverse, and AI agents — without any per-post work.

= What it does =

* **JSON-LD structured data** — Outputs a `schema.org/Person` block in `<head>` on every singular post/page, letting search engines and AI crawlers understand the author's name, URL, bio, job title, organization, and social profiles.
* **Open Graph & fediverse meta tags** — Adds `<meta name="author">`, `<meta property="article:author">`, and `<meta name="fediverse:creator">` to each post/page, enabling Mastodon link-verification and rich social previews.
* **RSS/Atom feed enrichment** — Appends a structured author card (with bio and links) to each feed item's content, and fills in `<managingEditor>` and `<dc:creator>` at the channel and item level.
* **Per-author user profiles** — Each WordPress user can override the site-wide defaults from their own profile page (job title, organization, Mastodon, Twitter/X, LinkedIn, GitHub, and a free-form "sameAs" URL list).
* **Site-wide defaults** — A simple settings page under **Settings → Author Identity** lets you set fallback values used whenever a user hasn't filled in their own profile.

= Supported identity fields =

* Name, URL, public e-mail, bio/description
* Job title and organization (with organization URL)
* Mastodon profile URL (+ automatic `fediverse:creator` handle derivation)
* Fediverse creator handle (manual override)
* X / Twitter handle
* LinkedIn profile URL
* GitHub profile URL
* Additional sameAs URLs (one per line)

== Installation ==

1. Upload the `author-identity` folder to your `/wp-content/plugins/` directory, or install it through the **Plugins → Add New** screen in wp-admin.
2. Activate the plugin via the **Plugins** screen in wp-admin.
3. Go to **Settings → Author Identity** and fill in the site-wide author identity fields.
4. Optionally, visit each author's **Users → Profile** page and fill in the per-author override fields.

== Frequently Asked Questions ==

= Does this replace a full SEO plugin? =

No. Author Identity focuses exclusively on *author* identity. It is designed to work alongside general SEO plugins rather than replace them. If your SEO plugin already outputs a `Person` schema block or `article:author`, you may want to disable the duplicate output.

= Can different posts have different authors? =

Yes. The structured data and meta tags are resolved per-post using the post's assigned author. Each author can maintain their own identity fields in their user profile.

= Does the plugin output anything on archive or home pages? =

Currently, the structured data and meta tags only fire on singular posts/pages (`is_singular()`). Feed enrichment runs for every feed item.

= What is the `fediverse:creator` meta tag? =

It is a proposed standard that allows Mastodon and other ActivityPub servers to verify the link between a web page and its author's fediverse account. See [fediverse-creator](https://blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/) for more context.

== Screenshots ==

1. **Settings page** — Configure site-wide author identity defaults.
2. **User profile fields** — Per-author overrides on the Edit User screen.
3. **JSON-LD output** — Structured data as rendered in the page source.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
First stable release.
