# Author Identity

> Structured author identity that travels with the work — across feeds, search, the fediverse, and AI — from one source of truth in WordPress.

A WordPress plugin that gives every post a rich, machine-readable author signature without any per-post work.

## Features

- **JSON-LD structured data** (`schema.org/Person`) in `<head>` on every singular post/page
- **Open Graph & fediverse meta tags** — `article:author`, `fediverse:creator`, and `author` meta for Mastodon link-verification and social previews
- **RSS/Atom feed enrichment** — author bio + links appended to each feed item; `<managingEditor>` and `<dc:creator>` populated at the channel and item level
- **Per-author user profiles** — each WordPress user can override the site-wide defaults from their own profile page
- **Site-wide defaults** — a single settings page under *Settings → Author Identity* acts as the fallback for any empty user-level field

## Supported identity fields

| Field | Description |
|---|---|
| Name | Display name |
| URL | Author's canonical URL (defaults to WP author archive) |
| Public e-mail | For `schema.org/Person.email` |
| Bio / Description | Short biography |
| Job Title | `schema.org/Person.jobTitle` |
| Organization | `schema.org/Organization.name` |
| Organization URL | `schema.org/Organization.url` |
| Mastodon URL | Profile URL; used for `sameAs` and `fediverse:creator` |
| Fediverse Creator | Manual `@user@domain` handle override |
| X / Twitter handle | Converted to a Twitter URL in `sameAs` |
| LinkedIn URL | Added to `sameAs` |
| GitHub URL | Added to `sameAs` |
| Additional sameAs URLs | One per line; appended to `sameAs` |

## Installation

1. Copy the `author-identity` folder (this repository) to your site's `wp-content/plugins/` directory.
2. Activate the plugin via **Plugins → Installed Plugins** in wp-admin.
3. Go to **Settings → Author Identity** and fill in the site-wide defaults.
4. Optionally, visit each author's **Users → Profile** page to set per-author overrides.

## Repository layout

```
author-identity.php          ← plugin entry point (WP plugin header)
includes/
  class-author-identity.php  ← singleton bootstrap / hook registration
  class-admin.php            ← wp-admin settings page + user profile fields
  class-structured-data.php  ← JSON-LD schema.org/Person output
  class-meta-tags.php        ← <meta> tags (author, OG, fediverse)
  class-feed-enhancer.php    ← RSS/Atom feed enrichment
admin/
  partials/
    settings-page.php        ← settings page HTML template
languages/                   ← translation-ready (.pot goes here)
readme.txt                   ← WordPress.org plugin readme
```

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).
