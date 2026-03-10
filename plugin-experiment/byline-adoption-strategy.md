# Byline Feed Plugin — Cross-Plugin Adoption Strategy

## Goal

Maximize adoption of the Byline RSS spec (bylinespec.org) across the WordPress ecosystem by building a single standalone plugin that outputs Byline-structured feed data regardless of which multi-author plugin (if any) a site uses.

This document complements `byline-spec-plan.md`, which covers the Authorship-specific integration. The strategy here is broader: a plugin that lives on wp.org and works for any WordPress site.

## The adoption problem

The Byline spec (v0.1.0, January 2026) has zero implementations and zero feed reader support. Spec adoption requires both supply (feeds emitting Byline data) and demand (readers parsing it). A WordPress plugin that "just works" for tens of thousands of multi-author sites lowers the supply-side barrier to zero. The pitch to feed reader developers then becomes: "here are real feeds already emitting Byline data — here's what you could do with it in your UI."

## Addressable audience

| Plugin | Active installs (March 2026) | Multi-author attribution? | Priority |
| --- | --- | --- | --- |
| Co-Authors Plus | ~20,000 | Yes — taxonomy + CPT guest authors | Must support |
| PublishPress Authors | ~20,000 | Yes — taxonomy + term meta (and optional user role) | Must support |
| Molongui Authorship | ~10,000 | Yes — custom post type guest authors | Nice to have |
| Simple Author Box | ~60,000 | No — display only, single author | Covered by core fallback |
| WP Post Author | ~10,000 | Partial — display focused | Covered by core fallback |
| HM Authorship | N/A (Composer only) | Yes — WP_User only | Skip (low count, high capability users) |
| No plugin (core WP) | Millions | Single `post_author` | Must support (baseline) |

**Primary targets:** Co-Authors Plus + PublishPress Authors (~40K multi-author sites) + core WordPress fallback (every WordPress site).

**Secondary:** Molongui (~10K). Worth adding if the adapter is straightforward.

**Skip for now:** HM Authorship (negligible installs; enterprise users can contribute their own adapter), Simple Author Box and WP Post Author (don't change the authorship model; covered by core fallback).

## Plugin architecture

### Adapter pattern

The plugin defines an internal interface: given a `WP_Post`, return an ordered array of normalized author objects. Each supported multi-author plugin gets an adapter that implements this interface. The feed output layer consumes the normalized objects without knowing which adapter produced them.

### Normalized author object

```php
$author = (object) [
    'id'           => 'jdoe',          // Unique within feed (slug or numeric ID)
    'display_name' => 'Jane Doe',
    'description'  => 'Staff writer.', // Bio, capped at 280 chars
    'url'          => 'https://...',   // Primary URL
    'avatar_url'   => 'https://...',   // Avatar image
    'role'         => 'staff',         // Byline role value
    'is_guest'     => false,           // Guest author flag
    'profiles'     => [],              // Array of ['href' => ..., 'rel' => ...] for social links
    'now_url'      => null,            // /now page URL if available
    'uses_url'     => null,            // /uses page URL if available
];
```

### Adapter resolution order

Auto-detect which multi-author plugin is active and load the corresponding adapter. Only one adapter is active at a time. If multiple multi-author plugins are detected, use the first match in priority order.

```
1. Co-Authors Plus     → check function_exists( 'get_coauthors' )
2. PublishPress Authors → check function_exists( 'get_post_authors' ) or class MultipleAuthors
3. Molongui Authorship  → check class_exists( 'Molongui\Authorship\Author' )
4. HM Authorship        → check function_exists( 'Authorship\get_authors' )
5. Core WordPress        → always available as fallback
```

### Adapter details

**Co-Authors Plus adapter**

```php
function get_byline_authors_cap( WP_Post $post ) : array {
    $coauthors = get_coauthors( $post->ID );
    return array_map( function( $coauthor ) {
        $is_guest = ( $coauthor->type ?? 'wpuser' ) === 'guest-author';
        return (object) [
            'id'           => $coauthor->user_nicename,
            'display_name' => $coauthor->display_name,
            'description'  => $coauthor->description ?? '',
            'url'          => $coauthor->website ?? '',
            'avatar_url'   => get_avatar_url( $coauthor->ID ),
            'role'         => $is_guest ? 'guest' : get_byline_role_from_caps( $coauthor ),
            'is_guest'     => $is_guest,
            'profiles'     => [],
            'now_url'      => null,
            'uses_url'     => null,
        ];
    }, $coauthors );
}
```

CAP's `get_coauthors()` returns an array of objects with a `->type` property: `'wpuser'` for WordPress users, `'guest-author'` for guest authors (CPT-based). The function is stable and well-documented despite CAP's maintenance status.

**PublishPress Authors adapter**

PPA exposes authors via `get_post_authors()` or through its `MultipleAuthors\Classes\Objects\Author` class. Each author object has `term_id`, `user_id` (0 for pure guest authors), `is_guest`, `display_name`, and profile fields accessible via term meta.

```php
function get_byline_authors_ppa( WP_Post $post ) : array {
    $authors = get_post_authors( $post->ID ); // or MultipleAuthors API
    return array_map( function( $author ) {
        $user = $author->user_id ? get_userdata( $author->user_id ) : null;
        return (object) [
            'id'           => $author->slug,
            'display_name' => $author->display_name,
            'description'  => get_term_meta( $author->term_id, 'description', true ) ?: '',
            'url'          => $user ? $user->user_url : '',
            'avatar_url'   => get_term_meta( $author->term_id, 'avatar', true ) ?: '',
            'role'         => $author->is_guest ? 'guest' : get_byline_role_from_user( $user ),
            'is_guest'     => (bool) $author->is_guest,
            'profiles'     => [],
            'now_url'      => null,
            'uses_url'     => null,
        ];
    }, $authors );
}
```

**Core WordPress fallback**

```php
function get_byline_authors_core( WP_Post $post ) : array {
    $user = get_userdata( (int) $post->post_author );
    if ( ! $user ) {
        return [];
    }
    return [ (object) [
        'id'           => $user->user_nicename,
        'display_name' => $user->display_name,
        'description'  => $user->description,
        'url'          => $user->user_url,
        'avatar_url'   => get_avatar_url( $user->ID ),
        'role'         => get_byline_role_from_user( $user ),
        'is_guest'     => false,
        'profiles'     => [],
        'now_url'      => null,
        'uses_url'     => null,
    ] ];
}
```

### Role mapping

A shared utility function maps WordPress capabilities to Byline role values:

```php
function get_byline_role_from_user( ?WP_User $user ) : string {
    if ( ! $user ) {
        return 'contributor';
    }
    if ( user_can( $user, 'edit_others_posts' ) ) {
        return 'staff';
    }
    if ( user_can( $user, 'edit_published_posts' ) ) {
        return 'contributor';
    }
    return 'contributor';
}
```

Filterable per-post: `apply_filters( 'byline_feed_role', $role, $author_object, $post )`.

### Feed output

Hook into WordPress feed actions:

- `rss2_ns` — namespace declaration.
- `rss2_head` — `<byline:contributors>` block with all authors contributing to posts in the current feed.
- `rss2_item` — per-item `<byline:author ref="..."/>`, `<byline:role>`, and `<byline:perspective>`.
- `atom_ns`, `atom_head`, `atom_entry` — parallel Atom implementation.

Standard `<author>` / `<dc:creator>` elements are always preserved. Byline data is additive.

### Perspective

The `byline:perspective` element is the spec's most powerful feature and the hardest to populate. No existing WordPress plugin stores this data.

The Byline feed plugin should:

1. Register a post meta field (`_byline_perspective`) with a select dropdown in the block editor sidebar via `PluginDocumentSettingPanel`.
2. Provide a filter (`byline_feed_perspective`) that lets themes compute perspective from existing data. Example:

```php
add_filter( 'byline_feed_perspective', function( $perspective, $post ) {
    if ( has_category( 'opinion', $post ) ) return 'personal';
    if ( has_category( 'news', $post ) ) return 'reporting';
    if ( has_category( 'tutorials', $post ) ) return 'tutorial';
    if ( has_category( 'reviews', $post ) ) return 'review';
    return $perspective;
}, 10, 2 );
```

3. If no perspective is set or computed, omit the element entirely (graceful absence per the spec's progressive enhancement principle).

## Adoption strategy

### Phase 1: ship the plugin on wp.org

Build the core + CAP adapter + PPA adapter + core fallback + perspective meta field. Target a lean plugin that installs in one click, auto-detects the multi-author setup, and immediately enriches every RSS2 and Atom feed with Byline data. No configuration required for the basic case.

### Phase 2: generate supply-side evidence

Once the plugin is live:

- Submit feeds from test sites to the Byline validator at bylinespec.org/tools/validator.
- Document the number of feeds emitting Byline data (even a rough estimate based on install count).
- Write a blog post demonstrating the output with screenshots of what a Byline-aware reader could display.
- Present at WordCamp Canada 2025: "Solving Content Collapse: Structured Identity in WordPress Feeds."

### Phase 3: engage the reader side

- Open issues or PRs on popular open-source feed readers (NetNewsWire, Miniflux, FreshRSS, Reeder) with mockups showing how Byline data could enhance their author display.
- Engage the IndieWeb community, where `/now` pages, `rel="me"` verification, and feed-first publishing already have traction.
- Coordinate with Terry Godier on the spec repo to list the WordPress plugin as the first implementation.

### Phase 4: iterate on the spec

As a first implementor, provide feedback on the spec based on real-world implementation experience. Areas likely to surface issues:

- How to handle multiple authors per item (the spec supports multiple `<byline:author>` elements but the interaction with channel-level `<byline:contributors>` needs real-world testing).
- Whether `byline:perspective` should support custom/extensible values beyond the enumerated list.
- How `byline:role` interacts with WordPress's more granular capability system.
- Whether `byline:affiliation` is practically implementable without dedicated editorial workflow tooling.

## Scope estimate

| Component | Estimated effort |
| --- | --- |
| Plugin bootstrap + adapter interface | ~50 lines |
| Co-Authors Plus adapter | ~40 lines |
| PublishPress Authors adapter | ~50 lines |
| Core WordPress fallback | ~25 lines |
| Feed output (RSS2 + Atom) | ~200 lines |
| Perspective meta field + sidebar UI | ~100 lines (PHP + JSX) |
| Filters and hooks for extensibility | ~30 lines |
| Testing across plugin combinations | Most of the actual work |

Total code is modest. The testing matrix (CAP active, PPA active, neither active, perspective set, perspective filtered, single author, multiple authors, guest authors, mixed) is where the effort lives.

## Naming

The plugin name should not include "Byline" in a way that confuses it with the abandoned wp.org "Byline" taxonomy plugin. Candidates:

- `feed-identity` — neutral, descriptive.
- `byline-feed` — direct spec reference, could confuse with the old Byline plugin.
- `structured-feeds` — broader but less discoverable.
- `author-feeds` — simple but generic.

Whatever the name, the wp.org description should lead with the problem ("your feed readers can't tell a press release from personal analysis") rather than the spec name.

## Broader vision

The Byline feed plugin is component 1 of a broader "structured author identity and content provenance" layer for WordPress. Subsequent components extend the same normalized author data into JSON-LD schema (technical SEO), ActivityPub federation, content rights signaling (AI training consent, TDM headers), and IndieWeb integration.

See **[Author Identity, Content Provenance, and Distribution Control](author-identity-vision.md)** for the full vision connecting Byline to ActivityPub, LLM discoverability, intellectual property protection, and the journalism/IndieWeb adoption angles.
