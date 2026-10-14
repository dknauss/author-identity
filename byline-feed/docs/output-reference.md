# Byline Feed Output Reference

This document describes what `byline-feed` emits today.

It is a consumer-facing reference for feed reader developers, theme/plugin developers, and anyone integrating with the plugin's output.

## Scope

Current shipped output:

- RSS2 Byline namespace and metadata
- Atom Byline namespace and metadata
- JSON Feed 1.1 output with `_byline` extensions
- `fediverse:creator` meta tags on singular HTML views
- author meta tags (`author`, `og:author`, `article:author`) on singular HTML views
- JSON-LD Article + Person schema on singular HTML views
- an `organization` author mode for posts in an institutional or collective voice
- advisory rights signals on singular/content-policy routes, denied items, and feed-level summaries
- admin-side consent audit logging
- Perspective post meta and feed output
- Public filters and actions for feed customization

Not yet shipped:

- standards-based license declarations beyond the current Byline rights summaries

## Output model

The plugin normalizes author data from one of four sources:

- core WordPress
- Co-Authors Plus
- PublishPress Authors
- HM Authorship

All output layers consume the same normalized author contract.

### Normalized author contract

Required fields:

| Field | Type | Meaning |
| --- | --- | --- |
| `id` | string | Stable author identifier used in `byline:person id` and `byline:author ref` |
| `display_name` | string | Human-readable author name |

Optional fields:

| Field | Type | Zero value | Current use |
| --- | --- | --- | --- |
| `description` | string | `''` | Emitted as `byline:context` when non-empty |
| `url` | string | `''` | Emitted as `byline:url` when non-empty |
| `avatar_url` | string | `''` | Emitted as `byline:avatar` when non-empty |
| `user_id` | int | `0` | Not emitted directly |
| `role` | string | `''` | Emitted as item/entry `byline:role` when non-empty |
| `is_guest` | bool | `false` | Not emitted directly |
| `profiles` | array | `[]` | Emitted as `byline:profile` when valid entries are present |
| `now_url` | string | `''` | Emitted as `byline:now` when non-empty |
| `uses_url` | string | `''` | Emitted as `byline:uses` when non-empty |
| `fediverse` | string | `''` | Emitted as `fediverse:creator` in HTML head when non-empty |
| `ap_actor_url` | string | `''` | Extends JSON-LD `sameAs` when confidently resolved; never substitutes for `fediverse` |
| `ai_consent` | string | `''` | Used in consent resolution and rights signaling |

Important current limitation:

- canonical plugin-owned storage now exists for linked WordPress users
- guest-author profile mapping in upstream multi-author plugins is not yet implemented unless a site injects values through filters

## HTML head output

The plugin hooks `wp_head` and emits one `<meta name="fediverse:creator">` tag for each normalized author on the current singular post who has a valid fediverse handle.

Example:

```html
<meta name="fediverse:creator" content="@jane@example.social" />
<meta name="fediverse:creator" content="@editor@example.news" />
```

Behavior:

- only emitted on singular post/page views
- omitted entirely on archives, the home page, and other non-singular routes
- handles are normalized to include a leading `@`
- `profiles[]` and `ap_actor_url` do not substitute for the `fediverse` handle
- per-author handle output can be overridden with `byline_feed_fediverse_handle`
- omitted for posts in `organization` author mode

### Author meta tags

The plugin also prints plain author meta tags for link-preview readers that ignore JSON-LD `Article` nodes. Mastodon's link cards, for example, read a byline from JSON-LD only when the node is typed `WebPage` or `NewsArticle`, and otherwise fall back to `og:author`.

Example:

```html
<meta name="author" content="Jane Doe, Alex Smith" />
<meta property="og:author" content="Jane Doe, Alex Smith" />
<meta property="article:author" content="https://example.com/author/jane-doe/" />
```

Behavior:

- only emitted on singular views of public post types
- `author` and `og:author` carry every author's name, comma-separated, in byline order
- one `article:author` per author with a profile URL (the author's `url`, else their author archive); Open Graph expects profile URLs here
- printed by default only in standalone schema mode, because Yoast SEO and Rank Math print their own author tags; override with `byline_feed_author_meta_tags_enabled`
- the tag list can be changed with `byline_feed_author_meta_tags`; entries without a key or content are dropped
- omitted for posts in `organization` author mode

## Author mode

`byline_feed_get_author_mode( $post )` returns `person` (the default) or `organization`, set with the `byline_feed_author_mode` filter. Use `organization` for writing in an institutional or collective voice that carries no personal byline:

- JSON-LD names the publisher `Organization` as the article's `author` (in Yoast SEO and Rank Math modes, a reference to their own publisher node)
- standalone JSON-LD is emitted even when no authors resolve
- no `fediverse:creator` or author meta tags are printed
- feed output is not yet affected: feeds still list the post's resolved authors

The decision is post-level, not a field of the normalized author contract. It is resolved in this order:

1. **The editor's choice.** The Attribution panel (block editor sidebar, or a classic-editor metabox) stores `person` or `organization` in the `_byline_author_mode` post meta. "Automatic" stores nothing. The meta is registered for REST.
2. **The `byline_feed_author_mode` filter.** It receives the stored choice (or `person` when none is stored), the post, and the stored value itself (`''` when the editor has not chosen).
3. **Default:** `person`.

Code that suggests an answer automatically (for example from whether the post is written in the first person) should defer to a choice made by hand:

```php
add_filter( 'byline_feed_author_mode', function ( $mode, $post, $stored ) {
	if ( '' !== $stored ) {
		return $mode; // The editor chose; leave it.
	}
	return my_site_post_has_byline( $post ) ? 'person' : 'organization';
}, 10, 3 );
```

[First Person](https://github.com/dknauss/first-person-byline) is the reference suggester: it attributes a post to the site when the post is not written in the first person, defers to a choice made by hand, and hides the visible byline from this same resolved answer, so what readers see and what machines read agree. Neither plugin requires the other.

Other plugins can read the resolved answer with `\Byline_Feed\byline_feed_get_author_mode( $post )` (check `function_exists()` first), so a visible byline and the metadata never disagree.

## Rights output

The plugin now ships an initial advisory AI-consent signaling slice.

Current behavior:

- per-author AI consent is stored in plugin-owned user meta
- per-post AI consent override is stored in post meta
- consent resolution uses the most restrictive linked-author preference when there is no post override
- denied posts emit `<meta name="robots" content="noai, noimageai">`
- denied posts emit a `TDMRep` header pointing to the policy URL
- RSS2 and Atom feed heads emit `<byline:rights consent="allow|deny|mixed" policy="..."/>` when the current feed has explicit consent signals
- denied RSS2 and Atom items emit `<byline:rights consent="deny" policy="..."/>`
- JSON Feed top-level `_byline.rights` summarizes the current feed with `allow`, `deny`, or `mixed`
- denied JSON Feed items emit `_byline.rights` with `consent` and `policy`
- `/ai.txt` is generated dynamically and is filterable
- consent changes are stored in an admin-only audit log at `Tools > AI Consent Audit Log`

Example HTML:

```html
<meta name="robots" content="noai, noimageai" />
```

Example header:

```text
TDMRep: https://example.com/ai.txt
```

Example feed-level summary:

```xml
<byline:rights consent="mixed" policy="https://example.com/ai.txt" />
```

Current limitations:

- these are advisory machine-readable signals, not enforcement
- the current feed-level summary is Byline-specific rather than a standards-based license declaration

## JSON-LD output

The plugin hooks `wp_head` and emits one `<script type="application/ld+json">` block on singular post views when schema output is enabled.

Behavior:

- emits `Article` schema with an ordered `author` array of `Person` objects, or the publisher `Organization` in `organization` author mode
- gives the article a stable `@id` (`<permalink>#article`) and `mainEntityOfPage`, and the publisher `@id` `<home>/#organization`, so other plugins can reference them instead of emitting their own page-level nodes
- uses the same normalized author contract as the feed outputs
- includes `profiles[]` in `sameAs`
- includes `ap_actor_url` in `sameAs` only when that URL is already available from trusted adapter/meta resolution
- does not print its own block when a known schema-owning SEO plugin is active; it enriches that plugin's Article node instead (authors, perspective):
  - Yoast SEO (`wpseo_schema_article`)
  - Rank Math (`rank_math/json_ld`)
- the mode can be forced with `byline_feed_schema_mode` (`yoast`, `rankmath`, or `standalone`)
- standalone output can be disabled per post with `byline_feed_schema_enabled`

Example:

```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "@id": "https://example.com/city-budget-passes-council/#article",
  "headline": "City budget passes council",
  "datePublished": "2026-03-15T12:00:00+00:00",
  "dateModified": "2026-03-15T12:00:00+00:00",
  "url": "https://example.com/city-budget-passes-council/",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://example.com/city-budget-passes-council/"
  },
  "author": [
    {
      "@type": "Person",
      "name": "Jane Doe",
      "url": "https://example.com/author/jane-doe/",
      "description": "Reporter covering local government and housing.",
      "image": "https://example.com/avatar/jane.jpg",
      "sameAs": [
        "https://example.com/@jane",
        "https://example.social/users/jane"
      ]
    },
    {
      "@type": "Person",
      "name": "Alex Smith"
    }
  ],
  "publisher": {
    "@type": "Organization",
    "@id": "https://example.com/#organization",
    "name": "Example Site",
    "url": "https://example.com/",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/icon.png"
    }
  }
}
```

## RSS2 output

The plugin hooks:

- `rss2_ns`
- `rss2_head`
- `rss2_item`

It adds Byline output without removing or replacing standard WordPress RSS2 elements.

### Namespace declaration

Added on the root `<rss>` element:

```xml
xmlns:byline="https://bylinespec.org/1.0"
```

### Feed-level contributors block

Added inside `<channel>`:

```xml
<byline:contributors>
  <byline:person id="jane-doe">
    <byline:name>Jane Doe</byline:name>
    <byline:context>Reporter covering local government and housing.</byline:context>
    <byline:url>https://example.com/authors/jane-doe/</byline:url>
    <byline:avatar>https://example.com/avatar/jane.jpg</byline:avatar>
    <byline:profile href="https://example.com/@jane" rel="me"/>
    <byline:now>https://example.com/now/</byline:now>
    <byline:uses>https://example.com/uses/</byline:uses>
  </byline:person>
</byline:contributors>
```

Behavior:

- authors are collected across posts in the current feed query
- contributor persons are deduplicated by normalized `id`
- optional fields are omitted entirely when empty
- descriptions are stripped to plain text and capped to 280 characters before output
- `byline:profile` entries require both `href` and `rel`

### Item-level output

Added inside each `<item>`:

```xml
<byline:author ref="jane-doe"/>
<byline:role>staff</byline:role>
<byline:perspective>reporting</byline:perspective>
```

Behavior:

- one `byline:author` is emitted for each normalized author on the post
- `byline:role` is emitted only when the normalized role is non-empty
- `byline:perspective` is emitted only when the stored or filtered perspective is valid

### Full RSS2 example

This example shows the Byline additions only. Standard RSS2 elements such as `<title>`, `<link>`, `<description>`, `<author>`, and `<dc:creator>` remain in place.

```xml
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:byline="https://bylinespec.org/1.0">
  <channel>
    <title>Example Site</title>
    <link>https://example.com/</link>

    <byline:contributors>
      <byline:person id="jane-doe">
        <byline:name>Jane Doe</byline:name>
        <byline:context>Reporter covering local government and housing.</byline:context>
        <byline:url>https://example.com/authors/jane-doe/</byline:url>
        <byline:avatar>https://example.com/avatar/jane.jpg</byline:avatar>
        <byline:profile href="https://example.com/@jane" rel="me"/>
        <byline:now>https://example.com/now/</byline:now>
        <byline:uses>https://example.com/uses/</byline:uses>
      </byline:person>
      <byline:person id="alex-smith">
        <byline:name>Alex Smith</byline:name>
      </byline:person>
    </byline:contributors>

    <item>
      <title>City budget passes council</title>
      <dc:creator><![CDATA[Jane Doe]]></dc:creator>
      <byline:author ref="jane-doe"/>
      <byline:role>staff</byline:role>
      <byline:author ref="alex-smith"/>
      <byline:role>contributor</byline:role>
      <byline:perspective>reporting</byline:perspective>
    </item>
  </channel>
</rss>
```

## Atom output

The plugin hooks:

- `atom_ns`
- `atom_head`
- `atom_entry`

Atom uses the same normalized author contract and the same Byline vocabulary as RSS2.

### Namespace declaration

Added on the root `<feed>` element:

```xml
xmlns:byline="https://bylinespec.org/1.0"
```

### Feed-level contributors block

Added inside `<feed>`:

```xml
<byline:contributors>
  <byline:person id="jane-doe">
    <byline:name>Jane Doe</byline:name>
    <byline:context>Reporter covering local government and housing.</byline:context>
    <byline:url>https://example.com/authors/jane-doe/</byline:url>
    <byline:avatar>https://example.com/avatar/jane.jpg</byline:avatar>
    <byline:profile href="https://example.com/@jane" rel="me"/>
    <byline:now>https://example.com/now/</byline:now>
    <byline:uses>https://example.com/uses/</byline:uses>
  </byline:person>
</byline:contributors>
```

### Entry-level output

Added inside each `<entry>`:

```xml
<byline:author ref="jane-doe"/>
<byline:role>staff</byline:role>
<byline:perspective>reporting</byline:perspective>
```

### Full Atom example

This example shows the Byline additions only. Standard Atom elements such as `<author>`, `<title>`, `<id>`, and `<updated>` remain in place.

```xml
<feed xmlns="http://www.w3.org/2005/Atom"
      xmlns:byline="https://bylinespec.org/1.0">
  <title>Example Site</title>

  <byline:contributors>
    <byline:person id="jane-doe">
      <byline:name>Jane Doe</byline:name>
      <byline:context>Reporter covering local government and housing.</byline:context>
      <byline:url>https://example.com/authors/jane-doe/</byline:url>
      <byline:avatar>https://example.com/avatar/jane.jpg</byline:avatar>
      <byline:profile href="https://example.com/@jane" rel="me"/>
      <byline:now>https://example.com/now/</byline:now>
      <byline:uses>https://example.com/uses/</byline:uses>
    </byline:person>
  </byline:contributors>

  <entry>
    <title>City budget passes council</title>
    <author>
      <name>Jane Doe</name>
    </author>
    <byline:author ref="jane-doe"/>
    <byline:role>staff</byline:role>
    <byline:perspective>reporting</byline:perspective>
  </entry>
</feed>
```

## JSON Feed output

The plugin supports JSON Feed in two modes:

- integration mode with an existing JSON Feed plugin
- standalone fallback at `/feed/json`

In both modes, it emits standard JSON Feed 1.1 fields plus Byline data in `_byline` extension objects.

### Feed-level output

Feed-level author entries are emitted in the top-level `authors` array and deduplicated by normalized author `id`.

Example:

```json
{
  "version": "https://jsonfeed.org/version/1.1",
  "title": "Example Site",
  "authors": [
    {
      "name": "Jane Doe",
      "url": "https://example.com/authors/jane-doe/",
      "avatar": "https://example.com/avatar/jane.jpg",
      "_byline": {
        "id": "jane-doe",
        "context": "Reporter covering local government and housing.",
        "role": "staff",
        "profiles": [
          {
            "href": "https://example.com/@jane",
            "rel": "me"
          }
        ],
        "now_url": "https://example.com/now/",
        "uses_url": "https://example.com/uses/"
      }
    }
  ],
  "_byline": {
    "spec_version": "1.0",
    "org": {
      "name": "Example Site",
      "url": "https://example.com"
    }
  }
}
```

### Item-level output

Each item may include:

- `authors`: one JSON Feed author entry per normalized author
- `_byline.perspective`: when the perspective value is set and valid

Example item fragment:

```json
{
  "id": "https://example.com/?p=123",
  "url": "https://example.com/city-budget-passes-council/",
  "title": "City budget passes council",
  "authors": [
    {
      "name": "Jane Doe",
      "_byline": {
        "id": "jane-doe",
        "role": "staff"
      }
    },
    {
      "name": "Alex Smith",
      "_byline": {
        "id": "alex-smith",
        "role": "contributor"
      }
    }
  ],
  "_byline": {
    "perspective": "reporting"
  }
}
```

Behavior:

- feed-level `authors` are deduplicated across posts
- item-level `authors` preserve per-post attribution order
- `_byline` carries Byline-specific properties that do not fit standard JSON Feed author fields
- empty optional fields are omitted rather than emitted as empty values

## Perspective values

The plugin accepts these perspective values:

| Value | Meaning |
| --- | --- |
| `personal` | Personal or opinion-driven content |
| `reporting` | News or reporting |
| `analysis` | Analysis or commentary |
| `official` | Official statement |
| `sponsored` | Sponsored content |
| `satire` | Satire or humor |
| `review` | Review or critique |
| `announcement` | Announcement or release notes |
| `tutorial` | Tutorial or how-to |
| `curation` | Curated links or references |
| `fiction` | Creative fiction |
| `interview` | Interview or Q&A |

Invalid values are silently dropped from output.

## Filters and actions

### Filters

| Hook | Parameters | Purpose |
| --- | --- | --- |
| `byline_feed_adapter` | `( Adapter $adapter )` | Override the auto-detected adapter instance |
| `byline_feed_authors` | `( object[] $authors, WP_Post $post )` | Modify normalized authors before validation and output |
| `byline_feed_perspective` | `( string $perspective, WP_Post $post )` | Compute or override the perspective value |
| `byline_feed_author_mode` | `( string $mode, WP_Post $post, string $stored )` | Attribute a post to its people (`person`, default) or to the publisher (`organization`); `$stored` is the editor's choice or `''` |
| `byline_feed_person_xml` | `( string $xml, object $author )` | Modify a single emitted `byline:person` XML fragment |
| `byline_feed_item_xml` | `( string $xml, WP_Post $post, object[] $authors )` | Modify RSS2 item XML |
| `byline_feed_atom_entry_xml` | `( string $xml, WP_Post $post, object[] $authors )` | Modify Atom entry XML |
| `byline_feed_json_author_extension` | `( array $ext, object $author, ?WP_Post $post )` | Modify `_byline` author extension data in JSON Feed |
| `byline_feed_json_item` | `( array $item, WP_Post $post )` | Modify a single JSON Feed item before output |
| `byline_feed_json_feed` | `( array $feed )` | Modify the complete JSON Feed before encoding |
| `byline_feed_schema_enabled` | `( bool $enabled, WP_Post $post )` | Enable or disable JSON-LD output for the current singular post |
| `byline_feed_schema_person` | `( array $person, object $author )` | Modify one JSON-LD `Person` object |
| `byline_feed_schema_article` | `( array $article, WP_Post $post )` | Modify the full JSON-LD `Article` object |
| `byline_feed_schema_mode` | `( string $mode )` | Force `yoast`, `rankmath`, or `standalone` schema mode |
| `byline_feed_author_meta_tags_enabled` | `( bool $enabled, WP_Post $post )` | Print author meta tags (default: standalone schema mode only) |
| `byline_feed_author_meta_tags` | `( array $tags, WP_Post $post )` | Modify the author meta tags (`attribute`, `key`, `content`) before output |

### Actions

| Hook | Parameters | Fires when |
| --- | --- | --- |
| `byline_feed_after_rss2_contributors` | none | after RSS2 `<byline:contributors>` output |
| `byline_feed_after_rss2_item` | none | after RSS2 item-level Byline output |
| `byline_feed_after_atom_contributors` | none | after Atom `<byline:contributors>` output |
| `byline_feed_after_atom_entry` | none | after Atom entry-level Byline output |
| `byline_feed_invalid_author_contract` | `( string $message, WP_Post $post )` | when invalid normalized author data is dropped during validation |

## Customization examples

### Compute perspective from taxonomy when no explicit value is set

```php
add_filter(
	'byline_feed_perspective',
	function ( $perspective, $post ) {
		if ( '' !== $perspective ) {
			return $perspective;
		}

		if ( has_category( 'opinion', $post ) ) {
			return 'personal';
		}

		if ( has_category( 'news', $post ) ) {
			return 'reporting';
		}

		return '';
	},
	10,
	2
);
```

### Append extra XML to each emitted person

```php
add_filter(
	'byline_feed_person_xml',
	function ( $xml, $author ) {
		if ( empty( $author->user_id ) ) {
			return $xml;
		}

		$extra = "\t\t\t\t<byline:url>" . esc_url( get_author_posts_url( $author->user_id ) ) . "</byline:url>\n";
		return str_replace( "\t\t\t</byline:person>\n", $extra . "\t\t\t</byline:person>\n", $xml );
	},
	10,
	2
);
```

### Add custom XML after each RSS2 item block

```php
add_action(
	'byline_feed_after_rss2_item',
	function () {
		echo "\t\t<example:flag>true</example:flag>\n";
	}
);
```

## Compatibility notes

- Byline output is additive. The plugin does not remove core RSS2 `<author>`, `<dc:creator>`, or Atom `<author>` elements.
- Empty optional person fields are omitted, not emitted as empty elements.
- Contributor lists are deduplicated per feed request by normalized author `id`.
- Author contract validation drops malformed author entries before output rather than emitting broken XML.
- Canonical plugin-owned user meta currently covers `profiles`, `now_url`, and `uses_url` for linked WordPress users.
- Upstream guest-author field mapping for those values is not implemented yet.

## Related files

- [Plugin readme](../readme.txt)
- [WP-02 specification](../../implementation-strategy/wp-02.md)
- [WP-03 specification](../../implementation-strategy/wp-03.md)
- [Coverage matrix](../../docs/quality/TEST_COVERAGE_MATRIX.md)
