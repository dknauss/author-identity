# Byline Feed Plugin — Implementation Spec and Roadmap

## Plugin identity

- **Working name:** `byline-feed` (final name TBD, see `author-identity-vision.md` naming section)
- **Type:** WordPress plugin, distributed via wp.org
- **License:** GPLv2 or later
- **PHP floor:** 7.4
- **WP floor:** 6.0
- **Dependencies:** None (all multi-author plugin integrations are optional adapters)

## Architectural overview

The plugin has three layers:

```
┌─────────────────────────────────────────────────┐
│                  Output Layer                   │
│  RSS2 · Atom · JSON-LD · fediverse:creator ·    │
│  TDM headers · ai.txt · rights metadata         │
├─────────────────────────────────────────────────┤
│              Normalized Author API              │
│  byline_feed_get_authors( WP_Post ) : array     │
│  Returns ordered array of author objects        │
├─────────────────────────────────────────────────┤
│                 Adapter Layer                   │
│  Co-Authors Plus · PublishPress Authors ·       │
│  Molongui · HM Authorship · Core WP fallback    │
└─────────────────────────────────────────────────┘
```

All output channels consume the same normalized author array. All adapters produce the same normalized author array. The adapter layer is the load-bearing abstraction.

## Normalized author object contract

Every adapter MUST return an ordered array of objects conforming to this shape:

```php
(object) [
    // Required
    'id'           => string,  // Unique within feed. Slug or prefixed numeric ID.
    'display_name' => string,  // Author's display name.

    // Optional identity
    'description'  => string,  // Bio. Output layer caps at 280 chars for Byline context.
    'url'          => string,  // Primary URL (personal site, about page).
    'avatar_url'   => string,  // Avatar image URL.
    'user_id'      => int,     // WordPress user ID if available, 0 if guest-only.

    // Optional role/type
    'role'         => string,  // Byline role: 'creator','editor','guest','staff','founder','contributor','bot'.
    'is_guest'     => bool,    // Whether this is a guest author.

    // Optional extended identity (progressive enhancement)
    'profiles'     => array,   // Array of ['href' => string, 'rel' => string].
    'now_url'      => string,  // /now page URL.
    'uses_url'     => string,  // /uses page URL.
    'fediverse'    => string,  // Fediverse handle (@user@instance).

    // Optional rights
    'ai_consent'   => string,  // 'allow', 'deny', or '' (unset).
]
```

Fields not available from a given adapter MUST be set to empty string, empty array, 0, or false as appropriate. The output layer MUST NOT assume any optional field is populated.

## Adapter detection and priority

On `plugins_loaded`, the plugin checks for active multi-author plugins and loads the first matching adapter:

```
Priority 1: Co-Authors Plus      — function_exists( 'get_coauthors' )
Priority 2: PublishPress Authors — function_exists( 'publishpress_authors_get_post_authors' )
                                   OR function_exists( 'get_post_authors' )
                                   OR class_exists( 'MultipleAuthors\\Classes\\Objects\\Author' )
Priority 3: Molongui Authorship  — class_exists( 'Molongui\\Authorship\\Author' )
Priority 4: HM Authorship        — function_exists( 'Authorship\\get_authors' )
Priority 5: Core WordPress       — always available (fallback)
```

The active adapter is filterable: `apply_filters( 'byline_feed_adapter', $adapter_instance )`.

## Work package sequence

Each work package is a self-contained deliverable. Packages are ordered by dependency — each builds on the previous. Work packages 1-3 constitute the MVP for wp.org submission.

| WP | Name | Depends on | Deliverable |
| --- | --- | --- | --- |
| 01 | Plugin scaffold and adapter interface | — | Plugin activates, detects multi-author plugin, returns normalized authors |
| 02 | Byline RSS2 and Atom output | 01 | Feeds include valid Byline namespace, contributors, per-item author refs |
| 03 | Perspective meta field and editor UI | 01, 02 | Editors set perspective per post, appears in feed output |
| 04 | `fediverse:creator` meta tag output | 01 | Shared links on Mastodon show author bylines |
| 05 | JSON-LD schema output | 01 | Multi-author Article + Person schema in `wp_head` |
| 06 | Content rights and AI consent | 01 | Per-author/per-post consent, TDM headers, `ai.txt` |

Detailed scaffolds for each: `Implementation Strategy/wp-01.md` through `Implementation Strategy/wp-06.md`.

## Scope philosophy: one plugin, gated growth

All six work packages ship inside a single plugin. The adapter layer is the product — it normalizes author data once and routes it to every output channel. Splitting outputs into separate plugins would force users to install multiple things to solve one problem ("my identity doesn't travel with my content") and create coordination overhead between plugins that share the same data source. The single-source-of-truth principle demands a single plugin.

However, scope expansion must be earned, not assumed. The Byline feed output (WP-01/02/03) is the thesis — it validates the adapter architecture and tests spec adoption. The other output channels (fediverse:creator, JSON-LD, AI consent) are the hedge — they deliver value through channels that already have deployed consumers (Mastodon, Google, AI crawlers) regardless of whether Byline gains feed reader adoption. If Byline never gets reader traction, the plugin still does four useful things.

The gates below prevent broader scope from diluting MVP execution while recognizing that not every output channel depends on Byline spec adoption.

## Release gates

```
Gate A ─── MVP quality ──────────────────────────────────────────────
           WP-01/02/03 merged, feed XML valid, compatibility
           verified on core + CAP + PPA.

Gate B ─── Real-world adoption ──────────────────────────────────────
           Production feeds emitting valid Byline data, early
           operator feedback collected.

Gate B' ── Adapter-proven expansion (after B) ───────────────────────
           WP-04 (fediverse:creator) and WP-05 (JSON-LD schema).
           These output channels have deployed infrastructure
           already consuming their signals — Mastodon parses
           fediverse:creator tags today, Google parses schema.org
           today. They validate the adapter architecture across
           new output formats without any dependency on Byline
           spec adoption by feed readers.
           WP-06 HTML/header signals (nosnippet, TDM headers,
           robots.txt tokens, ai.txt) also ship here — they work
           independently of feed reader support.

Gate C ─── Reader-side signal ───────────────────────────────────────
           At least one feed reader maintainer indicates
           implementation interest or parser experimentation.

Gate D ─── Feed-level rights metadata (after C) ─────────────────────
           WP-06 feed integration (`cc:license` for explicit license
           declarations, plus any dedicated deny-policy extension)
           ships here. Feed-level rights metadata only has value
           when readers parse Byline data — unlike the
           HTML/header signals that work independently.
```

**Rationale for splitting Gate D.** The original four-gate sequence treated WP-04/05/06 as a single block gated on reader-side signal. But WP-04 and WP-05 don't depend on Byline spec adoption at all:

- **WP-04 (fediverse:creator):** Mastodon's author attribution feature launched July 2024 specifically for journalism. The infrastructure is deployed and waiting for publishers to emit the tags. No feed reader involvement needed.
- **WP-05 (JSON-LD schema):** Google already parses Article + Person schema. Multi-author schema support across the WordPress ecosystem is weak. This delivers E-E-A-T value on day one.
- **WP-06 (HTML/header signals):** `nosnippet`, `tdm-reservation`/`tdm-policy` headers, `robots.txt` token rules, and `ai.txt` all operate through HTTP and HTML mechanisms that crawlers already understand. They don't need feed reader support.
- **WP-06 (feed-level rights):** Feed-level rights metadata (`cc:license` for explicit license declarations and any dedicated deny-policy extension) is the piece that genuinely depends on readers parsing Byline data. It gates on C.

Waiting for reader-side signal before shipping fediverse:creator tags and JSON-LD schema would mean sitting on proven infrastructure to protect against a risk (Byline spec adoption) that those features don't share. The split gates let the plugin grow its value proposition as the adapter layer proves out, while feed-level features still wait for their specific dependency.

## Filter and hook API

The plugin exposes the following public API for theme and plugin developers:

### Functions

- `byline_feed_get_authors( WP_Post $post ) : array` — returns normalized author array for the given post.
- `byline_feed_get_perspective( WP_Post $post ) : string` — returns the perspective value for the given post (from meta or filter).

### Filters

- `byline_feed_adapter` — override the auto-detected adapter instance.
- `byline_feed_authors` — modify the normalized author array after adapter resolution. Receives `( $authors, $post )`.
- `byline_feed_role` — override role mapping per author per post. Receives `( $role, $author_object, $post )`.
- `byline_feed_perspective` — compute or override perspective. Receives `( $perspective, $post )`.
- `byline_feed_person_xml` — modify the XML output for a `byline:person` element. Receives `( $xml, $author_object )`.
- `byline_feed_item_xml` — modify the XML output for per-item Byline elements. Receives `( $xml, $post, $authors )`.
- `byline_feed_schema_person` — modify the JSON-LD Person object. Receives `( $person_array, $author_object )`.
- `byline_feed_ai_consent` — override AI consent per author per post. Receives `( $consent, $author_object, $post )`.
- `byline_feed_fediverse_handle` — override fediverse handle per author. Receives `( $handle, $author_object )`.

### Actions

- `byline_feed_after_rss2_contributors` — fires after the `<byline:contributors>` block is output in RSS2 head.
- `byline_feed_after_rss2_item` — fires after per-item Byline elements are output.

## Test strategy

### Unit tests

Each adapter gets its own test class mocking the upstream plugin's API functions. Tests verify:

- Correct normalized object shape for each adapter.
- Empty/missing field handling.
- Guest author detection.
- Role mapping.
- Author ordering preservation.

### Integration tests

Require the actual multi-author plugin to be active (via WP test suite's plugin loading):

- CAP installed → feed output includes Byline elements for co-authored posts.
- PPA installed → feed output includes Byline elements for posts with multiple PPA authors.
- No multi-author plugin → feed output includes Byline elements from core `post_author`.

### Feed validation

After each feed output test, validate the XML against:

- Well-formedness (XML parsing succeeds).
- Byline namespace is declared.
- Standard `<author>` / `<dc:creator>` elements are still present alongside Byline elements.
- `byline:person` elements have required `id` and `name` children.

### Testing matrix

| Scenario | Adapter | Authors | Perspective | Expected |
| --- | --- | --- | --- | --- |
| Single author, no plugin | Core | 1 user | unset | One `byline:person`, no `byline:perspective` |
| Single author, perspective set | Core | 1 user | `analysis` | One `byline:person`, `byline:perspective` = `analysis` |
| Two co-authors via CAP | CAP | 1 user + 1 guest | unset | Two `byline:person` refs, guest has `role=guest` |
| Three authors via PPA | PPA | 2 users + 1 guest | `reporting` | Three refs, correct order, perspective present |
| Author with fediverse handle | Any | 1 user with meta | unset | `fediverse:creator` tag in `wp_head` |
| Author denies AI training | Any | 1 user, consent=deny | unset | AI crawler policy signal + TDM policy header |

## File structure

```
byline-feed/
├── byline-feed.php                     # Plugin bootstrap, adapter detection
├── composer.json
├── package.json
├── readme.txt                          # wp.org readme
│
├── inc/
│   ├── namespace.php                   # Public API functions, hook registration
│   ├── interface-adapter.php           # Adapter interface definition
│   ├── class-adapter-core.php          # Core WordPress fallback adapter
│   ├── class-adapter-cap.php           # Co-Authors Plus adapter
│   ├── class-adapter-ppa.php           # PublishPress Authors adapter
│   ├── class-adapter-molongui.php      # Molongui Authorship adapter
│   ├── class-adapter-authorship.php    # HM Authorship adapter
│   ├── feed-rss2.php                   # RSS2 Byline output hooks
│   ├── feed-atom.php                   # Atom Byline output hooks
│   ├── schema.php                      # JSON-LD output
│   ├── fediverse.php                   # fediverse:creator meta tag output
│   ├── rights.php                      # AI consent, TDM headers, ai.txt
│   └── perspective.php                 # Perspective meta field registration
│
├── src/
│   └── perspective-panel.tsx           # Block editor sidebar for perspective
│
└── tests/
    └── phpunit/
        ├── test-adapter-core.php
        ├── test-adapter-cap.php
        ├── test-adapter-ppa.php
        ├── test-feed-rss2.php
        ├── test-feed-atom.php
        ├── test-schema.php
        ├── test-fediverse.php
        ├── test-rights.php
        └── test-perspective.php
```

## Acceptance criteria for wp.org submission (MVP)

Work packages 01 + 02 + 03 constitute the MVP:

1. Plugin activates without errors on PHP 7.4+ / WP 6.0+.
2. Plugin auto-detects Co-Authors Plus, PublishPress Authors, or falls back to core.
3. RSS2 feeds include valid `xmlns:byline` namespace declaration.
4. RSS2 feeds include `<byline:contributors>` with `<byline:person>` for each author in the feed.
5. RSS2 feed items include `<byline:author ref="..."/>` and `<byline:role>` for attributed authors.
6. Atom feeds include equivalent Byline elements.
7. Standard `<author>` and `<dc:creator>` elements are preserved (Byline is additive).
8. Perspective meta field is available in the block editor.
9. `byline:perspective` appears in feed output when set.
10. All filters are documented and functional.
11. PHPUnit test suite passes for all adapter and feed output scenarios.
12. No PHPCS violations against WordPress coding standards.
